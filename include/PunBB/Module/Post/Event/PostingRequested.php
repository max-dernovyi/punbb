<?php

declare(strict_types=1);

namespace PunBB\Module\Post\Event;

use PunBB\Module\Framework\Event\EventInterface;

/**
 * The posting page was asked for, before anything about the visitor is checked.
 */
final class PostingRequested implements EventInterface {}
