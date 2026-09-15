<?php

declare(strict_types=1);

namespace PunBB\Module\Edit\Event;

use PunBB\Module\Framework\Event\EventInterface;

/**
 * The edit page was asked for, before anything about the visitor is checked.
 */
final class EditRequested implements EventInterface {}
