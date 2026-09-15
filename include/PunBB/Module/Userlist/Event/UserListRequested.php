<?php

declare(strict_types=1);

namespace PunBB\Module\Userlist\Event;

use PunBB\Module\Framework\Event\EventInterface;

/**
 * The member list was asked for, before anything about the visitor is checked.
 */
final class UserListRequested implements EventInterface {}
