<?php

declare(strict_types=1);

namespace PunBB\Module\Viewtopic\Event;

use PunBB\Module\Framework\Event\EventInterface;

/**
 * A topic's page was asked for, before anything about the visitor is checked.
 */
final class TopicViewRequested implements EventInterface {}
