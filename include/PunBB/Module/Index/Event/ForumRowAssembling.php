<?php

declare(strict_types=1);

namespace PunBB\Module\Index\Event;

use InvalidArgumentException;
use PunBB\Module\Framework\Event\EventInterface;
use PunBB\Module\Index\Api\Data\ForumInterface;
use PunBB\Module\Index\View\ForumRow;
use PunBB\Module\Layout\Event\PartsByName;

/**
 * A stage of a forum's row on the board index, where an observer may change
 * the parts built so far: its classes, the parts of its title and of the line
 * below it, its moderators, and the lines of its subject and its figures. The
 * rows are counted within their category, the first odd; markup appended goes
 * before the row.
 */
final class ForumRowAssembling implements EventInterface {
	use PartsByName;

	/** Before anything about the forum is built. */
	public const START = 'start';

	/** A forum on another site: its title and the line below it, before the line is joined. */
	public const REDIRECT_SUBJECT = 'redirect_subject';

	/** A forum on another site: its lines, before the row is placed. */
	public const REDIRECT_BODY = 'redirect_body';

	/** The parts of the title, before they are joined. */
	public const TITLE = 'title';

	/** The links to the moderators, before they are joined. */
	public const MODERATORS = 'moderators';

	/** The parts of the line below the title, before they are joined. */
	public const SUBJECT = 'subject';

	/** The lines of the subject and the figures, before the row's classes. */
	public const BODY = 'body';

	/** The row, classes and all, before it is placed. */
	public const ROW = 'row';

	/** The groups of parts: the row's classes, joined with spaces. */
	public const PART_STATUS = 'status';

	/** The title's parts, joined with spaces. */
	public const PART_TITLE = 'title';

	/** The parts of the line below the title, joined with spaces. */
	public const PART_SUBJECT = 'subject';

	/** The moderators' names or links, joined with commas. */
	public const PART_MODERATORS = 'moderators';

	/** The lines of the subject cell. */
	public const PART_BODY_SUBJECT = 'body_subject';

	/** The lines of the figures. */
	public const PART_BODY_INFO = 'body_info';

	private const STAGES = array(self::START, self::REDIRECT_SUBJECT, self::REDIRECT_BODY, self::TITLE, self::MODERATORS, self::SUBJECT, self::BODY, self::ROW);

	private string $markup = '';

	public function __construct(
		private readonly string $stage,
		private readonly ForumInterface $forum,
		private readonly ForumRow $row,
		private int $itemCount
	) {
		if (!in_array($stage, self::STAGES, true))
			throw new InvalidArgumentException(sprintf('A forum\'s row has no stage "%s"', $stage));

		$this->parts = array(
			self::PART_STATUS		=> $row->status,
			self::PART_TITLE		=> $row->title,
			self::PART_SUBJECT		=> $row->subject,
			self::PART_MODERATORS	=> $row->moderators,
			self::PART_BODY_SUBJECT	=> $row->bodySubject,
			self::PART_BODY_INFO	=> $row->bodyInfo,
		);
	}

	public function stage(): string {
		return $this->stage;
	}

	public function forum(): ForumInterface {
		return $this->forum;
	}

	/** The row's classes after main-item, each with the space before it. */
	public function style(): string {
		return $this->row->style();
	}

	public function setStyle(string $style): void {
		$this->row->setStyle($style);
	}

	/** The rows counted in the category so far. */
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
