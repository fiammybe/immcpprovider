<?php

declare(strict_types=1);

namespace ImMcpProvider\Setup;

use ImMcpProvider\OAuth\KeyStore;
use Throwable;

final class Requirements
{
    private const MINIMUM_PHP_VERSION_ID = 80200;

    private const REQUIRED_EXTENSIONS = ['openssl', 'json', 'mbstring'];

    /**
     * @return array<int, array{
     *     label: string,
     *     ok: bool,
     *     detail: string
     * }>
     */
    public function checks(): array
    {
        $checks = [
            [
                'label' => 'PHP 8.2 or newer',
                'ok' => PHP_VERSION_ID >= self::MINIMUM_PHP_VERSION_ID,
                'detail' => PHP_VERSION,
            ],
        ];

        foreach (self::REQUIRED_EXTENSIONS as $extension) {
            $checks[] = [
                'label' => "PHP extension {$extension}",
                'ok' => extension_loaded($extension),
                'detail' => '',
            ];
        }

        $checks[] = $this->sessionDirectoryCheck();
        $checks[] = $this->keysCheck();

        return $checks;
    }

    /** @return array<int, string> */
    public function failures(): array
    {
        $failures = [];

        foreach ($this->checks() as $check) {
            if ($check['ok']) {
                continue;
            }

            $failures[] = $check['detail'] === '' ? $check['label'] : "{$check['label']}: {$check['detail']}";
        }

        return $failures;
    }

    /**
     * @return array{
     *     label: string,
     *     ok: bool,
     *     detail: string
     * }
     */
    private function sessionDirectoryCheck(): array
    {
        $directory = ICMS_TRUST_PATH . '/mcpapi/sessions';
        $ready = is_dir($directory) || mkdir($directory, 0775, true) || is_dir($directory);

        return [
            'label' => 'Session directory is writable',
            'ok' => $ready && is_writable($directory),
            'detail' => $directory,
        ];
    }

    /**
     * @return array{
     *     label: string,
     *     ok: bool,
     *     detail: string
     * }
     */
    private function keysCheck(): array
    {
        try {
            $keys = KeyStore::fromTrustPath();
            $keys->privateKey();
            $keys->publicKey();
            $keys->encryptionKey();
        } catch (Throwable $exception) {
            return [
                'label' => 'OAuth signing keys',
                'ok' => false,
                'detail' => $exception->getMessage(),
            ];
        }

        return [
            'label' => 'OAuth signing keys',
            'ok' => true,
            'detail' => ICMS_TRUST_PATH . '/mcpapi',
        ];
    }
}
