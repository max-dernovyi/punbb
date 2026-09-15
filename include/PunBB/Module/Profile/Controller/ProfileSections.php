<?php

declare(strict_types=1);

namespace PunBB\Module\Profile\Controller;

use Closure;
use PunBB\Module\Framework\Event\EventDispatcher;
use PunBB\Module\Framework\Http\Request;
use PunBB\Module\Framework\Http\Response;
use PunBB\Module\Layout\Chrome\Crumb;
use PunBB\Module\Layout\Chrome\PageHead;
use PunBB\Module\Layout\Page\PageResponder;
use PunBB\Module\Layout\View\Html;
use PunBB\Module\Layout\View\Parts;
use PunBB\Module\Layout\View\TemplateRenderer;
use PunBB\Module\Message\Page\MessagePage;
use PunBB\Module\Profile\Api\Data\ProfileUserInterface;
use PunBB\Module\Profile\Api\ProfilesInterface;
use PunBB\Module\Profile\Event\ModeratorForumRendering;
use PunBB\Module\Profile\Event\ProfileDetailsSelected;
use PunBB\Module\Profile\Event\ProfileMenuAssembling;
use PunBB\Module\Profile\Event\ProfileRendering;
use PunBB\Module\Profile\Event\ProfileSectionRequested;
use PunBB\Module\Profile\Model\Moderator;
use PunBB\Module\Profile\View\FormView;
use PunBB\Module\Site\Config\PacksInterface;
use PunBB\Module\Site\Config\SettingsInterface;
use PunBB\Module\Site\Format\FormatterInterface;
use PunBB\Module\Site\Format\TimeFormat;
use PunBB\Module\Site\Format\TimeZones;
use PunBB\Module\Site\Language\LanguageInterface;
use PunBB\Module\Site\Security\CsrfTokensInterface;
use PunBB\Module\Site\Url\UrlsInterface;
use PunBB\Module\Site\Visitor\GroupPermission;
use PunBB\Module\Site\Visitor\VisitorInterface;

/**
 * The profile as it is shown: as a whole to a visitor who may not change it,
 * and section by section, with its menu, to one who may.
 */
final class ProfileSections {
	private const TEMPLATES = __DIR__.'/../templates/';

	/** @var array<string, string> section => the language pack's name for it */
	private const MENU = array(
		'about'		=> 'Section about',
		'identity'	=> 'Section identity',
		'settings'	=> 'Section settings',
		'signature'	=> 'Section signature',
		'avatar'	=> 'Section avatar',
		'admin'		=> 'Section admin',
	);

	public function __construct(
		private readonly EventDispatcher $events,
		private readonly PageResponder $pages,
		private readonly TemplateRenderer $templates,
		private readonly MessagePage $messages,
		private readonly ProfilesInterface $profiles,
		private readonly PacksInterface $packs,
		private readonly VisitorInterface $visitor,
		private readonly LanguageInterface $language,
		private readonly SettingsInterface $settings,
		private readonly UrlsInterface $urls,
		private readonly FormatterInterface $formatter,
		private readonly CsrfTokensInterface $tokens
	) {}

	/**
	 * @param array<string, Html> $strings
	 * @param ?Submission $submission the section's form, when it was saved and is shown again
	 */
	public function handle(Request $request, ProfileUserInterface $user, string $section, array $strings, ?Submission $submission): Response {
		$signature = $user->signature() !== '' ? $this->formatter->signature($user->signature()) : null;

		if (!ProfilePage::editable($this->visitor, $user))
			return $this->details($user, $strings, $signature);

		$menu = $this->menu($user, $section, $strings);

		return match (true) {
			$section === 'about'										=> $this->about($user, $strings, $signature, $menu),
			$section === 'identity'										=> $this->identity($request, $user, $strings, $menu, $submission),
			$section === 'settings'										=> $this->preferences($user, $strings, $menu),
			$section === 'signature' && $this->enabled('o_signatures')	=> $this->signature($request, $user, $strings, $signature, $menu, $submission),
			$section === 'avatar' && $this->enabled('o_avatars')		=> $this->avatar($user, $strings, $menu, $submission),
			$section === 'admin'										=> $this->administration($request, $user, $strings, $menu),
			default														=> $this->unknown($request, $user, $section),
		};
	}

	/**
	 * The profile as a whole, for a visitor who may not change it.
	 *
	 * @param array<string, Html> $strings
	 */
	private function details(ProfileUserInterface $user, array $strings, ?Html $signature): Response {
		$selected = new ProfileDetailsSelected(ProfileRendering::DETAILS, $user);
		$this->events->dispatch($selected);

		$ident = $this->ident($user, $selected);

		$info = new Parts();
		$this->addDetails($info, $user, $strings);
		$info->set('registered', $this->line($strings, 'Registered', ' '.$this->formatter->time($user->registered(), TimeFormat::Date)->html));
		$info->set('lastpost', $this->line($strings, 'Last post', ' '.$this->lastPost($user)));

		if ($this->enabled('o_show_post_count') || $this->visitor->isModerating())
			$info->set('posts', $this->line($strings, 'Posts', $this->formatter->number($user->posts())->html));

		$contact = new Parts();
		$mails = !$this->visitor->isGuest() && $this->visitor->can(GroupPermission::SendEmail);

		if ($user->emailSetting() === 0 && $mails)
			$contact->set('email', $this->emailLine($user, $strings));

		if ($user->emailSetting() !== 2 && $mails)
			$contact->set('forum-mail', $this->forumMailLine($user, $strings));

		$this->addWebsites($contact, $user, $strings);
		$this->addMessengers($contact, $user, $strings, false, ' ');

		$activity = null;
		if ($this->visitor->can(GroupPermission::Search))
		{
			$activity = new Parts();
			$activity->set('search_posts', Html::format('<li class="first-item"><a href="%s">%s</a></li>', $this->urls->link('search_user_posts', array($user->id())), Html::format(ProfilePage::string($strings, 'View user posts'), $user->username()))->html);
			$activity->set('search_topics', Html::format('<li><a href="%s">%s</a></li>', $this->urls->link('search_user_topics', array($user->id())), Html::format(ProfilePage::string($strings, 'View user topics'), $user->username()))->html);
		}

		$crumbs = array(
			new Crumb($this->settings->value('o_board_title'), $this->urls->link('index')),
			new Crumb(sprintf(ProfilePage::string($strings, 'Users profile')->html, $user->username())),
		);

		$values = $this->detailValues($user, $strings, 'Profile welcome', 'Profile welcome user');
		$shownSignature = $this->enabled('o_signatures') ? $signature : null;

		return $this->pages->respond(new PageHead('profile', $crumbs, indexable: true), fn (): array => array('main' => ProfilePage::render($this->events, $this->templates, ProfileRendering::DETAILS, self::TEMPLATES.'details.phtml', $user, array(), $values,
			function (FormView $view, Closure $at) use ($ident, $info, $contact, $activity, $shownSignature): void {
				$this->placeDetails($view, $at, $ident, $info, $contact, $activity ?? new Parts(), $shownSignature, "\n");
				$at('user_info_end');
			}
		)));
	}

