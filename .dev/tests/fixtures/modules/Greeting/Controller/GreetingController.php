<?php

declare(strict_types=1);

namespace PunBBFixture\Module\Greeting\Controller;

use PunBB\Module\Framework\Http\Request;
use PunBB\Module\Framework\Http\Response;
use PunBB\Module\Framework\Routing\ControllerInterface;
use PunBBFixture\Module\Greeting\Api\GreeterInterface;

/**
 * Fixture: answers its route with the greeting the plugged contract builds for
 * the name in the query.
 */
final class GreetingController implements ControllerInterface {
	public function __construct(private readonly GreeterInterface $greeter) {}

	public function handle(Request $request): Response {
		$name = $request->query['name'] ?? 'world';

		return new Response($this->greeter->greet(is_string($name) ? $name : 'world')->text(), 200, array('Content-Type' => 'text/plain; charset=utf-8'));
	}
}
