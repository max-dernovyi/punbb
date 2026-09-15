<?php

declare(strict_types=1);

namespace PunBB\Module\Misc\Event;

use PunBB\Module\Framework\Event\EventInterface;

/**
 * misc.php was asked for: the rules, marking read, the search plugin, a form or a subscription, before anything is checked.
 */
final class MiscRequested implements EventInterface {}
