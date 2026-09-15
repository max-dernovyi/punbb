<?php

declare(strict_types=1);

namespace PunBB\Module\Site\Language;

use PunBB\Module\Layout\View\Html;

/**
 * The visitor's language pack. A pack is a file of lang/<language>/ — 'common',
 * 'userlist', 'help' — and its strings are markup.
 */
interface LanguageInterface {
	/** The string $key of pack $pack; '' for a key the pack does not have. */
	public function text(string $pack, string $key): Html;

	/** @return array<string, Html> every string of pack $pack, by key */
	public function strings(string $pack): array;

	/** The mail template $name of the visitor's language, as its file holds it: the subject line first; '' when there is none. */
	public function mailTemplate(string $name): string;

	/** @return list<string> the name of every language the board has a pack for */
	public function available(): array;
}
