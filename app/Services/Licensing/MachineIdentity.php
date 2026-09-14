<?php

namespace App\Services\Licensing;

use RuntimeException;
use Symfony\Component\Process\Process;

class MachineIdentity
{
    public function id(): string
    {
        if (PHP_OS_FAMILY === 'Windows') {
            $process = new Process(['reg.exe', 'query', 'HKLM\SOFTWARE\Microsoft\Cryptography', '/v', 'MachineGuid', '/reg:64']);
            $process->setTimeout(10)->mustRun();
            preg_match('/MachineGuid\s+REG_SZ\s+([a-f0-9-]+)/i', $process->getOutput(), $match);
            $identity = strtolower($match[1] ?? '');
            if (!preg_match('/^[a-f0-9]{8}(?:-[a-f0-9]{4}){3}-[a-f0-9]{12}$/D', $identity)) {
                throw new RuntimeException('Windows machine identifier is unavailable.');
            }
        } elseif (PHP_OS_FAMILY === 'Linux') {
            $identity = strtolower(trim((string) @file_get_contents('/etc/machine-id')));
            if (!preg_match('/^[a-f0-9]{32}$/D', $identity)) {
                throw new RuntimeException('Linux machine identifier is unavailable.');
            }
        } else {
            throw new RuntimeException('This operating system is not supported for device binding.');
        }

        // Stable across network, hostname, app path and user-account changes.
        return strtoupper(hash('sha256', "atelier-license-machine-v1\0".PHP_OS_FAMILY."\0".$identity));
    }
}
