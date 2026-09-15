<?php

declare(strict_types=1);

namespace PunBB\Module\Extensions\Model;

use PunBB\Module\Extensions\Api\Data\ExtensionRecordInterface;

final readonly class ExtensionRecord implements ExtensionRecordInterface {
	/** @param list<string> $dependencies */
	public function __construct(
		private string $id,
		private string $title,
		private string $version,
		private string $description,
		private string $author,
		private string $uninstallCode,
		private string $uninstallNote,
		private array $dependencies
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
}
