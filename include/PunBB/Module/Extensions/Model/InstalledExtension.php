<?php

declare(strict_types=1);

namespace PunBB\Module\Extensions\Model;

use PunBB\Module\Extensions\Api\Data\InstalledExtensionInterface;

final readonly class InstalledExtension implements InstalledExtensionInterface {
	/** @param list<string> $dependencies */
	public function __construct(
		private string $id,
		private string $title,
		private string $version,
		private string $description,
		private string $author,
		private string $uninstallCode,
		private string $uninstallNote,
		private array $dependencies,
		private bool $disabled
	) {}

	public function id(): string {
		return $this->id;
	}

	public function title(): string {
		return $this->title;
	}

	public function version(): string {
		return $this->version;
	}

	public function description(): string {
		return $this->description;
	}

	public function author(): string {
		return $this->author;
	}

	public function uninstallCode(): string {
		return $this->uninstallCode;
	}

	public function uninstallNote(): string {
		return $this->uninstallNote;
	}

	public function dependencies(): array {
		return $this->dependencies;
	}

	public function isDisabled(): bool {
		return $this->disabled;
	}

	/**
	 * The ids in a dependencies column: each between pipes, "|a|b|".
	 *
	 * @return list<string>
	 */
	public static function dependencyList(?string $column): array {
		return array_values(array_filter(explode('|', substr($column ?? '', 1, -1)), static fn (string $id): bool => $id !== ''));
	}

	/** @param list<string> $dependencies the ids as a dependencies column stores them */
	public static function dependencyColumn(array $dependencies): string {
		return '|'.implode('|', $dependencies).'|';
	}
}
