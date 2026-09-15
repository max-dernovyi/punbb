<?php

declare(strict_types=1);

namespace PunBB\Module\Prune\Event;

use PunBB\Module\Framework\Event\EventInterface;

/**
 * Pruning was asked for, before anything about the visitor is checked.
 */
final class PruneRequested implements EventInterface {}
