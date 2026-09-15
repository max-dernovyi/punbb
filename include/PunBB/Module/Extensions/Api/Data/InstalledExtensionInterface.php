<?php

declare(strict_types=1);

namespace PunBB\Module\Extensions\Api\Data;

/**
 * An extension or hotfix the board has installed.
 */
interface InstalledExtensionInterface {
	/** Its directory under extensions/; a hotfix's starts with hotfix_. */
	public function id(): string;

	public function title(): string;

	public function version(): string;

	public function description(): string;

	public function author(): string;

	/** The code that uninstalls it; '' when there is none. */
	public function uninstallCode(): string;

	/** What the administrator is told before uninstalling it; '' when nothing. */
	public function uninstallNote(): string;

	/** @return list<string> the ids of the extensions it depends on */
	public function dependencies(): array;

	public function isDisabled(): bool;
}
