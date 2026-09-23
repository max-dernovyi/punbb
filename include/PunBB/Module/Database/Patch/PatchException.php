<?php

declare(strict_types=1);

namespace PunBB\Module\Database\Patch;

use RuntimeException;

/** A data patch declared wrongly, or one that failed while it was applied. */
final class PatchException extends RuntimeException {}
