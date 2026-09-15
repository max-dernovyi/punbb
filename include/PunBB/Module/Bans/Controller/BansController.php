<?php

declare(strict_types=1);

namespace PunBB\Module\Bans\Controller;

use PunBB\Module\Bans\Api\BanCandidatesInterface;
use PunBB\Module\Bans\Api\BansInterface;
use PunBB\Module\Bans\Api\Data\BanCandidateInterface;
use PunBB\Module\Bans\Api\Data\BanInterface;
use PunBB\Module\Bans\Event\BanAssembling;
use PunBB\Module\Bans\Event\BanChangeStep;
use PunBB\Module\Bans\Event\BanFormRendering;
use PunBB\Module\Bans\Event\BansRendering;
use PunBB\Module\Bans\Event\BansRequested;
use PunBB\Module\Bans\Event\BanTargetSelecting;
use PunBB\Module\Bans\Model\Ban;
use PunBB\Module\Bans\View\BanFormView;
use PunBB\Module\Framework\Event\EventDispatcher;
use PunBB\Module\Framework\Http\Request;
use PunBB\Module\Framework\Http\Response;
use PunBB\Module\Framework\Routing\ControllerInterface;
use PunBB\Module\Layout\Chrome\Crumb;
use PunBB\Module\Layout\Chrome\PageHead;
use PunBB\Module\Layout\Page\PageResponder;
use PunBB\Module\Layout\View\Html;
use PunBB\Module\Layout\View\TemplateRenderer;
use PunBB\Module\Message\Page\ConfirmPage;
use PunBB\Module\Message\Page\MessagePage;
use PunBB\Module\Message\Page\RedirectPage;
use PunBB\Module\Site\Cache\BanCacheInterface;
use PunBB\Module\Site\Config\SettingsInterface;
use PunBB\Module\Site\Flash\FlashMessagesInterface;
use PunBB\Module\Site\Format\FormatterInterface;
use PunBB\Module\Site\Format\TimeFormat;
use PunBB\Module\Site\Language\LanguageInterface;
use PunBB\Module\Site\Mail\EmailAddressesInterface;
use PunBB\Module\Site\Security\CsrfTokensInterface;
use PunBB\Module\Site\Url\UrlsInterface;
use PunBB\Module\Site\Visitor\GroupPermission;
use PunBB\Module\Site\Visitor\VisitorInterface;

/**
 * admin/bans.php: the bans, a page at a time, with the form starting a ban for
 * a username; the form adding a ban for a member or editing one; saving a ban
 * and removing one. For administrators, and moderators whose group may ban.
 */
final class BansController implements ControllerInterface {
	private const LIST = __DIR__.'/../templates/bans.phtml';

	private const FORM = __DIR__.'/../templates/form.phtml';

	/** A domain banning every address at it. */
	private const DOMAIN = '/^[a-z0-9-]+(\.[a-z0-9-]+)*(\.[a-z]{2,4})$/';

	public function __construct(
		private readonly EventDispatcher $events,
		private readonly PageResponder $pages,
		private readonly TemplateRenderer $templates,
		private readonly MessagePage $messages,
		private readonly RedirectPage $redirects,
		private readonly ConfirmPage $confirmations,
		private readonly BansInterface $bans,
		private readonly BanCandidatesInterface $candidates,
		private readonly BanCacheInterface $cache,
		private readonly VisitorInterface $visitor,
		private readonly LanguageInterface $language,
		private readonly SettingsInterface $settings,
		private readonly UrlsInterface $urls,
		private readonly FormatterInterface $formatter,
		private readonly CsrfTokensInterface $tokens,
		private readonly FlashMessagesInterface $flash,
		private readonly EmailAddressesInterface $addresses
	) {}

	public function handle(Request $request): Response {
		$this->events->dispatch(new BansRequested());

		if (!$this->visitor->isAdministrator() && (!$this->visitor->can(GroupPermission::Moderate) || !$this->visitor->can(GroupPermission::BanUsers)))
			return $this->messages->respond($this->language->text('common', 'No permission'), json: $request->xhr);

		$common = $this->language->strings('admin_common');
		$strings = $this->language->strings('admin_bans');

		if (isset($request->query['add_ban']) || isset($request->post['add_ban']))
			return $this->addForm($request, $common, $strings);

		if (isset($request->query['edit_ban']))
			return $this->editForm($request, $common, $strings);

		if (isset($request->post['add_edit_ban']))
			return $this->save($request, $strings);

		if (isset($request->query['del_ban']))
			return $this->remove($request, $strings);

		return $this->list($request, $common, $strings);
	}

