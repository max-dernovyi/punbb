<?php

declare(strict_types=1);

namespace PunBB\Module\Database\Schema;

use PunBB\Module\Database\Sql\Platform;
use PunBB\Module\Framework\Modules\ModuleInterface;

/**
 * The schema every module declares: the tables of each module that owns any,
 * in load order.
 */
final class DeclaredSchema {
	/** @var list<ModuleInterface> */
	private readonly array $modules;

	/** @param ModuleInterface ...$modules in load order */
	public function __construct(ModuleInterface ...$modules) {
		$this->modules = array_values($modules);
	}

	/**
	 * @return list<Table>
	 * @throws SchemaException two modules declare one table
	 */
	public function tables(Platform $platform): array {
		return array_merge(...array_values($this->owned($platform)));
	}

	/**
	 * The tables module $module declares for $platform; none for a module that owns none.
	 *
	 * @return list<Table>
	 * @throws SchemaException two modules declare one table
	 */
	public function tablesOf(string $module, Platform $platform): array {
		return $this->owned($platform)[$module] ?? array();
	}

	/**
	 * Table $name as its module declares it for $platform.
	 *
	 * @throws SchemaException no module declares it
	 */
	public function table(string $name, Platform $platform): Table {
		foreach ($this->tables($platform) as $table)
			if ($table->name === $name)
				return $table;

		throw new SchemaException(sprintf('No module declares table "%s"', $name));
	}

	/**
	 * @return array<string, list<Table>> module => its tables, for each module that owns any, in load order
	 * @throws SchemaException two modules declare one table
	 */
	private function owned(Platform $platform): array {
		$owned = array();
		$owners = array();

		foreach ($this->modules as $module)
		{
			if (!$module instanceof TableOwnerInterface)
				continue;

			foreach ($module->tables($platform) as $table)
			{
				if (isset($owners[$table->name]))
					throw new SchemaException(sprintf('Modules %s and %s both declare table "%s"', $owners[$table->name], $module->name(), $table->name));

				$owners[$table->name] = $module->name();
				$owned[$module->name()][] = $table;
			}
		}

		return $owned;
	}
}
