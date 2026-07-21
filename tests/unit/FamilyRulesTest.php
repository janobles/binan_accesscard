<?php

use App\Controllers\Families\FamilyController;
use App\Validation\FamilyRules;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * @internal
 */
final class FamilyRulesTest extends CIUnitTestCase
{
    public function testBirthdayCannotBeInTheFuture(): void
    {
        $rules = new FamilyRules();
        $today = new DateTimeImmutable('today');

        $this->assertTrue($rules->not_future_date($today->format('Y-m-d')));
        $this->assertTrue($rules->not_future_date($today->modify('-1 day')->format('Y-m-d')));
        $this->assertFalse($rules->not_future_date($today->modify('+1 day')->format('Y-m-d')));
        $this->assertFalse($rules->not_future_date('not-a-date'));
        $this->assertTrue(service('validation')->check($today->format('Y-m-d'), 'not_future_date'));
        $this->assertFalse(service('validation')->check($today->modify('+1 day')->format('Y-m-d'), 'not_future_date'));
    }

    public function testSharedMemberRulesRejectOneCharacterProfileValues(): void
    {
        $rules = \App\Models\Families\MemberModel::VALIDATION_RULES;

        $this->assertStringContainsString('min_length[2]', $rules['civilstatus']);
        $this->assertStringContainsString('min_length[2]', $rules['education']);
        $this->assertStringContainsString('min_length[2]', $rules['job']);
        $this->assertStringContainsString('min_length[2]', $rules['religion']);
        $this->assertStringContainsString('not_future_date', $rules['birthday']);
    }

    public function testOtherProfileValuesCannotContainNumbersOnly(): void
    {
        $rules = new FamilyRules();

        $this->assertFalse($rules->not_numeric_only('123'));
        $this->assertFalse($rules->not_numeric_only('123 456'));
        $this->assertTrue($rules->not_numeric_only('Religion 2'));

        foreach (['civilstatus', 'religion', 'education', 'job'] as $field) {
            $this->assertStringContainsString('not_numeric_only', \App\Models\Families\MemberModel::VALIDATION_RULES[$field]);
        }
    }

    public function testSharedModalFieldsExposeBrowserConstraints(): void
    {
        helper('family_modal');
        $fields = array_column(family_modal_prepare([])['personFields'], null, 'name');

        $this->assertSame(date('Y-m-d'), $fields['birthday']['max']);
        $this->assertSame(2, $fields['civilstatus']['otherMinlength']);
        $this->assertSame(2, $fields['education']['otherMinlength']);
        $this->assertSame(2, $fields['job']['otherMinlength']);
        $this->assertSame(2, $fields['religion']['otherMinlength']);

        foreach (['civilstatus', 'religion', 'education', 'job'] as $field) {
            $this->assertSame('.*[^\d\s].*', $fields[$field]['otherPattern']);
        }
    }

    public function testQrNumberAllowsLeadingZerosWithinSevenDigitLimit(): void
    {
        $method = new ReflectionMethod(FamilyController::class, 'rulesForEntryType');
        $rules = $method->invoke(new FamilyController(), 'head');
        $validation = service('validation');

        $this->assertTrue($validation->check('00823', $rules['qr_control_no']));
        $this->assertSame(823, (int) '00823');
        $this->assertTrue($validation->check('9999999', $rules['qr_control_no']));
        $this->assertFalse($validation->check('10000000', $rules['qr_control_no']));
    }
}
