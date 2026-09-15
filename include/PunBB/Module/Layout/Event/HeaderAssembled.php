<?php

declare(strict_types=1);

namespace PunBB\Module\Layout\Event;

use PunBB\Module\Framework\Event\EventInterface;

/**
 * Every region above the page's own content is final.
 */
final class HeaderAssembled implements EventInterface {}
