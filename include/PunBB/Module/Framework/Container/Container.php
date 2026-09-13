<?php

declare(strict_types=1);

namespace PunBB\Module\Framework\Container;

use Closure;
use Psr\Container\ContainerInterface;
use Throwable;

/**
 * A PSR-11 container over explicit factories: nothing is autowired, and every
 * service is created once, on first use.
 */
final class Container implements ContainerInterface {
	/** @var array<string, object> */
	private array $instances = array();

	/** @var array<string, true> ids being created, in request order */
	private array $resolving = array();

	/** @param array<string, Closure(Container): object> $factories */
	public function __construct(private readonly array $factories) {}

	/**
	 * @template T of object
	 * @param string|class-string<T> $id
	 * @return ($id is class-string<T> ? T : object)
	 */
	public function get(string $id): object {
		if (isset($this->instances[$id]))
			return $this->instances[$id];

		if (!isset($this->factories[$id]))
			throw new NotFoundException(sprintf('No service "%s" is wired', $id));

		if (isset($this->resolving[$id]))
			throw new ContainerException('Circular service dependency: '.implode(' -> ', array_keys($this->resolving)).' -> '.$id);

		$this->resolving[$id] = true;

		try {
			$service = ($this->factories[$id])($this);
		}
		catch (ContainerException $e) {
			throw $e;
		}
		// PSR-11: a missing dependency must not read as the entry itself missing.
		catch (Throwable $e) {
			throw new ContainerException(sprintf('Service "%s" could not be created: %s', $id, $e->getMessage()), 0, $e);
		}
		finally {
			unset($this->resolving[$id]);
		}

		if ((class_exists($id) || interface_exists($id)) && !$service instanceof $id)
			throw new ContainerException(sprintf('Service "%s" was wired to a %s', $id, $service::class));

		return $this->instances[$id] = $service;
	}

	public function has(string $id): bool {
		return isset($this->factories[$id]);
	}
}
