<?php

declare(strict_types=1);

namespace PunBB\Module\Groups\Event;

use PunBB\Module\Framework\Event\EventInterface;

/**
 * The groups page was asked for, before anything about the visitor is checked.
 */
final class GroupsRequested implements EventInterface {}
