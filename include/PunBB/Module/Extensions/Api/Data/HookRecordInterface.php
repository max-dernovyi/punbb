<?php

declare(strict_types=1);

namespace PunBB\Module\Extensions\Api\Data;

/**
 * The code an extension attaches at a hook point.
 */
interface HookRecordInterface {
	/** The hook point. */
	public function id(): string;

	public function extensionId(): string;

	public function code(): string;

	public function installedAt(): int;

	/** 0 to 10: lower runs first. */
	public function priority(): int;
}
