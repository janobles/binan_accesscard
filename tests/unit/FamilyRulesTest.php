<?php

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
        $this->assertStringContainsString('not_future_date', $rules['birthday']);
    }

    public function testSharedModalFieldsExposeBrowserConstraints(): void
    {
        helper('family_modal');
        $fields = array_column(family_modal_prepare([])['personFields'], null, 'name');

        $this->assertSame(date('Y-m-d'), $fields['birthday']['max']);
        $this->assertSame(2, $fields['civilstatus']['otherMinlength']);
        $this->assertSame(2, $fields['education']['otherMinlength']);
        $this->assertSame(2, $fields['job']['otherMinlength']);
    }
}
