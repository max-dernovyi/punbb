<?php

declare(strict_types=1);

namespace PunBB\Module\Extern\Api\Data;

/**
 * An item of a feed as it is written out: its link and its description are
 * markup, the rest is text.
 */
interface FeedItemInterface {
	public function id(): int;

	public function title(): string;

	/** A URL, encoded for an attribute. */
	public function link(): string;

	/** The post, as markup. */
	public function description(): string;

	public function authorName(): string;

	/** null when the author's address is not shown. */
	public function authorEmail(): ?string;

	/** A URL, encoded for an attribute; null for a guest. */
	public function authorUri(): ?string;

	public function published(): int;
}
