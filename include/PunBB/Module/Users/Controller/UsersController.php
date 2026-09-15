<?php

declare(strict_types=1);

namespace PunBB\Module\Users\Controller;

use PunBB\Module\Framework\Event\EventDispatcher;
use PunBB\Module\Framework\Http\Request;
use PunBB\Module\Framework\Http\Response;
use PunBB\Module\Framework\Routing\ControllerInterface;
use PunBB\Module\Layout\Chrome\ChromeInterface;
use PunBB\Module\Layout\Chrome\Crumb;
use PunBB\Module\Layout\Chrome\PageHead;
use PunBB\Module\Layout\Page\PageResponder;
use PunBB\Module\Layout\View\Html;
use PunBB\Module\Layout\View\Parts;
use PunBB\Module\Layout\View\TemplateRenderer;
use PunBB\Module\Message\Page\MessagePage;
use PunBB\Module\Message\Page\RedirectPage;
use PunBB\Module\Site\Cache\BanCacheInterface;
use PunBB\Module\Site\Config\SettingsInterface;
use PunBB\Module\Site\Flash\FlashMessagesInterface;
use PunBB\Module\Site\Format\FormatterInterface;
use PunBB\Module\Site\Format\TimeFormat;
use PunBB\Module\Site\Language\LanguageInterface;
use PunBB\Module\Site\Moderation\ModeratorListsInterface;
use PunBB\Module\Site\Security\CsrfTokensInterface;
use PunBB\Module\Site\Url\UrlsInterface;
use PunBB\Module\Site\Visitor\GroupPermission;
use PunBB\Module\Site\Visitor\VisitorInterface;
use PunBB\Module\Users\Api\Data\FoundUserInterface;
use PunBB\Module\Users\Api\Data\ListedGroupInterface;
use PunBB\Module\Users\Api\UsersInterface;
use PunBB\Module\Users\Event\ActionFormRendering;
use PunBB\Module\Users\Event\BanUsersStep;
use PunBB\Module\Users\Event\ChangeGroupStep;
use PunBB\Module\Users\Event\DeleteUsersStep;
use PunBB\Module\Users\Event\ModerationButtonsAssembling;
use PunBB\Module\Users\Event\ResultRowAssembling;
use PunBB\Module\Users\Event\ResultsEnding;
use PunBB\Module\Users\Event\ResultsTableAssembling;
use PunBB\Module\Users\Event\SearchSelected;
use PunBB\Module\Users\Event\UsersActionRequested;
use PunBB\Module\Users\Event\UserSearchFormRendering;
use PunBB\Module\Users\Event\UsersRequested;
use PunBB\Module\Users\Model\UserBan;
use PunBB\Module\Users\Model\UserSearch;
use PunBB\Module\Site\Removal\UserRemovalInterface;
use PunBB\Module\Users\View\FormView;

/**
 * admin/users.php, for administrators and moderators: the forms searching the
 * users, the addresses a user posted from, the users who posted from an
 * address, the users a search finds, and deleting, banning or moving into
 * another group the users selected among them.
 */
final class UsersController implements ControllerInterface {
	private const SEARCH_TEMPLATE = __DIR__.'/../templates/search.phtml';

	private const ADDRESSES_TEMPLATE = __DIR__.'/../templates/addresses.phtml';

	private const USERS_TEMPLATE = __DIR__.'/../templates/users.phtml';

	private const DELETE_TEMPLATE = __DIR__.'/../templates/delete.phtml';

	private const BAN_TEMPLATE = __DIR__.'/../templates/ban.phtml';

	private const CHANGE_GROUP_TEMPLATE = __DIR__.'/../templates/change_group.phtml';

	private const SELECT_ALL_SCRIPT = 'PUNBB.common.addDOMReadyEvent(PUNBB.common.initToggleCheckboxes);';

	/** An address as the page script accepted it: a dotted quad anywhere in the text, or a whole IPv6 address. */
	private const IPV4 = '/[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}/';

	private const IPV6 = '/^((([0-9A-Fa-f]{1,4}:){7}[0-9A-Fa-f]{1,4})|(([0-9A-Fa-f]{1,4}:){6}:[0-9A-Fa-f]{1,4})|(([0-9A-Fa-f]{1,4}:){5}:([0-9A-Fa-f]{1,4}:)?[0-9A-Fa-f]{1,4})|(([0-9A-Fa-f]{1,4}:){4}:([0-9A-Fa-f]{1,4}:){0,2}[0-9A-Fa-f]{1,4})|(([0-9A-Fa-f]{1,4}:){3}:([0-9A-Fa-f]{1,4}:){0,3}[0-9A-Fa-f]{1,4})|(([0-9A-Fa-f]{1,4}:){2}:([0-9A-Fa-f]{1,4}:){0,4}[0-9A-Fa-f]{1,4})|(([0-9A-Fa-f]{1,4}:){6}((\b((25[0-5])|(1\d{2})|(2[0-4]\d)|(\d{1,2}))\b)\.){3}(\b((25[0-5])|(1\d{2})|(2[0-4]\d)|(\d{1,2}))\b))|(([0-9A-Fa-f]{1,4}:){0,5}:((\b((25[0-5])|(1\d{2})|(2[0-4]\d)|(\d{1,2}))\b)\.){3}(\b((25[0-5])|(1\d{2})|(2[0-4]\d)|(\d{1,2}))\b))|(::([0-9A-Fa-f]{1,4}:){0,5}((\b((25[0-5])|(1\d{2})|(2[0-4]\d)|(\d{1,2}))\b)\.){3}(\b((25[0-5])|(1\d{2})|(2[0-4]\d)|(\d{1,2}))\b))|([0-9A-Fa-f]{1,4}::([0-9A-Fa-f]{1,4}:){0,5}[0-9A-Fa-f]{1,4})|(::([0-9A-Fa-f]{1,4}:){0,6}[0-9A-Fa-f]{1,4})|(([0-9A-Fa-f]{1,4}:){1,7}:))$/';

