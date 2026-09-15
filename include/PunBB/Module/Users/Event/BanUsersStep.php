<?php

declare(strict_types=1);

namespace PunBB\Module\Users\Event;

use InvalidArgumentException;
use PunBB\Module\Framework\Event\EventInterface;

/**
 * A step of banning users: once some are selected, before the selection is
 * read; once the ban's form is submitted, before its expiry is checked; and
 * once their bans are stored, before the browser is sent back to the search form.
 */
final class BanUsersStep implements EventInterface {
	public const SELECTED = 'selected';

	/** Carries the message and the expiry as submitted, trimmed. */
	public const SUBMITTED = 'submitted';

	/** Carries the message, '' for none, and the expiry as a moment, null for none. */
	public const BANNED = 'banned';

	/** @param list<int> $ids the users selected; none while the selection is not read yet */
	public function __construct(
		private readonly string $step,
		private readonly array $ids = array(),
		private readonly string $message = '',
		private readonly string $expiry = '',
		private readonly ?int $expire = null
	) {
		if (!in_array($step, array(self::SELECTED, self::SUBMITTED, self::BANNED), true))
			throw new InvalidArgumentException(sprintf('Banning users has no step "%s"', $step));
	}

	public function step(): string {
		return $this->step;
	}

	/** @return list<int> */
	public function ids(): array {
		return $this->ids;
	}

	/** What the banned users are told. */
	public function message(): string {
		return $this->message;
	}

	/** When the bans end, as submitted: a date, 'Never' or ''. */
	public function expiry(): string {
		return $this->expiry;
	}

	/** When the bans end, once banned; null for bans removed by hand. */
	public function expire(): ?int {
		return $this->expire;
	}
}
