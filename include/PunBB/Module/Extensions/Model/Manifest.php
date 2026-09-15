<?php

declare(strict_types=1);

namespace PunBB\Module\Extensions\Model;

use PunBB\Module\Extensions\Api\Data\ManifestDependencyInterface;
use PunBB\Module\Extensions\Api\Data\ManifestHookInterface;
use PunBB\Module\Extensions\Api\Data\ManifestInterface;
use PunBB\Module\Extensions\Api\Data\ManifestNoteInterface;

final readonly class Manifest implements ManifestInterface {
	/**
	 * @param list<ManifestDependencyInterface> $dependencies
	 * @param list<ManifestNoteInterface> $notes
	 * @param list<ManifestHookInterface> $hooks
	 */
	public function __construct(
		private string $id,
		private string $title,
		private string $version,
		private string $description,
		private string $author,
		private string $maxTestedOn,
		private array $dependencies,
		private array $notes,
		private string $installCode,
		private string $uninstallCode,
		private array $hooks
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

	public function maxTestedOn(): string {
		return $this->maxTestedOn;
	}

	public function dependencies(): array {
		return $this->dependencies;
	}

	public function notes(): array {
		return $this->notes;
	}

	public function installCode(): string {
		return $this->installCode;
	}

	public function uninstallCode(): string {
		return $this->uninstallCode;
	}

	public function hooks(): array {
		return $this->hooks;
	}
}
