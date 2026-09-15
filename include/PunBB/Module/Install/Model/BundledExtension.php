<?php

declare(strict_types=1);

namespace PunBB\Module\Install\Model;

use PunBB\Module\Install\Api\Data\ExtensionHookInterface;
use PunBB\Module\Install\Api\Data\ExtensionInterface;

final readonly class BundledExtension implements ExtensionInterface {
	/** @param list<ExtensionHookInterface> $hooks */
	public function __construct(
		private string $id,
		private string $title,
		private string $version,
		private string $description,
		private string $author,
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

	public function hooks(): array {
		return $this->hooks;
	}
}
