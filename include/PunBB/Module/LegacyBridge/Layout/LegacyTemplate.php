<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Layout;

/**
 * The template a theme's stylesheet script is handed while the head is built,
 * so a script substituting its own markers into it still can: $tpl_main for a
 * board page, $tpl_redir and $tpl_maint for the redirect and the maintenance
 * message.
 */
final class LegacyTemplate {
	public ?string $html = null;

	public function __construct(public readonly string $variable = 'tpl_main') {}
}
