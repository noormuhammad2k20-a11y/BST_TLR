<?php

namespace App\Services\Licensing;

use Illuminate\Validation\ValidationException;
use RuntimeException;

class LicenseInstaller
{
    public function __construct(private LicenseChecker $checker) {}

    public function install(string $contents): void
    {
        $result = $this->checker->verify($contents);
        if (!$result->valid()) {
            throw ValidationException::withMessages(['license' => $result->message]);
        }
        $path = config('license.path');
        $directory = dirname($path);
        if (!is_dir($directory) && !@mkdir($directory, 0700, true) && !is_dir($directory)) {
            throw new RuntimeException('License storage is not writable.');
        }
        $temporary = tempnam($directory, '.license-');
        if ($temporary === false) {
            throw new RuntimeException('License storage is not writable.');
        }
        try {
            @chmod($temporary, 0600);
            if (file_put_contents($temporary, $contents, LOCK_EX) !== strlen($contents) || !@rename($temporary, $path)) {
                throw new RuntimeException('Unable to save the license. The existing license was retained.');
            }
        } finally {
            if (is_file($temporary)) {
                @unlink($temporary);
            }
        }
    }
}
