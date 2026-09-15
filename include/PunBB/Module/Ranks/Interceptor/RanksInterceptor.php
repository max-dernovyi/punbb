<?php

declare(strict_types=1);

namespace PunBB\Module\Ranks\Interceptor;

use PunBB\Module\Framework\Plugin\PluginChain;
use PunBB\Module\Ranks\Api\Data\RankInterface;
use PunBB\Module\Ranks\Api\RanksInterface;

final class RanksInterceptor implements RanksInterface {
	public function __construct(private readonly RanksInterface $subject, private readonly PluginChain $plugins) {}

	public function all(): array {
		return $this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->all(...));
	}

	public function minPostsTaken(int $minPosts, ?int $exceptId): bool {
		return $this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->minPostsTaken(...));
	}

	public function add(RankInterface ...$ranks): void {
		$this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->add(...));
	}

	public function update(RankInterface ...$ranks): void {
		$this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->update(...));
	}

	public function remove(int ...$ids): void {
		$this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->remove(...));
	}
}
