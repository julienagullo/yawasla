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
        if ($exception instanceof ApiException) {
            // Erreur d'origine (ex. PDOException) : absente de la réponse, mais indispensable au diagnostic
            if ($exception->getPrevious() !== null) {
                $this->logger->warning($exception->getMessage(), [
                    'code' => $exception->getErrorCode(),
                    'exception' => $exception->getPrevious(),
                ]);
            }

            $status = $exception->getStatus();
            $payload = ['error' => ['code' => $exception->getErrorCode(), 'message' => $exception->getMessage()]];

            if ($exception->getParams() !== []) {
                $payload['error']['params'] = $exception->getParams();
            }
        } elseif ($exception instanceof HttpException) {
            $status = $exception->getCode();
            $payload = ['error' => ['message' => $exception->getMessage()]];
        } else {
            $status = 500;
            $payload = $this->serverErrorPayload($exception);
        }

        $status = $status >= 400 && $status < 600 ? $status : 500;

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