	/**
	 * The introduction: what the profile shows, what the member and the staff
	 * see privately, and the links changing the password and address.
	 *
	 * @param array<string, Html> $strings
	 * @param array<string, Html> $menu
	 */
	private function about(ProfileUserInterface $user, array $strings, ?Html $signature, array $menu): Response {
		$own = $this->visitor->id() === $user->id();
		$staff = $this->visitor->isModerating();

		$selected = new ProfileDetailsSelected(ProfileRendering::ABOUT, $user);
		$this->events->dispatch($selected);

		$ident = $this->ident($user, $selected);

		$private = new Parts();
		$info = new Parts();
		$this->addDetails($info, $user, $strings);
		$info->set('registered', $this->line($strings, 'Registered', ' '.$this->formatter->time($user->registered(), TimeFormat::Date)->html));
		$info->set('lastvisit', $this->line($strings, 'Last visit', ' '.$this->formatter->time($user->lastVisit(), TimeFormat::DateTime)->html));
		$info->set('lastpost', $this->line($strings, 'Last post', ' '.$this->lastPost($user)));

		$posts = $this->line($strings, 'Posts', $this->formatter->number($user->posts())->html);
		if ($this->enabled('o_show_post_count') || $staff)
			$info->set('posts', $posts);
		else
			$private->set('posts', $posts);

		if ($staff && $user->adminNote() !== '')
			$private->set('note', Html::format('<li><span>%s: <strong>%s</strong></span></li>', ProfilePage::string($strings, 'Note'), $user->adminNote())->html);

		$contact = new Parts();

		if ($user->emailSetting() === 0 && !$this->visitor->isGuest() && $this->visitor->can(GroupPermission::SendEmail))
			$contact->set('email', $this->emailLine($user, $strings));
		else if ($own || $staff)
			$private->set('email', $this->emailLine($user, $strings));

		if ($user->emailSetting() !== 2)
			$contact->set('forum-mail', $this->forumMailLine($user, $strings));
		else if ($own || $staff)
			$private->set('forum-mail', $this->forumMailLine($user, $strings));

		$this->addWebsites($contact, $user, $strings);

		if ($staff)
			$private->set('ip', Html::format('<li><span>%s: <a href="%s">%s</a></span></li>', ProfilePage::string($strings, 'IP'), $this->urls->link('get_host', array(Html::escape($user->registrationIp())->html)), $user->registrationIp())->html);

		$this->addMessengers($contact, $user, $strings, true, '');

		$activity = new Parts();
		if ($this->visitor->can(GroupPermission::Search) || $staff)
		{
			$activity->set('search_posts', Html::format('<li class="first-item"><a href="%s">%s</a></li>', $this->urls->link('search_user_posts', array($user->id())), $own ? ProfilePage::string($strings, 'View your posts') : Html::format(ProfilePage::string($strings, 'View user posts'), $user->username()))->html);
			$activity->set('search_topics', Html::format('<li><a href="%s">%s</a></li>', $this->urls->link('search_user_topics', array($user->id())), $own ? ProfilePage::string($strings, 'View your topics') : Html::format(ProfilePage::string($strings, 'View user topics'), $user->username()))->html);
		}

		if (($own || $this->visitor->isAdministrator()) && $this->enabled('o_subscriptions'))
		{
			$activity->set('search_subs', Html::format('<li%s><a href="%s">%s</a></li>', new Html($activity->isEmpty() ? ' class="first-item"' : ''), $this->urls->link('search_subscriptions', array($user->id())), $own ? ProfilePage::string($strings, 'View your subscriptions') : Html::format(ProfilePage::string($strings, 'View user subscriptions'), $user->username()))->html);
			$activity->set('search_forum_subs', Html::format('<li%s><a href="%s">%s</a></li>', new Html($activity->isEmpty() ? ' class="first-item"' : ''), $this->urls->link('search_forum_subscriptions', array($user->id())), $own ? ProfilePage::string($strings, 'View your forum subscriptions') : Html::format(ProfilePage::string($strings, 'View user forum subscriptions'), $user->username()))->html);
		}

		$options = new Parts();
		if ($own || $this->visitor->isAdministrator() || ($this->visitor->can(GroupPermission::Moderate) && $this->visitor->can(GroupPermission::ChangePasswords)))
			$options->set('change_password', Html::format('<span%s><a href="%s">%s</a></span>', new Html($options->isEmpty() ? ' class="first-item"' : ''), $this->urls->link('change_password', array($user->id())), $own ? ProfilePage::string($strings, 'Change your password') : Html::format(ProfilePage::string($strings, 'Change user password'), $user->username()))->html);

		if (!$staff)
			$options->set('change_email', Html::format('<span%s><a href="%s">%s</a></span>', new Html($options->isEmpty() ? ' class="first-item"' : ''), $this->urls->link('change_email', array($user->id())), $own ? ProfilePage::string($strings, 'Change your e-mail') : Html::format(ProfilePage::string($strings, 'Change user e-mail'), $user->username()))->html);

		$crumbs = ProfilePage::crumbs($this->settings, $this->urls, $user, ProfilePage::string($strings, 'Users profile'));
		$crumbs[] = new Crumb(ProfilePage::string($strings, 'Section about')->html);

		$values = $this->detailValues($user, $strings, 'Profile welcome', 'Profile welcome user');
		$shownSignature = $this->enabled('o_signatures') ? $signature : null;

		return $this->pages->respond(new PageHead('profile-about', $crumbs, menu: $menu), fn (): array => array('main' => ProfilePage::render($this->events, $this->templates, ProfileRendering::ABOUT, self::TEMPLATES.'about.phtml', $user, array(ProfileRendering::USER_OPTIONS => $options), $values,
			function (FormView $view, Closure $at, ProfileRendering $start) use ($ident, $info, $contact, $activity, $shownSignature, $private): void {
				$view->show('options', ProfilePage::joined($start, ProfileRendering::USER_OPTIONS, ' '));

				$this->placeDetails($view, $at, $ident, $info, $contact, $activity, $shownSignature, '');

				$event = $at('pre_user_private_info', array(ProfileRendering::USER_PRIVATE => $private));
				$shown = ProfilePage::carries($event, ProfileRendering::USER_PRIVATE);
				$view->show('private', $shown ? ProfilePage::joined($event, ProfileRendering::USER_PRIVATE, "\n\t\t\t\t\t\t") : null);
				if ($shown)
					$view->numberItem('private');

				$at('user_info_end');
			}
		)));
	}

