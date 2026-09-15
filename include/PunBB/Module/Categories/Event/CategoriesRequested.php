<?php

declare(strict_types=1);

namespace PunBB\Module\Categories\Event;

use PunBB\Module\Framework\Event\EventInterface;

/**
 * The categories page was asked for, before anything about the visitor is checked.
 */
final class CategoriesRequested implements EventInterface {}
