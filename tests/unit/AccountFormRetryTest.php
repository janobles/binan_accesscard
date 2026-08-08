<?php

namespace Tests\Unit;

use App\Libraries\AccountFormRetry;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * A rejected account submission has to survive two requests before the AJAX
 * modal fragment renders, so it rides in session data rather than flashdata.
 * These tests cover the park/pop contract and the view honouring what it pops.
 */
final class AccountFormRetryTest extends CIUnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $_SESSION = [];
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        parent::tearDown();
    }

    public function testTakeReturnsWhatRememberParked(): void
    {
        AccountFormRetry::remember('create', ['username' => 'ab', 'role' => 'viewer'], ['username' => 'Too short.']);

        $retry = AccountFormRetry::take('create');

        $this->assertSame(['username' => 'ab', 'role' => 'viewer'], $retry['input']);
        $this->assertSame(['username' => 'Too short.'], $retry['errors']);
    }

    public function testTakeClearsSoAnUnrelatedOpenIsNotPrefilled(): void
    {
        AccountFormRetry::remember('create', ['username' => 'ab'], ['username' => 'Too short.']);

        AccountFormRetry::take('create');
        $second = AccountFormRetry::take('create');

        $this->assertSame([], $second['input']);
        $this->assertSame([], $second['errors']);
    }

    public function testPasswordFieldsAreNeverParked(): void
    {
        AccountFormRetry::remember('create', [
            'username'         => 'ab',
            'password'         => 'hunter2secret',
            'new_password'     => 'hunter2secret',
            'confirm_password' => 'hunter2secret',
            'current_password' => 'hunter2secret',
        ], []);

        $input = AccountFormRetry::take('create')['input'];

        $this->assertSame(['username' => 'ab'], $input);
    }

    public function testAnEditRejectionDoesNotPrefillADifferentAccount(): void
    {
        AccountFormRetry::remember('edit', ['username' => 'taken'], ['username' => 'Already used.'], 7);

        $retry = AccountFormRetry::take('edit', 9);

        $this->assertSame([], $retry['input'], 'Account 7\'s rejected values must not leak into account 9\'s form.');
    }

    public function testAModeMismatchDropsTheParkedSubmission(): void
    {
        AccountFormRetry::remember('create', ['username' => 'ab'], []);

        $this->assertSame([], AccountFormRetry::take('self')['input']);

        // Dropped, not merely withheld: the create form does not get it back
        // on the next request either.
        $this->assertSame(['input' => [], 'errors' => []], AccountFormRetry::take('create'));
    }

    public function testEditFormShowsTheSubmittedUsernameNotTheStoredOne(): void
    {
        $html = view('Accounts/account-form-modal', [
            'mode'    => 'edit',
            'account' => ['userID' => 7, 'username' => 'stored_name', 'role' => 'viewer'],
            'details' => ['last_name' => 'Stored', 'first_name' => 'Name'],
            'old'     => ['username' => 'typed_name', 'last_name' => 'Typed'],
            'errors'  => ['username' => 'Username is already taken.'],
        ]);

        $this->assertStringContainsString('value="typed_name"', $html);
        $this->assertStringNotContainsString('value="stored_name"', $html);
        $this->assertStringContainsString('value="Typed"', $html);
        $this->assertStringContainsString('Username is already taken.', $html);
    }

    public function testStoredValuesStandInForFieldsTheSubmissionDidNotCarry(): void
    {
        $html = view('Accounts/account-form-modal', [
            'mode'    => 'edit',
            'account' => ['userID' => 7, 'username' => 'stored_name', 'role' => 'viewer'],
            'details' => ['last_name' => 'Stored', 'first_name' => 'Name'],
            'old'     => ['username' => 'typed_name'],
            'errors'  => [],
        ]);

        $this->assertStringContainsString('value="Stored"', $html);
    }

    public function testAPasswordRejectionLandsUnderItsOwnField(): void
    {
        // What ProfileController::reopenWithPasswordError parks. The message
        // belongs on the field that was wrong, not in a page-level flash.
        $html = view('Accounts/account-form-modal', [
            'mode'    => 'self',
            'account' => ['userID' => 7, 'username' => 'me', 'role' => 'viewer'],
            'details' => ['last_name' => 'Stored', 'first_name' => 'Name'],
            'old'     => ['username' => 'me'],
            'errors'  => ['current_password' => 'Your current password is incorrect.'],
        ]);

        $this->assertStringContainsString('Your current password is incorrect.', $html);
        $this->assertStringContainsString('name="current_password" type="password"', $html);
    }

    public function testCreateFormIsBlankWithNothingParked(): void
    {
        $html = view('Accounts/account-form-modal', ['mode' => 'create']);

        $this->assertStringContainsString('name="username"', $html);
        $this->assertStringContainsString('value=""', $html);
    }
}
