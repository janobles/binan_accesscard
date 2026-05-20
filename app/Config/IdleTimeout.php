<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

/**
 * Idle session timeout settings for logged-in staff accounts.
 */
class IdleTimeout extends BaseConfig
{
    public int $seconds = 60;
}
