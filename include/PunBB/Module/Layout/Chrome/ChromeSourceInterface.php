<?php

declare(strict_types=1);

namespace PunBB\Module\Layout\Chrome;

use PunBB\Module\Layout\View\Html;

/**
 * What the chrome reads about the page, the visit and the board. Each value is
 * read when the chrome reaches it, so what an observer changed on the way is
 * what the rest of the chrome shows.
 */
interface ChromeSourceInterface {
	/** The page's id: 'index', 'viewtopic', 'admin-settings-setup'. */
	public function pageId(): string;

	/** 'admin-page', 'paged-page', 'menu-page' or 'basic-page', unless the page named its own. */
	public function pageType(): string;

	public function allowsIndexing(): bool;

	public function viewer(): Viewer;

	public function board(): Board;

	public function languageIdentifier(): string;

	public function languageDirection(): string;

	/** A string of the common language pack; the packs hold markup. */
	public function text(string $key): Html;

	/** A URL of the forum's scheme by its name, encoded for an attribute. */
	public function link(string $name): Html;

	public function baseUrl(): string;

	/** @return array<string, Html> what the page put in the head before the chrome built it */
	public function headEntries(): array;

	/** The page's MicroID hash, null on a page without one. */
	public function microid(): ?string;

	/** The URL of the page's feed in $format, 'rss' or 'atom'; null on a page without one. */
	public function feed(string $format): ?Html;

	/** @return list<Html> the page's first, previous, next and last links */
	public function pageNavigation(): array;

	/** @return list<Html> the lines the theme wrote into the head */
	public function themeHead(): array;

	/** The stylesheets registered for the page. */
	public function stylesheets(): Html;

	/** The breadcrumbs: as links, or reversed as the text of a title. */
	public function crumbs(bool $reverse): Html;

	/** The text of the last breadcrumb. */
	public function lastCrumb(): string;

	/** The page's own main heading, null to use the last breadcrumb. */
	public function mainTitle(): ?Html;

	/** The page count shown beside the main heading, null when there is none. */
	public function mainHeadPages(): ?Html;

	/** @return list<Html> */
	public function pagePost(): array;

	/** @return list<Html> */
	public function mainMenu(): array;

	/** The list items of the main navigation. */
	public function navigation(): Html;

	/** The list items of the administration menu, or of the current section's submenu. */
	public function adminMenu(bool $submenu): Html;

	public function flashMessages(): Html;

	public function hasUnreadReports(): bool;

	/** The forum jump list for the visitor's group. */
	public function quickjump(): Html;

	/** Whether the page carries the debug footer. */
	public function debugging(): bool;

	/** The generation time and query count line, null unless the forum debugs. */
	public function queryTime(): ?Html;

	/** The table of the queries the page ran, null unless the forum shows them. */
	public function savedQueries(): ?Html;

	public function addInlineScript(string $code, int $weight): void;

	public function addScript(string $url, int $weight): void;

	/** An inline script of the page's own, in the default group and weight. */
	public function addPageScript(string $code): void;

	/** The scripts registered for the page. */
	public function scripts(): Html;
}
