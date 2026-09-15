<?php

declare(strict_types=1);

namespace PunBB\Module\Extern\Event;

use PunBB\Module\Framework\Event\EventInterface;

/**
 * Syndication was asked for, before anything about the visitor is checked.
 */
final class ExternRequested implements EventInterface {}
