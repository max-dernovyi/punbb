<?php

declare(strict_types=1);

namespace PunBB\Module\Database\Schema;

use PunBB\Module\Database\Schema\Change\ChangeInterface;
use PunBB\Module\Database\Sql\Platform;

/**
 * Brings the database to the schema the modules declare: the differ decides
 * what changes, the schema's driver how each change is spelled.
 */
final class SchemaSynchronizer {
	public function __construct(
		private readonly DeclaredSchema $declared,
		private readonly SchemaInterface $schema,
		private readonly SchemaDiffer $differ = new SchemaDiffer()
	) {}

	/** @return list<ChangeInterface> what the database lacks of the declared schema, table by table in declared order */
	public function changes(Platform $platform): array {
		$changes = array();
		foreach ($this->declared->tables($platform) as $table)
			array_push($changes, ...$this->differ->diff($table, $this->schema->describe($table->name), $platform));

		return $changes;
	}

	/** @return list<ChangeInterface> the changes made */
	public function synchronize(Platform $platform): array {
		$changes = $this->changes($platform);
		foreach ($changes as $change)
			$change->applyTo($this->schema);

		return $changes;
	}
}
