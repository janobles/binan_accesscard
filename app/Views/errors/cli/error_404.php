<?php
/**
 * The not-found report printed when a CLI run addresses a command that does not
 * exist. Stock CodeIgniter, unmodified.
 */

use CodeIgniter\CLI\CLI;

CLI::error('ERROR: ' . $code);
CLI::write($message);
CLI::newLine();
