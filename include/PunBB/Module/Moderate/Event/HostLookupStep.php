<?php

declare(strict_types=1);

namespace PunBB\Module\Moderate\Event;

use InvalidArgumentException;
use PunBB\Module\Framework\Event\EventInterface;

/**
 * A step of looking up the name an address resolves to: once it is asked for,
 * before the address or the post is read; and once the address is known,
 * before it is looked up.
 */
final class HostLookupStep implements EventInterface {
	public const SELECTED = 'selected';

	public const SHOWING = 'showing';

	public function __construct(private readonly string $step, private readonly string $address = '') {
		if (!in_array($step, array(self::SELECTED, self::SHOWING), true))
			throw new InvalidArgumentException(sprintf('Looking up an address has no step "%s"', $step));
	}

	public function step(): string {
		return $this->step;
	}

	/** What was asked for, an address or a post's id, once selected; the address looked up once it is known. */
	public function address(): string {
		return $this->address;
	}
}
