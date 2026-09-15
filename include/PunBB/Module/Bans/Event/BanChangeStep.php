<?php

declare(strict_types=1);

namespace PunBB\Module\Bans\Event;

use InvalidArgumentException;
use PunBB\Module\Bans\Api\Data\BanInterface;
use PunBB\Module\Framework\Event\EventInterface;

/**
 * A step of changing a ban: a ban saved, once the form is submitted with
 * something to ban and before what it bans is checked, and once it is stored;
 * a ban removed, before and after. The browser is sent back to the list next.
 */
final class BanChangeStep implements EventInterface {
	/** The ban as submitted, trimmed, its email in lower case and its expiry not read yet. */
	public const SAVING = 'saving';

	/** The ban as stored. */
	public const SAVED = 'saved';

	/** A removal carries the ban's id only. */
	public const REMOVING = 'removing';

	public const REMOVED = 'removed';

	private const STEPS = array(self::SAVING, self::SAVED, self::REMOVING, self::REMOVED);

	/**
	 * @param bool $adding whether the ban is a new one
	 * @param string $expire the expiry date as submitted
	 */
	public function __construct(private readonly string $step, private readonly BanInterface $ban, private readonly bool $adding = false, private readonly string $expire = '') {
		if (!in_array($step, self::STEPS, true))
			throw new InvalidArgumentException(sprintf('Changing a ban has no step "%s"', $step));
	}

	public function step(): string {
		return $this->step;
	}

	public function ban(): BanInterface {
		return $this->ban;
	}

	public function adding(): bool {
		return $this->adding;
	}

	/** The expiry date as submitted: YYYY-MM-DD, "Never" or empty. */
	public function submittedExpire(): string {
		return $this->expire;
	}
}
