<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Layout;

use FlashMessenger;
use Loader;
use PunBB\Module\LegacyBridge\Database\LegacyConnection;
use PunBB\Module\LegacyBridge\Hook\PointEvaluator;
use PunBB\Module\Layout\Chrome\Board;
use PunBB\Module\Layout\Chrome\ChromeException;
use PunBB\Module\Layout\Chrome\ChromeSourceInterface;
use PunBB\Module\Layout\Chrome\Updates;
use PunBB\Module\Layout\Chrome\Viewer;
use PunBB\Module\Layout\View\Html;

/**
 * The chrome as the legacy bootstrap describes it: the globals include/common.php
 * and the page set, the helpers of include/functions.php, the loader, the flash
 * messenger and the connection. Each value is read when the chrome asks, as
 * header.php and footer.php read them.
 */
final class LegacyChromeSource implements ChromeSourceInterface {
	public function __construct(private readonly PointEvaluator $points, private readonly LegacyTemplate $template) {}

	public function pageId(): string {
		return defined('FORUM_PAGE') ? Markers::markup(constant('FORUM_PAGE')) : 'unknown';
	}

	public function pageType(): string {
		if (!defined('FORUM_PAGE_TYPE'))
		{
			$page = self::globalArray('forum_page');

			if (str_starts_with($this->pageId(), 'admin'))
				define('FORUM_PAGE_TYPE', 'admin-page');
			else if (!empty($page['page_post']))
				define('FORUM_PAGE_TYPE', 'paged-page');
			else if (!empty($page['main_menu']))
				define('FORUM_PAGE_TYPE', 'menu-page');
			else
				define('FORUM_PAGE_TYPE', 'basic-page');
		}

		return Markers::markup(constant('FORUM_PAGE_TYPE'));
	}

	public function allowsIndexing(): bool {
		return defined('FORUM_ALLOW_INDEX');
	}

	public function viewer(): Viewer {
		$user = self::globalArray('forum_user');

		return new Viewer(
			(int) Markers::markup($user['id'] ?? 1),
			Markers::markup($user['username'] ?? ''),
			(bool) ($user['is_guest'] ?? true),
			($user['g_id'] ?? null) == self::constant('FORUM_ADMIN'),
			(bool) ($user['is_admmod'] ?? false),
			($user['g_read_board'] ?? null) == '1',
			($user['g_search'] ?? null) == '1',
			Markers::markup($user['language'] ?? ''),
			Markers::markup($user['style'] ?? '')
		);
	}

	public function board(): Board {
		$config = self::globalArray('forum_config');
		$updates = null;

		if (($config['o_check_for_updates'] ?? null) == '1')
		{
			$cached = self::globalArray('forum_updates');
			$updates = new Updates(!empty($cached['fail']), isset($cached['version']) ? Markers::markup($cached['version']) : null, isset($cached['hotfix']));
		}

		return new Board(
			Markers::markup($config['o_board_title'] ?? ''),
			Markers::markup($config['o_board_desc'] ?? ''),
			($config['o_announcement'] ?? null) == '1',
			Markers::markup($config['o_announcement_heading'] ?? ''),
			new Html(Markers::markup($config['o_announcement_message'] ?? '')),
			($config['o_maintenance'] ?? null) == '1',
			($config['o_quickjump'] ?? null) == '1',
			($config['o_show_version'] ?? null) == '1' ? Markers::markup($config['o_cur_version'] ?? '') : null,
			($config['o_report_method'] ?? null) == 1,
			$updates,
			// A database a newer release updated: the release moves, FORUM_DB_REVISION stays at 7
			version_compare(Markers::markup($config['o_cur_version'] ?? ''), Markers::markup(self::constant('FORUM_VERSION')), '>')
		);
	}

	public function languageIdentifier(): string {
		return $this->text('lang_identifier')->html;
	}

	public function languageDirection(): string {
		return $this->text('lang_direction')->html;
	}

	public function text(string $key): Html {
		return new Html(Markers::markup(self::globalArray('lang_common')[$key] ?? ''));
	}

	public function link(string $name): Html {
		return new Html(Markers::markup(\forum_link(self::globalArray('forum_url')[$name] ?? '')));
	}

