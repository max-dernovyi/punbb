<?php
/**
 * Events that break the event rules, one rule each, so EventContractTest can
 * prove its checks catch them.
 */

declare(strict_types=1);

namespace PunBBFixture\BrokenEvent;

use AllowDynamicProperties;
use ArrayAccess;
use ArrayIterator;
use IteratorAggregate;
use PunBB\Module\Framework\Event\EventInterface;
use PunBBFixture\Module\Greeting\Api\Data\GreetingInterface;
use PunBBFixture\Module\Greeting\Model\Journal;
use Traversable;

final class TypedEvent implements EventInterface {
	/** @param list<GreetingInterface> $greetings */
	public function __construct(public readonly int $topicId, private array $greetings, public private(set) string $subject = '') {}

	/** @return list<GreetingInterface> */
	public function greetings(): array {
		return $this->greetings;
	}

	public function add(GreetingInterface $greeting): void {
		$this->greetings[] = $greeting;
	}

	public function retitle(?string $subject): static {
		$this->subject = (string) $subject;
		return $this;
	}
}

class OpenEvent implements EventInterface {}

#[AllowDynamicProperties]
final class BagEvent implements EventInterface {}

final class MagicEvent implements EventInterface {
	public function __get(string $name): string {
		return $name;
	}
}

final class OffsetEvent implements EventInterface, ArrayAccess {
	public function offsetExists(mixed $offset): bool {
		return false;
	}

	public function offsetGet(mixed $offset): mixed {
		return null;
	}

	public function offsetSet(mixed $offset, mixed $value): void {}

	public function offsetUnset(mixed $offset): void {}
}

/** @implements IteratorAggregate<int, string> */
final class IterableEvent implements EventInterface, IteratorAggregate {
	public function getIterator(): Traversable {
		return new ArrayIterator(array());
	}
}

final class WritableEvent implements EventInterface {
	public string $text = '';
}

final class StaticEvent implements EventInterface {
	public static int $count = 0;
}

final class ColumnsEvent implements EventInterface {
	/** @return array<string, mixed> */
	public function row(): array {
		return array();
	}
}

final class ServiceEvent implements EventInterface {
	public function __construct(private Journal $journal) {}

	public function journal(): Journal {
		return $this->journal;
	}
}

final class ServicePropertyEvent implements EventInterface {
	public function __construct(public readonly Journal $journal) {}
}

final class MixedEvent implements EventInterface {
	public function payload(): mixed {
		return null;
	}
}

final class UntypedEvent implements EventInterface {
	public function reword($text) {}
}

final class ReferenceEvent implements EventInterface {
	private string $text = '';

	public function &text(): string {
		return $this->text;
	}
}

final class ReferenceParameterEvent implements EventInterface {
	public function fill(string &$text): void {}
}
