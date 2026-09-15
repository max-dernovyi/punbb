<?php

declare(strict_types=1);

namespace PunBB\Module\Misc\Api\Data;

/**
 * A member another member mails through the board's form.
 */
interface RecipientInterface {
	public function id(): int;

	public function username(): string;

	/** '' when the member has none. */
	public function email(): string;

	/** 0 shows the address, 1 hides it, 2 also refuses mail through the form. */
	public function emailSetting(): int;
}
