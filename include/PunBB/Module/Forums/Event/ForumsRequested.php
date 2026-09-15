<?php

declare(strict_types=1);

namespace PunBB\Module\Forums\Event;

use PunBB\Module\Framework\Event\EventInterface;

/**
 * The forums page was asked for, before anything about the visitor is checked.
 */
final class ForumsRequested implements EventInterface {}