	/**
	 * The form for a new ban, filled in from the member a link names by id or the list's form by username.
	 *
	 * @param array<string, Html> $common
	 * @param array<string, Html> $strings
	 */
	private function addForm(Request $request, array $common, array $strings): Response {
		if (isset($request->query['add_ban']))
		{
			$userId = self::integer($request->query['add_ban']);
			if ($userId < 2)
				return $this->messages->respond($this->language->text('common', 'Bad request'), json: $request->xhr);

			$this->events->dispatch(new BanTargetSelecting(BanTargetSelecting::USER, userId: $userId));

			$candidate = $this->candidates->byId($userId);
			if ($candidate === null)
				return $this->messages->respond(self::string($strings, 'No user id message'), json: $request->xhr);
		}
		else
		{
			$username = self::text($request->post['new_ban_user'] ?? null);

			$this->events->dispatch(new BanTargetSelecting(BanTargetSelecting::USERNAME, username: $username));

			$candidate = $username !== '' ? $this->candidates->byUsername($username) : null;
			if ($username !== '' && $candidate === null)
				return $this->messages->respond(self::string($strings, 'No user username message'), json: $request->xhr);
		}

		if ($candidate !== null && $candidate->isAdministrator())
			return $this->messages->respond(self::string($strings, 'User is admin message'), json: $request->xhr);

		return $this->form(true, 0, $candidate, $candidate !== null ? $this->banFor($candidate) : new Ban(0, '', null, null, null, null, 0), '', $common, $strings);
	}

	/** What a ban for $candidate is filled in with: their username, email, and the address they last posted from or registered from. */
	private function banFor(BanCandidateInterface $candidate): BanInterface {
		$lastIp = $this->candidates->lastKnownIp($candidate->id());

		return new Ban(0, $candidate->username(), $lastIp !== null && $lastIp !== '' ? $lastIp : $candidate->registrationIp(), $candidate->email(), null, null, 0);
	}

	/**
	 * @param array<string, Html> $common
	 * @param array<string, Html> $strings
	 */
	private function editForm(Request $request, array $common, array $strings): Response {
		$banId = self::integer($request->query['edit_ban']);
		if ($banId < 1)
			return $this->messages->respond($this->language->text('common', 'Bad request'), json: $request->xhr);

		$this->events->dispatch(new BanTargetSelecting(BanTargetSelecting::BAN, banId: $banId));

		$ban = $this->bans->find($banId);
		if ($ban === null)
			return $this->messages->respond($this->language->text('common', 'Bad request'), json: $request->xhr);

		// The expiry is a date, so it is shown in GMT
		return $this->form(false, $banId, null, $ban, $ban->expire() !== null ? gmdate('Y-m-d', $ban->expire()) : '', $common, $strings);
	}

	/**
	 * @param ?BanCandidateInterface $candidate the member the ban is for, when one was found
	 * @param array<string, Html> $common
	 * @param array<string, Html> $strings
	 */
	private function form(bool $adding, int $banId, ?BanCandidateInterface $candidate, BanInterface $ban, string $expire, array $common, array $strings): Response {
		$crumbs = $this->crumbs($common);
		$crumbs[] = new Crumb(self::string($strings, 'Ban advanced')->html);

		$action = $this->urls->link('admin_bans');
		$username = $ban->username() ?? '';

		$view = new BanFormView($adding, array(
			'aba'		=> $strings,
			'action'	=> $action,
			'token'		=> $this->tokens->token($action->html),
			'mode'		=> $adding ? 'add' : 'edit',
			'editing'	=> !$adding,
			'banId'		=> $banId,
			'username'	=> $username,
			'email'		=> strtolower($ban->email() ?? ''),
			'ip'		=> $ban->ip() ?? '',
			'message'	=> $ban->message() ?? '',
			'expire'	=> $expire,
			'stats'		=> $username !== '' && $candidate !== null
				? Html::format(' %s<a href="%s?ip_stats=%s">%s</a>', self::string($strings, 'IP-addresses help stats'), $this->urls->link('admin_users'), $candidate->id(), self::string($strings, 'IP-addresses help link'))
				: new Html(''),
		));

		return $this->pages->respond(new PageHead('admin-bans', $crumbs, section: 'users', view: 'form'), fn (): array => array('main' => $this->formMain($view)));
	}