	/**
	 * The identity: the staff's fields, the member's details and contacts.
	 *
	 * @param array<string, Html> $strings
	 * @param array<string, Html> $menu
	 */
	private function identity(Request $request, ProfileUserInterface $user, array $strings, array $menu, ?Submission $submission): Response {
		$own = $this->visitor->id() === $user->id();
		$staff = $this->visitor->isModerating();
		$renames = $staff && ($this->visitor->isAdministrator() || $this->visitor->can(GroupPermission::RenameUsers));

		$crumbs = ProfilePage::crumbs($this->settings, $this->urls, $user, ProfilePage::string($strings, 'Users profile'));
		$crumbs[] = new Crumb(ProfilePage::string($strings, 'Section identity')->html);

		$action = $this->urls->link('profile_identity', array($user->id()));
		$hidden = ProfilePage::hiddenFields($this->tokens->token($action->html), $renames ? array('old_username' => Html::format('<input type="hidden" name="old_username" value="%s" />', $user->username())->html) : array());

		$post = $request->post;
		$fields = array();
		foreach (array('realname', 'location', 'url', 'facebook', 'twitter', 'linkedin', 'jabber', 'skype', 'msn', 'icq', 'aim', 'yahoo') as $name)
		{
			$submitted = $submission?->details->value($name);
			$fields[$name] = $submitted !== null ? (string) $submitted : self::column($user, $name);
		}

		$values = array(
			'profile'		=> $strings,
			'common'		=> $this->language->strings('common'),
			'heading'		=> Html::format(ProfilePage::string($strings, $own ? 'Identity welcome' : 'Identity welcome user'), $user->username()),
			'action'		=> $action,
			'hasRequired'	=> $staff,
			'renames'		=> $renames,
			'staff'			=> $staff,
			'administrator'	=> $this->visitor->isAdministrator(),
			'setsTitle'		=> $this->visitor->can(GroupPermission::SetTitle),
			'username'		=> ProfilePage::submitted($post['req_username'] ?? null) ?? $user->username(),
			'email'			=> ProfilePage::submitted($post['req_email'] ?? null) ?? $user->email(),
			'title'			=> ProfilePage::submitted($post['title'] ?? null) ?? $user->title(),
			'adminNote'		=> ProfilePage::submitted($post['admin_note'] ?? null) ?? $user->adminNote(),
			'posts'			=> $user->posts(),
			'fields'		=> $fields,
		);

		$errors = $submission !== null ? $submission->errors : array();

		return $this->pages->respond(new PageHead('profile-identity', $crumbs, menu: $menu), fn (): array => array('main' => ProfilePage::render($this->events, $this->templates, ProfileRendering::IDENTITY, self::TEMPLATES.'identity.phtml', $user, array(ProfileRendering::HIDDEN_FIELDS => $hidden), $values,
			function (FormView $view, Closure $at, ProfileRendering $start) use ($errors, $staff, $renames): void {
				$view->show('hidden', ProfilePage::joined($start, ProfileRendering::HIDDEN_FIELDS, "\n\t\t\t\t"));
				$view->show('errors', $errors !== array() ? ProfilePage::joined($at('pre_errors', array(ProfileRendering::ERRORS => ProfilePage::errorParts($errors))), ProfileRendering::ERRORS, "\n\t\t\t\t") : null);

				if ($staff)
				{
					$at('pre_req_info_fieldset');
					$view->numberGroup('required_group');

					$at('pre_username');
					if ($renames)
					{
						$view->numberItem('username_item');
						$view->numberField('username');
					}

					$at('pre_email');
					$view->numberItem('email_item');
					$view->numberField('email');

					$at('pre_req_info_fieldset_end');
					$at('req_info_fieldset_end');
				}

				$at('pre_personal_fieldset');
				$view->restartItems();
				$view->numberGroup('personal_group');

				foreach (array('realname' => true, 'title' => $this->visitor->can(GroupPermission::SetTitle), 'location' => true, 'admin_note' => $staff, 'num_posts' => $this->visitor->isAdministrator()) as $name => $shown)
				{
					$at('pre_'.$name);
					if ($shown)
					{
						$view->numberItem($name.'_item');
						$view->numberField($name);
					}
				}

				$at('pre_personal_fieldset_end');
				$at('personal_fieldset_end');
				$view->restartItems();

				$view->numberGroup('contact_group');
				foreach (array('url', 'facebook', 'twitter', 'linkedin') as $name)
				{
					$at('pre_'.$name);
					$view->numberItem($name.'_item');
					$view->numberField($name);
				}

				// The messengers number their items on from the contacts
				$view->numberGroup('messengers_group');
				foreach (array('jabber', 'skype', 'msn', 'icq', 'aim', 'yahoo') as $name)
				{
					$at('pre_'.$name);
					$view->numberItem($name.'_item');
					$view->numberField($name);
				}

				$at('pre_contact_fieldset_end');
				$at('contact_fieldset_end');
			}
		)));
	}

