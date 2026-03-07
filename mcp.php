<?php
declare(strict_types=1);

require_once __DIR__ . '/../../mainfile.php';
require_once __DIR__ . '/class/MCPProvider.php';

header('Content-Type: application/json; charset=utf-8');

$raw = file_get_contents('php://input');
$payload = json_decode($raw, true) ?? [];

$provider = new MCPProvider();

try {
    $result = $provider->handle($payload);

    echo json_encode([
        'jsonrpc' => '2.0',
        'result'  => $result,
        'id'      => $payload['id'] ?? null,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    http_response_code(500);

    echo json_encode([
        'jsonrpc' => '2.0',
        'error'   => [
            'code'    => -32000,
            'message' => $e->getMessage(),
        ],
        'id' => $payload['id'] ?? null,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
