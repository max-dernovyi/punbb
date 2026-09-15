<?php

declare(strict_types=1);

namespace PunBB\Module\Message\Page;

use PunBB\Module\Framework\Event\EventDispatcher;
use PunBB\Module\Framework\Http\Response;
use PunBB\Module\Layout\Chrome\ChromeFactoryInterface;
use PunBB\Module\Layout\Chrome\Layout;
use PunBB\Module\Layout\View\Html;
use PunBB\Module\Layout\View\TemplateRenderer;
use PunBB\Module\Message\Event\RedirectHeadAssembling;
use PunBB\Module\Message\Event\RedirectJsonSending;
use PunBB\Module\Message\Event\RedirectShowing;
use PunBB\Module\Site\Config\SettingsInterface;
use PunBB\Module\Site\Language\LanguageInterface;
use PunBB\Module\Site\Url\UrlsInterface;

/**
 * Sends the browser on after an action: a page with a message that forwards
 * it after the board's delay, a Location header when the delay is none, or
 * JSON for a script.
 */
final class RedirectPage {
	private const TEMPLATE = __DIR__.'/../templates/redirect.phtml';

	public function __construct(
		private readonly EventDispatcher $events,
		private readonly ChromeFactoryInterface $chromes,
		private readonly TemplateRenderer $templates,
		private readonly LanguageInterface $language,
		private readonly SettingsInterface $settings,
		private readonly UrlsInterface $urls
	) {}

	/**
	 * @param string $destination a URL, a path, or a page relative to the forum root, encoded for an attribute
	 * @param bool $json whether a script asked, and is answered in JSON
	 */
	public function respond(string $destination, Html $message, bool $json = false): Response {
		$showing = new RedirectShowing($destination, $message->html);
		$this->events->dispatch($showing);

		$destination = RedirectTarget::normalise($showing->destination(), $this->urls->base());

		if ($json)
		{
			$sending = RedirectJsonSending::redirect($showing->message(), $destination);
			$this->events->dispatch($sending);

			return self::json($sending);
		}

		$delay = $this->settings->value('o_redirect_delay');
		$status = 200;
		$headers = Layout::headers(time());

		if (is_numeric($delay) && (int) $delay === 0)
		{
			$status = 302;
			$headers = array('Location' => str_replace('&amp;', '&', $destination)) + $headers;
		}

		// The destination is encoded for an attribute already; only what would end the attribute or the tag is left to encode
		$link = new Html(str_replace(array('<', '>', '"'), array('&lt;', '&gt;', '&quot;'), $destination));

		$chrome = $this->chromes->bare(Layout::REDIRECT);

		$head = array(
			'refresh'	=> Html::format('<meta http-equiv="refresh" content="%s;URL=%s" />', $delay, $link)->html,
			'title'		=> Html::format('<title>%s%s%s</title>', $this->language->text('common', 'Redirecting'), $this->language->text('common', 'Title separator'), $this->settings->value('o_board_title'))->html,
		);

		foreach ($chrome->themeHead() as $number => $line)
			$head['style'.$number] = $line->html;

		$assembling = new RedirectHeadAssembling($head);
		$this->events->dispatch($assembling);

		$entries = array();
		foreach ($assembling->names() as $name)
			$entries[] = (string) $assembling->entry($name);

		$main = $this->templates->render(self::TEMPLATE, array(
			'message'		=> new Html($showing->message()),
			'redirecting'	=> $this->language->text('common', 'Redirecting'),
			'forwarding'	=> Html::format($this->language->text('common', 'Forwarding info'), $delay, $this->language->text('common', intval($delay) === 1 ? 'second' : 'seconds')),
			'destination'	=> $link,
			'click'			=> $this->language->text('common', 'Click redirect'),
		));

		return new Response($chrome->close(array(
			'head'	=> new Html(implode("\n", $entries).$chrome->stylesheets()->html),
			'main'	=> new Html("\t".(new Html($main))->trim()->html),
		)), $status, $headers);
	}

	/** The reply $sending carries, sent as JSON. */
	public static function json(RedirectJsonSending $sending): Response {
		$reply = array('code' => $sending->code(), 'message' => $sending->message());

		if ($sending->destination() !== null)
			$reply['destination_url'] = $sending->destination();

		if ($sending->token() !== null)
			$reply += array('csrf_token' => $sending->token(), 'prev_url' => $sending->previousUrl());

		foreach ($sending->names() as $name)
			$reply['post_data'][$name] = $sending->entry($name);

		return new Response((string) json_encode($reply), 200, array('Content-type' => 'application/json; charset=utf-8'));
	}
}