	/**
	 * The settings: language, time zone and formats, style and what posts show, pagination, mail and subscriptions.
	 *
	 * @param array<string, Html> $strings
	 * @param array<string, Html> $menu
	 */
	private function preferences(ProfileUserInterface $user, array $strings, array $menu): Response {
		$own = $this->visitor->id() === $user->id();

		$crumbs = ProfilePage::crumbs($this->settings, $this->urls, $user, ProfilePage::string($strings, 'Users profile'));
		$crumbs[] = new Crumb(ProfilePage::string($strings, 'Section settings')->html);

		$action = $this->urls->link('profile_settings', array($user->id()));

		$languages = $this->packs->languages();
		natcasesort($languages);

		$styles = $this->packs->styles();
		natcasesort($styles);

		$timezones = array();
		foreach (TimeZones::OFFSETS as $offset => $label)
			$timezones[] = array('name' => (string) $offset, 'label' => ProfilePage::string($strings, $label), 'selected' => TimeZones::is($user->timezone(), (string) $offset));

		$values = array(
			'profile'		=> $strings,
			'heading'		=> Html::format(ProfilePage::string($strings, $own ? 'Settings welcome' : 'Settings welcome user'), $user->username()),
			'action'		=> $action,
			'languages'		=> count($languages) > 1 ? array_map(static fn (string $language): array => array('name' => $language, 'selected' => $language === $user->language()), array_values($languages)) : null,
			'timezones'		=> $timezones,
			'timeFormats'	=> $this->formats($this->formatter->timeFormats(), TimeFormat::Time, $user->timeFormat()),
			'dateFormats'	=> $this->formats($this->formatter->dateFormats(), TimeFormat::Date, $user->dateFormat()),
			'onlyStyle'		=> count($styles) === 1 ? reset($styles) : null,
			'styles'		=> count($styles) > 1 ? array_map(static fn (string $style): array => array('name' => $style, 'label' => str_replace('_', ' ', $style), 'selected' => $style === $user->style()), array_values($styles)) : null,
			'options'		=> array(
				'smilies'			=> $this->enabled('o_smilies') || $this->enabled('o_smilies_sig'),
				'avatars'			=> $this->enabled('o_avatars'),
				'images'			=> $this->enabled('p_message_img_tag'),
				'signatureImages'	=> $this->enabled('o_signatures') && $this->enabled('p_sig_img_tag'),
				'signatures'		=> $this->enabled('o_signatures'),
				'subscriptions'		=> $this->enabled('o_subscriptions'),
			),
			'user'			=> array(
				'dst'				=> $user->daylightSaving(),
				'show_smilies'		=> $user->showsSmilies(),
				'show_avatars'		=> $user->showsAvatars(),
				'show_img'			=> $user->showsImages(),
				'show_img_sig'		=> $user->showsSignatureImages(),
				'show_sig'			=> $user->showsSignatures(),
				'disp_topics'		=> $user->topicsPerPage(),
				'disp_posts'		=> $user->postsPerPage(),
				'email_setting'		=> $user->emailSetting(),
				'notify_with_post'	=> $user->notifiesWithPost(),
				'auto_notify'		=> $user->subscribesAutomatically(),
			),
		);

		$hidden = ProfilePage::hiddenFields($this->tokens->token($action->html));
		$showsLanguages = $values['languages'] !== null;
		$showsStyles = $values['styles'] !== null;

		return $this->pages->respond(new PageHead('profile-settings', $crumbs, menu: $menu), fn (): array => array('main' => ProfilePage::render($this->events, $this->templates, ProfileRendering::SETTINGS, self::TEMPLATES.'settings.phtml', $user, array(ProfileRendering::HIDDEN_FIELDS => $hidden), $values,
			function (FormView $view, Closure $at, ProfileRendering $start) use ($showsLanguages, $showsStyles): void {
				$view->show('hidden', ProfilePage::joined($start, ProfileRendering::HIDDEN_FIELDS, "\n\t\t\t\t"));

				$at('pre_local_fieldset');
				$view->numberGroup('local_group');

				$at('pre_language');
				if ($showsLanguages)
				{
					$view->numberItem('language_item');
					$view->numberField('language');
				}

				foreach (array('timezone', 'dst' => 'dst_checkbox', 'time_format', 'date_format') as $name => $position)
				{
					$name = is_string($name) ? $name : $position;
					$at('pre_'.$position);
					$view->numberItem($name.'_item');
					$view->numberField($name);
				}

				$at('pre_local_fieldset_end');
				$at('local_fieldset_end');
				$view->restartItems();

				$at('pre_display_fieldset');
				$view->numberGroup('display_group');

				$at('pre_style');
				if ($showsStyles)
				{
					$view->numberItem('style_item');
					$view->numberField('style');
				}

				$at('pre_image_display_fieldset');
				$view->numberItem('image_item');
				foreach (array('show_smilies' => $this->enabled('o_smilies') || $this->enabled('o_smilies_sig'), 'show_avatars' => $this->enabled('o_avatars'), 'show_img' => $this->enabled('p_message_img_tag'), 'show_img_sig' => $this->enabled('o_signatures') && $this->enabled('p_sig_img_tag')) as $name => $shown)
					if ($shown)
						$view->numberField($name);

				$at('new_image_display_option');
				$at('pre_image_display_fieldset_end');

				$at('pre_show_sigs_checkbox');
				if ($this->enabled('o_signatures'))
				{
					$view->numberItem('show_sig_item');
					$view->numberField('show_sig');

					$at('pre_display_fieldset_end');
				}

				$at('display_fieldset_end');
				$view->restartItems();

				$at('pre_pagination_fieldset');
				$view->numberGroup('pagination_group');

				foreach (array('disp_topics', 'disp_posts') as $name)
				{
					$at('pre_'.$name);
					$view->numberItem($name.'_item');
					$view->numberField($name);
				}

				$at('pre_pagination_fieldset_end');
				$at('pagination_fieldset_end');
				$view->restartItems();

				$at('pre_email_fieldset');
				$view->numberGroup('email_group');

				$at('pre_email_settings_fieldset');
				$view->numberItem('email_setting_item');
				foreach (array(0, 1, 2) as $setting)
					$view->numberField('email_setting_'.$setting);

				$at('new_email_setting_option');
				$at('pre_email_settings_fieldset_end');
				$at('email_settings_fieldset_end');

				if ($this->enabled('o_subscriptions'))
				{
					$view->numberItem('subscription_item');
					$view->numberField('notify_with_post');
					$view->numberField('auto_notify');

					$at('new_subscription_option');
					$at('pre_subscription_fieldset_end');
					$at('subscription_fieldset_end');
				}

				$at('pre_email_fieldset_end');
				$view->restartItems();
				$at('email_fieldset_end');
			}
		)));
	}

