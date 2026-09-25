<?php

declare(strict_types=1);

namespace Yawasla\Core;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use Slim\Exception\HttpException;
use Throwable;

final class JsonErrorHandler
{
    public function __construct(
        private ResponseFactoryInterface $responseFactory,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(
        ServerRequestInterface $request,
        Throwable $exception,
        bool $displayErrorDetails,
        bool $logErrors,
        bool $logErrorDetails,
    ): ResponseInterface {
        $isHttpException = $exception instanceof HttpException;
        $status = $isHttpException ? $exception->getCode() : 500;
        $status = $status >= 400 && $status < 600 ? $status : 500;

        $payload = $isHttpException
            ? ['error' => ['message' => $exception->getMessage()]]
            : $this->serverErrorPayload($exception);

        $response = $this->responseFactory->createResponse($status)
            ->withHeader('Content-Type', 'application/json');

        $response->getBody()->write((string) json_encode($payload, JSON_UNESCAPED_UNICODE));

        return $response;
    }

    private function serverErrorPayload(Throwable $exception): array
    {
        $debugId = bin2hex(random_bytes(4));

        $this->logger->error($exception->getMessage(), [
            'debug_id' => $debugId,
            'exception' => $exception,
        ]);

        return [
            'error' => [
                'message' => 'Une erreur est survenue.',
                'id' => $debugId,
            ],
        ];
    }
}