	/** @var array<string, array{string, string, int, int}> the text fields of the details and contacts fieldsets: form field => the position before it, its label, size and length */
	private const DETAILS = array(
		'username'		=> array(UserSearchFormRendering::PRE_USERNAME, 'Username label', 35, 25),
		'title'			=> array(UserSearchFormRendering::PRE_USER_TITLE, 'Title label', 35, 50),
		'realname'		=> array(UserSearchFormRendering::PRE_REALNAME, 'Real name label', 35, 40),
		'location'		=> array(UserSearchFormRendering::PRE_LOCATION, 'Location label', 35, 30),
		'signature'		=> array(UserSearchFormRendering::PRE_SIGNATURE, 'Signature label', 35, 512),
		'admin_note'	=> array(UserSearchFormRendering::PRE_ADMIN_NOTE, 'Admin note label', 35, 30),
	);

	/** @var array<string, array{string, string, int, int}> */
	private const CONTACTS = array(
		'email'		=> array(UserSearchFormRendering::PRE_EMAIL, 'E-mail address label', 35, 80),
		'url'		=> array(UserSearchFormRendering::PRE_WEBSITE, 'Website label', 35, 100),
		'jabber'	=> array(UserSearchFormRendering::PRE_JABBER, 'Jabber label', 35, 80),
		'icq'		=> array(UserSearchFormRendering::PRE_ICQ, 'ICQ label', 12, 12),
		'msn'		=> array(UserSearchFormRendering::PRE_MSN, 'MSN Messenger label', 35, 80),
		'aim'		=> array(UserSearchFormRendering::PRE_AIM, 'AOL IM label', 20, 20),
		'yahoo'		=> array(UserSearchFormRendering::PRE_YAHOO, 'Yahoo Messenger label', 20, 20),
	);

	/** @var array<string, array{string, string, string}> the activity fieldset's fields: name => the position before it, its label and the markup between the label and its help */
	private const ACTIVITY = array(
		'posts_greater'		=> array(UserSearchFormRendering::PRE_MIN_POSTS, 'More posts label', ' '),
		'posts_less'		=> array(UserSearchFormRendering::PRE_MAX_POSTS, 'Less posts label', ' '),
		'last_post_after'	=> array(UserSearchFormRendering::PRE_LAST_POST_AFTER, 'Last post after label', ' '),
		'last_post_before'	=> array(UserSearchFormRendering::PRE_LAST_POST_BEFORE, 'Last post before label', ''),
		'registered_after'	=> array(UserSearchFormRendering::PRE_REGISTERED_AFTER, 'Registered after label', ' '),
		'registered_before'	=> array(UserSearchFormRendering::PRE_REGISTERED_BEFORE, 'Registered before label', ' '),
	);

	public function __construct(
		private readonly EventDispatcher $events,
		private readonly PageResponder $pages,
		private readonly TemplateRenderer $templates,
		private readonly MessagePage $messages,
		private readonly RedirectPage $redirects,
		private readonly UsersInterface $users,
		private readonly UserRemovalInterface $removal,
		private readonly BanCacheInterface $bans,
		private readonly ModeratorListsInterface $moderators,
		private readonly VisitorInterface $visitor,
		private readonly LanguageInterface $language,
		private readonly SettingsInterface $settings,
		private readonly UrlsInterface $urls,
		private readonly FormatterInterface $formatter,
		private readonly CsrfTokensInterface $tokens,
		private readonly FlashMessagesInterface $flash
	) {}

	public function handle(Request $request): Response {
		$this->events->dispatch(new UsersRequested());

		if (!$this->visitor->isModerating())
			return $this->messages->respond($this->language->text('common', 'No permission'), json: $request->xhr);

		$common = $this->language->strings('admin_common');
		$strings = $this->language->strings('admin_users');
		$bans = $this->language->strings('admin_bans');

		if (isset($request->query['ip_stats']))
			return $this->addresses($request, $common, $strings);

		if (isset($request->query['show_users']))
			return $this->posters($request, $common, $strings);

		$post = $request->post;

		if (isset($post['delete_users']) || isset($post['delete_users_comply']) || isset($post['delete_users_cancel']))
			return $this->delete($request, $common, $strings);

		if (isset($post['ban_users']) || isset($post['ban_users_comply']))
			return $this->ban($request, $common, $strings, $bans);

		if (isset($post['change_group']) || isset($post['change_group_comply']) || isset($post['change_group_cancel']))
			return $this->changeGroup($request, $common, $strings);

		if (isset($request->query['find_user']))
			return $this->found($request, $common, $strings);

		$this->events->dispatch(new UsersActionRequested());

		return $this->searchForm($common, $strings);
	}

