<?php

declare(strict_types=1);

namespace PunBB\Module\Layout\View;

use Stringable;

/**
 * Markup, output as it is. Everything else a template or format() receives is
 * text and is escaped on the way out, so markup is only ever what was built as
 * markup.
 */
final readonly class Html implements Stringable {
	/** What a trimmed region loses at either end: ASCII whitespace, NUL and the no-break space. */
	private const TRIM = '[ \t\n\r\0\x0B\x{A0}]+';

	public function __construct(public string $html) {}

	/** Text for an HTML element or a quoted attribute. */
	public static function escape(string $text): self {
		return new self(htmlspecialchars($text, ENT_QUOTES, 'UTF-8'));
	}

	/**
	 * Text for the inside of a quoted string literal in an inline script. A
	 * script element is raw text: entities stay literal there, and a backslash
	 * would escape the closing quote.
	 */
	public static function script(string $text): self {
		$json = json_encode($text, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_INVALID_UTF8_SUBSTITUTE);

		return new self($json === false ? '' : substr($json, 1, -1));
	}

	/** $markup with its sprintf() conversions filled in turn: Html as it is, text escaped. */
	public static function format(self|string $markup, self|string|int ...$values): self {
		$arguments = array();
		foreach ($values as $value)
			$arguments[] = $value instanceof self ? $value->html : htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');

		return new self(sprintf($markup instanceof self ? $markup->html : $markup, ...$arguments));
	}

	/** @param iterable<self> $parts */
	public static function join(string $glue, iterable $parts): self {
		$html = array();
		foreach ($parts as $part)
			$html[] = $part->html;

		return new self(implode($glue, $html));
	}

	/** Without the whitespace at either end, as the legacy regions were trimmed. */
	public function trim(): self {
		return new self(preg_replace('/^'.self::TRIM.'|'.self::TRIM.'$/u', '', $this->html) ?? '');
	}

	public function __toString(): string {
		return $this->html;
	}
}
