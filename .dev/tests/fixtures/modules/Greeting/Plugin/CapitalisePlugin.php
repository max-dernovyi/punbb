<?php

declare(strict_types=1);

namespace PunBBFixture\Module\Greeting\Plugin;

use PunBBFixture\Module\Greeting\Api\Data\GreetingInterface;
use PunBBFixture\Module\Greeting\Api\GreeterInterface;
use PunBBFixture\Module\Greeting\Model\Journal;

/**
 * Tidies the name on the way in and ends the sentence on the way out.
 */
final class CapitalisePlugin {
	public function __construct(private readonly Journal $journal) {}

	/** @return list<string>|null */
	public function beforeGreet(GreeterInterface $subject, string $name): ?array {
		$this->journal->record('Greeting before('.$name.')');

		return array(ucfirst(trim($name)));
	}

	public function afterGreet(GreeterInterface $subject, GreetingInterface $result, string $name): GreetingInterface {
		$this->journal->record('Greeting after('.$name.')');

		return $result->withText($result->text().'.');
	}
}
