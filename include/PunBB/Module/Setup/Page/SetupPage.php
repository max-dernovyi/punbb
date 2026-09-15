<?php

declare(strict_types=1);

namespace PunBB\Module\Setup\Page;

use PunBB\Module\Framework\Http\Response;
use PunBB\Module\Layout\View\Html;
use PunBB\Module\Layout\View\TemplateRenderer;

/**
 * The pages a setup route answers with besides its own: the forum's error page,
 * and a line of text where nothing can be rendered yet.
 */
final class SetupPage {
	private const ERROR = __DIR__.'/../templates/error.phtml';

	public function __construct(private readonly TemplateRenderer $templates) {}

	/** The forum's error page, showing $message under the title of board $board. */
	public function error(Html $message, string $board = 'PunBB'): Response {
		return new Response($this->templates->render(self::ERROR, array(
			'board'		=> $board,
			'message'	=> $message,
		)), 503, array('Content-Type' => 'text/html; charset=utf-8'));
	}

	/** $markup alone, as the setup scripts exited with it. */
	public function text(Html $markup): Response {
		return new Response($markup->html);
	}
}
