<?php

declare(strict_types=1);

namespace PunBBFixture\Module\Courtesy;

use PunBB\Module\Framework\Container\Container;
use PunBB\Module\Framework\Modules\ModuleInterface;
use PunBB\Module\Framework\Modules\Wiring;
use PunBBFixture\Module\Courtesy\Observer\PostscriptObserver;
use PunBBFixture\Module\Courtesy\Plugin\HonorificPlugin;
use PunBBFixture\Module\Greeting\Api\GreeterInterface;
use PunBBFixture\Module\Greeting\Event\GreetingSending;
use PunBBFixture\Module\Greeting\Model\Journal;

/**
 * Fixture: plugs the Greeting module's contract and observes its event, each
 * after Greeting's own.
 */
final class Module implements ModuleInterface {
	public function name(): string {
		return 'Courtesy';
	}

	public function dependencies(): array {
		return array('Greeting');
	}

	public function loadAfter(): array {
		return array();
	}

	public function wire(Wiring $wiring): void {
		$wiring->plugin(GreeterInterface::class, HonorificPlugin::class, fn (Container $c): object => new HonorificPlugin($c->get(Journal::class)));
		$wiring->observer(GreetingSending::class, PostscriptObserver::class, fn (Container $c): object => new PostscriptObserver($c->get(Journal::class)));
	}
}
