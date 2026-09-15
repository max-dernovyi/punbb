<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Site;

use InvalidArgumentException;
use PunBB\Module\LegacyBridge\Layout\LegacyChromeSource;
use PunBB\Module\LegacyBridge\Layout\Markers;
use PunBB\Module\Layout\View\Html;
use PunBB\Module\Site\Language\LanguageInterface;
use PunBB\Module\Site\Visitor\VisitorInterface;

/**
 * The language packs as the pages loaded them: each file of lang/<language>/
 * defines one $lang_* global, which extension code reads and changes. A pack is
 * loaded the first time it is asked for, and read from its global every time.
 */
final class LegacyLanguage implements LanguageInterface {
	/** @var array<string, string> pack => the global it defines */
	private array $globals = array('common' => 'lang_common');

	public function __construct(private readonly VisitorInterface $visitor) {}

	public function text(string $pack, string $key): Html {
		$strings = $GLOBALS[$this->global($pack)] ?? null;

		return new Html(is_array($strings) ? Markers::markup($strings[$key] ?? '') : '');
	}

	public function strings(string $pack): array {
		$strings = $GLOBALS[$this->global($pack)] ?? null;

		$texts = array();
		foreach (is_array($strings) ? $strings : array() as $key => $text)
			$texts[(string) $key] = new Html(Markers::markup($text));

		return $texts;
	}

	public function mailTemplate(string $name): string {
		if (preg_match('/^[a-z_]+$/', $name) !== 1)
			throw new InvalidArgumentException(sprintf('"%s" is not a mail template', $name));

		$template = @file_get_contents(LegacyChromeSource::root().'lang/'.$this->visitor->language().'/mail_templates/'.$name.'.tpl');

		return is_string($template) ? $template : '';
	}

	/** Every directory of lang/ holding a common.php, in the order a person reads them. */
	public function available(): array {
		$root = LegacyChromeSource::root().'lang/';

		$languages = array();
		foreach (scandir($root) ?: array() as $entry)
			if ($entry !== '.' && $entry !== '..' && is_dir($root.$entry) && file_exists($root.$entry.'/common.php'))
				$languages[] = $entry;

		natcasesort($languages);

		return array_values($languages);
	}

	private function global(string $pack): string {
		if (isset($this->globals[$pack]))
			return $this->globals[$pack];

		if (preg_match('/^[a-z_]+$/', $pack) !== 1)
			throw new InvalidArgumentException(sprintf('"%s" is not a language pack', $pack));

		$defined = (static function (string $__file): array {
			require $__file;

			return get_defined_vars();
		})(LegacyChromeSource::root().'lang/'.$this->visitor->language().'/'.$pack.'.php');

		$global = '';
		foreach ($defined as $name => $strings)
		{
			if (str_starts_with($name, 'lang_'))
			{
				$GLOBALS[$name] = $strings;
				$global = $name;
			}
		}

		return $this->globals[$pack] = $global;
	}
}
