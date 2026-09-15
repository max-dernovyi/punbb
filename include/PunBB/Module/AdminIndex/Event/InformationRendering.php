<?php

declare(strict_types=1);

namespace PunBB\Module\AdminIndex\Event;

use InvalidArgumentException;
use PunBB\Module\Framework\Event\EventInterface;

/**
 * A position in the administration's index, which an observer may add markup
 * at. The page numbers its boxes in order; markup that adds a box counts it,
 * and the page numbers on from there.
 */
final class InformationRendering implements EventInterface {
	public const MAIN_OUTPUT_START = 'main_output_start';

	public const PRE_VERSION = 'pre_version';

	public const PRE_COMMUNITY = 'pre_community';

	public const PRE_SERVER_LOAD = 'pre_server_load';

	/** Before the environment box, reached also by a moderator, who is not shown it. */
	public const PRE_ENVIRONMENT = 'pre_environment';

	/** Before the database box, reached only by an administrator. */
	public const PRE_DATABASE = 'pre_database';

	public const ITEMS_END = 'items_end';

	public const END = 'end';

	public const POSITIONS = array(
		self::MAIN_OUTPUT_START, self::PRE_VERSION, self::PRE_COMMUNITY, self::PRE_SERVER_LOAD,
		self::PRE_ENVIRONMENT, self::PRE_DATABASE, self::ITEMS_END, self::END,
	);

	private string $markup = '';

	public function __construct(private readonly string $position, private int $itemCount) {
		if (!in_array($position, self::POSITIONS, true))
			throw new InvalidArgumentException(sprintf('The administration\'s index has no position "%s"', $position));
	}

	public function position(): string {
		return $this->position;
	}

	/** The boxes numbered so far. */
	public function itemCount(): int {
		return $this->itemCount;
	}

	/** The box count once the markup added here is counted in. */
	public function count(int $items): void {
		$this->itemCount = $items;
	}

	public function append(string $markup): void {
		$this->markup .= $markup;
	}

	public function markup(): string {
		return $this->markup;
	}
}
