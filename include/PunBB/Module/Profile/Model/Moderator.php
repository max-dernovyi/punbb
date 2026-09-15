<?php

declare(strict_types=1);

namespace PunBB\Module\Profile\Model;

use PunBB\Module\Profile\Api\Data\ModeratorInterface;

final readonly class Moderator implements ModeratorInterface {
	public function __construct(private int $userId, private string $username) {}

	public function userId(): int {
		return $this->userId;
	}

	public function username(): string {
		return $this->username;
	}

	/**
	 * A moderators' list as a forum stores it serialized: username => id.
	 *
	 * @param list<ModeratorInterface> $moderators
	 * @return array<string, int>
	 */
	public static function stored(array $moderators): array {
		$stored = array();
		foreach ($moderators as $moderator)
			$stored[$moderator->username()] = $moderator->userId();

		return $stored;
	}

	/**
	 * @param array<array-key, int> $stored username => id
	 * @return list<self>
	 */
	public static function listed(array $stored): array {
		$moderators = array();
		foreach ($stored as $username => $userId)
			$moderators[] = new self($userId, (string) $username);

		return $moderators;
	}
}
