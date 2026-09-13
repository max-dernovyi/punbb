<?php

declare(strict_types=1);

namespace PunBB\Module\Framework\Container;

use Psr\Container\NotFoundExceptionInterface;
use RuntimeException;

final class NotFoundException extends RuntimeException implements NotFoundExceptionInterface {}
