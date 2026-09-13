<?php

declare(strict_types=1);

namespace PunBBFixture\Module\Courtesy\Plugin;

use PunBBFixture\Module\Greeting\Api\Data\GreetingInterface;
use PunBBFixture\Module\Greeting\Api\GreeterInterface;
use PunBBFixture\Module\Greeting\Model\Journal;

/**
 * Adds a title to the name and a welcome to the greeting.
 */
final class HonorificPlugin {
	public function __construct(private readonly Journal $journal) {}

	/** @return list<string>|null */
	public function beforeGreet(GreeterInterface $subject, string $name): ?array {
		$this->journal->record('Courtesy before('.$name.')');

		return array('Dr. '.$name);
	}

	public function afterGreet(GreeterInterface $subject, GreetingInterface $result): GreetingInterface {
		$this->journal->record('Courtesy after');

		return $result->withText($result->text().' Welcome back!');
	}
}
