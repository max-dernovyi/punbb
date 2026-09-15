<?php

declare(strict_types=1);

namespace PunBB\Module\AdminIndex\Event;

use PunBB\Module\Framework\Event\EventInterface;

/**
 * The administration's index was asked for, before anything about the visitor is checked.
 */
final class InformationRequested implements EventInterface {}
