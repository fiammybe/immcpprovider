<?php

declare(strict_types=1);

namespace ImMcpProvider\OAuth;

use RuntimeException;

final class KeyStore
{
    private const PRIVATE_KEY_FILE = 'private.pem';

    private const PUBLIC_KEY_FILE = 'public.pem';

    private const ENCRYPTION_KEY_FILE = 'encryption.key';

    public function __construct(private readonly string $directory)
    {
    }

    public static function fromTrustPath(): self
    {
        return new self(ICMS_TRUST_PATH . '/mcpapi');
    }

    public function privateKey(): string
    {
        return $this->read(self::PRIVATE_KEY_FILE);
    }

    public function publicKey(): string
    {
        return $this->read(self::PUBLIC_KEY_FILE);
    }

    public function encryptionKey(): string
    {
        return $this->read(self::ENCRYPTION_KEY_FILE);
    }

    private function read(string $file): string
    {
        $path = "{$this->directory}/{$file}";

        if (! is_file($path)) {
            $this->generate();
        }

        $contents = file_get_contents($path);

        if ($contents === false || $contents === '') {
            throw new RuntimeException("Could not read the OAuth key file '{$file}'.");
        }

        return $contents;
    }

    private function generate(): void
    {
        if (! is_dir($this->directory) && ! mkdir($this->directory, 0700, true) && ! is_dir($this->directory)) {
            throw new RuntimeException('Could not create the OAuth key directory.');
        }

        $lock = fopen("{$this->directory}/.lock", 'c');

        if ($lock === false || ! flock($lock, LOCK_EX)) {
            throw new RuntimeException('Could not lock the OAuth key directory.');
        }

        try {
            if ($this->keysExist()) {
                return;
            }

            $this->writeKeyPair();
            $this->write(self::ENCRYPTION_KEY_FILE, base64_encode(random_bytes(32)));
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    private function keysExist(): bool
    {
        foreach ([self::PRIVATE_KEY_FILE, self::PUBLIC_KEY_FILE, self::ENCRYPTION_KEY_FILE] as $file) {
            if (! is_file("{$this->directory}/{$file}")) {
                return false;
            }
        }

        return true;
    }

    private function writeKeyPair(): void
    {
        $options = [
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ];

        $resource = openssl_pkey_new($options);

        if ($resource === false) {
            $options['config'] = $this->writeMinimalOpenSslConfig();
            $resource = openssl_pkey_new($options);
        }

        if ($resource === false || ! openssl_pkey_export($resource, $privateKey, null, $options)) {
            $reason = openssl_error_string() ?: 'unknown error';

            throw new RuntimeException("Could not generate the OAuth signing key ({$reason}). Is the OpenSSL extension configured?");
        }

        $details = openssl_pkey_get_details($resource);

        if ($details === false) {
            throw new RuntimeException('Could not read the generated OAuth public key.');
        }

        $this->write(self::PRIVATE_KEY_FILE, $privateKey);
        $this->write(self::PUBLIC_KEY_FILE, $details['key']);
    }

    private function writeMinimalOpenSslConfig(): string
    {
        $path = "{$this->directory}/openssl.cnf";
        $contents = "[req]\ndistinguished_name = req_distinguished_name\n\n[req_distinguished_name]\n";

        if (file_put_contents($path, $contents, LOCK_EX) === false) {
            throw new RuntimeException('Could not write the OpenSSL fallback configuration.');
        }

        return $path;
    }

    private function write(string $file, string $contents): void
    {
        $path = "{$this->directory}/{$file}";

        if (file_put_contents($path, $contents, LOCK_EX) === false) {
            throw new RuntimeException("Could not write the OAuth key file '{$file}'.");
        }

        chmod($path, 0600);
    }
}
