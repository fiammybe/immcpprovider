<?php

declare(strict_types=1);

namespace ImMcpProvider\Support;

use Throwable;

final class ActivityLog
{
    private const MAX_BYTES = 200000;

    public static function record(string $message): void
    {
        try {
            $path = self::path();
            $directory = dirname($path);

            if (! is_dir($directory) && ! mkdir($directory, 0775, true) && ! is_dir($directory)) {
                return;
            }

            if (is_file($path) && filesize($path) > self::MAX_BYTES) {
                self::truncate($path);
            }

            $line = gmdate('Y-m-d\TH:i:s\Z') . ' ' . str_replace(["\r", "\n"], ' ', $message) . "\n";

            file_put_contents($path, $line, FILE_APPEND | LOCK_EX);
        } catch (Throwable) {
            return;
        }
    }

    /** @return array<int, string> */
    public static function tail(int $lines = 60): array
    {
        $path = self::path();

        if (! is_file($path)) {
            return [];
        }

        $contents = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        if ($contents === false) {
            return [];
        }

        return array_slice($contents, -$lines);
    }

    private static function path(): string
    {
        return ICMS_TRUST_PATH . '/mcpapi/activity.log';
    }

    private static function truncate(string $path): void
    {
        $contents = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        if ($contents === false) {
            return;
        }

        $kept = array_slice($contents, (int) (count($contents) / 2));

        file_put_contents($path, implode("\n", $kept) . "\n", LOCK_EX);
    }
}
