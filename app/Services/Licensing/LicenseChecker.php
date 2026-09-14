<?php

namespace App\Services\Licensing;

use Carbon\CarbonImmutable;
use Throwable;

class LicenseChecker
{
    public const MAX_BYTES = 16384;
    public const SIGNING_CONTEXT = "atelier-offline-license-v1\0";

    public function __construct(private MachineIdentity $machine) {}

    public function check(): LicenseResult
    {
        $path = config('license.path');
        if (!is_file($path)) {
            return new LicenseResult('missing', 'Activate this device using the license file supplied by your software provider.');
        }
        $contents = @file_get_contents($path, false, null, 0, self::MAX_BYTES + 1);

        return $contents === false
            ? new LicenseResult('unreadable', 'The license file cannot be read. Please contact your software provider.')
            : $this->verify($contents);
    }

    public function verify(string $contents): LicenseResult
    {
        try {
            if (!function_exists('openssl_verify')) {
                return new LicenseResult('configuration', 'License verification is unavailable. Please enable the PHP OpenSSL extension.');
            }
            $key = openssl_pkey_get_public((string) @file_get_contents(config('license.public_key')));
            $keyDetails = $key === false ? false : openssl_pkey_get_details($key);
            if (!$keyDetails || $keyDetails['type'] !== OPENSSL_KEYTYPE_RSA || $keyDetails['bits'] !== 3072) {
                return new LicenseResult('configuration', 'The license verification key is unavailable. Please contact your software provider.');
            }
            if (strlen($contents) > self::MAX_BYTES) {
                return $this->invalid();
            }
            $envelope = json_decode($contents, true, 8, JSON_THROW_ON_ERROR);
            if (!is_array($envelope) || count($envelope) !== 2 || !is_string($envelope['payload'] ?? null) || !is_string($envelope['signature'] ?? null)) {
                return $this->invalid();
            }
            $payload = base64_decode($envelope['payload'], true);
            $signature = base64_decode($envelope['signature'], true);
            if ($payload === false || $signature === false || strlen($signature) !== 384 ||
                openssl_verify(self::SIGNING_CONTEXT.$payload, $signature, $key, OPENSSL_ALGO_SHA256) !== 1) {
                return $this->invalid();
            }
            // Interpret fields only after authenticating the exact payload bytes.
            $data = json_decode($payload, true, 8, JSON_THROW_ON_ERROR);
            if (!is_array($data) || count($data) !== 7 || ($data['version'] ?? null) !== 1 || ($data['product'] ?? null) !== 'atelier-tailor' ||
                !is_string($data['client_name'] ?? null) || trim($data['client_name']) === '' || strlen($data['client_name']) > 200 ||
                !is_string($data['machine_id'] ?? null) || !preg_match('/^[A-F0-9]{64}$/D', $data['machine_id']) ||
                !in_array($data['type'] ?? null, ['Trial', 'Lifetime'], true) ||
                !is_int($data['issued_at'] ?? null) || $data['issued_at'] < 1 || !array_key_exists('expires_at', $data)) {
                return $this->invalid();
            }
            if (($data['type'] === 'Lifetime' && $data['expires_at'] !== null) ||
                ($data['type'] === 'Trial' && (!is_int($data['expires_at']) || $data['expires_at'] <= $data['issued_at']))) {
                return $this->invalid();
            }
            try {
                $machineId = $this->machine->id();
            } catch (Throwable) {
                return new LicenseResult('machine_unavailable', 'The device identifier cannot be read. Please contact your software provider.');
            }
            if (!hash_equals($data['machine_id'], $machineId)) {
                return new LicenseResult('wrong_machine', 'This license belongs to another device. Request a license for the Machine ID shown below.');
            }
            if ($data['type'] === 'Trial') {
                $now = CarbonImmutable::now('UTC')->timestamp;
                if ($now < $data['issued_at']) {
                    return new LicenseResult('clock', 'The device clock is earlier than the license issue date. Correct the clock and try again.', $data);
                }
                if ($now >= $data['expires_at']) {
                    return new LicenseResult('expired', 'Your trial has expired. Contact your software provider for a new license.', $data);
                }
            }

            return new LicenseResult('valid', 'This device is activated.', $data);
        } catch (Throwable) {
            return $this->invalid();
        }
    }

    private function invalid(): LicenseResult
    {
        return new LicenseResult('invalid', 'The license file is corrupted, modified, or not issued by your software provider.');
    }
}
