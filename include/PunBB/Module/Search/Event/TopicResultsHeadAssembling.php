<?php

declare(strict_types=1);

namespace PunBB\Module\Search\Event;

use PunBB\Module\Framework\Event\EventInterface;
use PunBB\Module\Layout\Event\PartsByName;
use PunBB\Module\Layout\View\Parts;

/**
 * The summary above topics found, before it is joined: an observer may change
 * the label of the subjects and of the figures, and add markup before the list.
 */
final class TopicResultsHeadAssembling implements EventInterface {
	use PartsByName;

	/** What the subjects are, joined with spaces. */
	public const SUBJECT = 'subject';

	/** What the figures are, joined with commas. */
	public const INFO = 'info';

	private string $markup = '';

	public function __construct(Parts $subject, Parts $info) {
		$this->parts = array(self::SUBJECT => $subject, self::INFO => $info);
	}

	public function append(string $markup): void {
		$this->markup .= $markup;
	}

	public function markup(): string {
		return $this->markup;
	}
}
