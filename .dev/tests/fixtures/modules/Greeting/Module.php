<?php

declare(strict_types=1);

namespace PunBBFixture\Module\Greeting;

use PunBB\Module\Framework\Container\Container;
use PunBB\Module\Framework\Event\EventDispatcher;
use PunBB\Module\Framework\Modules\ModuleInterface;
use PunBB\Module\Framework\Modules\Wiring;
use PunBBFixture\Module\Greeting\Api\GreeterInterface;
use PunBBFixture\Module\Greeting\Event\GreetingSending;
use PunBBFixture\Module\Greeting\Interceptor\GreeterInterceptor;
use PunBBFixture\Module\Greeting\Model\Greeter;
use PunBBFixture\Module\Greeting\Model\Journal;
use PunBBFixture\Module\Greeting\Model\Postman;
use PunBBFixture\Module\Greeting\Observer\SignatureObserver;
use PunBBFixture\Module\Greeting\Plugin\CapitalisePlugin;

/**
 * Fixture: owns the greeter contract and the sending event, and plugs and
 * observes them first.
 */
final class Module implements ModuleInterface {
	public function name(): string {
		return 'Greeting';
	}

	public function dependencies(): array {
		return array('Framework');
	}

	public function loadAfter(): array {
		return array();
	}

	public function wire(Wiring $wiring): void {
		$wiring->service(Journal::class, fn (): object => new Journal());
		$wiring->contract(GreeterInterface::class, GreeterInterceptor::class, fn (Container $c): object => new Greeter($c->get(Journal::class)));
		$wiring->plugin(GreeterInterface::class, CapitalisePlugin::class, fn (Container $c): object => new CapitalisePlugin($c->get(Journal::class)));
		$wiring->service(Postman::class, fn (Container $c): object => new Postman($c->get(EventDispatcher::class)));
		$wiring->observer(GreetingSending::class, SignatureObserver::class, fn (Container $c): object => new SignatureObserver($c->get(Journal::class)));
	}
}