	/**
	 * The addresses a user posted from.
	 *
	 * @param array<string, Html> $common
	 * @param array<string, Html> $strings
	 */
	private function addresses(Request $request, array $common, array $strings): Response {
		$userId = self::integer($request->query['ip_stats']);
		if ($userId < 1)
			return $this->badRequest($request);

		$this->events->dispatch(new SearchSelected(SearchSelected::IP_STATS, userId: $userId));

		$addresses = $this->users->addressesOf($userId);
		$heading = Html::format(self::string($strings, 'IP addresses found'), count($addresses));

		$head = new PageHead('admin-iresults', $this->crumbs($common, self::string($strings, 'User search results')), section: 'users');

		return $this->pages->respond($head, function () use ($addresses, $heading, $strings): array {
			$table = new ResultsTableAssembling(SearchSelected::IP_STATS, count($addresses),
				self::header(array('ip' => self::string($strings, 'IP address'), 'lastused' => self::string($strings, 'Last used'), 'timesfound' => self::string($strings, 'Times found'), 'actions' => self::string($strings, 'Actions'))),
				new Parts(), new Parts());
			$this->events->dispatch($table);

			$rows = array();
			foreach ($addresses as $index => $address)
			{
				$start = new ResultRowAssembling(SearchSelected::IP_STATS, ResultRowAssembling::START, $index + 1, self::style($index + 1), array(), address: $address);
				$this->events->dispatch($start);

				$rows[] = $this->row(new ResultRowAssembling(SearchSelected::IP_STATS, ResultRowAssembling::CELLS, $index + 1, $start->style(), self::cells(array(
					'ip'			=> Html::format('<a href="%s">%s</a>', $this->urls->link('get_host', array($address->address())), $address->address()),
					'lastused'		=> $this->formatter->time($address->lastUsed(), TimeFormat::DateTime),
					'timesfound'	=> Html::escape((string) $address->timesUsed()),
					'actions'		=> Html::format('<a href="%s?show_users=%s">%s</a>', $this->urls->link('admin_users'), $address->address(), self::string($strings, 'Find more users')),
				)), address: $address), $start);
			}

			if ($addresses === array())
				$rows[] = $this->emptyRow(SearchSelected::IP_STATS, array('ip' => self::string($strings, 'No posts by user'), 'lastused' => new Html(' - '), 'timesfound' => new Html(' - '), 'actions' => new Html(' - ')));

			$body = $this->templates->render(self::ADDRESSES_TEMPLATE, array(
				'heading'		=> $heading,
				'headOptions'	=> self::options($table, ResultsTableAssembling::HEAD_OPTIONS),
				'footOptions'	=> self::options($table, ResultsTableAssembling::FOOT_OPTIONS),
				'header'		=> self::joined($table, ResultsTableAssembling::HEADER),
				'rows'			=> $rows,
			));

			$end = new ResultsEnding(SearchSelected::IP_STATS, count($addresses));
			$this->events->dispatch($end);

			return array('main' => (new Html($table->markup().$body.$end->markup()))->trim());
		});
	}

	/**
	 * The users who posted from an address, the guests among them by the name their posts carry.
	 *
	 * @param array<string, Html> $common
	 * @param array<string, Html> $strings
	 */
	private function posters(Request $request, array $common, array $strings): Response {
		$address = is_string($request->query['show_users']) ? $request->query['show_users'] : '';
		if ($address === '' || $address === '0' || (preg_match(self::IPV4, $address) !== 1 && preg_match(self::IPV6, $address) !== 1))
			return $this->messages->respond(self::string($strings, 'Invalid IP address'), json: $request->xhr);

		$this->events->dispatch(new SearchSelected(SearchSelected::SHOW_USERS, address: $address));

		$misc = $this->language->strings('misc');
		$posters = $this->users->postersFrom($address);

		$head = new PageHead('admin-uresults', $this->crumbs($common, self::string($strings, 'User search results')), section: 'users', view: 'show_users');

		return $this->pages->respond($head, function (ChromeInterface $chrome) use ($posters, $common, $strings, $misc): array {
			$table = $this->usersTable(SearchSelected::SHOW_USERS, count($posters), 'aus-show-users-results-form', $common, $strings, $misc);

			$rows = array();
			foreach ($posters as $index => $poster)
			{
				$user = $this->users->member($poster->id());

				$start = new ResultRowAssembling(SearchSelected::SHOW_USERS, ResultRowAssembling::START, $index + 1, self::style($index + 1), array(), poster: $poster, user: $user);
				$this->events->dispatch($start);

				$cells = $user !== null
					? $this->userCells($user, $this->formatter->memberTitle($user->username(), $user->title(), $user->postCount(), $user->groupId(), $user->groupTitle()), $strings)
					: self::cells(array('username' => Html::escape($poster->name()), 'title' => self::string($strings, 'Guest'), 'posts' => new Html(' - '), 'actions' => new Html(' - '), 'select' => new Html(' - ')));

				$rows[] = $this->row(new ResultRowAssembling(SearchSelected::SHOW_USERS, ResultRowAssembling::CELLS, $index + 1, $start->style(), $cells, poster: $poster, user: $user), $start);
			}

			if ($posters === array())
				$rows[] = $this->emptyRow(SearchSelected::SHOW_USERS, array('username' => self::string($strings, 'Cannot find IP'), 'title' => new Html(' - '), 'posts' => new Html(' - '), 'actions' => new Html(' - '), 'select' => new Html(' - ')));

			return array('main' => $this->usersMain($chrome, $table, $rows, 'aus-show-users-results-form', 'main-frm', $common, $strings));
		});
	}

