<?php

declare(strict_types=1);

namespace PunBB\Module\Users\Api\Data;

/**
 * A column of the users table a search matches, and the text it matches:
 * a * in it stands for any run of characters, and case is ignored.
 */
interface SearchFieldInterface {
	public const FIELDS = array('username', 'email', 'title', 'realname', 'url', 'jabber', 'icq', 'msn', 'aim', 'yahoo', 'location', 'signature', 'admin_note');

	/** One of FIELDS. */
	public function field(): string;

	public function text(): string;
}
