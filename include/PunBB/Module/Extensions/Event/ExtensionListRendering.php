<?php

declare(strict_types=1);

namespace PunBB\Module\Extensions\Event;

use InvalidArgumentException;
use PunBB\Module\Framework\Event\EventInterface;
use PunBB\Module\Layout\Event\PartsByName;
use PunBB\Module\Layout\View\Parts;

/**
 * A position on the list of extensions or of hotfixes, which an observer may
 * add markup at: before it, before what is available for install, before what
 * is installed, and after it. Before what is available, its boxes and the
 * boxes of the directories that failed to load may still change, with how
 * many of each are shown; none is shown unless its count says so.
 */
final class ExtensionListRendering implements EventInterface {
	use PartsByName;

	public const MANAGE = 'manage';

	public const HOTFIXES = 'hotfixes';

	public const OUTPUT_START = 'output_start';

	public const PRE_DISPLAY_AVAILABLE = 'pre_display_available';

	public const PRE_DISPLAY_INSTALLED = 'pre_display_installed';

	public const END = 'end';

	private const POSITIONS = array(self::OUTPUT_START, self::PRE_DISPLAY_AVAILABLE, self::PRE_DISPLAY_INSTALLED, self::END);

	/** The groups of parts: the boxes available for install, and those of the directories that failed to load. */
	public const AVAILABLE = 'available';

	public const FAILED = 'failed';

	private string $markup = '';

	public function __construct(
		private readonly string $list,
		private readonly string $position,
		?Parts $available = null,
		?Parts $failed = null,
		private int $availableCount = 0,
		private int $failedCount = 0
	) {
		if (!in_array($list, array(self::MANAGE, self::HOTFIXES), true) || !in_array($position, self::POSITIONS, true))
			throw new InvalidArgumentException(sprintf('The list of %s has no position "%s"', $list, $position));

		$this->parts = array(self::AVAILABLE => $available ?? new Parts(), self::FAILED => $failed ?? new Parts());
	}

	public function list(): string {
		return $this->list;
	}

	public function position(): string {
		return $this->position;
	}

	public function availableCount(): int {
		return $this->availableCount;
	}

	public function failedCount(): int {
		return $this->failedCount;
	}

	public function count(int $available, int $failed): void {
		$this->availableCount = $available;
		$this->failedCount = $failed;
	}

	public function append(string $markup): void {
		$this->markup .= $markup;
	}

	public function markup(): string {
		return $this->markup;
	}
}
