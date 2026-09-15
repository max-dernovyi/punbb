<?php

declare(strict_types=1);

namespace PunBB\Module\Extensions\Api\Data;

/**
 * What installing an extension stores about it, from its manifest.
 */
interface ExtensionRecordInterface {
	public function id(): string;

	public function title(): string;

	public function version(): string;

	public function description(): string;

	public function author(): string;

	/** '' when the manifest has none. */
	public function uninstallCode(): string;

	/** '' when the manifest has none. */
	public function uninstallNote(): string;

	/** @return list<string> */
	public function dependencies(): array;
}
