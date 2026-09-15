<?php

declare(strict_types=1);

namespace PunBB\Module\Search\Api\Data;

/**
 * What a results page lists: the quick search or the stored search behind it,
 * whether it lists posts, topics or forums, and the address its pages are at.
 */
interface ListingInterface {
	/** The quick search, such as 'show_new'; null for a stored keyword or author search. */
	public function action(): ?string;

	/** 'posts', 'topics' or 'forums'. */
	public function showAs(): string;

	/** The name of the address in the URL scheme, such as 'search_results'. */
	public function url(): string;

	/** What fills the address: the stored search's id, the member, the forum; '' for nothing. */
	public function argument(): int|string;
}
