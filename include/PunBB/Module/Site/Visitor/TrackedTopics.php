<?php

declare(strict_types=1);

namespace PunBB\Module\Site\Visitor;

/**
 * What a visitor read since their last visit: when they last read each topic,
 * and when they marked each forum read.
 */
final readonly class TrackedTopics {
	/**
	 * @param array<int, int> $topics topic id => when it was read
	 * @param array<int, int> $forums forum id => when it was marked read
	 */
	public function __construct(private array $topics = array(), private array $forums = array()) {}

	/** When the topic was read; 0 when it was not. */
	public function topic(int $id): int {
		return $this->topics[$id] ?? 0;
	}

	/** When the forum was marked read; 0 when it was not. */
	public function forum(int $id): int {
		return $this->forums[$id] ?? 0;
	}

	/** @return array<int, int> topic id => when it was read */
	public function topics(): array {
		return $this->topics;
	}

	/** @return array<int, int> forum id => when it was marked read */
	public function forums(): array {
		return $this->forums;
	}
}
