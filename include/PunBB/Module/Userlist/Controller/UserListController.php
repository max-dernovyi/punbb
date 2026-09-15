<?php

declare(strict_types=1);

namespace PunBB\Module\Userlist\Controller;

use PunBB\Module\Framework\Event\EventDispatcher;
use PunBB\Module\Framework\Http\Request;
use PunBB\Module\Framework\Http\Response;
use PunBB\Module\Framework\Routing\ControllerInterface;
use PunBB\Module\Layout\Chrome\Crumb;
use PunBB\Module\Layout\Chrome\PageHead;
use PunBB\Module\Layout\Page\PageResponder;
use PunBB\Module\Layout\View\Html;
use PunBB\Module\Layout\View\TemplateRenderer;
use PunBB\Module\Message\Page\MessagePage;
use PunBB\Module\Site\Config\SettingsInterface;
use PunBB\Module\Site\Format\FormatterInterface;
use PunBB\Module\Site\Format\TimeFormat;
use PunBB\Module\Site\Language\LanguageInterface;
use PunBB\Module\Site\Url\UrlsInterface;
use PunBB\Module\Site\Visitor\GroupPermission;
use PunBB\Module\Site\Visitor\VisitorInterface;
use PunBB\Module\Userlist\Api\Data\MemberSearchInterface;
use PunBB\Module\Userlist\Api\MemberDirectoryInterface;
use PunBB\Module\Userlist\Event\MemberRowAssembling;
use PunBB\Module\Userlist\Event\MemberRowStarting;
use PunBB\Module\Userlist\Event\MemberTableAssembling;
use PunBB\Module\Userlist\Event\UserListRendering;
use PunBB\Module\Userlist\Event\UserListRequested;
use PunBB\Module\Userlist\Model\MemberSearch;
use PunBB\Module\Userlist\View\Pagination;
use PunBB\Module\Userlist\View\UserListView;

/**
 * userlist.php: the registered members, searched by username and group, sorted,
 * fifty to a page.
 */
final class UserListController implements ControllerInterface {
	public const PER_PAGE = 50;

	private const TEMPLATE = __DIR__.'/../templates/userlist.phtml';

	public function __construct(
		private readonly EventDispatcher $events,
		private readonly PageResponder $pages,
		private readonly TemplateRenderer $templates,
		private readonly MessagePage $messages,
		private readonly MemberDirectoryInterface $members,
		private readonly VisitorInterface $visitor,
		private readonly LanguageInterface $language,
		private readonly SettingsInterface $settings,
		private readonly UrlsInterface $urls,
		private readonly FormatterInterface $formatter
	) {}

	public function handle(Request $request): Response {
		$this->events->dispatch(new UserListRequested());

		if (!$this->visitor->can(GroupPermission::ReadBoard))
			return $this->messages->respond($this->language->text('common', 'No view'), json: $request->xhr);

		if (!$this->visitor->can(GroupPermission::ViewUsers))
			return $this->messages->respond($this->language->text('common', 'No permission'), json: $request->xhr);

		$strings = $this->language->strings('userlist');
		$searchesUsernames = $this->visitor->can(GroupPermission::SearchUsers);
		$showsPostCount = $this->settings->enabled('o_show_post_count') || $this->visitor->isModerating();

		$search = MemberSearch::fromQuery($request->query, $searchesUsernames, $showsPostCount);
		$total = $this->members->count($search);
		$pagination = Pagination::of($total, $request->query['p'] ?? null, self::PER_PAGE);

		$searched = $search->username() !== '' || $search->groupId() > -1;
		$itemsInfo = $total > 0
			? $this->formatter->itemsInfo(self::string($strings, $searched ? 'Users found' : 'Users'), $pagination->offset + 1, $pagination->lastOnPage, $total, $pagination->pages)
			: self::string($strings, 'Users');

		$options = $request->query !== array()
			? array(Html::format('<span class="first-item"><a href="%s">%s</a></span>', $this->urls->link('users'), self::string($strings, 'Perform new search')))
			: array();

		$view = new UserListView($search, $searchesUsernames, $showsPostCount, $itemsInfo, $options, $this->urls->base().'/userlist.php', $strings);

		return $this->pages->respond($this->head($search, $pagination), fn (): array => array('main' => $this->main($view, $search, $pagination, $showsPostCount, $strings)));
	}

	private function head(MemberSearchInterface $search, Pagination $pagination): PageHead {
		$arguments = self::urlArguments($search);
		$page = $this->language->text('common', 'Page');

		$navigation = array();
		if ($pagination->page < $pagination->pages)
		{
			$navigation['last'] = Html::format('<link rel="last" href="%s" title="%s %s" />', $this->urls->sublink('users_browse', 'page', $pagination->pages, $arguments), $page, $pagination->pages);
			$navigation['next'] = Html::format('<link rel="next" href="%s" title="%s %s" />', $this->urls->sublink('users_browse', 'page', $pagination->page + 1, $arguments), $page, $pagination->page + 1);
		}

		if ($pagination->page > 1)
		{
			$navigation['prev'] = Html::format('<link rel="prev" href="%s" title="%s %s" />', $this->urls->sublink('users_browse', 'page', $pagination->page - 1, $arguments), $page, $pagination->page - 1);
			$navigation['first'] = Html::format('<link rel="first" href="%s" title="%s 1" />', $this->urls->link('users_browse', $arguments), $page);
		}

		return new PageHead('userlist',
			array(
				new Crumb($this->settings->value('o_board_title'), $this->urls->link('index')),
				new Crumb($this->language->text('common', 'User list')->html),
			),
			indexable: true,
			page: $pagination->page,
			pageCount: $pagination->pages > 1 ? Html::format($this->language->text('common', 'Page info'), $pagination->page, $pagination->pages) : null,
			pagePost: array('paging' => Html::format('<p class="paging"><span class="pages">%s</span> %s</p>',
				$this->language->text('common', 'Pages'), $this->urls->pagination($pagination->pages, $pagination->page, 'users_browse', $arguments))),
			navigation: $navigation
		);
	}

