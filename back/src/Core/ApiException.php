<?php

declare(strict_types=1);

namespace Yawasla\Core;

use RuntimeException;
use Throwable;

/**
 * Erreur affichable à l'utilisateur : le front traduit `errorCode` (ex. "database.create_failed")
 * avec `params`, le message (en français) ne sert que de fallback et pour les logs.
 * Rendue par JsonErrorHandler sous la forme { error: { code, params?, message } }.
 */
final class ApiException extends RuntimeException
{
    /**
     * @param array<string, string|int> $params
     */
    public function __construct(
        private string $errorCode,
        string $message,
        private array $params = [],
        private int $status = 422,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public function getErrorCode(): string
    {
        return $this->errorCode;
    }

    /** @return array<string, string|int> */
    public function getParams(): array
    {
        return $this->params;
    }

    public function getStatus(): int
    {
        return $this->status;
    }
}
