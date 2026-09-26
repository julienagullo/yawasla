<?php

declare(strict_types=1);

namespace Yawasla\Core;

use RuntimeException;

/** Le serveur MySQL répond mais la base configurée n'existe pas (erreur 1049). */
final class UnknownDatabaseException extends RuntimeException
{
}
