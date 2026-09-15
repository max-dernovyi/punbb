<?php

declare(strict_types=1);

namespace PunBB\Module\Viewforum\Event;

use PunBB\Module\Framework\Event\EventInterface;

/**
 * A forum's page was asked for, before anything about the visitor is checked.
 */
final class ForumViewRequested implements EventInterface {}
