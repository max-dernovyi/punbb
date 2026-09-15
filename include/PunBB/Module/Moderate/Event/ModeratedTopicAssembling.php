<?php

declare(strict_types=1);

namespace PunBB\Module\Moderate\Event;

use InvalidArgumentException;
use PunBB\Module\Framework\Event\EventInterface;
use PunBB\Module\Layout\Event\PartsByName;
use PunBB\Module\Moderate\Api\Data\ListedTopicInterface;
use PunBB\Module\Moderate\View\TopicRow;

/**
 * A stage of a topic's row in the moderation of its forum, where an observer
 * may change the parts built so far: its classes, the parts of its title and
 * of the title's status, its links to pages and new posts, the parts of the
 * line below the title, and the lines of its subject and its figures, the last
 * of which selects the topic. The rows and the checkboxes are counted; markup
 * appended goes before the row.
 */
final class ModeratedTopicAssembling implements EventInterface {
	use PartsByName;

	/** Before anything about the topic is built. */
	public const START = 'start';

	/** A topic moved elsewhere: its heading, before the figures. */
	public const MOVED_SUBJECT = 'moved_subject';

	/** A topic moved elsewhere, its figures built. */
	public const MOVED_ROW = 'moved_row';

	/** The sticky and closed marks, before they are joined into the title. */
	public const TITLE_STATUS = 'title_status';

	/** The parts of the title, before they are joined into the heading. */
	public const TITLE = 'title';

	/** The links to the topic's pages and its new posts, before they are joined. */
	public const NAV = 'nav';

	/** A topic of the forum, its figures built. */
	public const NORMAL_ROW = 'normal_row';

	/** The row's classes, before they are joined into its style. */
	public const STATUS = 'status';

	/** The row, classes and all, before it is placed. */
	public const ROW = 'row';

	public const PART_STATUS = 'status';

	public const PART_TITLE = 'title';

	public const PART_TITLE_STATUS = 'title_status';

	public const PART_NAV = 'nav';

	public const PART_SUBJECT = 'subject';

	public const PART_BODY_SUBJECT = 'body_subject';

	public const PART_BODY_INFO = 'body_info';

	private const STAGES = array(self::START, self::MOVED_SUBJECT, self::MOVED_ROW, self::TITLE_STATUS, self::TITLE, self::NAV, self::NORMAL_ROW, self::STATUS, self::ROW);

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
		private int $itemCount,
		private int $fieldCount
	) {
		if (!in_array($stage, self::STAGES, true))
			throw new InvalidArgumentException(sprintf('A moderated topic\'s row has no stage "%s"', $stage));

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

	/** The checkboxes numbered so far. */
	public function fieldCount(): int {
		return $this->fieldCount;
	}

	public function count(int $items, int $fields): void {
		$this->itemCount = $items;
		$this->fieldCount = $fields;
	}

	public function append(string $markup): void {
		$this->markup .= $markup;
	}

	public function markup(): string {
		return $this->markup;
	}
}