	/**
	 * The signature, as the board shows it and as the member wrote it.
	 *
	 * @param array<string, Html> $strings
	 * @param array<string, Html> $menu
	 */
	private function signature(Request $request, ProfileUserInterface $user, array $strings, ?Html $signature, array $menu, ?Submission $submission): Response {
		$own = $this->visitor->id() === $user->id();
		$common = $this->language->strings('common');

		$crumbs = ProfilePage::crumbs($this->settings, $this->urls, $user, ProfilePage::string($strings, 'Users profile'));
		$crumbs[] = new Crumb(ProfilePage::string($strings, 'Section signature')->html);

		$action = $this->urls->link('profile_signature', array($user->id()));

		$options = new Parts();
		foreach (array('bbcode' => array('p_sig_bbcode', 'BBCode'), 'img' => array('p_sig_img_tag', 'Images'), 'smilies' => array('o_smilies_sig', 'Smilies')) as $name => [$setting, $label])
			if ($this->enabled($setting))
				$options->set($name, Html::format('<span%s><a class="exthelp" href="%s" title="%s">%s</a></span>', new Html($options->isEmpty() ? ' class="first-item"' : ''), $this->urls->link('help', array($name)), Html::format(ProfilePage::string($common, 'Help page'), ProfilePage::string($common, $label)), ProfilePage::string($common, $label))->html);

		$values = array(
			'profile'	=> $strings,
			'heading'	=> Html::format(ProfilePage::string($strings, $own ? 'Sig welcome' : 'Sig welcome user'), $user->username()),
			'action'	=> $action,
			'demo'		=> $signature,
			'maximum'	=> Html::format(ProfilePage::string($strings, 'Sig max size'), $this->formatter->number((int) $this->settings->value('p_sig_length')), $this->formatter->number((int) $this->settings->value('p_sig_lines'))),
			'signature'	=> ProfilePage::submitted($request->post['signature'] ?? null) ?? $user->signature(),
		);

		$parts = array(ProfileRendering::HIDDEN_FIELDS => ProfilePage::hiddenFields($this->tokens->token($action->html)), ProfileRendering::TEXT_OPTIONS => $options);
		$errors = $submission !== null ? $submission->errors : array();

		return $this->pages->respond(new PageHead('profile-signature', $crumbs, menu: $menu), fn (): array => array('main' => ProfilePage::render($this->events, $this->templates, ProfileRendering::SIGNATURE, self::TEMPLATES.'signature.phtml', $user, $parts, $values,
			function (FormView $view, Closure $at, ProfileRendering $start) use ($errors, $common, $signature): void {
				$view->show('hidden', ProfilePage::joined($start, ProfileRendering::HIDDEN_FIELDS, "\n\t\t\t\t"));
				$view->show('textOptions', ProfilePage::carries($start, ProfileRendering::TEXT_OPTIONS) ? Html::format(ProfilePage::string($common, 'You may use'), ProfilePage::joined($start, ProfileRendering::TEXT_OPTIONS, ' ')) : null);
				$view->show('errors', $errors !== array() ? ProfilePage::joined($at('pre_errors', array(ProfileRendering::ERRORS => ProfilePage::errorParts($errors))), ProfileRendering::ERRORS, "\n\t\t\t\t\t") : null);

				$at('pre_fieldset');
				$view->numberGroup('group');

				$at('pre_signature_demo');
				if ($signature !== null)
					$view->numberItem('demo_item');

				$at('pre_signature_text');
				$view->numberItem('signature_item');
				$view->numberField('signature');

				$at('pre_fieldset_end');
				$at('fieldset_end');
			}
		)));
	}

	/**
	 * The avatar: the one the member has, and the form uploading another.
	 *
	 * @param array<string, Html> $strings
	 * @param array<string, Html> $menu
	 */
	private function avatar(ProfileUserInterface $user, array $strings, array $menu, ?Submission $submission): Response {
		$own = $this->visitor->id() === $user->id();

		$crumbs = ProfilePage::crumbs($this->settings, $this->urls, $user, ProfilePage::string($strings, 'Users profile'));
		$crumbs[] = new Crumb(ProfilePage::string($strings, 'Section avatar')->html);

		$action = $this->urls->link('profile_avatar', array($user->id()));

		$markup = $this->formatter->avatar($user->id(), $user->avatarType(), $user->avatarWidth(), $user->avatarHeight(), $user->username(), true);
		$demo = $markup->html !== '' ? $markup : null;

		$width = $this->settings->value('o_avatars_width');
		$height = $this->settings->value('o_avatars_height');
		$size = (int) $this->settings->value('o_avatars_size');
		$sizeInfo = Html::format('<li><span>%s</span></li>', Html::format(ProfilePage::string($strings, 'Avatar info size'), $width, $height, $this->formatter->number($size), $this->formatter->number((int) ceil($size / 1024))))->html;

		$info = $demo !== null
			? new Parts(array('avatar_replace' => '<li><span>'.ProfilePage::string($strings, 'Avatar info replace')->html.'</span></li>', 'avatar_type' => '<li><span>'.ProfilePage::string($strings, 'Avatar info type')->html.'</span></li>', 'avatar_size' => $sizeInfo))
			: new Parts(array('avatar_none' => '<li><span>'.ProfilePage::string($strings, 'Avatar info none')->html.'</span></li>', 'avatar_info' => '<li><span>'.ProfilePage::string($strings, 'Avatar info type')->html.'</span></li>', 'avatar_size' => $sizeInfo));

		$values = array(
			'profile'		=> $strings,
			'heading'		=> Html::format(ProfilePage::string($strings, $own ? 'Avatar welcome' : 'Avatar welcome user'), $user->username()),
			'action'		=> $action,
			'demo'			=> $demo,
			'deleteLink'	=> $this->urls->link('delete_avatar', array($user->id(), $this->tokens->token('delete_avatar'.$user->id().$this->visitor->id()))),
		);

		$hidden = ProfilePage::hiddenFields($this->tokens->token($action->html));
		$hidden = new Parts(array('form_sent' => (string) $hidden->entry('form_sent'), 'max_file_size' => Html::format('<input type="hidden" name="MAX_FILE_SIZE" value="%s" />', $this->settings->value('o_avatars_size'))->html, 'csrf_token' => (string) $hidden->entry('csrf_token')));

		$errors = $submission !== null ? $submission->errors : array();

		return $this->pages->respond(new PageHead('profile-avatar', $crumbs, menu: $menu), fn (): array => array('main' => ProfilePage::render($this->events, $this->templates, ProfileRendering::AVATAR, self::TEMPLATES.'avatar.phtml', $user, array(ProfileRendering::HIDDEN_FIELDS => $hidden), $values,
			function (FormView $view, Closure $at, ProfileRendering $start) use ($errors, $info): void {
				$view->show('hidden', ProfilePage::joined($start, ProfileRendering::HIDDEN_FIELDS, "\n\t\t\t\t"));
				$view->show('errors', $errors !== array() ? ProfilePage::joined($at('pre_errors', array(ProfileRendering::ERRORS => ProfilePage::errorParts($errors))), ProfileRendering::ERRORS, "\n\t\t\t") : null);

				$view->show('info', ProfilePage::joined($at('pre_fieldset', array(ProfileRendering::INFO => $info)), ProfileRendering::INFO, "\n\t\t\t\t\t"));
				$view->numberGroup('group');

				$at('pre_cur_avatar_info');
				$view->numberItem('current_item');

				$at('pre_avatar_upload');
				$view->numberItem('upload_item');
				$view->numberField('upload');

				$at('pre_fieldset_end');
				$at('fieldset_end');
			}
		)));
	}

