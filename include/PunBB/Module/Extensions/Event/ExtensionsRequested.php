<?php

declare(strict_types=1);

namespace PunBB\Module\Extensions\Event;

use PunBB\Module\Framework\Event\EventInterface;

/**
 * The extensions page was asked for, before anything is checked.
 */
final class ExtensionsRequested implements EventInterface {}
