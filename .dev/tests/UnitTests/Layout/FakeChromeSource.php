<?php
/**
 * A chrome source over plain properties, which records what the chrome read
 * and when, and keeps the scripts registered with it.
 *
 * @copyright (C) 2008-2012 PunBB, partially based on code (C) 2008-2009 FluxBB.org
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package PunBB
 */

use PunBB\Module\Layout\Chrome\Board;
use PunBB\Module\Layout\Chrome\ChromeSourceInterface;
use PunBB\Module\Layout\Chrome\Viewer;
use PunBB\Module\Layout\View\Html;

final class FakeChromeSource implements ChromeSourceInterface {
	public string $pageId = 'viewtopic';

	public Viewer $viewer;

	public Board $board;

	/** @var list<string> */
	public array $crumbs = array('Board', 'Topic <1>');

	/** @var list<string> */
	public array $pagePost = array();

	public bool $debugging = false;

	public ?Html $savedQueries = null;

	public bool $unreadReports = false;

	/** @var list<string> what the chrome read, in order */
	public array $reads = array();

	/** @var list<string> the scripts registered, in order */
	public array $scripts = array();

	public function __construct() {
		$this->viewer = self::a('member');
		$this->board = self::boardWith();
	}

	public static function a(string $who): Viewer {
		return match ($who) {
			'guest'	=> new Viewer(1, 'Guest', true, false, false, true, true, 'English', 'Oxygen'),
			'admin'	=> new Viewer(2, 'admin', false, true, true, true, true, 'English', 'Oxygen'),
			default	=> new Viewer(3, 'Member <m>', false, false, false, true, true, 'English', 'Oxygen'),
		};
	}

	public static function boardWith(bool $quickjump = true, bool $maintenance = false): Board {
		return new Board('Board & Co', 'About "it"', false, '', new Html(''), $maintenance, $quickjump, null, false, null, false);
	}

	public function pageId(): string {
		return $this->pageId;
	}

	public function pageType(): string {
		return 'basic-page';
	}

	public function allowsIndexing(): bool {
		return false;
	}

	public function viewer(): Viewer {
		return $this->viewer;
	}

	public function board(): Board {
		return $this->board;
	}

	public function languageIdentifier(): string {
		return 'en';
	}

	public function languageDirection(): string {
		return 'ltr';
	}

	public function text(string $key): Html {
		return new Html($key === 'Powered by' ? 'Powered by %s, supported by %s.' : ($key === 'Logged in as' ? 'Logged in as %s.' : '['.$key.']'));
	}

	public function link(string $name): Html {
		return new Html('/'.$name.'?a=1&amp;b=2');
	}

	public function baseUrl(): string {
		return 'http://forum.test';
	}

	public function headEntries(): array {
		return array('early' => new Html('<meta name="early" />'));
	}

	public function microid(): ?string {
		return null;
	}

	public function feed(string $format): ?Html {
		return null;
	}

	public function pageNavigation(): array {
		return array();
	}

	public function themeHead(): array {
		$this->reads[] = 'theme';

		return array(new Html(''));
	}

	public function stylesheets(): Html {
		$this->reads[] = 'stylesheets';

		return new Html('<link rel="stylesheet" href="oxygen.css" />'."\n");
	}

	public function crumbs(bool $reverse): Html {
		$this->reads[] = 'crumbs';
		$crumbs = $reverse ? array_reverse($this->crumbs) : $this->crumbs;

		return Html::join($reverse ? ' — ' : ' → ', array_map(Html::escape(...), $crumbs));
	}

	public function lastCrumb(): string {
		return (string) end($this->crumbs);
	}

	public function mainTitle(): ?Html {
		return null;
	}

	public function mainHeadPages(): ?Html {
		return null;
	}

	public function pagePost(): array {
		return array_map(static fn (string $markup): Html => new Html($markup), $this->pagePost);
	}

	public function mainMenu(): array {
		return array();
	}

	public function navigation(): Html {
		$this->reads[] = 'navigation';

		return new Html('<li id="navindex"><a href="/index">Index</a></li>');
	}

	public function adminMenu(bool $submenu): Html {
		return new Html($submenu ? '' : '<li>Start</li>');
	}

	public function flashMessages(): Html {
		$this->reads[] = 'flash';

		return new Html('');
	}

	public function hasUnreadReports(): bool {
		return $this->unreadReports;
	}

	public function quickjump(): Html {
		$this->reads[] = 'quickjump';

		return new Html('<form id="qjump"></form>'."\n");
	}

	public function debugging(): bool {
		return $this->debugging;
	}

	public function queryTime(): ?Html {
		return $this->debugging ? new Html('Generated in 0.1 seconds') : null;
	}

	public function savedQueries(): ?Html {
		return $this->savedQueries;
	}

	public function addInlineScript(string $code, int $weight): void {
		$this->scripts[] = $weight.': '.$code;
	}

	public function addScript(string $url, int $weight): void {
		$this->scripts[] = $weight.': '.$url;
	}

	public function addPageScript(string $code): void {
		$this->scripts[] = 'page: '.$code;
	}

	public function scripts(): Html {
		$this->reads[] = 'scripts';

		return new Html('<script>'.count($this->scripts).' scripts</script>');
	}
}