	/**
	 * The administration: banning and deleting the member, their group and the forums they moderate.
	 *
	 * @param array<string, Html> $strings
	 * @param array<string, Html> $menu
	 */
	private function administration(Request $request, ProfileUserInterface $user, array $strings, array $menu): Response {
		$own = $this->visitor->id() === $user->id();

		if (!$this->visitor->isAdministrator() && (!$this->visitor->can(GroupPermission::Moderate) || !$this->visitor->can(GroupPermission::BanUsers) || $own))
			return $this->messages->respond($this->language->text('common', 'Bad request'), json: $request->xhr);

		$crumbs = ProfilePage::crumbs($this->settings, $this->urls, $user, ProfilePage::string($strings, 'Users profile'));
		$crumbs[] = new Crumb(ProfilePage::string($strings, 'Section admin')->html);

		$action = $this->urls->link('profile_admin', array($user->id()));

		$values = array(
			'profile'	=> $strings,
			'action'	=> $action,
		);

		$hidden = ProfilePage::hiddenFields($this->tokens->token($action->html));

		return $this->pages->respond(new PageHead('profile-admin', $crumbs, menu: $menu), fn (): array => array('main' => ProfilePage::render($this->events, $this->templates, ProfileRendering::ADMIN, self::TEMPLATES.'admin.phtml', $user, array(ProfileRendering::HIDDEN_FIELDS => $hidden), $values,
			function (FormView $view, Closure $at, ProfileRendering $start) use ($user, $strings, $own): void {
				$view->show('hidden', ProfilePage::joined($start, ProfileRendering::HIDDEN_FIELDS, "\n\t\t\t\t"));
				$view->numberGroup('group');

				$event = $at('pre_user_management', array(ProfileRendering::USER_MANAGEMENT => $this->management($view, $user, $strings)));
				$management = ProfilePage::carries($event, ProfileRendering::USER_MANAGEMENT);
				$view->show('management', $management ? ProfilePage::joined($event, ProfileRendering::USER_MANAGEMENT, "\n\t\t\t") : null);

				$groups = null;
				if ($management)
				{
					$at('pre_membership');

					if (!$this->visitor->can(GroupPermission::Moderate) && !$own)
					{
						$at('pre_group_membership');
						$view->numberItem('group_item');
						$view->numberField('group_field');

						$groups = array();
						foreach ($this->profiles->groups() as $group)
							$groups[] = array('id' => $group->id(), 'title' => $group->title(), 'selected' => $group->id() === $user->groupId() || ($user->groupId() === null && (string) $group->id() === $this->settings->value('o_default_user_group')));

						$at('pre_group_membership_submit');
						$view->numberItem('group_submit_item');
					}
				}

				$view->show('groups', $groups);

				$at('pre_mod_assignment');

				$checklist = null;
				if ($this->visitor->isAdministrator() && ($user->isAdministrator() || $user->moderates()))
				{
					$at('pre_mod_assignment_fieldset');
					$view->numberItem('moderator_item');

					$at('pre_forum_checklist');
					$checklist = $this->checklist($view, $user);

					$at('pre_mod_assignment_fieldset_end');
					$at('mod_assignment_fieldset_end');
					$view->numberItem('moderator_submit_item');

					$at('form_end');
				}

				$view->show('checklist', $checklist);
			}
		)));
	}

	/**
	 * The links banning and deleting the member, each a box numbered among the section's items.
	 *
	 * @param array<string, Html> $strings
	 */
	private function management(FormView $view, ProfileUserInterface $user, array $strings): Parts {
		$box = fn (string $heading, Html $link, string $text): string => Html::format("<div class=\"ct-set set%s\">\n\t\t\t\t<div class=\"ct-box\">\n\t\t\t\t\t<h3 class=\"ct-legend hn\">%s</h3>\n\t\t\t\t<p><a href=\"%s\">%s</a></p>\n\t\t\t\t</div>\n\t\t\t</div>",
			$view->numberItem($heading), ProfilePage::string($strings, $heading), $link, ProfilePage::string($strings, $text))->html;

		$ban = new Html($this->urls->link('admin_bans')->html.'&amp;add_ban='.$user->id());

		$parts = new Parts();
		if ($this->visitor->can(GroupPermission::Moderate))
			$parts->set('ban', $box('Ban user', $ban, 'Ban user info'));
		else if (!$user->isAdministrator())
		{
			$parts->set('ban', $box('Ban user', $ban, 'Ban user info'));
			$parts->set('delete', $box('Delete user', $this->urls->link('delete_user', array($user->id())), 'Delete user info'));
		}

		return $parts;
	}

