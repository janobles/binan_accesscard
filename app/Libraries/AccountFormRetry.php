<?php

namespace App\Libraries;

/**
 * Carries a rejected account-form submission across to the modal that reopens.
 *
 * The account modals are AJAX fragments, so a failed POST takes three requests
 * to get back on screen: the POST redirects to the accounts page, layout.php's
 * auto-open clicks the trigger, and the trigger fetches the fragment. Flashdata
 * lives for one request, so it is already gone when the fragment renders. The
 * submitted values and the validation errors are parked in ordinary session
 * data here instead, and the fragment pops them.
 *
 * Written by AccountController::create/update and ProfileController::update;
 * read by their matching form actions.
 */
class AccountFormRetry
{
    private const KEY = 'account_form_retry';

    /**
     * Fields never parked: a password does not belong in session storage, and
     * the token is reissued per request anyway.
     */
    private const EXCLUDED = ['password', 'new_password', 'confirm_password', 'current_password', 'csrf_test_name'];

    /**
     * @param string               $mode   create|edit|self, matched on the way back out.
     * @param array<string, mixed> $input  The raw POST body.
     * @param array<string, string> $errors Validation errors keyed by field name.
     * @param integer              $userId Which account an edit was aimed at; 0 for the other modes.
     */
    public static function remember(string $mode, array $input, array $errors, int $userId = 0): void
    {
        $kept = [];

        foreach ($input as $field => $value) {
            if (! in_array($field, self::EXCLUDED, true) && is_scalar($value)) {
                $kept[(string) $field] = (string) $value;
            }
        }

        session()->set(self::KEY, [
            'mode'   => $mode,
            'userID' => $userId,
            'input'  => $kept,
            'errors' => $errors,
        ]);
    }

    /**
     * Reads the parked submission and clears it, so a form opened later never
     * shows a stale rejection. Anything parked for a different form is dropped
     * rather than returned: by the time another modal opens it is stale too.
     *
     * @return array{input: array<string, string>, errors: array<string, string>}
     */
    public static function take(string $mode, int $userId = 0): array
    {
        $parked = session()->get(self::KEY);
        session()->remove(self::KEY);
        $empty = ['input' => [], 'errors' => []];

        if (! is_array($parked) || ($parked['mode'] ?? '') !== $mode) {
            return $empty;
        }

        if ($mode === 'edit' && (int) ($parked['userID'] ?? 0) !== $userId) {
            return $empty;
        }

        return [
            'input'  => is_array($parked['input'] ?? null) ? $parked['input'] : [],
            'errors' => is_array($parked['errors'] ?? null) ? $parked['errors'] : [],
        ];
    }
}
