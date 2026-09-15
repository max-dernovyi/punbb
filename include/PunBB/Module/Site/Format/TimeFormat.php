<?php

declare(strict_types=1);

namespace PunBB\Module\Site\Format;

/**
 * Which parts of a moment are shown.
 */
enum TimeFormat {
	case DateTime;
	case Date;
	case Time;
}
