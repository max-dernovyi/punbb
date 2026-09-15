<?php

declare(strict_types=1);

namespace PunBB\Module\Search\Event;

use PunBB\Module\Framework\Event\EventInterface;

/**
 * The search page was asked for, before anything about the visitor is checked.
 */
final class SearchRequested implements EventInterface {}
