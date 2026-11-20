<?php

declare(strict_types=1);

namespace ImMcpProvider\Tool;

use ImMcpProvider\Ipf\IpfGateway;
use ImMcpProvider\Security\AccessPolicy;

/**
 * The entry point for other modules that want to use the shared tools.
 *
 * Tools run with the permissions of the signed-in user (icms::$user) and apply the same exclusions as the MCP
 * endpoint. Failures are signalled with Mcp\Exception\ToolCallException. Nothing else in this module is a
 * contract: dependents should not build IpfGateway or AccessPolicy themselves.
 */
final class ToolKit
{
    /** Raised only when a change can break a dependent module (a removed or renamed tool, field or error type). */
    public const API_VERSION = 1;

    public static function catalog(): ToolCatalog
    {
        return new ToolCatalog();
    }

    public static function tools(): IpfTools
    {
        return new IpfTools(new IpfGateway(new AccessPolicy()));
    }
}
