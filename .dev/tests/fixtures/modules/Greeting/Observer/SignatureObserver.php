<?php

declare(strict_types=1);

namespace PunBBFixture\Module\Greeting\Observer;

use PunBBFixture\Module\Greeting\Event\GreetingSending;
use PunBBFixture\Module\Greeting\Model\Journal;

/**
 * Signs every greeting on its way out.
 */
final class SignatureObserver {
	public function __construct(private readonly Journal $journal) {}

	public function observe(GreetingSending $event): void {
		$this->journal->record('Greeting observed('.$event->greeting()->text().')');

		$event->reword($event->greeting()->text().' -- the forum');
	}
}
