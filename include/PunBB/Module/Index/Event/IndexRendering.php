<?php

declare(strict_types=1);

namespace PunBB\Module\Index\Event;

use InvalidArgumentException;
use PunBB\Module\Framework\Event\EventInterface;

/**
 * A position in the board index, which an observer may add markup at: around
 * the forums, and around the statistics and the visitors online below them.
 */
final class IndexRendering implements EventInterface {
	public const MAIN_OUTPUT_START = 'main_output_start';

	/** After the forums, or the message that there are none. */
	public const END = 'end';

	public const INFO_OUTPUT_START = 'info_output_start';

	public const STATS_END = 'stats_end';

	/** Before the visitors online, also where the board does not list them. */
	public const USERS_ONLINE_START = 'users_online_start';

	/** Inside the visitors online, after their names. */
	public const NEW_ONLINE_DATA = 'new_online_data';

	public const USERS_ONLINE_END = 'users_online_end';

	public const INFO_END = 'info_end';

	private const POSITIONS = array(self::MAIN_OUTPUT_START, self::END, self::INFO_OUTPUT_START, self::STATS_END, self::USERS_ONLINE_START, self::NEW_ONLINE_DATA, self::USERS_ONLINE_END, self::INFO_END);

	private string $markup = '';

	public function __construct(private readonly string $position) {
		if (!in_array($position, self::POSITIONS, true))
			throw new InvalidArgumentException(sprintf('The board index has no position "%s"', $position));
	}

	public function position(): string {
		return $this->position;
	}

	public function append(string $markup): void {
		$this->markup .= $markup;
	}

	public function markup(): string {
		return $this->markup;
	}
}
