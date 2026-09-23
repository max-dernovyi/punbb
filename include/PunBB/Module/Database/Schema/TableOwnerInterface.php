<?php

declare(strict_types=1);

namespace PunBB\Module\Database\Schema;

use PunBB\Module\Database\Sql\Platform;

/**
 * A module that owns tables, declaring them on its module class: what each
 * table is, never the statements that make it so.
 */
interface TableOwnerInterface {
	/** @return list<Table> in the order they are created */
	public function tables(Platform $platform): array;
}
