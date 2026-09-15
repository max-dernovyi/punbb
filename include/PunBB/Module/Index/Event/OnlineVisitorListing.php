<?php

declare(strict_types=1);

namespace PunBB\Module\Index\Event;

use PunBB\Module\Framework\Event\EventInterface;
use PunBB\Module\Index\Api\Data\OnlineVisitorInterface;

/**
 * A visitor online about to be counted. Markup appended goes before the visitors online.
 */
final class OnlineVisitorListing implements EventInterface {
	private string $markup = '';

	public function __construct(private readonly OnlineVisitorInterface $visitor) {}

	public function visitor(): OnlineVisitorInterface {
		return $this->visitor;
	}

	public function append(string $markup): void {
		$this->markup .= $markup;
	}

	public function markup(): string {
		return $this->markup;
	}
}
