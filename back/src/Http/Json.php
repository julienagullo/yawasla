<?php

declare(strict_types=1);

namespace Yawasla\Http;

use Psr\Http\Message\ResponseInterface;

final class Json
{
    /** @param array<string, mixed> $payload */
    public static function respond(ResponseInterface $response, array $payload, int $status = 200): ResponseInterface
    {
        $response->getBody()->write((string) json_encode($payload, JSON_UNESCAPED_UNICODE));

        return $response->withStatus($status)->withHeader('Content-Type', 'application/json');
    }
}