	/** The forums the member may moderate, by category, each checked where they do. */
	private function checklist(FormView $view, ProfileUserInterface $user): Html {
		$markup = '';
		$category = 0;

		foreach ($this->profiles->moderatableForums() as $forum)
		{
			[$groups, $items, $fields] = $view->counts();
			$start = new ModeratorForumRendering(ModeratorForumRendering::START, $user, $forum, $groups, $items, $fields);
			$this->events->dispatch($start);
			$view->recount($start);
			$markup .= $start->markup();

			if ($forum->categoryId() !== $category)
			{
				if ($category !== 0)
					$markup .= "\n\t\t\t\t\t\t".'</fieldset>'."\n";

				$markup .= Html::format("\t\t\t\t\t\t<fieldset>\n\t\t\t\t\t\t\t<legend><span>%s:</span></legend>\n", $forum->categoryName())->html;
				$category = $forum->categoryId();
			}

			$field = $view->numberField('forum_'.$forum->forumId());
			$markup .= Html::format("\t\t\t\t\t\t\t<div class=\"checklist-item\"><span class=\"fld-input\"><input type=\"checkbox\" id=\"fld%s\" name=\"moderator_in[%s]\" value=\"1\"%s /></span> <label for=\"fld%s\">%s</label></div>\n",
				$field, $forum->forumId(), new Html(in_array($user->id(), Moderator::stored($forum->moderators()), true) ? ' checked="checked"' : ''), $field, $forum->forumName())->html;

			[$groups, $items, $fields] = $view->counts();
			$end = new ModeratorForumRendering(ModeratorForumRendering::END, $user, $forum, $groups, $items, $fields);
			$this->events->dispatch($end);
			$view->recount($end);
			$markup .= $end->markup();
		}

		if ($category !== 0)
			$markup .= "\t\t\t\t\t\t".'</fieldset>'."\n";

		return new Html($markup);
	}

	/** A section the profile does not have: an observer may answer it, or the request is refused. */
	private function unknown(Request $request, ProfileUserInterface $user, string $section): Response {
		$this->events->dispatch(new ProfileSectionRequested($user, $section));

		return $this->messages->respond($this->language->text('common', 'Bad request'), json: $request->xhr);
	}

	/**
	 * The menu of the sections a visitor who may change the profile has.
	 *
	 * @param array<string, Html> $strings
	 * @return array<string, Html>
	 */
	private function menu(ProfileUserInterface $user, string $section, array $strings): array {
		$own = $this->visitor->id() === $user->id();

		$sections = array('about', 'identity', 'settings');
		if ($this->enabled('o_signatures'))
			$sections[] = 'signature';
		if ($this->enabled('o_avatars'))
			$sections[] = 'avatar';
		if ($this->visitor->isAdministrator() || ($this->visitor->can(GroupPermission::Moderate) && $this->visitor->can(GroupPermission::BanUsers) && !$own))
			$sections[] = 'admin';

		$entries = array();
		foreach ($sections as $name)
		{
			$class = $name === 'about' ? ' class="first-item'.($section === $name ? ' active' : '').'"' : ($section === $name ? ' class="active"' : '');
			$entries[$name] = Html::format('<li%s><a href="%s"><span>%s</span></a></li>', new Html($class), $this->urls->link('profile_'.$name, array($user->id())), ProfilePage::string($strings, self::MENU[$name]))->html;
		}

		$assembling = new ProfileMenuAssembling($user, $section, $own, $entries);
		$this->events->dispatch($assembling);

		$menu = array();
		foreach ($assembling->names() as $name)
			$menu[$name] = new Html((string) $assembling->entry($name));

		return $menu;
	}

	/** The lines identifying the member: what observers added, their name, avatar and title. */
	private function ident(ProfileUserInterface $user, ProfileDetailsSelected $selected): Parts {
		$ident = new Parts();
		foreach ($selected->names() as $name)
			$ident->set($name, (string) $selected->entry($name));

		$ident->set('username', Html::format('<li class="username%s"><strong>%s</strong></li>', new Html($user->realname() === '' ? ' fn nickname' : ' nickname'), $user->username())->html);

		if ($this->enabled('o_avatars'))
		{
			$avatar = $this->formatter->avatar($user->id(), $user->avatarType(), $user->avatarWidth(), $user->avatarHeight(), $user->username(), true);
			if ($avatar->html !== '')
				$ident->set('avatar', '<li class="useravatar">'.$avatar->html.'</li>');
		}

		$ident->set('usertitle', '<li class="usertitle"><span>'.$this->formatter->memberTitle($user->username(), $user->title(), $user->posts(), $user->groupId(), $user->groupTitle())->html.'</span></li>');

		return $ident;
	}

	/**
	 * The member's real name and location, censored where the board censors.
	 *
	 * @param array<string, Html> $strings
	 */
	private function addDetails(Parts $info, ProfileUserInterface $user, array $strings): void {
		if ($user->realname() !== '')
			$info->set('realname', Html::format('<li><span>%s: <strong class="fn">%s</strong></span></li>', ProfilePage::string($strings, 'Realname'), $this->censored($user->realname()))->html);

		if ($user->location() !== '')
			$info->set('location', Html::format('<li><span>%s: <strong> %s</strong></span></li>', ProfilePage::string($strings, 'From'), $this->censored($user->location()))->html);
	}

	/**
	 * The member's website and social profiles, censored where the board censors.
	 *
	 * @param array<string, Html> $strings
	 */
	private function addWebsites(Parts $contact, ProfileUserInterface $user, array $strings): void {
		if ($user->url() !== '')
		{
			$address = $this->urls->webAddress($user->url());
			$contact->set('website', Html::format('<li><span>%s: <a href="%s" class="external url" rel="me">%s</a></span></li>', ProfilePage::string($strings, 'Website'), $address->href, $this->censored($address->text))->html);
		}

		foreach (array('facebook' => array('Facebook', 'https://www.facebook.com/'), 'twitter' => array('Twitter', 'https://twitter.com/')) as $network => [$label, $site])
		{
			$name = $this->censored($network === 'facebook' ? $user->facebook() : $user->twitter());
			if ($name === '')
				continue;

			$url = str_starts_with($name, 'http://') || str_starts_with($name, 'https://') ? $name : $site.$name;
			$contact->set($network, Html::format('<li><span>%s: <a href="%s" class="external url">%s</a></span></li>', ProfilePage::string($strings, $label), $url, $url)->html);
		}

		if ($user->linkedin() !== '')
		{
			$url = $this->censored($user->linkedin());
			$contact->set('linkedin', Html::format('<li><span>%s: <a href="%s" class="external url" rel="me">%s</a></span></li>', ProfilePage::string($strings, 'LinkedIn'), $url, $url)->html);
		}
	}

