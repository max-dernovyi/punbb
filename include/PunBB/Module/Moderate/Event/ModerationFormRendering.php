<?php

declare(strict_types=1);

namespace PunBB\Module\Moderate\Event;

use InvalidArgumentException;
use PunBB\Module\Framework\Event\EventInterface;
use PunBB\Module\Layout\Event\FormMarkup;
use PunBB\Module\Layout\Event\MarkupEntries;

/**
 * A position in a form confirming a change of posts or topics, which an
 * observer may add markup at: deleting posts, splitting them off, moving,
 * merging or deleting topics. At the form's start its hidden fields, named
 * ('csrf_token', 'posts', 'topics'), may still change. The form numbers its
 * field groups, items and fields in order.
 */
final class ModerationFormRendering implements EventInterface {
	use FormMarkup;
	use MarkupEntries;

	public const DELETE_POSTS = 'delete_posts';

	public const SPLIT_POSTS = 'split_posts';

	public const MOVE_TOPICS = 'move_topics';

	public const MERGE_TOPICS = 'merge_topics';

	public const DELETE_TOPICS = 'delete_topics';

	public const OUTPUT_START = 'output_start';

	public const PRE_FIELDSET = 'pre_fieldset';

	/** Before the new topic's subject, which only splitting asks for. */
	public const PRE_SUBJECT = 'pre_subject';

	/** Before the list of forums, which only moving asks for. */
	public const PRE_MOVE_TO_FORUM = 'pre_move_to_forum';

	/** Before leaving redirects, which moving and merging ask about. */
	public const PRE_REDIRECT_CHECKBOX = 'pre_redirect_checkbox';

	/** Before the confirmation, which deleting and splitting ask for. */
	public const PRE_CONFIRM_CHECKBOX = 'pre_confirm_checkbox';

	public const PRE_FIELDSET_END = 'pre_fieldset_end';

	/** After the fieldset, before the form's buttons. */
	public const FIELDSET_END = 'fieldset_end';

	public const END = 'end';

	/** @var array<string, list<string>> form => its positions, in order */
	public const POSITIONS = array(
		self::DELETE_POSTS	=> array(self::OUTPUT_START, self::PRE_FIELDSET, self::PRE_CONFIRM_CHECKBOX, self::PRE_FIELDSET_END, self::FIELDSET_END, self::END),
		self::SPLIT_POSTS	=> array(self::OUTPUT_START, self::PRE_FIELDSET, self::PRE_SUBJECT, self::PRE_CONFIRM_CHECKBOX, self::PRE_FIELDSET_END, self::FIELDSET_END, self::END),
		self::MOVE_TOPICS	=> array(self::OUTPUT_START, self::PRE_FIELDSET, self::PRE_MOVE_TO_FORUM, self::PRE_REDIRECT_CHECKBOX, self::PRE_FIELDSET_END, self::FIELDSET_END, self::END),
		self::MERGE_TOPICS	=> array(self::OUTPUT_START, self::PRE_FIELDSET, self::PRE_REDIRECT_CHECKBOX, self::PRE_FIELDSET_END, self::FIELDSET_END, self::END),
		self::DELETE_TOPICS	=> array(self::OUTPUT_START, self::PRE_FIELDSET, self::PRE_CONFIRM_CHECKBOX, self::PRE_FIELDSET_END, self::FIELDSET_END, self::END),
	);

	/**
	 * @param list<int> $ids the posts or the topics changed
	 * @param array<string, string> $hiddenFields the form's hidden fields, at its start only
	 */
	public function __construct(
		private readonly string $form,
		private readonly string $position,
		private readonly int $forumId,
		private readonly array $ids,
		array $hiddenFields,
		int $groupCount,
		int $itemCount,
		int $fieldCount
	) {
		if (!in_array($position, self::POSITIONS[$form] ?? array(), true))
			throw new InvalidArgumentException(sprintf('The moderation form "%s" has no position "%s"', $form, $position));

		$this->entries = $hiddenFields;
		$this->count($groupCount, $itemCount, $fieldCount);
	}

	public function form(): string {
		return $this->form;
	}

	public function position(): string {
		return $this->position;
	}

	public function forumId(): int {
		return $this->forumId;
	}

	/** @return list<int> */
	public function ids(): array {
		return $this->ids;
	}

	private function accept(string $name): void {
		if ($this->position !== self::OUTPUT_START)
			throw new InvalidArgumentException('A moderation form\'s hidden fields are placed by now');
	}
}
