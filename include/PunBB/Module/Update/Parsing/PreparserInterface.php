<?php

declare(strict_types=1);

namespace PunBB\Module\Update\Parsing;

/**
 * The tidying a message's or a signature's BBCode goes through before it is
 * stored, which text stored before 1.3 never went through.
 */
interface PreparserInterface {
	/** $text with its BBCode tidied, as the board's settings allow it in a message or, when $signature, in a signature. */
	public function preparse(string $text, bool $signature): string;
}
