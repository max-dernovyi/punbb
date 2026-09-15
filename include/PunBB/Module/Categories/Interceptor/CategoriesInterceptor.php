<?php

declare(strict_types=1);

namespace PunBB\Module\Categories\Interceptor;

use PunBB\Module\Categories\Api\CategoriesInterface;
use PunBB\Module\Categories\Api\Data\CategoryInterface;
use PunBB\Module\Framework\Plugin\PluginChain;

final class CategoriesInterceptor implements CategoriesInterface {
	public function __construct(private readonly CategoriesInterface $subject, private readonly PluginChain $plugins) {}

	public function all(): array {
		return $this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->all(...));
	}

	public function allById(): array {
		return $this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->allById(...));
	}

	public function name(int $id): ?string {
		return $this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->name(...));
	}

	public function forumIds(int $id): array {
		return $this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->forumIds(...));
	}

	public function add(CategoryInterface ...$categories): void {
		$this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->add(...));
	}

	public function update(CategoryInterface ...$categories): void {
		$this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->update(...));
	}

	public function removeForums(int ...$forumIds): void {
		$this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->removeForums(...));
	}

	public function removeForumSubscriptions(int ...$forumIds): void {
		$this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->removeForumSubscriptions(...));
	}

	public function remove(int ...$ids): void {
		$this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->remove(...));
	}
}
