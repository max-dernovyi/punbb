<?php

declare(strict_types=1);

namespace PunBB\Module\Profile\Event;

use PunBB\Module\Framework\Event\EventInterface;

/**
 * A profile was asked for, before the request is read.
 */
final class ProfileRequested implements EventInterface {}
