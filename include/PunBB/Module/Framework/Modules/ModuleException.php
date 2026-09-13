<?php

declare(strict_types=1);

namespace PunBB\Module\Framework\Modules;

use LogicException;

/** The module graph or a module's wiring is wrong; fixed in code, never at runtime. */
final class ModuleException extends LogicException {}
