<?php

declare(strict_types=1);

namespace PunBB\Module\Bans\Event;

use PunBB\Module\Framework\Event\EventInterface;

/**
 * The bans page was asked for, before anything about the visitor is checked.
 */
final class BansRequested implements EventInterface {}
