<?php

declare(strict_types=1);

namespace PunBB\Module\Update\Model;

use PunBB\Module\Update\Api\Data\TableColumnInterface;

final readonly class TableColumn implements TableColumnInterface {
	public function __construct(private string $name, private string $type, private ?string $collation, private bool $nullable, private ?string $default) {}

	public function name(): string {
		return $this->name;
	}

	public function type(): string {
		return $this->type;
	}

	public function collation(): ?string {
		return $this->collation;
	}

	public function nullable(): bool {
		return $this->nullable;
	}

	public function default(): ?string {
		return $this->default;
	}
}
