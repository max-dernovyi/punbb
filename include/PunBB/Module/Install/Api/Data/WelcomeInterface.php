<?php

declare(strict_types=1);

namespace PunBB\Module\Install\Api\Data;

/**
 * The post a new board opens with, and where it is filed.
 */
interface WelcomeInterface {
	public function category(): string;

	public function forum(): string;

	public function forumDescription(): string;

	public function subject(): string;

	public function message(): string;

	public function poster(): string;

	public function posterId(): int;

	/** As a Unix timestamp. */
	public function posted(): int;
}
