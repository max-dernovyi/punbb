<?php

declare(strict_types=1);

namespace PunBB\Module\Extensions\Model;

use PunBB\Module\Extensions\Api\Data\HookRecordInterface;

final readonly class HookRecord implements HookRecordInterface {
	public function __construct(private string $id, private string $extensionId, private string $code, private int $installedAt, private int $priority) {}

	public function id(): string {
		return $this->id;
	}

	public function extensionId(): string {
		return $this->extensionId;
	}

	public function code(): string {
		return $this->code;
	}

	public function installedAt(): int {
		return $this->installedAt;
	}

	public function priority(): int {
		return $this->priority;
	}
}