	/**
	 * The users the search form's criteria match, a page at a time.
	 *
	 * @param array<string, Html> $common
	 * @param array<string, Html> $strings
	 */
	private function found(Request $request, array $common, array $strings): Response {
		$order = UserSearch::orderOf($request->query);
		if ($order === null)
			return $this->badRequest($request);

		[$orderBy, $descending] = $order;

		$fields = array();
		foreach (is_array($request->query['form'] ?? null) ? $request->query['form'] : array() as $name => $value)
			$fields[(string) $name] = self::text($value);

		$this->events->dispatch(new SearchSelected(SearchSelected::FIND_USER, orderBy: $orderBy, descending: $descending, fields: $fields));

		$refusal = UserSearch::refusal($request->query);
		if ($refusal !== null)
			return $this->messages->respond(self::string($strings, $refusal), json: $request->xhr);

		$search = UserSearch::fromQuery($request->query, $orderBy, $descending);

		$misc = $this->language->strings('misc');
		$count = $this->users->count($search);

		$perPage = $this->visitor->topicsPerPage();
		$pages = $perPage > 0 ? (int) ceil($count / $perPage) : 0;
		$requested = $request->query['p'] ?? null;
		$page = is_numeric($requested) && $requested > 1 && $requested <= $pages ? (int) $requested : 1;
		$offset = $perPage * ($page - 1);

		$head = new PageHead('admin-uresults', $this->crumbs($common, self::string($strings, 'User search results')), section: 'users', page: $page, view: 'find_user',
			pagePost: array('paging' => Html::format('<p class="paging"><span class="pages">%s</span> %s</p>', $this->language->text('common', 'Pages'),
				$this->urls->pagination($pages, $page, 'admin_users', pageInQuery: true, query: '?find_user='.$search->criteria()))));

		return $this->pages->respond($head, function (ChromeInterface $chrome) use ($search, $count, $offset, $perPage, $common, $strings, $misc): array {
			$table = $this->usersTable(SearchSelected::FIND_USER, $count, 'aus-find-user-results-form', $common, $strings, $misc);

			$users = $this->users->find($search, $offset, min($perPage, $count));
			$banned = $this->language->text('common', 'Banned')->html;

			$rows = array();
			if ($count > 0)
			{
				foreach ($users as $index => $user)
				{
					// The results say which users have not confirmed their address, unless a ban titles them
					$title = in_array($user->groupId(), array(null, ListedGroupInterface::UNVERIFIED), true) && $user->title() !== $banned
						? Html::format('<strong>%s</strong>', self::string($strings, 'Not verified'))
						: $this->formatter->memberTitle($user->username(), $user->title(), $user->postCount(), $user->groupId(), $user->groupTitle());

					$start = new ResultRowAssembling(SearchSelected::FIND_USER, ResultRowAssembling::START, $index + 1, self::style($index + 1), array(), user: $user);
					$this->events->dispatch($start);

					$rows[] = $this->row(new ResultRowAssembling(SearchSelected::FIND_USER, ResultRowAssembling::CELLS, $index + 1, $start->style(), $this->userCells($user, $title, $strings), user: $user), $start);
				}
			}
			else
				$rows[] = $this->emptyRow(SearchSelected::FIND_USER, array('username' => self::string($strings, 'No match'), 'title' => new Html(' - '), 'posts' => new Html(' - '), 'actions' => new Html(' - '), 'select' => new Html(' - ')));

			return array('main' => $this->usersMain($chrome, $table, $rows, 'aus-find-user-results-form', 'main-forum', $common, $strings));
		});
	}

	/**
	 * The head of a search's users, with selecting all of them when there are any.
	 *
	 * @param array<string, Html> $common
	 * @param array<string, Html> $strings
	 * @param array<string, Html> $misc
	 */
	private function usersTable(string $search, int $count, string $formId, array $common, array $strings, array $misc): ResultsTableAssembling {
		$headOptions = new Parts();
		$footOptions = new Parts();

		if ($count > 0)
		{
			$selectAll = Html::format('<span class="select-all js_link" data-check-form="%s">%s</span>', $formId, self::string($common, 'Select all'))->html;
			$headOptions->set('select', $selectAll);
			$footOptions->set('select', $selectAll);
		}

		$table = new ResultsTableAssembling($search, $count, self::header(array(
			'username'	=> self::string($strings, 'User information'),
			'title'		=> self::string($strings, 'Title column'),
			'posts'		=> self::string($strings, 'Posts'),
			'actions'	=> self::string($strings, 'Actions'),
			'select'	=> self::string($misc, 'Select'),
		)), $headOptions, $footOptions);
		$this->events->dispatch($table);

		return $table;
	}

	/**
	 * A search's users with the buttons changing those selected.
	 *
	 * @param list<array<string, mixed>> $rows
	 * @param array<string, Html> $common
	 * @param array<string, Html> $strings
	 */
	private function usersMain(ChromeInterface $chrome, ResultsTableAssembling $table, array $rows, string $formId, string $contentClass, array $common, array $strings): Html {
		$search = $table->search();
		$count = $table->count();

		$offered = array();
		if ($count > 0)
		{
			if ($this->visitor->isAdministrator() || ($this->visitor->can(GroupPermission::Moderate) && $this->visitor->can(GroupPermission::BanUsers)))
				$offered['ban'] = array('ban_users', self::string($strings, 'Ban'));

			if ($this->visitor->isAdministrator())
			{
				$offered['delete'] = array('delete_users', self::string($common, 'Delete'));
				$offered['change_group'] = array('change_group', self::string($strings, 'Change group'));
			}
		}

		$buttons = array();
		foreach ($offered as $key => [$name, $label])
			$buttons[$key] = Html::format('<span class="submit%s"><input type="submit" name="%s" value="%s" /></span>', new Html($buttons === array() ? ' first-item' : ''), $name, $label)->html;

		$assembling = new ModerationButtonsAssembling($search, $count, $buttons);
		$this->events->dispatch($assembling);

		$action = new Html($this->urls->link('admin_users')->html.'?action=modify_users');

		$body = $this->templates->render(self::USERS_TEMPLATE, array(
			'heading'		=> Html::format(self::string($strings, 'Users found'), $count),
			'headOptions'	=> self::options($table, ResultsTableAssembling::HEAD_OPTIONS),
			'footOptions'	=> self::options($table, ResultsTableAssembling::FOOT_OPTIONS),
			'formId'		=> $formId,
			'contentClass'	=> $contentClass,
			'action'		=> $action,
			'token'			=> $this->tokens->token($action->html),
			'header'		=> self::joined($table, ResultsTableAssembling::HEADER),
			'rows'			=> $rows,
			'beforeButtons'	=> new Html($assembling->markup()),
			'buttons'		=> $assembling->names() !== array() ? new Html(implode(' ', array_map(static fn (string $name): string => (string) $assembling->entry($name), $assembling->names()))) : null,
		));

		$chrome->inlineScript(self::SELECT_ALL_SCRIPT);

		$end = new ResultsEnding($search, $count);
		$this->events->dispatch($end);

		return (new Html($table->markup().$body.$end->markup()))->trim();
	}

