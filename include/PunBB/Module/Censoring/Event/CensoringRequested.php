<?php

declare(strict_types=1);

namespace PunBB\Module\Censoring\Event;

use PunBB\Module\Framework\Event\EventInterface;

/**
 * The censoring page was asked for, before anything about the visitor is checked.
 */
final class CensoringRequested implements EventInterface {}
