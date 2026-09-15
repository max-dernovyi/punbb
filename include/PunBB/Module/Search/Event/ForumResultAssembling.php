<?php

declare(strict_types=1);

namespace PunBB\Module\Search\Event;

use InvalidArgumentException;
use PunBB\Module\Framework\Event\EventInterface;
use PunBB\Module\Layout\Event\PartsByName;
use PunBB\Module\Search\Api\Data\ResultForumInterface;
use PunBB\Module\Search\View\ForumResult;

/**
 * A stage of a forum subscribed to, where an observer may change the parts
 * built so far: its category's summary labels when it opens one, its classes,
 * the parts of its title and of the line below it, and the lines of its
 * subject and its figures. The forums are counted within their category, and
 * markup appended goes before the forum, or before its category's heading.
 */
final class ForumResultAssembling implements EventInterface {
	use PartsByName;

	/** The labels of a new category's summary, before its heading. */
	public const CATEGORY_HEAD = 'category_head';

	/** A forum on another site: the parts of the line below its title, before they are joined. */
	public const REDIRECT_SUBJECT = 'redirect_subject';

	/** A forum on another site: its lines, before its classes. */
	public const REDIRECT_BODY = 'redirect_body';

	/** The parts of the title, before they are joined. */
	public const TITLE = 'title';

	/** The parts of the line below the title, before they are joined. */
	public const SUBJECT = 'subject';

	/** The lines of the subject and the figures, before the row's classes. */
	public const BODY = 'body';

	/** The row, classes and all, before it is placed. */
	public const ROW = 'row';

	public const PART_HEADER_SUBJECT = 'header_subject';

	public const PART_HEADER_INFO = 'header_info';

	public const PART_STATUS = 'status';

	public const PART_TITLE = 'title';

	public const PART_SUBJECT = 'subject';

	public const PART_BODY_SUBJECT = 'body_subject';

	public const PART_BODY_INFO = 'body_info';

	private const STAGES = array(self::CATEGORY_HEAD, self::REDIRECT_SUBJECT, self::REDIRECT_BODY, self::TITLE, self::SUBJECT, self::BODY, self::ROW);

	private string $markup = '';

	/** @param int $categoryCount the categories opened, this forum's among them */
	public function __construct(
		private readonly string $stage,
		private readonly ResultForumInterface $forum,
		private readonly ForumResult $result,
		private int $itemCount,
		private readonly int $categoryCount
	) {
		if (!in_array($stage, self::STAGES, true))
			throw new InvalidArgumentException(sprintf('A forum subscribed to has no stage "%s"', $stage));

		$this->parts = array(
			self::PART_HEADER_SUBJECT	=> $result->headerSubject,
			self::PART_HEADER_INFO		=> $result->headerInfo,
			self::PART_STATUS			=> $result->status,
			self::PART_TITLE			=> $result->title,
			self::PART_SUBJECT			=> $result->subject,
			self::PART_BODY_SUBJECT		=> $result->bodySubject,
			self::PART_BODY_INFO		=> $result->bodyInfo,
		);
	}

	public function stage(): string {
		return $this->stage;
	}

	public function forum(): ResultForumInterface {
		return $this->forum;
	}

	/** The row's classes after main-item, each with the space before it. */
	public function style(): string {
		return $this->result->style;
	}

	public function setStyle(string $style): void {
		$this->result->style = $style;
	}

	public function categoryCount(): int {
		return $this->categoryCount;
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
