<?php

declare(strict_types=1);

namespace PunBB\Module\Post\Event;

use InvalidArgumentException;
use PunBB\Module\Framework\Event\EventInterface;
use PunBB\Module\Layout\Event\MarkupEntries;
use PunBB\Module\Post\Api\Data\LocationInterface;
use PunBB\Module\Post\Api\Data\ReviewPostInterface;

/**
 * A stage of a post in the topic review below the reply form. Before it is
 * placed an observer may change the parts of its heading, each markup — num,
 * byline, link — and its message, markup; markup appended goes before the
 * post. At the head stage markup goes inside its heading, at the entry stage
 * inside it after its message. The posts are counted from the newest.
 */
final class ReviewPostAssembling implements EventInterface {
	use MarkupEntries;

	public const ROW = 'row';

	public const HEAD = 'head';

	public const ENTRY = 'entry';

	private const STAGES = array(self::ROW, self::HEAD, self::ENTRY);

	private string $markup = '';

	/**
	 * @param array<string, string> $ident
	 * @param int $itemCount the posts of the review counted so far, this one included
	 * @param int $itemTotal how many posts the review shows
	 */
	public function __construct(
		private readonly string $stage,
		private readonly LocationInterface $location,
		private readonly ReviewPostInterface $post,
		array $ident,
		private string $message,
		private int $itemCount,
		private readonly int $itemTotal
	) {
		if (!in_array($stage, self::STAGES, true))
			throw new InvalidArgumentException(sprintf('A post in the topic review has no stage "%s"', $stage));

		$this->entries = $ident;
	}

	public function stage(): string {
		return $this->stage;
	}

	public function location(): LocationInterface {
		return $this->location;
	}

	public function post(): ReviewPostInterface {
		return $this->post;
	}

	/** The message, parsed: markup. */
	public function message(): string {
		return $this->message;
	}

	public function setMessage(string $markup): void {
		$this->message = $markup;
	}

	public function itemCount(): int {
		return $this->itemCount;
	}

	public function itemTotal(): int {
		return $this->itemTotal;
	}

	public function count(int $items): void {
		$this->itemCount = $items;
	}

	public function append(string $markup): void {
		$this->markup .= $markup;
	}

	public function markup(): string {
		return $this->markup;
	}

	private function accept(string $name): void {}
}
