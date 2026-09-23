<?php

declare(strict_types=1);

namespace PunBBModule\Guestbook\Plugin;

use PunBB\Module\Index\Api\BoardIndexInterface;
use PunBB\Module\Index\Api\Data\StatisticsInterface;
use PunBBModule\Guestbook\Model\CountedStatistics;
use PunBBModule\Guestbook\Model\Entries;

final class StatisticsPlugin {
	public function __construct(private readonly Entries $entries) {}

	public function afterStatistics(BoardIndexInterface $subject, StatisticsInterface $result): StatisticsInterface {
		return new CountedStatistics($result, $this->entries->count());
	}
}
