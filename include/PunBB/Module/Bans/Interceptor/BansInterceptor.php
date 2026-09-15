<?php

declare(strict_types=1);

namespace PunBB\Module\Bans\Interceptor;

use PunBB\Module\Bans\Api\BansInterface;
use PunBB\Module\Bans\Api\Data\BanInterface;
use PunBB\Module\Framework\Plugin\PluginChain;

final class BansInterceptor implements BansInterface {
	public function __construct(private readonly BansInterface $subject, private readonly PluginChain $plugins) {}

	public function count(): int {
		return $this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->count(...));
	}

	public function page(int $offset, int $limit): array {
		return $this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->page(...));
	}

	public function find(int $id): ?BanInterface {
		return $this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->find(...));
	}

	public function add(BanInterface ...$bans): void {
		$this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->add(...));
	}

	public function update(BanInterface ...$bans): void {
		$this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->update(...));
	}

	public function remove(int ...$ids): void {
		$this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->remove(...));
	}
}
