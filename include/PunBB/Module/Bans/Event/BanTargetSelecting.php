<?php

declare(strict_types=1);

namespace PunBB\Module\Bans\Event;

use InvalidArgumentException;
use PunBB\Module\Framework\Event\EventInterface;

/**
 * What the ban form is about to be filled in from was chosen, before it is
 * looked up: a member by id, from a link; a member by the username the list's
 * form submitted, empty for a ban tied to nobody; or a stored ban, to edit.
 */
final class BanTargetSelecting implements EventInterface {
	public const USER = 'user';

	public const USERNAME = 'username';

	public const BAN = 'ban';

	private const TARGETS = array(self::USER, self::USERNAME, self::BAN);

	/**
	 * @param int $userId the member chosen by id; 0 otherwise
	 * @param string $username the username submitted; '' otherwise
	 * @param int $banId the ban to edit; 0 otherwise
	 */
	public function __construct(private readonly string $target, private readonly int $userId = 0, private readonly string $username = '', private readonly int $banId = 0) {
		if (!in_array($target, self::TARGETS, true))
			throw new InvalidArgumentException(sprintf('A ban form has no target "%s"', $target));
	}

	public function target(): string {
		return $this->target;
	}

	public function userId(): int {
		return $this->userId;
	}

	public function username(): string {
		return $this->username;
	}

	public function banId(): int {
		return $this->banId;
	}
}
