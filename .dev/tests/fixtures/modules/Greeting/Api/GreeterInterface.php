<?php

declare(strict_types=1);

namespace PunBBFixture\Module\Greeting\Api;

use PunBBFixture\Module\Greeting\Api\Data\GreetingInterface;

interface GreeterInterface {
	public function greet(string $name): GreetingInterface;

	public function farewell(string $name): GreetingInterface;
}
