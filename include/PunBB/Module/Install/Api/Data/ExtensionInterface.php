<?php

declare(strict_types=1);

namespace PunBB\Module\Install\Api\Data;

/**
 * An extension shipped with the forum, as its manifest describes it.
 */
interface ExtensionInterface {
	public function id(): string;

	public function title(): string;

	public function version(): string;

	public function description(): string;

	public function author(): string;

	/** @return list<ExtensionHookInterface> */
	public function hooks(): array;
}
