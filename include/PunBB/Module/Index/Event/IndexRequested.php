<?php

declare(strict_types=1);

namespace PunBB\Module\Index\Event;

use PunBB\Module\Framework\Event\EventInterface;

/**
 * The board index was asked for, before anything about the visitor is checked.
 */
final class IndexRequested implements EventInterface {}
