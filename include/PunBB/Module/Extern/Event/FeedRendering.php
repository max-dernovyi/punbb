<?php

declare(strict_types=1);

namespace PunBB\Module\Extern\Event;

use InvalidArgumentException;
use PunBB\Module\Extern\Api\Data\FeedItemInterface;
use PunBB\Module\Framework\Event\EventInterface;

/**
 * A position in a written feed, where an observer may add markup: among the
 * feed's own elements, or among an item's. Each format has its positions.
 */
final class FeedRendering implements EventInterface {
	public const RSS_INFO = 'rss_info';

	public const RSS_ITEM = 'rss_item';

	public const ATOM_INFO = 'atom_info';

	public const ATOM_ITEM = 'atom_item';

	public const XML_INFO = 'xml_info';

	public const XML_ITEM = 'xml_item';

	public const POSITIONS = array(self::RSS_INFO, self::RSS_ITEM, self::ATOM_INFO, self::ATOM_ITEM, self::XML_INFO, self::XML_ITEM);

	private string $markup = '';

	/**
	 * @param list<FeedItemInterface> $items every item of the feed
	 * @param ?FeedItemInterface $item the item written, at an item's position
	 */
	public function __construct(
		private readonly string $position,
		private readonly string $kind,
		private readonly string $title,
		private readonly string $link,
		private readonly string $description,
		private readonly array $items,
		private readonly ?FeedItemInterface $item = null
	) {
		if (!in_array($position, self::POSITIONS, true))
			throw new InvalidArgumentException(sprintf('A feed has no position "%s"', $position));
	}

	public function position(): string {
		return $this->position;
	}

	/** FeedAssembling::TOPICS or FeedAssembling::POSTS. */
	public function kind(): string {
		return $this->kind;
	}

	public function title(): string {
		return $this->title;
	}

	public function link(): string {
		return $this->link;
	}

	public function description(): string {
		return $this->description;
	}

	/** @return list<FeedItemInterface> */
	public function items(): array {
		return $this->items;
	}

	public function item(): ?FeedItemInterface {
		return $this->item;
	}

	public function append(string $markup): void {
		$this->markup .= $markup;
	}

	public function markup(): string {
		return $this->markup;
	}
}
