<?php

declare(strict_types=1);

namespace PunBB\Module\Moderate\Model;

use PunBB\Module\Moderate\Api\Data\ModeratedForumInterface;
use PunBB\Module\Moderate\Api\Data\ModeratorInterface;

final readonly class ModeratedForum implements ModeratedForumInterface {
	/** @param list<ModeratorInterface> $moderators */
	public function __construct(
		private int $id,
		private string $name,
		private string $redirectUrl,
		private int $topicCount,
		private array $moderators,
		private bool $sortsByPosted
	) {}

	/** @return list<Moderator> the moderators a forum stores serialized, username => user id */
	public static function moderatorsOf(?string $stored): array {
		$moderators = $stored !== null && $stored !== '' ? @unserialize($stored, array('allowed_classes' => false)) : array();

		$list = array();
		foreach (is_array($moderators) ? $moderators : array() as $username => $id)
			$list[] = new Moderator(is_numeric($id) ? (int) $id : 0, (string) $username);

		return $list;
	}

	public function id(): int {
		return $this->id;
	}

	public function name(): string {
		return $this->name;
	}

	public function redirectUrl(): string {
		return $this->redirectUrl;
	}

	public function topicCount(): int {
		return $this->topicCount;
	}

	public function moderators(): array {
		return $this->moderators;
	}

	public function sortsByPosted(): bool {
		return $this->sortsByPosted;
	}
}
