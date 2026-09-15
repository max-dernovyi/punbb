<?php

declare(strict_types=1);

namespace PunBB\Module\Reindex\Event;

use PunBB\Module\Framework\Event\EventInterface;

/**
 * The rebuild of the search index was asked for, before anything about the visitor is checked.
 */
final class ReindexRequested implements EventInterface {}
