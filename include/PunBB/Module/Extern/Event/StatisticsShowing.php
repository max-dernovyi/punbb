<?php

declare(strict_types=1);

namespace PunBB\Module\Extern\Event;

use PunBB\Module\Extern\Api\Data\StatisticsInterface;
use PunBB\Module\Framework\Event\EventInterface;

/**
 * The board's statistics, before they are written; an observer may replace them.
 */
final class StatisticsShowing implements EventInterface {
	public function __construct(private StatisticsInterface $statistics) {}

	public function statistics(): StatisticsInterface {
		return $this->statistics;
	}

	public function replace(StatisticsInterface $statistics): void {
		$this->statistics = $statistics;
	}
}
