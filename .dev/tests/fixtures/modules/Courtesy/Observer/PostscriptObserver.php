<?php

declare(strict_types=1);

namespace PunBBFixture\Module\Courtesy\Observer;

use PunBBFixture\Module\Greeting\Event\GreetingSending;
use PunBBFixture\Module\Greeting\Model\Journal;

/**
 * Adds a postscript below whatever the greeting says by now.
 */
final class PostscriptObserver {
	public function __construct(private readonly Journal $journal) {}

	public function observe(GreetingSending $event): void {
		$this->journal->record('Courtesy observed('.$event->greeting()->text().')');

		$event->reword($event->greeting()->text().' P.S. Mind the gap.');
	}
}