	/**
	 * The cells of a user the search found.
	 *
	 * @param array<string, Html> $strings
	 * @return array<string, string>
	 */
	private function userCells(FoundUserInterface $user, Html $title, array $strings): array {
		$note = $user->adminNote() !== '' ? Html::format('<span class="usernote">%s %s</span>', self::string($strings, 'Admin note'), $user->adminNote()) : new Html('');

		return self::cells(array(
			'username'	=> Html::format('<span><a href="%s">%s</a></span><span class="usermail"><a href="mailto:%s">%s</a></span>%s', $this->urls->link('user', array($user->id())), $user->username(), $user->email(), $user->email(), $note),
			'title'		=> $title,
			'posts'		=> $this->formatter->number($user->postCount()),
			'actions'	=> Html::format('<span><a href="%s?ip_stats=%s">%s</a></span> <span><a href="%s">%s</a></span>', $this->urls->link('admin_users'), $user->id(), self::string($strings, 'View IP stats'),
				$this->urls->link('search_user_posts', array($user->id())), self::string($strings, 'Show posts')),
			'select'	=> Html::format('<input type="checkbox" name="users[%s]" value="1" />', $user->id()),
		));
	}

	/**
	 * A row once its observers built it, with what they added before it at either stage.
	 *
	 * @return array<string, mixed> what the template shows of the row
	 */
	private function row(ResultRowAssembling $cells, ResultRowAssembling $start): array {
		$this->events->dispatch($cells);

		return array('before' => new Html($start->markup().$cells->markup()), 'style' => $cells->style(), 'cells' => new Html(implode("\n\t\t\t\t", array_map(static fn (string $name): string => (string) $cells->entry($name), $cells->names()))));
	}

	/**
	 * The row saying a search found nothing.
	 *
	 * @param array<string, Html> $contents
	 * @return array<string, mixed>
	 */
	private function emptyRow(string $search, array $contents): array {
		$start = new ResultRowAssembling($search, ResultRowAssembling::EMPTY_START, 0, '', array());
		$this->events->dispatch($start);

		return $this->row(new ResultRowAssembling($search, ResultRowAssembling::EMPTY_CELLS, 0, 'odd row1', self::cells($contents)), $start);
	}

	/**
	 * Deleting the users selected, once the deletion is confirmed; the form confirming it until it is.
	 *
	 * @param array<string, Html> $common
	 * @param array<string, Html> $strings
	 */
	private function delete(Request $request, array $common, array $strings): Response {
		if (isset($request->post['delete_users_cancel']))
			return $this->redirects->respond($this->urls->link('admin_users')->html, self::string($common, 'Cancel redirect'), $request->xhr);

		if (!$this->visitor->isAdministrator())
			return $this->messages->respond($this->language->text('common', 'No permission'), json: $request->xhr);

		if (self::nothingSelected($request))
			return $this->messages->respond(self::string($strings, 'No users selected'), json: $request->xhr);

		$this->events->dispatch(new DeleteUsersStep(DeleteUsersStep::SELECTED));

		$ids = self::selected($request);
		if ($this->users->includesAdministrators(...$ids))
			return $this->messages->respond(self::string($strings, 'Delete admin message'), json: $request->xhr);

		if (isset($request->post['delete_users_comply']))
		{
			$withPosts = isset($request->post['delete_posts']);

			$this->events->dispatch(new DeleteUsersStep(DeleteUsersStep::CONFIRMED, $ids, $withPosts));

			foreach ($ids as $id)
				if ($id > 1)
					$this->removal->remove($id, $withPosts);

			$this->events->dispatch(new DeleteUsersStep(DeleteUsersStep::DELETED, $ids, $withPosts));

			return $this->redirects->respond($this->urls->link('admin_users')->html, self::string($strings, 'Users deleted'), $request->xhr);
		}

		return $this->actionForm(ActionFormRendering::DELETE, self::DELETE_TEMPLATE, $ids, 'delete', self::string($strings, 'Delete users'), $common, $strings, function (FormView $view): void {
			$view->numberGroup('group');
			$view->numberItem('item');
			$view->numberField('field');
		});
	}

