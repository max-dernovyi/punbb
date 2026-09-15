<?php

declare(strict_types=1);

namespace PunBB\Module\Layout\Event;

use PunBB\Module\Framework\Event\EventInterface;

/**
 * The page's scripts are about to be rendered: the last moment to register one.
 */
final class ScriptsAssembling implements EventInterface {}
