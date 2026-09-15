<?php

declare(strict_types=1);

namespace PunBB\Module\Viewtopic\Model;

use PunBB\Module\Viewtopic\Api\Data\ModeratorInterface;

final readonly class Moderator implements ModeratorInterface {
	public function __construct(private int $userId, private string $username) {}

	/** @return list<self> the moderators a forum stores serialized, username => user id */
	public static function listOf(?string $stored): array {
		$moderators = $stored !== null && $stored !== '' ? @unserialize($stored, array('allowed_classes' => false)) : array();

		$list = array();
		foreach (is_array($moderators) ? $moderators : array() as $username => $id)
			$list[] = new self(is_numeric($id) ? (int) $id : 0, (string) $username);

		return $list;
	}

	/**
	 * The moderators as a forum stores them.
	 *
	 * @param list<ModeratorInterface> $moderators
	 */
	public static function stored(array $moderators): ?string {
		$stored = array();
		foreach ($moderators as $moderator)
			$stored[$moderator->username()] = $moderator->userId();

		return $stored !== array() ? serialize($stored) : null;
	}

	public function userId(): int {
		return $this->userId;
	}

	public function username(): string {
		return $this->username;
	}
}