	/**
	 * Banning the users selected, once the ban's form is submitted; the form until it is.
	 *
	 * @param array<string, Html> $common
	 * @param array<string, Html> $strings
	 * @param array<string, Html> $bans
	 */
	private function ban(Request $request, array $common, array $strings, array $bans): Response {
		if (!$this->visitor->isAdministrator() && (!$this->visitor->can(GroupPermission::Moderate) || !$this->visitor->can(GroupPermission::BanUsers)))
			return $this->messages->respond($this->language->text('common', 'No permission'), json: $request->xhr);

		if (self::nothingSelected($request))
			return $this->messages->respond(self::string($strings, 'No users selected'), json: $request->xhr);

		$this->events->dispatch(new BanUsersStep(BanUsersStep::SELECTED));

		$ids = self::selected($request);
		if ($this->users->includesAdministrators(...$ids))
			return $this->messages->respond(self::string($strings, 'Ban admin message'), json: $request->xhr);

		if (isset($request->post['ban_users_comply']))
		{
			$message = self::text($request->post['ban_message'] ?? null);
			$expiry = self::text($request->post['ban_expire'] ?? null);

			$this->events->dispatch(new BanUsersStep(BanUsersStep::SUBMITTED, $ids, $message, $expiry));

			$expire = null;
			if ($expiry !== '' && $expiry !== 'Never')
			{
				$expire = strtotime($expiry);
				if ($expire === false || $expire <= time())
					return $this->messages->respond(self::string($bans, 'Invalid expire message'), json: $request->xhr);
			}

			// A user is banned at the address of their latest post, or the one they registered from
			$addresses = array();
			foreach ($this->users->postAddresses(...$ids) as $address)
				$addresses[$address->userId()] = $address->address();

			$userBans = array();
			foreach ($this->users->banTargets(...$ids) as $target)
				$userBans[] = new UserBan($target->id(), $target->username(), ($addresses[$target->id()] ?? '') !== '' ? $addresses[$target->id()] : $target->registrationIp(),
					$target->email(), $message !== '' ? $message : null, $expire, $this->visitor->id());

			$this->users->ban(...$userBans);
			$this->bans->rebuild();

			$this->flash->info(self::string($strings, 'Users banned'));

			$this->events->dispatch(new BanUsersStep(BanUsersStep::BANNED, $ids, $message, $expiry, $expire));

			return $this->redirects->respond($this->urls->link('admin_users')->html, self::string($strings, 'Users banned'), $request->xhr);
		}

		return $this->actionForm(ActionFormRendering::BAN, self::BAN_TEMPLATE, $ids, 'ban', self::string($strings, 'Ban users'), $common, $strings, function (FormView $view) use ($bans): void {
			$view->numberGroup('group');
			$view->numberItem('message_item');
			$view->numberField('message_field');
			$view->numberItem('expire_item');
			$view->numberField('expire_field');
			$view->show('aba', $bans);
		});
	}

	/**
	 * Moving the users selected into the group submitted; the form choosing it until it is.
	 *
	 * @param array<string, Html> $common
	 * @param array<string, Html> $strings
	 */
	private function changeGroup(Request $request, array $common, array $strings): Response {
		if (!$this->visitor->isAdministrator())
			return $this->messages->respond($this->language->text('common', 'No permission'), json: $request->xhr);

		if (isset($request->post['change_group_cancel']))
			return $this->redirects->respond($this->urls->link('admin_users')->html, self::string($common, 'Cancel redirect'), $request->xhr);

		if (self::nothingSelected($request))
			return $this->messages->respond(self::string($strings, 'No users selected'), json: $request->xhr);

		$this->events->dispatch(new ChangeGroupStep(ChangeGroupStep::SELECTED));

		$ids = self::selected($request);

		if (isset($request->post['change_group_comply']))
		{
			$groupId = self::integer($request->post['move_to_group'] ?? 0);

			$this->events->dispatch(new ChangeGroupStep(ChangeGroupStep::SUBMITTED, $ids, $groupId));

			$moderates = $this->users->groupModerates($groupId);
			if ($groupId === ListedGroupInterface::GUESTS || $moderates === null)
				return $this->badRequest($request);

			$this->users->moveToGroup($groupId, ...$ids);

			// Moved out of a moderating group, they may still be listed as moderators of a forum
			if ($groupId !== ListedGroupInterface::ADMINISTRATORS && !$moderates)
				$this->moderators->clean();

			$this->events->dispatch(new ChangeGroupStep(ChangeGroupStep::CHANGED, $ids, $groupId));

			return $this->redirects->respond($this->urls->link('admin_users')->html, self::string($strings, 'User groups updated'), $request->xhr);
		}

		return $this->actionForm(ActionFormRendering::CHANGE_GROUP, self::CHANGE_GROUP_TEMPLATE, $ids, 'change_group', self::string($strings, 'Change group'), $common, $strings, function (FormView $view): void {
			$view->numberGroup('group');
			$view->numberItem('item');
			$view->numberField('field');

			$default = (int) $this->settings->value('o_default_user_group');
			$view->show('groups', array_map(static fn (ListedGroupInterface $group): array => array('id' => $group->id(), 'title' => $group->title(), 'default' => $group->id() === $default), $this->users->moveTargets()));
		});
	}

