<?php

declare(strict_types=1);

namespace PunBBFixture\Module\Greeting;

use PunBB\Module\Database\Schema\Column;
use PunBB\Module\Database\Schema\Table;
use PunBB\Module\Database\Schema\TableOwnerInterface;
use PunBB\Module\Database\Sql\Platform;
use PunBB\Module\Framework\Container\Container;
use PunBB\Module\Framework\Event\EventDispatcher;
use PunBB\Module\Framework\Modules\ModuleInterface;
use PunBB\Module\Framework\Modules\Wiring;
use PunBBFixture\Module\Greeting\Api\GreeterInterface;
use PunBBFixture\Module\Greeting\Controller\GreetingController;
use PunBBFixture\Module\Greeting\Event\GreetingSending;
use PunBBFixture\Module\Greeting\Interceptor\GreeterInterceptor;
use PunBBFixture\Module\Greeting\Model\Greeter;
use PunBBFixture\Module\Greeting\Model\Journal;
use PunBBFixture\Module\Greeting\Model\Postman;
use PunBBFixture\Module\Greeting\Observer\SignatureObserver;
use PunBBFixture\Module\Greeting\Plugin\CapitalisePlugin;

/**
 * Fixture: owns the greeter contract, the sending event, a route and a table,
 * and plugs and observes them first.
 */
final class Module implements ModuleInterface, TableOwnerInterface {
	public function name(): string {
		return 'Greeting';
	}

	public function dependencies(): array {
		return array('Framework', 'Database');
	}

	public function loadAfter(): array {
		return array();
	}

	public function version(): string {
		return '1.0.0';
	}

	public function wire(Wiring $wiring): void {
		$wiring->service(Journal::class, fn (): object => new Journal());
		$wiring->contract(GreeterInterface::class, GreeterInterceptor::class, fn (Container $c): object => new Greeter($c->get(Journal::class)));
		$wiring->plugin(GreeterInterface::class, CapitalisePlugin::class, fn (Container $c): object => new CapitalisePlugin($c->get(Journal::class)));
		$wiring->service(Postman::class, fn (Container $c): object => new Postman($c->get(EventDispatcher::class)));
		$wiring->observer(GreetingSending::class, SignatureObserver::class, fn (Container $c): object => new SignatureObserver($c->get(Journal::class)));
		$wiring->route(array('greeting.php', 'greeting/'), GreetingController::class, fn (Container $c): object => new GreetingController($c->get(GreeterInterface::class)));
	}

	public function tables(Platform $platform): array {
		return array(new Table('greetings', array(
			new Column('id', 'INT(10) UNSIGNED', false, 0),
			new Column('recipient', 'VARCHAR(200)', false, ''),
		), array('id')));
	}
}