	private function formMain(BanFormView $view): Html {
		$at = function (string $position) use ($view): void {
			$event = $view->rendering($position);
			$this->events->dispatch($event);
			$view->place($event);
		};

		$at(BanFormRendering::OUTPUT_START);
		$at(BanFormRendering::PRE_CRITERIA_FIELDSET);
		$view->numberGroup('group');

		foreach (array('username' => BanFormRendering::PRE_USERNAME, 'email' => BanFormRendering::PRE_EMAIL, 'ip' => BanFormRendering::PRE_IP, 'message' => BanFormRendering::PRE_MESSAGE, 'expire' => BanFormRendering::PRE_EXPIRE) as $name => $position)
		{
			$at($position);
			$view->numberItem($name.'_item');
			$view->numberField($name.'_field');
		}

		$at(BanFormRendering::CRITERIA_PRE_FIELDSET_END);
		$at(BanFormRendering::CRITERIA_FIELDSET_END);

		$body = $this->templates->render(self::FORM, $view->variables());

		$end = $view->rendering(BanFormRendering::END);
		$this->events->dispatch($end);

		return (new Html($view->markup(BanFormRendering::OUTPUT_START)->html.$body.$end->markup()))->trim();
	}

	/** @param array<string, Html> $strings */
	private function save(Request $request, array $strings): Response {
		$username = self::text($request->post['ban_user'] ?? null);
		$ip = self::text($request->post['ban_ip'] ?? null);
		$email = strtolower(self::text($request->post['ban_email'] ?? null));
		$message = self::text($request->post['ban_message'] ?? null);
		$expire = self::text($request->post['ban_expire'] ?? null);

		if ($username === '' && $ip === '' && $email === '')
			return $this->messages->respond(self::string($strings, 'Must enter message'), json: $request->xhr);

		if (strtolower($username) === 'guest')
			return $this->messages->respond(self::string($strings, 'Can\'t ban guest user'), json: $request->xhr);

		$mode = $request->post['mode'] ?? '';
		$adding = $mode === 'add';
		$banId = $adding ? 0 : self::integer($request->post['ban_id'] ?? 0);

		$this->events->dispatch(new BanChangeStep(BanChangeStep::SAVING, new Ban($banId, $username, $ip, $email, $message, null, $this->visitor->id()), $adding, $expire));

		if ($ip !== '')
		{
			$ip = self::addresses($ip);
			if ($ip === null)
				return $this->messages->respond(self::string($strings, 'Invalid IP message'), json: $request->xhr);
		}

		if ($email !== '' && !$this->addresses->isValid($email) && preg_match(self::DOMAIN, $email) !== 1)
			return $this->messages->respond(self::string($strings, 'Invalid e-mail message'), json: $request->xhr);

		$expiry = null;
		if ($expire !== '' && $expire !== 'Never')
		{
			$expiry = strtotime($expire);
			if ($expiry === false || $expiry <= time())
				return $this->messages->respond(self::string($strings, 'Invalid expire message'), json: $request->xhr);
		}

		$ban = new Ban($banId, self::stored($username), self::stored($ip), self::stored($email), self::stored($message), $expiry, $this->visitor->id());

		if ($adding)
			$this->bans->add($ban);
		else
			$this->bans->update($ban);

		$this->cache->rebuild();

		$done = self::string($strings, $mode === 'edit' ? 'Ban edited' : 'Ban added');
		$this->flash->info($done);

		$this->events->dispatch(new BanChangeStep(BanChangeStep::SAVED, $ban, $adding, $expire));

		return $this->redirects->respond($this->urls->link('admin_bans')->html, $done, $request->xhr);
	}

