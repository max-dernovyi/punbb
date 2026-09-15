<?php

declare(strict_types=1);

namespace PunBB\Module\Reports\Event;

use PunBB\Module\Framework\Event\EventInterface;

/**
 * The reports were asked for, before anything about the visitor is checked.
 */
final class ReportsRequested implements EventInterface {}
