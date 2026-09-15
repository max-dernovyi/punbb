<?php

declare(strict_types=1);

namespace PunBB\Module\Layout\Page;

use Closure;
use PunBB\Module\Framework\Http\Response;
use PunBB\Module\Layout\Chrome\ChromeFactoryInterface;
use PunBB\Module\Layout\Chrome\ChromeInterface;
use PunBB\Module\Layout\Chrome\Layout;
use PunBB\Module\Layout\Chrome\PageHead;
use PunBB\Module\Layout\View\Html;

/**
 * Answers with a page: its chrome opened, then its content rendered, then both
 * composed and sent with the headers every page carries.
 */
final class PageResponder {
	public function __construct(private readonly ChromeFactoryInterface $chromes) {}

	/**
	 * @param Closure(ChromeInterface): array<string, Html> $content renders the page's regions once the header is built
	 */
	public function respond(PageHead $head, Closure $content, int $status = 200): Response {
		$chrome = $this->chromes->open($head);

		return new Response($chrome->close($content($chrome)), $status, Layout::headers(time()));
	}
}
