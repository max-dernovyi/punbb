<?php

declare(strict_types=1);

namespace PunBB\Module\AdminIndex\Event;

use PunBB\Module\Framework\Event\EventInterface;

/**
 * An administrator asked for phpinfo(), before the server is asked whether it allows it.
 */
final class PhpInfoShowing implements EventInterface {}
