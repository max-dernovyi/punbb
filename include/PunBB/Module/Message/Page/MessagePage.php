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
use PunBB\Module\Message\Event\MessageJsonSending;
use PunBB\Module\Message\Event\MessageRendering;
use PunBB\Module\Message\Event\MessageShowing;
use PunBB\Module\Site\Config\SettingsInterface;
use PunBB\Module\Site\Language\LanguageInterface;
use PunBB\Module\Site\Url\UrlsInterface;

/**
 * Answers with a message: a page of its own, the message inside a page whose
 * header is already built, or JSON for a script.
 */
final class MessagePage {
	private const TEMPLATE = __DIR__.'/../templates/message.phtml';

	public function __construct(
		private readonly EventDispatcher $events,
		private readonly PageResponder $pages,
		private readonly TemplateRenderer $templates,
		private readonly LanguageInterface $language,
		private readonly SettingsInterface $settings,
		private readonly UrlsInterface $urls
	) {}

	/**
	 * @param Html $heading '' for the default heading
	 * @param list<Html> $options shown beside the heading
	 * @param bool $json whether a script asked, and is answered in JSON
	 */
	public function respond(Html $message, Html $link = new Html(''), Html $heading = new Html(''), array $options = array(), bool $json = false): Response {
		$showing = $this->showing($message, $link, $heading);

		if ($json)
		{
			$sending = new MessageJsonSending(-1, $showing->message());
			$this->events->dispatch($sending);

			return new Response((string) json_encode(array('code' => $sending->code(), 'message' => $sending->message())), 200, array('Content-type' => 'application/json; charset=utf-8'));
		}

		$heading = $showing->heading() !== '' ? $showing->heading() : $this->language->text('common', 'Forum message')->html;

		$head = new PageHead('message', array(
			new Crumb($this->settings->value('o_board_title'), $this->urls->link('index')),
			new Crumb($this->language->text('common', 'Forum message')->html),
		));

		return $this->pages->respond($head, fn (): array => array('main' => $this->main($showing, $heading, $options)));
	}

	/**
	 * The message as the main region of a page that has already built its header.
	 *
	 * @param list<Html> $options
	 */
	public function inside(Html $message, Html $link = new Html(''), Html $heading = new Html(''), array $options = array()): Html {
		$showing = $this->showing($message, $link, $heading);

		return $this->main($showing, $showing->heading(), $options);
	}

	private function showing(Html $message, Html $link, Html $heading): MessageShowing {
		$showing = new MessageShowing($message->html, $link->html, $heading->html);
		$this->events->dispatch($showing);

		return $showing;
	}

	/** @param list<Html> $options */
	private function main(MessageShowing $showing, string $heading, array $options): Html {
		$start = new MessageRendering(MessageRendering::START);
		$this->events->dispatch($start);

		$body = $this->templates->render(self::TEMPLATE, array(
			'options'	=> Html::join(' ', $options),
			'heading'	=> new Html($heading),
			'message'	=> new Html($showing->message()),
			'link'		=> new Html($showing->link()),
		));

		$end = new MessageRendering(MessageRendering::END);
		$this->events->dispatch($end);

		return new Html("\t".(new Html($start->markup().$body.$end->markup()))->trim()->html);
	}
}
