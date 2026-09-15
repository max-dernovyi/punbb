<?php

declare(strict_types=1);

namespace PunBB\Module\Moderate\Api\Data;

/**
 * The first of the posts split off into a new topic, which starts it.
 */
interface FirstPostInterface {
	public function id(): int;

	public function poster(): string;

	public function posted(): int;
}