	public function baseUrl(): string {
		return Markers::markup($GLOBALS['base_url'] ?? '');
	}

	public function headEntries(): array {
		$entries = array();
		foreach (self::globalArray('forum_head') as $name => $markup)
			$entries[(string) $name] = new Html(Markers::markup($markup));

		return $entries;
	}

	public function microid(): ?string {
		if (!str_starts_with($this->pageId(), 'profile'))
			return null;

		$user = self::globalArray('user');

		return sha1(sha1('mailto:'.Markers::markup($user['email'] ?? '')).sha1(Markers::markup(\forum_link(self::globalArray('forum_url')['user'] ?? '', $GLOBALS['id'] ?? null))));
	}

	public function feed(string $format): ?Html {
		$urls = self::globalArray('forum_url');

		$link = match ($this->pageId()) {
			'index'		=> \forum_link($urls['index_'.$format] ?? ''),
			'viewforum'	=> \forum_link($urls['forum_'.$format] ?? '', $GLOBALS['id'] ?? null),
			'viewtopic'	=> \forum_link($urls['topic_'.$format] ?? '', $GLOBALS['id'] ?? null),
			default		=> null,
		};

		return $link !== null ? new Html(Markers::markup($link)) : null;
	}

	public function pageNavigation(): array {
		return self::markupList(self::globalArray('forum_page')['nav'] ?? null);
	}

	public function themeHead(): array {
		$style = $this->viewer()->style;
		$script = self::root().'style/'.$style.'/'.$style.'.php';

		ob_start();

		if (file_exists($script))
		{
			if ($this->template->html !== null)
				LegacyScope::include($script, array($this->template->variable => &$this->template->html));
			else
				LegacyScope::include($script);
		}
		else
			$this->loader()->add_css($this->baseUrl().'/style/print.css', array('type' => 'url', 'group' => self::constant('FORUM_CSS_GROUP_SYSTEM'), 'media' => 'screen'));

		$lines = array();
		foreach (explode("\n", Markers::markup(\forum_trim((string) ob_get_clean()))) as $line)
			$lines[] = new Html($line);

		return $lines;
	}

	public function stylesheets(): Html {
		return new Html(Markers::markup($this->loader()->render_css()));
	}

	public function crumbs(bool $reverse): Html {
		return new Html(Markers::markup(\generate_crumbs($reverse)));
	}

	public function lastCrumb(): string {
		$crumbs = self::globalArray('forum_page')['crumbs'] ?? null;
		$last = is_array($crumbs) ? end($crumbs) : false;

		return Markers::markup(is_array($last) ? reset($last) : $last);
	}

	public function mainTitle(): ?Html {
		$page = self::globalArray('forum_page');

		return isset($page['main_title']) ? new Html(Markers::markup($page['main_title'])) : null;
	}

	public function mainHeadPages(): ?Html {
		$page = self::globalArray('forum_page');

		return isset($page['main_head_pages']) ? new Html(Markers::markup($page['main_head_pages'])) : null;
	}

	public function pagePost(): array {
		return self::markupList(self::globalArray('forum_page')['page_post'] ?? null);
	}

	public function mainMenu(): array {
		return self::markupList(self::globalArray('forum_page')['main_menu'] ?? null);
	}

	public function navigation(): Html {
		return new Html(Markers::markup(\generate_navlinks()));
	}

	public function adminMenu(bool $submenu): Html {
		$menu = Markers::markup(\generate_admin_menu($submenu));

		// The administration pages read the submenu back from $forum_page
		if ($submenu && isset($GLOBALS['forum_page']) && is_array($GLOBALS['forum_page']))
			$GLOBALS['forum_page']['admin_sub'] = $menu;

		return new Html($menu);
	}

	public function flashMessages(): Html {
		$flash = $GLOBALS['forum_flash'] ?? null;
		if (!$flash instanceof FlashMessenger)
			throw new ChromeException('The legacy bootstrap has no flash messenger in $forum_flash');

		return new Html(Markers::markup($flash->show(true)));
	}

