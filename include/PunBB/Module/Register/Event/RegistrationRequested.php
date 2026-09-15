<?php

declare(strict_types=1);

namespace PunBB\Module\Register\Event;

use PunBB\Module\Framework\Event\EventInterface;

/**
 * The registration page was asked for, before anything about the visitor is checked.
 */
final class RegistrationRequested implements EventInterface {}
