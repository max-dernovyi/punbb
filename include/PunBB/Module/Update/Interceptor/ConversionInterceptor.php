<?php

declare(strict_types=1);

namespace PunBB\Module\Update\Interceptor;

use PunBB\Module\Framework\Plugin\PluginChain;
use PunBB\Module\Update\Api\ConversionInterface;
use PunBB\Module\Update\Api\Data\TableColumnInterface;
use PunBB\Module\Update\Api\Data\TextRowInterface;

final class ConversionInterceptor implements ConversionInterface {
	public function __construct(private readonly ConversionInterface $subject, private readonly PluginChain $plugins) {}

	public function firstId(string $table): ?int {
		return $this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->firstId(...));
	}

	public function nextId(string $table, int $id): ?int {
		return $this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->nextId(...));
	}

	public function rows(string $table, string $idColumn, array $columns, ?int $from = null, ?int $to = null): array {
		return $this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->rows(...));
	}

	public function store(string $table, string $idColumn, TextRowInterface $row): void {
		$this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->store(...));
	}

	public function columns(string $table): array {
		return $this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->columns(...));
	}

	public function setDefaultCharset(string $table): void {
		$this->plugins->call($this, __FUNCTION__, func_get_args(), $this->subject->setDefaultCharset(...));
	}
}
