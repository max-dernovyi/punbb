<?php

declare(strict_types=1);

namespace PunBB\Module\Extensions\Model;

use PunBB\Module\Extensions\Api\Data\ManifestNoteInterface;

final readonly class ManifestNote implements ManifestNoteInterface {
	public function __construct(private string $type, private string $content) {}

	public function type(): string {
		return $this->type;
	}

	public function content(): string {
		return $this->content;
	}
}
