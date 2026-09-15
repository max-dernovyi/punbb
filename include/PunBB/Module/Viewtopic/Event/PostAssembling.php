<?php

declare(strict_types=1);

namespace PunBB\Module\Viewtopic\Event;

use InvalidArgumentException;
use PunBB\Module\Framework\Event\EventInterface;
use PunBB\Module\Layout\Event\PartsByName;
use PunBB\Module\Viewtopic\Api\Data\TopicPostInterface;
use PunBB\Module\Viewtopic\Api\Data\ViewedTopicInterface;
use PunBB\Module\Viewtopic\View\PostRow;

/**
 * A stage of a post in its topic's page, where an observer may change the
 * parts built so far, each markup: the parts of its heading, of what
 * identifies and describes its poster, of the poster's contacts, of its
 * actions, of the options below it, of its message, and its classes; and its
 * heading's subject. The posts are counted; markup appended goes before the
 * post, but at the entry stage inside it, after its message.
 */
final class PostAssembling implements EventInterface {
	use PartsByName;

	/** Before anything about the post is built. */
	public const START = 'start';

	/** The parts of its heading, before they are joined. */
	public const IDENT = 'ident';

	/** The poster's contacts, before they are joined into the options. */
	public const CONTACTS = 'contacts';

	/** Its actions, before they are joined into the options. */
	public const ACTIONS = 'actions';

	/** The post, classes and all, before it is placed. */
	public const ROW = 'row';

	/** What the page keeps of a member's parts for their next posts, before it is kept. */
	public const CACHED = 'cached';

	/** Inside the post, after its message. */
	public const ENTRY = 'entry';

	public const PART_POST_IDENT = 'post_ident';

	public const PART_AUTHOR_IDENT = 'author_ident';

	public const PART_AUTHOR_INFO = 'author_info';

	public const PART_POST_CONTACTS = 'post_contacts';

	public const PART_POST_ACTIONS = 'post_actions';

	public const PART_POST_OPTIONS = 'post_options';

	public const PART_MESSAGE = 'message';

	public const PART_ITEM_STATUS = 'item_status';

	private const STAGES = array(self::START, self::IDENT, self::CONTACTS, self::ACTIONS, self::ROW, self::CACHED, self::ENTRY);

	private string $markup = '';

	/**
	 * @param int $number the post's place in the topic, from 1
	 * @param int $itemCount the posts of the page counted so far
	 */
	public function __construct(
		private readonly string $stage,
		private readonly ViewedTopicInterface $topic,
		private readonly TopicPostInterface $post,
		private readonly int $number,
		private readonly PostRow $row,
		private int $itemCount
	) {
		if (!in_array($stage, self::STAGES, true))
			throw new InvalidArgumentException(sprintf('A post has no stage "%s"', $stage));

		$this->parts = array(
			self::PART_POST_IDENT		=> $row->postIdent,
			self::PART_AUTHOR_IDENT		=> $row->authorIdent,
			self::PART_AUTHOR_INFO		=> $row->authorInfo,
			self::PART_POST_CONTACTS	=> $row->postContacts,
			self::PART_POST_ACTIONS		=> $row->postActions,
			self::PART_POST_OPTIONS		=> $row->postOptions,
			self::PART_MESSAGE			=> $row->message,
			self::PART_ITEM_STATUS		=> $row->status,
		);
	}

	public function stage(): string {
		return $this->stage;
	}

	public function topic(): ViewedTopicInterface {
		return $this->topic;
	}

	public function post(): TopicPostInterface {
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
