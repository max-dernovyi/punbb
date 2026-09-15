<?php

declare(strict_types=1);

namespace PunBB\Module\Userlist\Event;

use PunBB\Module\Framework\Event\EventInterface;
use PunBB\Module\Userlist\Api\Data\MemberInterface;

/**
 * A member about to be listed, before their row is built; markup appended goes before the row.
 */
final class MemberRowStarting implements EventInterface {
	private string $markup = '';

	public function __construct(private readonly MemberInterface $member) {}

	public function member(): MemberInterface {
		return $this->member;
	}

	public function append(string $markup): void {
		$this->markup .= $markup;
	}

	public function markup(): string {
		return $this->markup;
	}
}
