<?php

declare(strict_types=1);

namespace PunBB\Module\Users\Event;

use PunBB\Module\Framework\Event\EventInterface;

/**
 * The users page was asked for, before anything about the visitor is checked.
 */
final class UsersRequested implements EventInterface {}
