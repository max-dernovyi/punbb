<?php

declare(strict_types=1);

namespace PunBB\Module\Login\Event;

use PunBB\Module\Framework\Event\EventInterface;

/**
 * The login page was asked for: to sign in, to sign out or for a new password, before anything is checked.
 */
final class LoginRequested implements EventInterface {}
