<?php
/**
 * Contracts and interceptors that break the service-contract rules, one rule
 * each, so ServiceContractTest can prove its checks catch them.
 */

declare(strict_types=1);

namespace PunBBFixture\BrokenContract\Api\Data {
	interface RowInterface {
		public function title(): string;
	}
}

namespace PunBBFixture\BrokenContract\Api {
	use ArrayAccess;
	use PunBBFixture\BrokenContract\Api\Data\RowInterface;
	use PunBBFixture\Module\Greeting\Model\Journal;

	interface TypedInterface {
		/** @param list<RowInterface> $rows */
		public function save(array $rows, ?RowInterface $parent, int|string $id): void;

		/** @return list<Data\RowInterface> */
		public function all(): array;

		/** @return list<int>|null */
		public function ids(bool $visible = true): ?array;
	}

	interface MagicInterface {
		public function __call(string $name, array $arguments): string;
	}

	interface OffsetInterface extends ArrayAccess {}

	interface ColumnsInterface {
		/** @return array<string, mixed> */
		public function row(int $id): array;
	}

	interface RowsOfColumnsInterface {
		/** @return list<array<string, string>> */
		public function rows(): array;
	}

	interface UndocumentedArrayInterface {
		public function rows(): array;
	}

	interface MixedInterface {
		public function find(mixed $id): ?RowInterface;
	}

	interface UntypedInterface {
		public function find($id);
	}

	interface ReferenceInterface {
		public function fill(string &$title): void;
	}

	interface ServiceParameterInterface {
		public function record(Journal $journal): void;
	}

	interface StaticInterface {
		public static function create(): RowInterface;
	}

	interface WideInterface {
		public function find(int $a, int $b, int $c, int $d, int $e, int $f, int $g, int $h, int $i): void;
	}
}

namespace PunBBFixture\BrokenContract\Interceptor {
	use PunBB\Module\Framework\Plugin\PluginChain;
	use PunBBFixture\Module\Greeting\Api\Data\GreetingInterface;
	use PunBBFixture\Module\Greeting\Api\GreeterInterface;

	final class MisroutedInterceptor implements GreeterInterface {
		public function __construct(private readonly GreeterInterface $subject, private readonly PluginChain $plugins) {}

		public function greet(string $name): GreetingInterface {
			return $this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->farewell(...));
		}

		public function farewell(string $name): GreetingInterface {
			return $this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->farewell(...));
		}
	}

	final class ChattyInterceptor implements GreeterInterface {
		public function __construct(private readonly GreeterInterface $subject, private readonly PluginChain $plugins) {}

		public function greet(string $name): GreetingInterface {
			$name = trim($name);
			return $this->plugins->call($this, __FUNCTION__, array($name), $this->subject->greet(...));
		}

		public function farewell(string $name): GreetingInterface {
			return $this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->farewell(...));
		}

		public function shout(string $name): string {
			return strtoupper($name);
		}
	}
}