	/** @param array<string, Html> $strings */
	private function remove(Request $request, array $strings): Response {
		$banId = self::integer($request->query['del_ban']);
		if ($banId < 1)
			return $this->messages->respond($this->language->text('common', 'Bad request'), json: $request->xhr);

		// A token posted with the request was checked on the way in; one in the link is checked here
		if (!isset($request->post['csrf_token']) && !$this->tokens->matches($request->query['csrf_token'] ?? null, 'del_ban'.$banId.$this->visitor->id()))
		{
			$confirmation = $this->confirmations->respond($request->post, $request->xhr);
			if ($confirmation !== null)
				return $confirmation;
		}

		$ban = new Ban($banId, null, null, null, null, null, 0);

		$this->events->dispatch(new BanChangeStep(BanChangeStep::REMOVING, $ban));

		$this->bans->remove($banId);
		$this->cache->rebuild();
		$this->flash->info(self::string($strings, 'Ban removed'));

		$this->events->dispatch(new BanChangeStep(BanChangeStep::REMOVED, $ban));

		return $this->redirects->respond($this->urls->link('admin_bans')->html, self::string($strings, 'Ban removed'), $request->xhr);
	}

	/**
	 * The bans, a page at a time, below the form starting a ban for a username.
	 *
	 * @param array<string, Html> $common
	 * @param array<string, Html> $strings
	 */
	private function list(Request $request, array $common, array $strings): Response {
		$count = $this->bans->count();
		$perPage = max(1, $this->visitor->topicsPerPage());
		$pages = (int) ceil($count / $perPage);

		$requested = $request->query['p'] ?? null;
		$page = !is_numeric($requested) || $requested <= 1 || $requested > $pages ? 1 : (int) $requested;

		$navigation = array();
		if ($page < $pages)
		{
			$navigation['last'] = Html::format('<link rel="last" href="%s" title="%s %s" />', $this->urls->sublink('admin_bans', 'page', $pages), $this->language->text('common', 'Page'), $pages);
			$navigation['next'] = Html::format('<link rel="next" href="%s" title="%s %s" />', $this->urls->sublink('admin_bans', 'page', $page + 1), $this->language->text('common', 'Page'), $page + 1);
		}

		if ($page > 1)
		{
			$navigation['prev'] = Html::format('<link rel="prev" href="%s" title="%s %s" />', $this->urls->sublink('admin_bans', 'page', $page - 1), $this->language->text('common', 'Page'), $page - 1);
			$navigation['first'] = Html::format('<link rel="first" href="%s" title="%s 1" />', $this->urls->link('admin_bans'), $this->language->text('common', 'Page'));
		}

		$head = new PageHead('admin-bans', $this->crumbs($common), section: 'users', page: $page,
			pagePost: array('paging' => Html::format('<p class="paging"><span class="pages">%s</span> %s</p>', $this->language->text('common', 'Pages'),
				$this->urls->pagination($pages, $page, 'admin_bans', pageInQuery: true))),
			navigation: $navigation);

		// The administration's lists are not rewritten: the action is a query string on the list's own
		$action = new Html($this->urls->link('admin_bans')->html.'&amp;action=more');

		return $this->pages->respond($head, fn (): array => array('main' => $this->listMain($action, $count, $perPage * ($page - 1), min($perPage, $count), $common, $strings)));
	}

	/**
	 * @param array<string, Html> $common
	 * @param array<string, Html> $strings
	 */
	private function listMain(Html $action, int $count, int $offset, int $limit, array $common, array $strings): Html {
		$start = new BansRendering(BansRendering::MAIN_OUTPUT_START, $action->html, array(
			'csrf_token'	=> Html::format('<input type="hidden" name="csrf_token" value="%s" />', $this->tokens->token($action->html))->html,
		));
		$this->events->dispatch($start);

		$hidden = array();
		foreach ($start->names() as $name)
			$hidden[] = (string) $start->entry($name);

		$bans = array();
		if ($count > 0)
			foreach ($this->bans->page($offset, $limit) as $key => $ban)
				$bans[] = $this->block($ban, $key + 1, $common, $strings);

		$body = $this->templates->render(self::LIST, array(
			'aba'		=> $strings,
			'action'	=> $action,
			'hidden'	=> new Html(implode("\n\t\t\t\t", $hidden)),
			'group'		=> $start->groupCount() + 1,
			'item'		=> $start->itemCount() + 1,
			'field'		=> $start->fieldCount() + 1,
			'listed'	=> $count > 0,
			'bans'		=> $bans,
		));

		$end = new BansRendering(BansRendering::END, $action->html, array());
		$this->events->dispatch($end);

		return (new Html($start->markup().$body.$end->markup()))->trim();
	}

