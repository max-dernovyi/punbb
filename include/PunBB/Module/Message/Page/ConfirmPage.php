<?php

declare(strict_types=1);

namespace PunBB\Module\Message\Page;

use PunBB\Module\Framework\Event\EventDispatcher;
use PunBB\Module\Framework\Http\Response;
use PunBB\Module\Layout\Chrome\Crumb;
use PunBB\Module\Layout\Chrome\PageHead;
use PunBB\Module\Layout\Page\PageResponder;
use PunBB\Module\Layout\View\Html;
use PunBB\Module\Layout\View\TemplateRenderer;
use PunBB\Module\Message\Event\ConfirmFormRendering;
use PunBB\Module\Message\Event\ConfirmFormRequested;
use PunBB\Module\Message\Event\RedirectJsonSending;
use PunBB\Module\Site\Config\SettingsInterface;
use PunBB\Module\Site\Language\LanguageInterface;
use PunBB\Module\Site\Security\CsrfTokensInterface;
use PunBB\Module\Site\Url\UrlsInterface;
use PunBB\Module\Site\Visitor\VisitorInterface;

/**
 * Asks the visitor to confirm a form whose token did not match: the same
 * fields posted again with a fresh token, or back where they came from.
 */
final class ConfirmPage {
	private const TEMPLATE = __DIR__.'/../templates/confirm.phtml';

	/** What the form carries itself, and does not post again. */
	private const OWN_FIELDS = array('csrf_token', 'prev_url');

	public function __construct(
		private readonly EventDispatcher $events,
		private readonly PageResponder $pages,
		private readonly TemplateRenderer $templates,
		private readonly RedirectPage $redirects,
		private readonly LanguageInterface $language,
		private readonly SettingsInterface $settings,
		private readonly UrlsInterface $urls,
		private readonly VisitorInterface $visitor,
		private readonly CsrfTokensInterface $tokens
	) {}

	/**
	 * The confirmation for $post, or the redirect back when it was cancelled;
	 * null when the board confirms nothing or an observer lets the request through unconfirmed.
	 *
	 * @param array<mixed> $post what the form posted
	 * @param bool $json whether a script asked, and is answered in JSON
	 */
	public function respond(array $post, bool $json = false): ?Response {
		if (!$this->tokens->confirms())
			return null;

		if (isset($post['confirm_cancel']))
			return $this->redirects->respond(Html::escape(is_string($post['prev_url'] ?? null) ? $post['prev_url'] : '')->html, $this->language->text('common', 'Cancel redirect'), $json);

		$requested = new ConfirmFormRequested();
		$this->events->dispatch($requested);

		if ($requested->isLetThrough())
			return null;

		$fields = array();
		foreach ($post as $name => $value)
			if (!in_array($name, self::OWN_FIELDS, true))
				$fields += self::flatten((string) $name, $value);

		$action = $this->urls->current();
		$token = $this->tokens->token($action);
		$previousUrl = Html::escape($this->visitor->previousUrl())->html;

		if ($json)
		{
			$sending = RedirectJsonSending::confirmation($this->language->text('common', 'CSRF token mismatch')->html, $token, $previousUrl);
			foreach ($fields as $name => $value)
				$sending->set($name, Html::escape($value)->html);

			$this->events->dispatch($sending);

			return RedirectPage::json($sending);
		}

		$hidden = array(
			'csrf_token'	=> Html::format('<input type="hidden" name="csrf_token" value="%s" />', $token)->html,
			'prev_url'		=> Html::format('<input type="hidden" name="prev_url" value="%s" />', new Html($previousUrl))->html,
		);

		foreach ($fields as $name => $value)
			$hidden[$name] = Html::format('<input type="hidden" name="%s" value="%s" />', $name, $value)->html;

		$head = new PageHead('dialogue', array(
			new Crumb($this->settings->value('o_board_title'), $this->urls->link('index')),
			new Crumb($this->language->text('common', 'Confirm action')->html),
		));

		return $this->pages->respond($head, fn (): array => array('main' => $this->main($action, $hidden)));
	}

	/** @param array<string, string> $hidden */
	private function main(string $action, array $hidden): Html {
		$start = new ConfirmFormRendering(ConfirmFormRendering::START, $action, $hidden);
		$this->events->dispatch($start);

		$fields = array();
		foreach ($start->names() as $name)
			$fields[] = (string) $start->entry($name);

		$body = $this->templates->render(self::TEMPLATE, array(
			'common'	=> $this->language->strings('common'),
			'action'	=> $action,
			'fields'	=> new Html(implode("\n\t\t\t\t", $fields)),
		));

		$end = new ConfirmFormRendering(ConfirmFormRendering::END, $action, array());
		$this->events->dispatch($end);

		return (new Html($start->markup().$body.$end->markup()))->trim();
	}

	/** @return array<string, string> $value as the hidden fields posting it again: an array as one field per element, named with its keys */
	private static function flatten(string $name, mixed $value): array {
		if (!is_array($value))
			return array($name => is_scalar($value) ? (string) $value : '');

		$fields = array();
		foreach ($value as $key => $element)
			$fields += self::flatten($name.'['.$key.']', $element);

		return $fields;
	}
}
