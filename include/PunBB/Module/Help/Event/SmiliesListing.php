<?php

declare(strict_types=1);

namespace PunBB\Module\Help\Event;

use PunBB\Module\Framework\Event\EventInterface;

/**
 * The smilies the help page is about to list: each text, and the image under
 * img/smilies/ it turns into.
 */
final class SmiliesListing implements EventInterface {
	/** @param array<string, string> $smilies text => image */
	public function __construct(private array $smilies) {}

	/** @return list<string> the texts, in order */
	public function texts(): array {
		return array_map(strval(...), array_keys($this->smilies));
	}

	public function image(string $text): ?string {
		return $this->smilies[$text] ?? null;
	}

	/** A text already listed keeps its place; a new one is listed last. */
	public function set(string $text, string $image): void {
		$this->smilies[$text] = $image;
	}

	public function remove(string $text): void {
		unset($this->smilies[$text]);
	}
}
