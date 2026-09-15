<?php

declare(strict_types=1);

namespace PunBB\Module\Layout\Chrome;

use LogicException;

/** The chrome was asked for something it does not carry. */
final class ChromeException extends LogicException {}
