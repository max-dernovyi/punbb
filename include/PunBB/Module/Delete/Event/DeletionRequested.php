<?php

declare(strict_types=1);

namespace PunBB\Module\Delete\Event;

use PunBB\Module\Framework\Event\EventInterface;

/**
 * The deletion page was asked for, before anything about the visitor is checked.
 */
final class DeletionRequested implements EventInterface {}
