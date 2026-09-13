<?php

declare(strict_types=1);

namespace PunBBFixture\Module\Greeting\Api\Data;

interface GreetingInterface {
	public function recipient(): string;

	public function text(): string;

	public function withText(string $text): GreetingInterface;
}
