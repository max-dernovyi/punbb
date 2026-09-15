<?php

declare(strict_types=1);

namespace PunBB\Module\Extern\Event;

use InvalidArgumentException;
use PunBB\Module\Extern\Api\Data\FeedEntryInterface;
use PunBB\Module\Extern\Api\Data\FeedItemInterface;
use PunBB\Module\Framework\Event\EventInterface;

/**
 * A feed while it is built, where an observer may change its title, link,
 * description and items: after each item is added, with the post it was
 * built from, and once every item is, before the feed is written.
 */
final class FeedAssembling implements EventInterface {
	/** An item was added, the last of the items. */
	public const ITEM = 'item';

	/** Every item was added. */
	public const COMPLETE = 'complete';

	/** A feed of the recent topics. */
	public const TOPICS = 'topics';

	/** A feed of a topic's recent posts. */
	public const POSTS = 'posts';

	/**
	 * @param string $link a URL, encoded for an attribute
	 * @param list<FeedItemInterface> $items
	 * @param ?FeedEntryInterface $entry the post the last item was built from, at the item stage
	 */
	public function __construct(
		private readonly string $stage,
		private readonly string $kind,
		private string $title,
		private string $link,
		private string $description,
		private array $items,
		private readonly ?FeedEntryInterface $entry = null
	) {
		if (!in_array($stage, array(self::ITEM, self::COMPLETE), true) || !in_array($kind, array(self::TOPICS, self::POSTS), true))
			throw new InvalidArgumentException(sprintf('A feed has no stage "%s" of kind "%s"', $stage, $kind));
	}

	public function stage(): string {
		return $this->stage;
	}

	public function kind(): string {
		return $this->kind;
	}

	public function entry(): ?FeedEntryInterface {
		return $this->entry;
	}

	public function title(): string {
		return $this->title;
	}

	public function setTitle(string $title): void {
		$this->title = $title;
	}

	public function link(): string {
		return $this->link;
	}

	public function setLink(string $link): void {
		$this->link = $link;
	}

	public function description(): string {
		return $this->description;
	}

	public function setDescription(string $description): void {
		$this->description = $description;
	}

	/** @return list<FeedItemInterface> */
	public function items(): array {
		return $this->items;
	}

	/** @param list<FeedItemInterface> $items */
	public function replaceItems(array $items): void {
		$this->items = $items;
	}
}