	/**
	 * The member's messengers, censored where the board censors, but the ICQ number.
	 *
	 * @param array<string, Html> $strings
	 * @param string $space what goes before a value, as each page wrote it
	 */
	private function addMessengers(Parts $contact, ProfileUserInterface $user, array $strings, bool $withSkype, string $space): void {
		$messengers = array('jabber' => array('Jabber', $user->jabber(), true));
		if ($withSkype)
			$messengers['skype'] = array('Skype', $user->skype(), true);

		$messengers += array(
			'icq'	=> array('ICQ', $user->icq(), false),
			'msn'	=> array('MSN', $user->msn(), true),
			'aim'	=> array('AOL IM', $user->aim(), true),
			'yahoo'	=> array('Yahoo', $user->yahoo(), true),
		);

		foreach ($messengers as $name => [$label, $value, $censored])
			if ($value !== '')
				$contact->set($name, Html::format('<li><span>%s: <strong>%s%s</strong></span></li>', ProfilePage::string($strings, $label), $space, $censored ? $this->censored($value) : $value)->html);
	}

	/**
	 * Places what the profile shows position by position, as the page and the introduction share it.
	 *
	 * @param Closure(string, array<string, Parts>=): ProfileRendering $at
	 * @param string $signatureEnd what follows the signature inside its box
	 */
	private function placeDetails(FormView $view, Closure $at, Parts $ident, Parts $info, Parts $contact, Parts $activity, ?Html $signature, string $signatureEnd): void {
		$about = $signatureEnd === '';

		$at('pre_user_info');

		$event = $at('pre_user_ident_info', array(ProfileRendering::USER_IDENT => $ident, ProfileRendering::USER_INFO => $info));
		$view->show('ident', ProfilePage::joined($event, ProfileRendering::USER_IDENT, "\n\t\t\t\t\t\t"));
		$view->show('info', ProfilePage::joined($event, ProfileRendering::USER_INFO, "\n\t\t\t\t\t\t"));
		$view->numberItem('ident');

		$event = $at('pre_user_contact_info', array(ProfileRendering::USER_CONTACT => $contact));
		$contactShown = ProfilePage::carries($event, ProfileRendering::USER_CONTACT);
		$view->show('contact', $contactShown ? ProfilePage::joined($event, ProfileRendering::USER_CONTACT, "\n\t\t\t\t\t\t") : null);
		if ($contactShown)
			$view->numberItem('contact');

		// The introduction reaches the activity's position only past the contacts, and the signature's only past the activity
		$activityShown = !$activity->isEmpty();
		if (!$about || $contactShown)
		{
			$event = $at('pre_user_activity_info', array(ProfileRendering::USER_ACTIVITY => $activity));
			$activityShown = ProfilePage::carries($event, ProfileRendering::USER_ACTIVITY);
			$activity = new Parts();
			foreach ($event->names(ProfileRendering::USER_ACTIVITY) as $name)
				$activity->set($name, (string) $event->entry(ProfileRendering::USER_ACTIVITY, $name));
		}

		$view->show('activity', $activityShown ? new Html($activity->join("\n\t\t\t\t\t\t")) : null);
		if ($activityShown)
			$view->numberItem('activity');

		if (!$about || $activityShown)
			$at('pre_user_sig_info');

		$view->show('signature', $signature);
		if ($signature !== null)
			$view->numberItem('signature');
	}

	/**
	 * @param array<string, Html> $strings
	 * @return array<string, mixed>
	 */
	private function detailValues(ProfileUserInterface $user, array $strings, string $own, string $other): array {
		return array(
			'profile'	=> $strings,
			'heading'	=> Html::format(ProfilePage::string($strings, $this->visitor->id() === $user->id() ? $own : $other), $user->username()),
		);
	}

	/**
	 * @param array<int, string> $formats
	 * @return list<array{key: int, example: Html, selected: bool}> each format once, with the current moment in it
	 */
	private function formats(array $formats, TimeFormat $format, int $chosen): array {
		$options = array();
		foreach (array_unique($formats) as $key => $pattern)
			$options[] = array('key' => $key, 'example' => $this->formatter->now($format, $pattern), 'selected' => $key === $chosen);

		return $options;
	}

	/** @param array<string, Html> $strings */
	private function line(array $strings, string $label, string $value): string {
		return '<li><span>'.ProfilePage::string($strings, $label)->html.': <strong>'.$value.'</strong></span></li>';
	}

	/** @param array<string, Html> $strings */
	private function emailLine(ProfileUserInterface $user, array $strings): string {
		return Html::format('<li><span>%s: <a href="mailto:%s" class="email">%s</a></span></li>', ProfilePage::string($strings, 'E-mail'), $user->email(), $this->censored($user->email()))->html;
	}

	/** @param array<string, Html> $strings */
	private function forumMailLine(ProfileUserInterface $user, array $strings): string {
		return Html::format('<li><span>%s: <a href="%s">%s</a></span></li>', ProfilePage::string($strings, 'E-mail'), $this->urls->link('email', array($user->id())), ProfilePage::string($strings, 'Send forum e-mail'))->html;
	}

	private function lastPost(ProfileUserInterface $user): string {
		$posted = $user->lastPost();

		return $posted !== null ? $this->formatter->time($posted, TimeFormat::DateTime)->html : $this->language->text('common', 'Never')->html;
	}

	private function censored(string $text): string {
		return $this->enabled('o_censoring') ? $this->formatter->censor($text) : $text;
	}

	private function enabled(string $setting): bool {
		return $this->settings->value($setting) === '1';
	}

	/** The member's value of an identity field, as their profile stores it. */
	private static function column(ProfileUserInterface $user, string $name): string {
		return match ($name) {
			'realname'	=> $user->realname(),
			'location'	=> $user->location(),
			'url'		=> $user->url(),
			'facebook'	=> $user->facebook(),
			'twitter'	=> $user->twitter(),
			'linkedin'	=> $user->linkedin(),
			'jabber'	=> $user->jabber(),
			'skype'		=> $user->skype(),
			'msn'		=> $user->msn(),
			'icq'		=> $user->icq(),
			'aim'		=> $user->aim(),
			default		=> $user->yahoo(),
		};
	}
}
