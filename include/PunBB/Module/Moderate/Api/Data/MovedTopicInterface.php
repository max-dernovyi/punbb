<?php

declare(strict_types=1);

namespace PunBB\Module\Moderate\Api\Data;

/**
 * A topic moved to another forum, as the redirect left where it was shows it.
 */
interface MovedTopicInterface {
	public function poster(): string;

	public function subject(): string;

	public function posted(): int;

	/** When it was last posted in. */
	public function lastPost(): int;
}
