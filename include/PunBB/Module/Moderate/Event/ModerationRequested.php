<?php

declare(strict_types=1);

namespace PunBB\Module\Moderate\Event;

use PunBB\Module\Framework\Event\EventInterface;

/**
 * The moderation page was asked for, before anything about the visitor is checked.
 */
final class ModerationRequested implements EventInterface {}
