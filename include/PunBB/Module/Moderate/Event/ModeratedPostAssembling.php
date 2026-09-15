<?php

declare(strict_types=1);

namespace PunBB\Module\Moderate\Event;

use InvalidArgumentException;
use PunBB\Module\Framework\Event\EventInterface;
use PunBB\Module\Layout\Event\PartsByName;
use PunBB\Module\Moderate\Api\Data\ModeratedPostInterface;
use PunBB\Module\Moderate\Api\Data\ModeratedTopicInterface;
use PunBB\Module\Moderate\View\PostRow;

/**
 * A stage of a post in the moderation of its topic, where an observer may
 * change the parts built so far, each markup: the parts of its heading, of
 * what identifies its poster and of its message, and its classes; its
 * heading's subject and the checkbox selecting it. The posts are counted.
 * Markup appended goes before the post at the first three stages, and inside
 * it at the last four: before the checkbox, after it, after the poster's
 * identity and after the message.
 */
final class ModeratedPostAssembling implements EventInterface {
	use PartsByName;

	/** Before anything about the post is built. */
	public const START = 'start';

	/** The parts of its heading, before they are joined. */
	public const IDENT = 'ident';

	/** The post, classes and all, before it is placed. */
	public const ROW = 'row';

	public const PRE_ITEM_SELECT = 'pre_item_select';

	public const HEAD_OPTION = 'head_option';

	public const USER_IDENT = 'user_ident';

	public const ENTRY = 'entry';

	public const PART_POST_IDENT = 'post_ident';

	public const PART_AUTHOR_IDENT = 'author_ident';

	public const PART_MESSAGE = 'message';

	public const PART_ITEM_STATUS = 'item_status';

	public const STAGES = array(self::START, self::IDENT, self::ROW, self::PRE_ITEM_SELECT, self::HEAD_OPTION, self::USER_IDENT, self::ENTRY);

	private string $markup = '';

	/**
	 * @param int $number the post's place in the topic, from 1
	 * @param int $itemCount the posts of the page counted so far
	 */
	public function __construct(
		private readonly string $stage,
		private readonly ModeratedTopicInterface $topic,
		private readonly ModeratedPostInterface $post,
		private readonly int $number,
		private readonly PostRow $row,
		private int $itemCount
	) {
		if (!in_array($stage, self::STAGES, true))
			throw new InvalidArgumentException(sprintf('A moderated post has no stage "%s"', $stage));

		$this->parts = array(
			self::PART_POST_IDENT	=> $row->postIdent,
			self::PART_AUTHOR_IDENT	=> $row->authorIdent,
			self::PART_MESSAGE		=> $row->message,
			self::PART_ITEM_STATUS	=> $row->status,
		);
	}

	public function stage(): string {
		return $this->stage;
	}

	public function topic(): ModeratedTopicInterface {
		return $this->topic;
	}

	public function post(): ModeratedPostInterface {
		return $this->post;
	}

	public function number(): int {
		return $this->number;
	}

	/** The heading of the post's message: the topic's subject, as a reply or as the topic. Markup. */
	public function subject(): string {
		return $this->row->subject();
	}

	public function setSubject(string $markup): void {
		$this->row->setSubject($markup);
	}

	/** The checkbox selecting the post, markup; '' for the topic's first post. */
	public function select(): string {
		return $this->row->select();
	}

	public function setSelect(string $markup): void {
		$this->row->setSelect($markup);
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
