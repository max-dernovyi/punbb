<?php

declare(strict_types=1);

namespace PunBB\Module\Database\Patch;

use PunBB\Module\Framework\Modules\ModuleInterface;

/**
 * The data patches every module declares, in the order they apply: each after
 * the patches it depends on, otherwise in module load order and then in the
 * order its module declares them.
 */
final class DeclaredPatches {
	/** At most 150 characters, as data_patches records a name. */
	private const NAME_PATTERN = '/^(?=.{1,150}$)([A-Z][A-Za-z0-9]*)::[a-z][a-z0-9_]*$/D';

	/** @var list<ModuleInterface> */
	private readonly array $modules;

	/** @var ?list<PatchDeclaration> */
	private ?array $ordered = null;

	/** @param ModuleInterface ...$modules in load order */
	public function __construct(ModuleInterface ...$modules) {
		$this->modules = array_values($modules);
	}

	/**
	 * @return list<PatchDeclaration>
	 * @throws PatchException a patch is misnamed or declared twice, or its dependencies are missing or circular
	 */
	public function ordered(): array {
		return $this->ordered ??= self::order($this->declared());
	}

	/** @return array<string, PatchDeclaration> name => patch, in declared order */
	private function declared(): array {
		$patches = array();

		foreach ($this->modules as $module)
		{
			if (!$module instanceof PatchOwnerInterface)
				continue;

			foreach ($module->patches() as $patch)
			{
				if (preg_match(self::NAME_PATTERN, $patch->name, $matches) !== 1 || $matches[1] !== $module->name())
					throw new PatchException(sprintf('Module %s declares data patch "%s"; a patch is named %s::<lowercase_name>, at most 150 characters', $module->name(), $patch->name, $module->name()));

				if (isset($patches[$patch->name]))
					throw new PatchException(sprintf('Data patch %s is declared twice', $patch->name));

				$patches[$patch->name] = $patch;
			}
		}

		return $patches;
	}

	/**
	 * @param array<string, PatchDeclaration> $patches
	 * @return list<PatchDeclaration>
	 */
	private static function order(array $patches): array {
		foreach ($patches as $name => $patch)
			foreach ($patch->dependencies as $dependency)
				if (!isset($patches[$dependency]))
					throw new PatchException(sprintf('Data patch %s depends on %s, which no module declares', $name, $dependency));

		$ordered = array();
		while (count($ordered) < count($patches))
		{
			$next = null;
			foreach ($patches as $name => $patch)
			{
				if (!isset($ordered[$name]) && array_diff($patch->dependencies, array_keys($ordered)) === array())
				{
					$next = $name;
					break;
				}
			}

			if ($next === null)
				throw new PatchException('Data patches depend on each other in a cycle: '.implode(', ', array_diff(array_keys($patches), array_keys($ordered))));

			$ordered[$next] = $patches[$next];
		}

		return array_values($ordered);
	}
}
