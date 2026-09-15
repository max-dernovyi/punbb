<?php

declare(strict_types=1);

namespace PunBB\Module\Users\Event;

use PunBB\Module\Framework\Event\EventInterface;

/**
 * The users page was asked for none of the searches and changes it knows,
 * before its search form is shown.
 */
final class UsersActionRequested implements EventInterface {}
