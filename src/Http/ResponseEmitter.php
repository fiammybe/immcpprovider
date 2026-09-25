<?php

declare(strict_types=1);

namespace ImMcpProvider\Http;

use Psr\Http\Message\ResponseInterface;

final class ResponseEmitter
{
    public function emit(ResponseInterface $response): void
    {
        http_response_code($response->getStatusCode());

        foreach ($response->getHeaders() as $name => $values) {
            foreach ($values as $index => $value) {
                header("{$name}: {$value}", $index === 0);
            }
        }

        $body = $response->getBody();

        if ($body->isSeekable()) {
            $body->rewind();
        }

        while (! $body->eof()) {
            echo $body->read(8192);

            if (connection_aborted() !== 0) {
                return;
            }
        }
    }
}
