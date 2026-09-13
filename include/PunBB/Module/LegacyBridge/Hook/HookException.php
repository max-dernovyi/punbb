<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Hook;

use LogicException;

/** A legacy point was reached in a way its stored code cannot honour. */
final class HookException extends LogicException {}
