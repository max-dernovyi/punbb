<?php

declare(strict_types=1);

namespace PunBB\Module\Settings\Event;

use PunBB\Module\Framework\Event\EventInterface;

/**
 * The settings were asked for, before anything about the visitor is checked.
 */
final class SettingsRequested implements EventInterface {}
