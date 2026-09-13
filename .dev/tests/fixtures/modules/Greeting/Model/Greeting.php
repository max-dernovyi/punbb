<?php

declare(strict_types=1);

namespace PunBBFixture\Module\Greeting\Model;

use PunBBFixture\Module\Greeting\Api\Data\GreetingInterface;

final readonly class Greeting implements GreetingInterface {
	public function __construct(private string $recipient, private string $text) {}

	public function recipient(): string {
		return $this->recipient;
	}

	public function text(): string {
		return $this->text;
	}

	public function withText(string $text): GreetingInterface {
		return new self($this->recipient, $text);
	}
}