	public function hasUnreadReports(): bool {
		$query = array(
			'SELECT'	=> 'COUNT(r.id)',
			'FROM'		=> 'reports AS r',
			'WHERE'		=> 'r.zapped IS NULL',
		);

		if (LegacyScope::attached('hd_qr_get_unread_reports_count'))
			$this->points->run('hd_qr_get_unread_reports_count', LegacyScope::with(array('query' => &$query)));

		$db = LegacyConnection::legacy();
		$result = $db->query_build($query);
		if ($result === false)
			\error(__FILE__, __LINE__);

		return (bool) $db->result($result);
	}

	public function quickjump(): Html {
		$group = Markers::markup(self::globalArray('forum_user')['g_id'] ?? '');
		$cache = Markers::markup(self::constant('FORUM_CACHE_DIR')).'cache_quickjump_'.$group.'.php';

		ob_start();

		if (file_exists($cache))
			LegacyScope::include($cache);

		if (!defined('FORUM_QJ_LOADED'))
		{
			if (!defined('FORUM_CACHE_FUNCTIONS_LOADED'))
				require self::root().'include/cache.php';

			\generate_quickjump_cache($group);
			LegacyScope::include($cache);
		}

		return new Html((string) ob_get_clean());
	}

	public function debugging(): bool {
		return defined('FORUM_DEBUG') || defined('FORUM_SHOW_QUERIES');
	}

	public function queryTime(): ?Html {
		if (!defined('FORUM_DEBUG'))
			return null;

		$db = LegacyConnection::legacy();
		$time = (float) Markers::markup(\forum_microtime()) - (float) Markers::markup($GLOBALS['forum_start'] ?? 0);
		$queries = 0.0;

		foreach ((array) $db->get_saved_queries() as $query)
			$queries += is_array($query) ? (float) Markers::markup($query[1] ?? 0) : 0.0;

		$share = $queries > 0 && $time > 0 ? $queries / $time * 100 : 0.0;

		return new Html(sprintf($this->text('Querytime')->html,
			Markers::markup(\forum_number_format($time, 3)),
			Markers::markup(\forum_number_format(100 - $share, 0)),
			Markers::markup(\forum_number_format($share, 0)),
			Markers::markup(\forum_number_format($db->get_num_queries()))));
	}

	public function savedQueries(): ?Html {
		return defined('FORUM_SHOW_QUERIES') ? new Html(Markers::markup(\get_saved_queries())) : null;
	}

	public function addInlineScript(string $code, int $weight): void {
		$this->loader()->add_js($code, array('type' => 'inline', 'weight' => $weight, 'group' => self::constant('FORUM_JS_GROUP_SYSTEM')));
	}

	public function addScript(string $url, int $weight): void {
		$this->loader()->add_js($url, array('weight' => $weight, 'async' => false, 'group' => self::constant('FORUM_JS_GROUP_SYSTEM')));
	}

	public function addPageScript(string $code): void {
		$this->loader()->add_js($code, array('type' => 'inline'));
	}

	public function scripts(): Html {
		return new Html(Markers::markup($this->loader()->render_js()));
	}

	/** The forum root, as the page scripts name it. */
	public static function root(): string {
		return defined('FORUM_ROOT') ? Markers::markup(constant('FORUM_ROOT')) : dirname(__DIR__, 5).'/';
	}

	/** @return array<mixed> the global $name, or nothing when it is not an array */
	private static function globalArray(string $name): array {
		return isset($GLOBALS[$name]) && is_array($GLOBALS[$name]) ? $GLOBALS[$name] : array();
	}

	/** @return list<Html> */
	private static function markupList(mixed $items): array {
		$list = array();
		foreach (is_array($items) ? $items : array() as $item)
			$list[] = new Html(Markers::markup($item));

		return $list;
	}

	private static function constant(string $name): mixed {
		if (!defined($name))
			throw new ChromeException(sprintf('The legacy bootstrap has not defined %s', $name));

		return constant($name);
	}

	private function loader(): Loader {
		$loader = $GLOBALS['forum_loader'] ?? null;
		if (!$loader instanceof Loader)
			throw new ChromeException('The legacy bootstrap has no loader in $forum_loader');

		return $loader;
	}
}
