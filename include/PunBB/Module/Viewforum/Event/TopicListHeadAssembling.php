<?php

declare(strict_types=1);

namespace PunBB\Module\Viewforum\Event;

use PunBB\Module\Framework\Event\EventInterface;
use PunBB\Module\Layout\Event\PartsByName;
use PunBB\Module\Layout\View\Parts;
use PunBB\Module\Viewforum\Api\Data\ViewedForumInterface;

/**
 * The head of a forum's topic list, before anything of the list is placed: the
 * labels its summary names the subject column with, joined with spaces, and
 * those it names the figures with, joined with commas; and the options above
 * and below the list, each joined with spaces. Markup appended goes before
 * the list.
 */
final class TopicListHeadAssembling implements EventInterface {
	use PartsByName;

	public const SUBJECT = 'subject';

	public const INFO = 'info';

	/** The links above the list: its feed, subscribing to the forum. */
	public const HEAD_OPTIONS = 'head_options';

	/** The links below the list: marking the forum read, moderating it. */
	public const FOOT_OPTIONS = 'foot_options';

	private string $markup = '';

	public function __construct(private readonly ViewedForumInterface $forum, Parts $subject, Parts $info, Parts $headOptions, Parts $footOptions) {
		$this->parts = array(self::SUBJECT => $subject, self::INFO => $info, self::HEAD_OPTIONS => $headOptions, self::FOOT_OPTIONS => $footOptions);
	}

	public function forum(): ViewedForumInterface {
		return $this->forum;
	}

	public function append(string $markup): void {
		$this->markup .= $markup;
	}

	public function markup(): string {
		return $this->markup;
	}
}
