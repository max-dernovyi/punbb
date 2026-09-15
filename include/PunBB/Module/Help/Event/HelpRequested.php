<?php

declare(strict_types=1);

namespace PunBB\Module\Help\Event;

use PunBB\Module\Framework\Event\EventInterface;

/**
 * The help page was asked for, before anything about the visitor is checked.
 */
final class HelpRequested implements EventInterface {}
