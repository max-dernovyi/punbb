<?php

declare(strict_types=1);

namespace PunBB\Module\Search\Event;

use InvalidArgumentException;
use PunBB\Module\Framework\Event\EventInterface;
use PunBB\Module\Layout\Event\PartsByName;
use PunBB\Module\Search\Api\Data\ResultPostInterface;
use PunBB\Module\Search\View\PostResult;

/**
 * A stage of a post found, where an observer may change what is built so far:
 * the parts of its heading, its links and its classes, and the markup of its
 * title, its author and its text. Markup appended goes before the post, and at
 * ENTRY after its text.
 */
final class PostResultAssembling implements EventInterface {
	use PartsByName;

	/** The heading's parts, before the rest of the post is built. */
	public const IDENT = 'ident';

	/** The post, built, before it is placed. */
	public const ROW = 'row';

	/** Inside the post, after its text. */
	public const ENTRY = 'entry';

	public const PART_IDENT = 'ident';

	public const PART_STATUS = 'status';

	public const PART_ACTIONS = 'actions';

	private string $markup = '';

	/**
	 * @param string $subject the topic's subject as the post shows it, censored
	 * @param int $number the post's place among the results, from 1
	 */
	public function __construct(
		private readonly string $stage,
		private readonly ResultPostInterface $post,
		private readonly string $subject,
		private readonly int $number,
		private readonly PostResult $result,
		private int $itemCount
	) {
		if (!in_array($stage, array(self::IDENT, self::ROW, self::ENTRY), true))
			throw new InvalidArgumentException(sprintf('A post found has no stage "%s"', $stage));

		$this->parts = array(self::PART_IDENT => $result->ident, self::PART_STATUS => $result->status, self::PART_ACTIONS => $result->actions);
	}

	public function stage(): string {
		return $this->stage;
	}

	public function post(): ResultPostInterface {
		return $this->post;
	}

	public function subject(): string {
		return $this->subject;
	}

	public function number(): int {
		return $this->number;
	}

	/** The title's markup: the topic's link, and its replies and forum. */
	public function title(): string {
		return $this->result->subject;
	}

	public function setTitle(string $markup): void {
		$this->result->subject = $markup;
	}

	/** The author's markup, a link to their profile where the visitor may view it. */
	public function author(): string {
		return $this->result->author;
	}

	public function setAuthor(string $markup): void {
		$this->result->author = $markup;
	}

	/** The post's text as markup. */
	public function message(): string {
		return $this->result->message;
	}

	public function setMessage(string $markup): void {
		$this->result->message = $markup;
	}

	public function itemCount(): int {
		return $this->itemCount;
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
}
