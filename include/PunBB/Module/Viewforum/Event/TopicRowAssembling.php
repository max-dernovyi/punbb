<?php

declare(strict_types=1);

namespace PunBB\Module\Viewforum\Event;

use InvalidArgumentException;
use PunBB\Module\Framework\Event\EventInterface;
use PunBB\Module\Layout\Event\PartsByName;
use PunBB\Module\Viewforum\Api\Data\ListedTopicInterface;
use PunBB\Module\Viewforum\View\TopicRow;

/**
 * A stage of a topic's row in its forum's list, where an observer may change
 * the parts built so far: its classes, the parts of its title and of the
 * title's status, its links to pages and new posts, the parts of the line
 * below the title, and the lines of its subject and its figures. The rows are
 * counted, the first odd; markup appended goes before the row.
 */
final class TopicRowAssembling implements EventInterface {
	use PartsByName;

	/** Before anything about the topic is built. */
	public const START = 'start';

	/** A topic moved elsewhere: its heading and the line below it, before the figures. */
	public const MOVED_SUBJECT = 'moved_subject';

	/** The sticky and closed marks, before they are joined into the title. */
	public const TITLE_STATUS = 'title_status';

	/** The parts of the title, before they are joined into the heading. */
	public const TITLE = 'title';

	/** The links to the topic's pages and its new posts, before they are joined. */
	public const NAV = 'nav';

	/** The parts of the line below the title, before they are joined. */
	public const SUBJECT = 'subject';

	/** The row's classes, before they are joined into its style. */
	public const STATUS = 'status';

	/** The row, classes and all, before it is placed. */
	public const ROW = 'row';

	/** The groups of parts: the row's classes, joined with spaces. */
	public const PART_STATUS = 'status';

	/** The title's parts, joined with spaces. */
	public const PART_TITLE = 'title';

	/** The sticky and closed marks, joined with commas. */
	public const PART_TITLE_STATUS = 'title_status';

	/** The links to the topic's pages and new posts, joined with two spaces. */
	public const PART_NAV = 'nav';

	/** The parts of the line below the title, joined with spaces. */
	public const PART_SUBJECT = 'subject';

	/** The lines of the subject cell. */
	public const PART_BODY_SUBJECT = 'body_subject';

	/** The lines of the figures. */
	public const PART_BODY_INFO = 'body_info';

	private const STAGES = array(self::START, self::MOVED_SUBJECT, self::TITLE_STATUS, self::TITLE, self::NAV, self::SUBJECT, self::STATUS, self::ROW);

	private string $markup = '';

	/**
	 * @param string $subject the topic's subject as the row shows it, censored
	 * @param int $number the topic's place in the forum, from 1
	 */
	public function __construct(
		private readonly string $stage,
		private readonly ListedTopicInterface $topic,
		private readonly string $subject,
		private readonly int $number,
		private readonly TopicRow $row,
		private int $itemCount
	) {
		if (!in_array($stage, self::STAGES, true))
			throw new InvalidArgumentException(sprintf('A topic\'s row has no stage "%s"', $stage));

		$this->parts = array(
			self::PART_STATUS		=> $row->status,
			self::PART_TITLE		=> $row->title,
			self::PART_TITLE_STATUS	=> $row->titleStatus,
			self::PART_NAV			=> $row->nav,
			self::PART_SUBJECT		=> $row->subject,
			self::PART_BODY_SUBJECT	=> $row->bodySubject,
			self::PART_BODY_INFO	=> $row->bodyInfo,
		);
	}

	public function stage(): string {
		return $this->stage;
	}

	public function topic(): ListedTopicInterface {
		return $this->topic;
	}

	/** The subject as the row shows it: censored where the board censors. */
	public function subject(): string {
		return $this->subject;
	}

	public function number(): int {
		return $this->number;
	}

	/** The row's classes after main-item, each with the space before it. */
	public function style(): string {
		return $this->row->style();
	}

	public function setStyle(string $style): void {
		$this->row->setStyle($style);
	}

	/** The rows counted so far. */
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
