<?php

declare(strict_types=1);

namespace PunBB\Module\Layout\View;

use Throwable;

/**
 * Renders a PHP template with escaping by default: a string reaches the
 * template escaped for HTML, Html as it is, a number or a bool as itself, and
 * an array with each element treated the same way. Nothing else crosses.
 */
final class TemplateRenderer {
	/** A variable name; one starting with __ is the renderer's own. */
	private const NAME = '/^[A-Za-z_][A-Za-z0-9_]*$/';

	/** @param array<string, mixed> $values the template's variables */
	public function render(string $file, array $values): string {
		if (!is_file($file))
			throw new TemplateException(sprintf('No template %s', $file));

		$variables = array();
		foreach ($values as $name => $value)
		{
			if (preg_match(self::NAME, $name) !== 1 || $name === 'this' || str_starts_with($name, '__'))
				throw new TemplateException(sprintf('Template %s cannot receive "%s" as a variable', $file, $name));

			$variables[$name] = self::escape($value, $name);
		}

		$level = ob_get_level();
		ob_start();

		try {
			self::include($file, $variables);
		}
		catch (Throwable $e) {
			while (ob_get_level() > $level)
				ob_end_clean();

			throw $e;
		}

		return (string) ob_get_clean();
	}

	private static function escape(mixed $value, string $name): mixed {
		if (is_string($value))
			return Html::escape($value);

		if ($value instanceof Html || is_int($value) || is_float($value) || is_bool($value) || $value === null)
			return $value;

		if (is_array($value))
		{
			$escaped = array();
			foreach ($value as $key => $element)
				$escaped[$key] = self::escape($element, $name);

			return $escaped;
		}

		throw new TemplateException(sprintf('Template variable $%s holds a %s; a template receives text, Html, numbers, bools and arrays of them', $name, get_debug_type($value)));
	}

	/**
	 * A scope holding the template's variables and the two parameters no
	 * variable may shadow.
	 *
	 * @param array<string, mixed> $variables
	 */
	private static function include(string $file, array $variables): void {
		(static function (string $__template, array $__variables): void {
			extract($__variables, EXTR_SKIP);
			require $__template;
		})($file, $variables);
	}
}
