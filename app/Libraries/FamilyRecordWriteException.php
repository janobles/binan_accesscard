<?php

namespace App\Libraries;

use RuntimeException;

/**
 * Thrown by FamilyRecordWriter when a family cannot be persisted (head insert,
 * member insert, or service assignment failed). The message is human-readable and
 * safe to surface to the operator. Callers catch this, roll back their transaction,
 * and report the message.
 */
class FamilyRecordWriteException extends RuntimeException
{
}
