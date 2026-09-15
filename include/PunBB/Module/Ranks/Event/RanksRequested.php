<?php

declare(strict_types=1);

namespace PunBB\Module\Ranks\Event;

use PunBB\Module\Framework\Event\EventInterface;

/**
 * The ranks page was asked for, before anything about the visitor is checked.
 */
final class RanksRequested implements EventInterface {}
