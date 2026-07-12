<?php

namespace Tests\Unit;

use App\Libraries\ActiveSessionRegistry;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * ActiveSessionRegistry unit tests — the file-backed single-session store behind
 * App\Filters\SingleSessionFilter. No DB dependency (pure writable/ JSON), so these
 * run without the sqlite3 extension. Each test uses a high, unlikely-to-collide
 * userID and forgets it in tearDown so the shared registry file is left clean.
 */
final class ActiveSessionRegistryTest extends CIUnitTestCase
{
    private const TEST_UID = 999_001;

    protected function tearDown(): void
    {
        parent::tearDown();
        ActiveSessionRegistry::forget(ActiveSessionRegistry::identityKey(self::TEST_UID, 'tester'));
        ActiveSessionRegistry::forget('user:devtester');
    }

    public function testIdentityKeyUsesUserIdForRealAccounts(): void
    {
        $this->assertSame('uid:42', ActiveSessionRegistry::identityKey(42, 'anyone'));
    }

    public function testIdentityKeyFallsBackToUsernameForInvalidUserId(): void
    {
        $this->assertSame('user:devtester', ActiveSessionRegistry::identityKey(0, 'DevTester'));
    }

    public function testPutThenGetReturnsStoredToken(): void
    {
        $identity = ActiveSessionRegistry::identityKey(self::TEST_UID, 'tester');
        ActiveSessionRegistry::put($identity, 'token-a', 'tester');

        $record = ActiveSessionRegistry::get($identity);

        $this->assertIsArray($record);
        $this->assertSame('token-a', $record['token']);
        $this->assertSame('tester', $record['username']);
    }

    public function testPutOverwritesPreviousToken(): void
    {
        // Overwriting is how a confirmed new login evicts the prior session.
        $identity = ActiveSessionRegistry::identityKey(self::TEST_UID, 'tester');
        ActiveSessionRegistry::put($identity, 'token-a', 'tester');
        ActiveSessionRegistry::put($identity, 'token-b', 'tester');

        $this->assertSame('token-b', ActiveSessionRegistry::get($identity)['token']);
    }

    public function testTouchOnlyRefreshesMatchingToken(): void
    {
        $identity = ActiveSessionRegistry::identityKey(self::TEST_UID, 'tester');
        ActiveSessionRegistry::put($identity, 'token-a', 'tester');

        // A stale session's token must not refresh the active entry's heartbeat.
        ActiveSessionRegistry::touch($identity, 'someone-elses-token');
        $this->assertSame('token-a', ActiveSessionRegistry::get($identity)['token']);

        // The active token refreshes updated_at without changing the token.
        ActiveSessionRegistry::touch($identity, 'token-a');
        $this->assertSame('token-a', ActiveSessionRegistry::get($identity)['token']);
    }

    public function testForgetRemovesEntry(): void
    {
        $identity = ActiveSessionRegistry::identityKey(self::TEST_UID, 'tester');
        ActiveSessionRegistry::put($identity, 'token-a', 'tester');
        ActiveSessionRegistry::forget($identity);

        $this->assertNull(ActiveSessionRegistry::get($identity));
    }

    public function testGetForUnknownIdentityIsNull(): void
    {
        $this->assertNull(ActiveSessionRegistry::get('uid:987654'));
    }
}
