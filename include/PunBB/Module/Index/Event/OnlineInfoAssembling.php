<?php

declare(strict_types=1);

namespace PunBB\Module\Index\Event;

use PunBB\Module\Framework\Event\EventInterface;
use PunBB\Module\Layout\Event\PartsByName;
use PunBB\Module\Layout\View\Parts;

/**
 * The visitors online, before they are shown: the counts of guests and
 * members, and the members, each a name or a link, both joined with the
 * language's separator. Markup appended goes before them.
 */
final class OnlineInfoAssembling implements EventInterface {
	use PartsByName;

	public const COUNTS = 'counts';

	public const MEMBERS = 'members';

	private string $markup = '';

	public function __construct(Parts $counts, Parts $members, private readonly int $guestCount, private readonly int $memberCount) {
		$this->parts = array(self::COUNTS => $counts, self::MEMBERS => $members);
	}

	public function guestCount(): int {
		return $this->guestCount;
	}

	public function memberCount(): int {
		return $this->memberCount;
	}

	public function append(string $markup): void {
		$this->markup .= $markup;
	}

	public function markup(): string {
		return $this->markup;
	}
}
