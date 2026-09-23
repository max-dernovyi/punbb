<?php

declare(strict_types=1);

namespace PunBB\Module\Database\Schema\Change;

use PunBB\Module\Database\Schema\SchemaInterface;

/**
 * One change the differ decided on. Its statements are the driver's: the
 * change only names what it is.
 */
interface ChangeInterface {
	public function applyTo(SchemaInterface $schema): void;

	/** The change in words, for a log or a failure: 'add column users.skype'. */
	public function describe(): string;
}
