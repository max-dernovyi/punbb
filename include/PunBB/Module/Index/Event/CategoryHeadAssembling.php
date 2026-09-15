<?php

declare(strict_types=1);

namespace PunBB\Module\Index\Event;

use PunBB\Module\Framework\Event\EventInterface;
use PunBB\Module\Index\Api\Data\ForumInterface;
use PunBB\Module\Layout\Event\PartsByName;
use PunBB\Module\Layout\View\Parts;

/**
 * A category's heading on the board index, before it is placed: the labels
 * its summary names the subject column with, joined with spaces, and those it
 * names the figures with, joined with commas. Markup appended goes before the
 * heading.
 */
final class CategoryHeadAssembling implements EventInterface {
	use PartsByName;

	public const SUBJECT = 'subject';

	public const INFO = 'info';

	private string $markup = '';

	/** @param int $number the category's place on the page, from 1 */
	public function __construct(private readonly ForumInterface $firstForum, private readonly int $number, Parts $subject, Parts $info) {
		$this->parts = array(self::SUBJECT => $subject, self::INFO => $info);
	}

	/** The first forum listed in the category, which carries the category's id and name. */
	public function firstForum(): ForumInterface {
		return $this->firstForum;
	}

	public function number(): int {
		return $this->number;
	}

	public function append(string $markup): void {
		$this->markup .= $markup;
	}

	public function markup(): string {
		return $this->markup;
	}
}
