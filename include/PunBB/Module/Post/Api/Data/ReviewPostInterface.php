<?php

declare(strict_types=1);

namespace PunBB\Module\Post\Api\Data;

/**
 * A post shown below the reply form.
 */
interface ReviewPostInterface {
	public function id(): int;

	public function poster(): string;

	public function message(): string;

	public function hidesSmilies(): bool;

	public function postedAt(): int;
}
