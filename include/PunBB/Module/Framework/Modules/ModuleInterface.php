<?php

declare(strict_types=1);

namespace PunBB\Module\Framework\Modules;

/**
 * A module's declaration, at PunBB\Module\<Name>\Module.
 */
interface ModuleInterface {
	/** The module's directory and namespace segment under PunBB\Module. */
	public function name(): string;

	/** @return list<string> modules that must be registered, loaded before this one */
	public function dependencies(): array;

	/** @return list<string> modules loaded before this one when registered, ignored otherwise */
	public function loadAfter(): array;

	/** Declares the module's services; nothing is created here. */
	public function wire(Wiring $wiring): void;
}