	/**
	 * A form asked for the users selected, which $fill numbers and completes once its start is placed.
	 *
	 * @param list<int> $ids
	 * @param array<string, Html> $common
	 * @param array<string, Html> $strings
	 * @param \Closure(FormView): void $fill
	 */
	private function actionForm(string $form, string $template, array $ids, string $view, Html $crumb, array $common, array $strings, \Closure $fill): Response {
		$action = new Html($this->urls->link('admin_users')->html.'?action=modify_users');

		$formView = new FormView(array(ActionFormRendering::OUTPUT_START, ActionFormRendering::END), array(
			'aus'		=> $strings,
			'common'	=> $common,
			'action'	=> $action,
			'token'		=> $this->tokens->token($action->html),
			'users'		=> implode(',', $ids),
		));

		$head = new PageHead('admin-users', $this->crumbs($common, $crumb), section: 'users', view: $view);

		return $this->pages->respond($head, function () use ($form, $template, $ids, $formView, $fill): array {
			$at = function (string $position) use ($form, $ids, $formView): Html {
				[$groups, $items, $fields] = $formView->counts();

				$event = new ActionFormRendering($form, $position, $ids, $groups, $items, $fields);
				$this->events->dispatch($event);

				return $formView->place($event);
			};

			$start = $at(ActionFormRendering::OUTPUT_START);
			$fill($formView);
			$body = $this->templates->render($template, $formView->variables());
			$end = $at(ActionFormRendering::END);

			return array('main' => (new Html($start->html.$body.$end->html))->trim());
		});
	}

	/**
	 * The forms searching the users by their details and by an address.
	 *
	 * @param array<string, Html> $common
	 * @param array<string, Html> $strings
	 */
	private function searchForm(array $common, array $strings): Response {
		$action = $this->urls->link('admin_users');

		$view = new FormView(UserSearchFormRendering::POSITIONS, array(
			'aus'			=> $strings,
			'action'		=> $action,
			'token'			=> $this->tokens->token($action->html.'?action=find_user'),
			'unverified'	=> ListedGroupInterface::UNVERIFIED,
		));

		return $this->pages->respond(new PageHead('admin-users', $this->crumbs($common), section: 'users'), function () use ($view, $strings): array {
			$at = function (string $position) use ($view): Html {
				[$groups, $items, $fields] = $view->counts();

				$event = new UserSearchFormRendering($position, $groups, $items, $fields);
				$this->events->dispatch($event);

				return $view->place($event);
			};

			$start = $at(UserSearchFormRendering::OUTPUT_START);

			$fieldsets = array();

			$fieldsets[] = $this->fieldset($at, $view, UserSearchFormRendering::PRE_USER_DETAILS_FIELDSET, 'Searches personal legend', UserSearchFormRendering::PRE_USER_DETAILS_FIELDSET_END, UserSearchFormRendering::USER_DETAILS_FIELDSET_END, $strings,
				fn (): array => $this->textFields($at, $view, self::DETAILS, $strings));

			$view->restartItems();
			$fieldsets[] = $this->fieldset($at, $view, UserSearchFormRendering::PRE_USER_CONTACTS_FIELDSET, 'Searches contact legend', UserSearchFormRendering::PRE_USER_CONTACTS_FIELDSET_END, UserSearchFormRendering::USER_CONTACTS_FIELDSET_END, $strings,
				fn (): array => $this->textFields($at, $view, self::CONTACTS, $strings));

			$view->restartItems();
			$fieldsets[] = $this->fieldset($at, $view, UserSearchFormRendering::PRE_USER_ACTIVITY_FIELDSET, 'Searches activity legend', UserSearchFormRendering::PRE_USER_ACTIVITY_FIELDSET_END, UserSearchFormRendering::USER_ACTIVITY_FIELDSET_END, $strings,
				function () use ($at, $view, $strings): array {
					$fields = array();
					foreach (self::ACTIVITY as $name => [$position, $label, $space])
					{
						$posts = str_starts_with($name, 'posts_');

						$fields[] = array(
							'pre'		=> $at($position),
							'item'		=> $view->numberItem($name.'_item'),
							'field'		=> $view->numberField($name.'_field'),
							'box'		=> $posts ? 'frm-short text' : 'text',
							'label'		=> Html::format('<span>%s</span>'.$space.'<small>%s</small>', self::string($strings, $label), self::string($strings, $posts ? 'Number of posts help' : 'Date format help')),
							'type'		=> $posts ? 'number' : 'text',
							'name'		=> $name,
							'size'		=> $posts ? 5 : 24,
							'maxlength'	=> $posts ? 8 : 19,
						);
					}

					return $fields;
				});

			$view->restartGroupsAndItems();
			$view->show('fieldsets', $fieldsets);

			$at(UserSearchFormRendering::PRE_RESULTS_FIELDSET);
			$view->numberGroup('results_group');

			$at(UserSearchFormRendering::PRE_SORT_BY);
			$view->numberItem('sort_by_item');
			$view->numberField('sort_by_field');
			$at(UserSearchFormRendering::NEW_SORT_BY_OPTION);

			$at(UserSearchFormRendering::PRE_SORT_ORDER);
			$view->numberItem('sort_order_item');
			$view->numberField('sort_order_field');

			$at(UserSearchFormRendering::PRE_FILTER_GROUP);
			$view->numberItem('filter_group_item');
			$view->numberField('filter_group_field');
			$view->show('groups', array_map(static fn (ListedGroupInterface $group): array => array('id' => $group->id(), 'title' => $group->title()), $this->users->searchGroups()));
			$at(UserSearchFormRendering::NEW_FILTER_GROUP_OPTION);

			$at(UserSearchFormRendering::PRE_RESULTS_FIELDSET_END);
			$at(UserSearchFormRendering::RESULTS_FIELDSET_END);

			$view->restartGroupsAndItems();

			$at(UserSearchFormRendering::PRE_IP_SEARCH_FIELDSET);
			$view->numberGroup('ip_group');

			$at(UserSearchFormRendering::PRE_IP_ADDRESS);
			$view->numberItem('ip_item');
			$view->numberField('ip_field');

			$at(UserSearchFormRendering::PRE_IP_SEARCH_FIELDSET_END);
			$at(UserSearchFormRendering::IP_SEARCH_FIELDSET_END);

			$body = $this->templates->render(self::SEARCH_TEMPLATE, $view->variables());

			$end = $at(UserSearchFormRendering::END);

			return array('main' => (new Html($start->html.$body.$end->html))->trim());
		});
	}

