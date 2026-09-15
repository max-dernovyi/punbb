<?php

declare(strict_types=1);

namespace PunBB\Module\Search\Event;

use InvalidArgumentException;
use PunBB\Module\Framework\Event\EventInterface;
use PunBB\Module\Layout\Event\PartsByName;
use PunBB\Module\Search\Api\Data\ResultTopicInterface;
use PunBB\Module\Search\View\TopicResult;

/**
 * A stage of a topic found, where an observer may change the parts built so
 * far: its classes, the parts of its title and of the title's status, its
 * links to pages and new posts, the parts of the line below the title, and the
 * lines of its subject and its figures. Markup appended goes before the row.
 */
final class TopicResultAssembling implements EventInterface {
	use PartsByName;

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

	public const PART_STATUS = 'status';

	public const PART_TITLE = 'title';

	public const PART_TITLE_STATUS = 'title_status';

	public const PART_NAV = 'nav';

	public const PART_SUBJECT = 'subject';

	public const PART_BODY_SUBJECT = 'body_subject';

	public const PART_BODY_INFO = 'body_info';

	private const STAGES = array(self::TITLE_STATUS, self::TITLE, self::NAV, self::SUBJECT, self::STATUS, self::ROW);

	private string $markup = '';

	/**
	 * @param string $subject the topic's subject as the row shows it, censored
	 * @param int $number the topic's place among the results, from 1
	 */
	public function __construct(
		private readonly string $stage,
		private readonly ResultTopicInterface $topic,
		private readonly string $subject,
		private readonly int $number,
		private readonly TopicResult $result,
		private int $itemCount
	) {
		if (!in_array($stage, self::STAGES, true))
			throw new InvalidArgumentException(sprintf('A topic found has no stage "%s"', $stage));

		$this->parts = array(
			self::PART_STATUS		=> $result->status,
			self::PART_TITLE		=> $result->title,
			self::PART_TITLE_STATUS	=> $result->titleStatus,
			self::PART_NAV			=> $result->nav,
			self::PART_SUBJECT		=> $result->subject,
			self::PART_BODY_SUBJECT	=> $result->bodySubject,
			self::PART_BODY_INFO	=> $result->bodyInfo,
		);
	}

	public function stage(): string {
		return $this->stage;
	}

	public function topic(): ResultTopicInterface {
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
		return $this->result->style;
	}

	public function setStyle(string $style): void {
		$this->result->style = $style;
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
