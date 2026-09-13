<?php

declare(strict_types=1);

namespace PunBBFixture\Module\Greeting\Model;

use PunBBFixture\Module\Greeting\Api\Data\GreetingInterface;
use PunBBFixture\Module\Greeting\Api\GreeterInterface;

final class Greeter implements GreeterInterface {
	public function __construct(private readonly Journal $journal) {}

	public function greet(string $name): GreetingInterface {
		$this->journal->record('Greeter::greet('.$name.')');

		return new Greeting($name, 'Hello, '.$name);
	}

	public function farewell(string $name): GreetingInterface {
		$this->journal->record('Greeter::farewell('.$name.')');

		return new Greeting($name, 'Goodbye, '.$name);
	}
}
