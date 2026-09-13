<?php
/**
 * Final interceptors of GreeterInterface whose constructor has the right arity
 * but not ($subject, PluginChain): the wiring must reject them by type.
 *
 * @copyright (C) 2008-2012 PunBB, partially based on code (C) 2008-2009 FluxBB.org
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package PunBB
 */

use PunBB\Module\Framework\Plugin\PluginChain;
use PunBBFixture\Module\Greeting\Api\Data\GreetingInterface;
use PunBBFixture\Module\Greeting\Api\GreeterInterface;
use PunBBFixture\Module\Greeting\Model\Journal;

final class InterceptorProbeWithoutChain implements GreeterInterface {
	public function __construct(private readonly GreeterInterface $subject, Journal $plugins) {}

	public function greet(string $name): GreetingInterface {
		return $this->subject->greet($name);
	}

	public function farewell(string $name): GreetingInterface {
		return $this->subject->farewell($name);
	}
}

final class InterceptorProbeSwapped implements GreeterInterface {
	public function __construct(PluginChain $plugins, private readonly GreeterInterface $subject) {}

	public function greet(string $name): GreetingInterface {
		return $this->subject->greet($name);
	}

	public function farewell(string $name): GreetingInterface {
		return $this->subject->farewell($name);
	}
}
