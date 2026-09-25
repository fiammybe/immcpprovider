<?php

declare(strict_types=1);

namespace ImMcpProvider;

use icms;
use icms_ipf_Handler;
use icms_module_Object;
use mod_mcpapi_IdentifiedHandler;
use RuntimeException;

final class Module
{
    public const DIRNAME = 'mcpapi';

    public static function url(string $path = ''): string
    {
        return ICMS_URL . '/modules/' . self::DIRNAME . '/' . ltrim($path, '/');
    }

    public static function issuer(): string
    {
        return rtrim(self::url(), '/');
    }

    public static function handler(string $name): icms_ipf_Handler
    {
        $handler = icms_getModuleHandler($name, self::DIRNAME, null, true);

        if (! $handler instanceof icms_ipf_Handler) {
            throw new RuntimeException("Handler '{$name}' is not available. Is the module installed?");
        }

        return $handler;
    }

    public static function identifiedHandler(string $name): mod_mcpapi_IdentifiedHandler
    {
        $handler = self::handler($name);

        if (! $handler instanceof mod_mcpapi_IdentifiedHandler) {
            throw new RuntimeException("Handler '{$name}' does not support identifier lookups.");
        }

        return $handler;
    }

    public static function purgeExpired(): void
    {
        $now = time();

        foreach (['accesstoken', 'refreshtoken', 'authcode'] as $name) {
            self::identifiedHandler($name)->deleteExpired($now);
        }
    }

    public static function object(): icms_module_Object
    {
        $module = icms::handler('icms_module')->getByDirname(self::DIRNAME);

        if (! $module instanceof icms_module_Object) {
            throw new RuntimeException('The module is not installed.');
        }

        return $module;
    }
}
