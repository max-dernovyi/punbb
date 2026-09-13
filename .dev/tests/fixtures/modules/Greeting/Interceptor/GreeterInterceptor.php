<?php

declare(strict_types=1);

namespace PunBBFixture\Module\Greeting\Interceptor;

use PunBB\Module\Framework\Plugin\PluginChain;
use PunBBFixture\Module\Greeting\Api\Data\GreetingInterface;
use PunBBFixture\Module\Greeting\Api\GreeterInterface;

final class GreeterInterceptor implements GreeterInterface {
	public function __construct(private readonly GreeterInterface $subject, private readonly PluginChain $plugins) {}

	public function greet(string $name): GreetingInterface {
		return $this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->greet(...));
	}

	public function farewell(string $name): GreetingInterface {
		return $this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->farewell(...));
	}
}
