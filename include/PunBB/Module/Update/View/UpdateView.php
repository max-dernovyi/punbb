<?php

declare(strict_types=1);

namespace PunBB\Module\Update\View;

use PunBB\Module\Layout\View\Html;
use PunBB\Module\Update\Controller\StageResult;

/**
 * What the updater's pages show.
 */
final class UpdateView {
	/**
	 * @param bool $from12 whether the board is at 1.2, whose text may need converting
	 * @param bool $seemsUtf8 whether its text reads as UTF-8 already
	 * @param bool $forced whether the conversion was asked for regardless
	 * @return array<string, mixed> the start form's variables
	 */
	public static function form(string $baseUrl, bool $from12, bool $seemsUtf8, bool $forced): array {
		return array(
			'baseUrl'		=> $baseUrl,
			'converts'		=> $from12 && (!$seemsUtf8 || $forced),
			'offersForce'	=> $from12 && $seemsUtf8 && !$forced,
		);
	}

	/**
	 * @param ?string $config the source of config.php, where the update could not write it
	 * @return array<string, mixed> the last page's variables
	 */
	public static function finished(string $baseUrl, ?string $config): array {
		return array(
			'baseUrl'	=> $baseUrl,
			'config'	=> $config,
		);
	}

	/** @return array<string, mixed> a stage's variables */
	public static function stage(StageResult $result): array {
		return array(
			'lines'		=> $result->lines,
			'script'	=> Html::script($result->next),
			'next'		=> $result->next,
		);
	}
}