	/**
	 * A fieldset of the form finding users, its fields numbered by $fields once its start is placed.
	 *
	 * @param \Closure(string): Html $at
	 * @param array<string, Html> $strings
	 * @param \Closure(): list<array<string, mixed>> $fields
	 * @return array<string, mixed>
	 */
	private function fieldset(\Closure $at, FormView $view, string $pre, string $legend, string $preEnd, string $end, array $strings, \Closure $fields): array {
		$fieldset = array('pre' => $at($pre), 'group' => $view->numberGroup($legend), 'legend' => self::string($strings, $legend));
		$fieldset['fields'] = $fields();
		$fieldset['preEnd'] = $at($preEnd);
		$fieldset['end'] = $at($end);

		return $fieldset;
	}

	/**
	 * @param \Closure(string): Html $at
	 * @param array<string, array{string, string, int, int}> $fields
	 * @param array<string, Html> $strings
	 * @return list<array<string, mixed>>
	 */
	private function textFields(\Closure $at, FormView $view, array $fields, array $strings): array {
		$shown = array();
		foreach ($fields as $name => [$position, $label, $size, $length])
			$shown[] = array(
				'pre'		=> $at($position),
				'item'		=> $view->numberItem($name.'_item'),
				'field'		=> $view->numberField($name.'_field'),
				'box'		=> 'text',
				'label'		=> Html::format('<span>%s</span>', self::string($strings, $label)),
				'type'		=> 'text',
				'name'		=> 'form['.$name.']',
				'size'		=> $size,
				'maxlength'	=> $length,
			);

		return $shown;
	}

	/**
	 * The breadcrumbs of the users page: the users' section for an administrator, then the searches, then $last.
	 *
	 * @param array<string, Html> $common
	 * @return list<Crumb>
	 */
	private function crumbs(array $common, ?Html $last = null): array {
		$crumbs = array(
			new Crumb($this->settings->value('o_board_title'), $this->urls->link('index')),
			new Crumb(self::string($common, 'Forum administration')->html, $this->urls->link('admin_index')),
		);

		if ($this->visitor->isAdministrator())
			$crumbs[] = new Crumb(self::string($common, 'Users')->html, $this->urls->link('admin_users'));

		$crumbs[] = new Crumb(self::string($common, 'Searches')->html, $this->urls->link('admin_users'));

		if ($last !== null)
			$crumbs[] = new Crumb($last->html);

		return $crumbs;
	}

	private function badRequest(Request $request): Response {
		return $this->messages->respond($this->language->text('common', 'Bad request'), json: $request->xhr);
	}

	/**
	 * The header cells, each numbered by its column.
	 *
	 * @param array<string, Html> $labels
	 */
	private static function header(array $labels): Parts {
		$cells = array();
		foreach ($labels as $name => $label)
			$cells[$name] = Html::format('<th class="tc%s" scope="col">%s</th>', count($cells), $label)->html;

		return new Parts($cells);
	}

	/**
	 * A row's cells, each numbered by its column.
	 *
	 * @param array<string, Html> $contents
	 * @return array<string, string>
	 */
	private static function cells(array $contents): array {
		$cells = array();
		foreach ($contents as $name => $content)
			$cells[$name] = Html::format('<td class="tc%s">%s</td>', count($cells), $content)->html;

		return $cells;
	}

	/** The classes of row $number: odd or even, and the first row's. */
	private static function style(int $number): string {
		return ($number % 2 !== 0 ? 'odd' : 'even').($number === 1 ? ' row1' : '');
	}

	private static function joined(ResultsTableAssembling $table, string $group): Html {
		return new Html(implode("\n\t\t\t\t", array_map(static fn (string $name): string => (string) $table->entry($group, $name), $table->names($group))));
	}

	/** The options of $group, joined with spaces; null when there are none. */
	private static function options(ResultsTableAssembling $table, string $group): ?Html {
		$names = $table->names($group);

		return $names !== array() ? new Html(implode(' ', array_map(static fn (string $name): string => (string) $table->entry($group, $name), $names))) : null;
	}

	/** Whether the request selects no users, as empty() read it. */
	private static function nothingSelected(Request $request): bool {
		$users = $request->post['users'] ?? null;

		return $users === null || $users === '' || $users === '0' || $users === array();
	}

	/**
	 * The users the request selects: the keys of the checkboxes of a list, or the ids a form carries on.
	 *
	 * @return list<int>
	 */
	private static function selected(Request $request): array {
		$users = $request->post['users'] ?? array();

		return array_map(self::integer(...), is_array($users) ? array_keys($users) : explode(',', is_scalar($users) ? (string) $users : ''));
	}

	/** A value the request carries as text, trimmed; anything else is empty. */
	private static function text(mixed $value): string {
		return is_string($value) ? (new Html($value))->trim()->html : '';
	}

	/** A value the request carries, as intval() took it. */
	private static function integer(mixed $value): int {
		return is_scalar($value) ? intval($value) : (int) ($value !== array() && $value !== null);
	}

	/** @param array<string, Html> $strings */
	private static function string(array $strings, string $key): Html {
		return $strings[$key] ?? new Html('');
	}
}
