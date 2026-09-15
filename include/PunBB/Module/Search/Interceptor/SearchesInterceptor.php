<?php

declare(strict_types=1);

namespace PunBB\Module\Search\Interceptor;

use PunBB\Module\Framework\Plugin\PluginChain;
use PunBB\Module\Search\Api\Data\SearchMarkInterface;
use PunBB\Module\Search\Api\Data\StoredSearchInterface;
use PunBB\Module\Search\Api\SearchesInterface;

final class SearchesInterceptor implements SearchesInterface {
	public function __construct(private readonly SearchesInterface $subject, private readonly PluginChain $plugins) {}

	public function markSearched(SearchMarkInterface ...$marks): void {
		$this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->markSearched(...));
	}

	public function keywordMatches(string $pattern, int $searchIn): array {
		return $this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->keywordMatches(...));
	}

	public function authorIds(string $pattern): array {
		return $this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->authorIds(...));
	}

	public function authorPosts(array $userIds): array {
		return $this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->authorPosts(...));
	}

	public function readableHits(array $postIds, int $groupId, ?array $forumIds, bool $asPosts): array {
		return $this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->readableHits(...));
	}

	public function onlineIdents(): array {
		return $this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->onlineIdents(...));
	}

	public function pruneCache(string ...$keptIdents): void {
		$this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->pruneCache(...));
	}

	public function store(StoredSearchInterface ...$searches): void {
		$this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->store(...));
	}

	public function stored(int $id, string $ident): ?StoredSearchInterface {
		return $this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->stored(...));
	}
}
