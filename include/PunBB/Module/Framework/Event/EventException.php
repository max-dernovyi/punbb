<?php

declare(strict_types=1);

namespace PunBB\Module\Framework\Event;

use LogicException;

/** An observer broke its event's contract while it ran. */
final class EventException extends LogicException {}
