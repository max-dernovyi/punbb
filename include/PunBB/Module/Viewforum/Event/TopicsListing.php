<?php

declare(strict_types=1);

namespace PunBB\Module\Viewforum\Event;

use PunBB\Module\Framework\Event\EventInterface;
use PunBB\Module\Viewforum\Api\Data\ListedTopicInterface;
use PunBB\Module\Viewforum\Api\Data\ViewedForumInterface;

/**
 * The topics of the page, before their rows are built: an observer may keep
 * fewer of them. Markup appended goes before the first row.
 */
final class TopicsListing implements EventInterface {
	private string $markup = '';

	/** @param list<ListedTopicInterface> $topics */
	public function __construct(private readonly ViewedForumInterface $forum, private array $topics) {}

	public function forum(): ViewedForumInterface {
		return $this->forum;
	}

	/** @return list<ListedTopicInterface> in the order they are listed */
	public function topics(): array {
		return $this->topics;
	}

	/**
	 * Lists only the topics among $topicIds, in the order they had.
	 *
	 * @param list<int> $topicIds
	 */
	public function keep(array $topicIds): void {
		$this->topics = array_values(array_filter($this->topics, static fn (ListedTopicInterface $topic): bool => in_array($topic->id(), $topicIds, true)));
	}

	public function append(string $markup): void {
		$this->markup .= $markup;
	}

	public function markup(): string {
		return $this->markup;
	}
}
