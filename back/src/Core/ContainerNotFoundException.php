<?php

declare(strict_types=1);

namespace Yawasla\Core;

use Psr\Container\NotFoundExceptionInterface;

class ContainerNotFoundException extends ContainerException implements NotFoundExceptionInterface
{
}
