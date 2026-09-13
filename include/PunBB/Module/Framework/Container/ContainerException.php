<?php

declare(strict_types=1);

namespace PunBB\Module\Framework\Container;

use Psr\Container\ContainerExceptionInterface;
use RuntimeException;

final class ContainerException extends RuntimeException implements ContainerExceptionInterface {}
