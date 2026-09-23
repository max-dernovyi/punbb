<?php

declare(strict_types=1);

namespace PunBB\Module\Database\Version;

use InvalidArgumentException;
use PunBB\Module\Framework\Modules\ModuleInterface;

/**
 * The version each module declares against the versions a board records for
 * it: a module whose declared version is ahead of its recorded schema or data
 * version has that part to bring up. A recorded version ahead of the declared
 * one is a module downgraded, which nothing undoes, so it is not behind.
 */
final class ModuleVersions {
	/** @var array<string, string> module => its declared version, in load order */
	private readonly array $declared;

	/** @param ModuleInterface ...$modules in load order */
	public function __construct(private readonly InstalledVersionsInterface $installed, ModuleInterface ...$modules) {
		$declared = array();
		foreach ($modules as $module)
			$declared[$module->name()] = $module->version();

		$this->declared = $declared;
	}

	/** @return array<string, string> module => its declared version, in load order */
	public function declared(): array {
		return $this->declared;
	}

	/** What the board records for $module, zero for both where it records nothing. */
	public function installed(string $module): InstalledVersion {
		return $this->installed->all()[$module] ?? new InstalledVersion();
	}

	public function schemaBehind(string $module): bool {
		return self::ahead($this->declared[$module] ?? InstalledVersion::ZERO, $this->installed($module)->schema);
	}

	public function dataBehind(string $module): bool {
		return self::ahead($this->declared[$module] ?? InstalledVersion::ZERO, $this->installed($module)->data);
	}

	/** @return list<string> the modules whose schema or data is behind their declared version, in load order */
	public function behind(): array {
		return $this->where(static fn (string $version, InstalledVersion $recorded): bool => self::ahead($version, $recorded->schema) || self::ahead($version, $recorded->data));
	}

	/** @return list<string> the modules whose schema is behind their declared version, in load order */
	public function behindOnSchema(): array {
		return $this->where(static fn (string $version, InstalledVersion $recorded): bool => self::ahead($version, $recorded->schema));
	}

	/** @return list<string> the modules whose data is behind their declared version, in load order */
	public function behindOnData(): array {
		return $this->where(static fn (string $version, InstalledVersion $recorded): bool => self::ahead($version, $recorded->data));
	}

	/** Records $module's tables at the version it declares. */
	public function recordSchema(string $module): void {
		$this->installed->recordSchema($module, $this->declaredVersion($module));
	}

	/** Records $module's data at the version it declares. */
	public function recordData(string $module): void {
		$this->installed->recordData($module, $this->declaredVersion($module));
	}

	/**
	 * Records every module at its declared version, schema and data: a fresh
	 * install creates both as each module declares them. The data of each of
	 * $dataLater is left to be recorded once its patches have run.
	 */
	public function recordAll(string ...$dataLater): void {
		foreach (array_keys($this->declared) as $module)
		{
			$this->recordSchema($module);
			if (!in_array($module, $dataLater, true))
				$this->recordData($module);
		}
	}

	/**
	 * @param callable(string, InstalledVersion): bool $behind
	 * @return list<string>
	 */
	private function where(callable $behind): array {
		$installed = $this->installed->all();

		$modules = array();
		foreach ($this->declared as $module => $version)
			if ($behind($version, $installed[$module] ?? new InstalledVersion()))
				$modules[] = $module;

		return $modules;
	}

	private function declaredVersion(string $module): string {
		return $this->declared[$module] ?? throw new InvalidArgumentException(sprintf('Module %s is not registered', $module));
	}

	private static function ahead(string $declared, string $recorded): bool {
		return version_compare($declared, $recorded, '>');
	}
}