	/**
	 * The search form, the groups to narrow to and the members found, each read where the page reaches it.
	 *
	 * @param array<string, Html> $strings the userlist language pack
	 */
	private function main(UserListView $view, MemberSearchInterface $search, Pagination $pagination, bool $showsPostCount, array $strings): Html {
		$this->at($view, UserListRendering::MAIN_OUTPUT_START);
		$this->at($view, UserListRendering::SEARCH_FIELDSET_START);
		$view->numberGroup('group');

		$this->at($view, UserListRendering::PRE_USERNAME);
		if ($this->visitor->can(GroupPermission::SearchUsers))
		{
			$view->numberItem('username_item');
			$view->numberField('username_field');
		}

		$this->at($view, UserListRendering::PRE_GROUP_SELECT);
		$view->numberItem('group_item');
		$view->numberField('group_field');

		$this->at($view, UserListRendering::SEARCH_NEW_GROUP_OPTION);
		$view->listGroups($this->members->groups());

		$this->at($view, UserListRendering::PRE_SORT_BY);
		$view->numberItem('sort_item');
		$view->numberField('sort_field');

		$this->at($view, UserListRendering::NEW_SORT_BY_OPTION);
		$this->at($view, UserListRendering::PRE_SORT_ORDER_FIELDSET);
		$view->numberItem('order_item');

		$this->at($view, UserListRendering::PRE_SORT_ORDER);
		$view->numberField('ascending_field');
		$view->numberField('descending_field');

		$this->at($view, UserListRendering::PRE_SORT_ORDER_FIELDSET_END);
		$this->at($view, UserListRendering::PRE_SEARCH_FIELDSET_END);
		$this->at($view, UserListRendering::SEARCH_FIELDSET_END);

		$members = $this->members->find($search, $pagination->offset, self::PER_PAGE);

		if ($members !== array())
		{
			$this->at($view, UserListRendering::RESULTS_PRE_HEADER);

			$columns = array('username' => 'Username', 'title' => 'Title');
			if ($showsPostCount)
				$columns['posts'] = 'Posts';
			$columns['registered'] = 'Registered';

			$headers = array();
			foreach ($columns as $column => $label)
				$headers[$column] = Html::format('<th class="tc%s" scope="col">%s</th>', count($headers), self::string($strings, $label))->html;

			$table = new MemberTableAssembling($headers);
			$this->events->dispatch($table);
			$view->head(new Html($view->markup(UserListRendering::RESULTS_PRE_HEADER)->html.$table->markup()), self::cells($table));

			foreach ($members as $number => $member)
			{
				$starting = new MemberRowStarting($member);
				$this->events->dispatch($starting);

				$cells = array();
				$cells['username'] = Html::format('<td class="tc%s"><a href="%s">%s</a></td>', count($cells), $this->urls->link('user', array($member->id())), $member->username())->html;
				$cells['title'] = Html::format('<td class="tc%s">%s</td>', count($cells), $this->formatter->memberTitle($member->username(), $member->title(), $member->postCount(), $member->groupId(), $member->groupTitle()))->html;

				if ($showsPostCount)
					$cells['posts'] = Html::format('<td class="tc%s">%s</td>', count($cells), $this->formatter->number($member->postCount()))->html;

				$cells['registered'] = Html::format('<td class="tc%s">%s</td>', count($cells), $this->formatter->time($member->registered(), TimeFormat::Date))->html;

				$row = new MemberRowAssembling($member, $number + 1, $cells);
				$this->events->dispatch($row);

				$view->addRow(new Html($starting->markup().$row->markup()), $number + 1, self::cells($row));
			}
		}

		$body = $this->templates->render(self::TEMPLATE, $view->variables());

		$end = $view->rendering(UserListRendering::END);
		$this->events->dispatch($end);

		return (new Html($view->markup(UserListRendering::MAIN_OUTPUT_START)->html.$body.$end->markup()))->trim();
	}

	private function at(UserListView $view, string $position): void {
		$event = $view->rendering($position);
		$this->events->dispatch($event);
		$view->place($event);
	}

	/** The cells an event carries, in order, one to a line. */
	private static function cells(MemberTableAssembling|MemberRowAssembling $event): Html {
		$cells = array();
		foreach ($event->names() as $name)
			$cells[] = (string) $event->entry($name);

		return new Html(implode("\n\t\t\t\t\t\t", $cells));
	}

	/** @return list<int|string> what the list's URLs carry: the group, the sort, its direction and the username */
	private static function urlArguments(MemberSearchInterface $search): array {
		return array($search->groupId(), $search->sortBy(), $search->descending() ? 'DESC' : 'ASC', $search->username() !== '' ? urlencode($search->username()) : '-');
	}

	/** @param array<string, Html> $strings */
	private static function string(array $strings, string $key): Html {
		return $strings[$key] ?? new Html('');
	}
}
