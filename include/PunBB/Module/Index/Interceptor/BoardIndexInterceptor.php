<?php

declare(strict_types=1);

namespace PunBB\Module\Index\Interceptor;

use PunBB\Module\Framework\Plugin\PluginChain;
use PunBB\Module\Index\Api\BoardIndexInterface;
use PunBB\Module\Index\Api\Data\StatisticsInterface;

final class BoardIndexInterceptor implements BoardIndexInterface {
	public function __construct(private readonly BoardIndexInterface $subject, private readonly PluginChain $plugins) {}

	public function forums(int $groupId): array {
		return $this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->forums(...));
	}

	public function activeTopics(int $groupId, int $since): array {
		return $this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->activeTopics(...));
	}

	public function statistics(): StatisticsInterface {
		return $this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->statistics(...));
	}

	public function onlineVisitors(): array {
		return $this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->onlineVisitors(...));
	}
}