	/**
	 * A ban's block: who created it, the links editing and removing it, and what it bans.
	 *
	 * @param array<string, Html> $common
	 * @param array<string, Html> $strings
	 * @return array<string, mixed>
	 */
	private function block(BanInterface $ban, int $number, array $common, array $strings): array {
		$lines = array();
		foreach (array('username' => 'Username', 'email' => 'E-mail', 'ip' => 'IP-ranges') as $name => $label)
		{
			$value = match ($name) {
				'username'	=> $ban->username(),
				'email'		=> $ban->email(),
				default		=> $ban->ip(),
			};

			if ($value !== null && $value !== '')
				$lines[$name] = Html::format('<li><span>%s</span> <strong>%s</strong></li>', self::string($strings, $label), $value)->html;
		}

		if ($ban->expire() !== null)
			$lines['expire'] = Html::format('<li><span>%s</span> <strong>%s</strong></li>', self::string($strings, 'Expires'), $this->formatter->time($ban->expire(), TimeFormat::Date))->html;

		if ($ban->message() !== null && $ban->message() !== '')
			$lines['message'] = Html::format('<li><span>%s</span> <strong>%s</strong></li>', self::string($strings, 'Message'), $ban->message())->html;

		$creator = $ban->creatorName() !== null && $ban->creatorName() !== ''
			? Html::format('<a href="%s">%s</a>', $this->urls->link('user', array($ban->creatorId())), $ban->creatorName())->html
			: self::string($common, 'Unknown')->html;

		$assembling = new BanAssembling($ban, $number, $lines, $creator);
		$this->events->dispatch($assembling);

		$info = array();
		foreach ($assembling->names() as $name)
			$info[] = (string) $assembling->entry($name);

		$list = $this->urls->link('admin_bans')->html;

		return array(
			'before'	=> new Html($assembling->markup()),
			'number'	=> $number,
			'head'		=> Html::format(self::string($strings, 'Current ban head'), new Html($assembling->creator())),
			'links'		=> Html::format(self::string($strings, 'Edit or remove'),
				Html::format('<a href="%s&amp;edit_ban=%s">%s</a>', new Html($list), $ban->id(), self::string($strings, 'Edit ban')),
				Html::format('<a href="%s&amp;del_ban=%s&amp;csrf_token=%s">%s</a>', new Html($list), $ban->id(), $this->tokens->token('del_ban'.$ban->id().$this->visitor->id()), self::string($strings, 'Remove ban'))),
			'info'		=> $info !== array() ? new Html(implode("\n", $info)) : null,
		);
	}

	/**
	 * @param array<string, Html> $common
	 * @return list<Crumb>
	 */
	private function crumbs(array $common): array {
		$crumbs = array(
			new Crumb($this->settings->value('o_board_title'), $this->urls->link('index')),
			new Crumb(self::string($common, 'Forum administration')->html, $this->urls->link('admin_index')),
		);
		if ($this->visitor->isAdministrator())
			$crumbs[] = new Crumb(self::string($common, 'Users')->html, $this->urls->link('admin_users'));
		$crumbs[] = new Crumb(self::string($common, 'Bans')->html, $this->urls->link('admin_bans'));

		return $crumbs;
	}

	/**
	 * $ip as addresses and ranges separated by one space each, their leading
	 * zeros dropped; null when one is not an IPv4 or IPv6 address or range.
	 */
	private static function addresses(string $ip): ?string {
		$addresses = array_map(trim(...), explode(' ', (string) preg_replace('/[\s]{2,}/', ' ', $ip)));

		foreach ($addresses as $key => $address)
		{
			$ipv6 = str_contains($address, ':');
			$octets = explode($ipv6 ? ':' : '.', $address);

			foreach ($octets as $position => $octet)
			{
				if ($ipv6)
				{
					$octets[$position] = $octet = ltrim($octet, '0');
					if ($position > 7 || ($octet !== '' && !ctype_xdigit($octet)) || intval($octet, 16) > 65535)
						return null;
				}
				else
				{
					$octets[$position] = $octet = strlen($octet) > 1 ? ltrim($octet, '0') : $octet;
					if ($position > 3 || !ctype_digit($octet) || intval($octet) > 255)
						return null;
				}
			}

			$addresses[$key] = implode($ipv6 ? ':' : '.', $octets);
		}

		return implode(' ', $addresses);
	}

	/** $value as the ban stores it: nothing for an empty one. */
	private static function stored(?string $value): ?string {
		return $value !== null && $value !== '' ? $value : null;
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
