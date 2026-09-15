<?php

declare(strict_types=1);

namespace PunBB\Module\Settings\Controller;

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
use PunBB\Module\Message\Page\RedirectPage;
use PunBB\Module\Settings\Api\ConfigurationInterface;
use PunBB\Module\Settings\Event\SettingsFormRendering;
use PunBB\Module\Settings\Event\SettingsFormStep;
use PunBB\Module\Settings\Event\SettingsRequested;
use PunBB\Module\Settings\Event\SettingsSectionRequested;
use PunBB\Module\Settings\Model\Setting;
use PunBB\Module\Settings\Model\SubmittedSettings;
use PunBB\Module\Settings\View\FormView;
use PunBB\Module\Site\Cache\ConfigCacheInterface;
use PunBB\Module\Site\Cache\QuickjumpCacheInterface;
use PunBB\Module\Site\Config\PacksInterface;
use PunBB\Module\Site\Config\SettingsInterface;
use PunBB\Module\Site\Flash\FlashMessagesInterface;
use PunBB\Module\Site\Format\FormatterInterface;
use PunBB\Module\Site\Format\TimeFormat;
use PunBB\Module\Site\Format\TimeZones;
use PunBB\Module\Site\Language\LanguageInterface;
use PunBB\Module\Site\Mail\EmailAddressesInterface;
use PunBB\Module\Site\Security\CsrfTokensInterface;
use PunBB\Module\Site\Url\UrlsInterface;
use PunBB\Module\Site\Visitor\VisitorInterface;

/**
 * admin/settings.php, for administrators: a section's form, and the settings
 * it posts validated and stored.
 */
final class SettingsController implements ControllerInterface {
	private const TEMPLATES = __DIR__.'/../templates/';

	/** @var array<string, string> section => its crumb in the administration's language pack */
	private const CRUMBS = array(
		'setup'			=> 'Setup',
		'features'		=> 'Features',
		'email'			=> 'E-mail',
		'announcements'	=> 'Announcements',
		'registration'	=> 'Registration',
		'maintenance'	=> 'Maintenance mode',
	);

	/** @var list<string> the positions of the update checks, which the features section has when the board can reach another server */
	private const UPDATE_CHECKS = array('pre_updates_fieldset', 'pre_updates_checkbox', 'pre_version_updates_checkbox', 'pre_updates_fieldset_end', 'updates_fieldset_end');

	/** @var list<string> the position of the box saying the update checks cannot run */
	private const NO_UPDATE_CHECKS = array('post_updates_disabled_box');

	public function __construct(
		private readonly EventDispatcher $events,
		private readonly PageResponder $pages,
		private readonly TemplateRenderer $templates,
		private readonly MessagePage $messages,
		private readonly RedirectPage $redirects,
		private readonly ConfigurationInterface $configuration,
		private readonly ConfigCacheInterface $configCache,
		private readonly QuickjumpCacheInterface $quickjump,
		private readonly PacksInterface $packs,
		private readonly EmailAddressesInterface $addresses,
		private readonly VisitorInterface $visitor,
		private readonly LanguageInterface $language,
		private readonly SettingsInterface $settings,
		private readonly UrlsInterface $urls,
		private readonly FormatterInterface $formatter,
		private readonly CsrfTokensInterface $tokens,
		private readonly FlashMessagesInterface $flash
	) {}

	public function handle(Request $request): Response {
		$this->events->dispatch(new SettingsRequested());

		if (!$this->visitor->isAdministrator())
			return $this->messages->respond($this->language->text('common', 'No permission'), json: $request->xhr);

		$common = $this->language->strings('admin_common');
		$strings = $this->language->strings('admin_settings');

		// A section that is not text is a malformed request, not a section name
		$section = is_string($request->query['section'] ?? null) ? $request->query['section'] : '';

		if (isset($request->post['form_sent']))
			return $this->save($request, $section, $strings);

		if (in_array($section, array('', '0'), true))
			$section = 'setup';

		if (!isset(SettingsFormRendering::SECTIONS[$section]))
		{
			$this->events->dispatch(new SettingsSectionRequested($section));

			return $this->messages->respond($this->language->text('common', 'Bad request'), json: $request->xhr);
		}

		return $this->form($section, $common, $strings);
	}

	/**
	 * The settings a section's form posted, validated as the section requires and
	 * stored where they differ from what the board has.
	 *
	 * @param array<string, Html> $strings
	 */
	private function save(Request $request, string $section, array $strings): Response {
		$form = $request->post['form'] ?? null;
		if (!is_array($form))
			return $this->messages->respond($this->language->text('common', 'Bad request'), json: $request->xhr);

		$settings = SubmittedSettings::fromPost($form);

		$this->events->dispatch(new SettingsFormStep(SettingsFormStep::SUBMITTED, $section, $settings));
		$this->events->dispatch(new SettingsFormStep(SettingsFormStep::VALIDATING, $section, $settings));

		$refusal = match ($section) {
			'setup'	=> $this->validateSetup($settings, $strings),
			'email'	=> $this->validateEmail($settings, $strings),
			default	=> null,
		};

		if ($section === 'features')
			self::validateFeatures($settings);
		else if ($section === 'announcements')
			self::validateMessage($settings, 'announcement', self::string($strings, 'Announcement message default'));
		else if ($section === 'registration')
			self::validateRegistration($settings, $strings);
		else if ($section === 'maintenance')
			self::validateMessage($settings, 'maintenance', self::string($strings, 'Maintenance message default'));

		if ($refusal !== null)
			return $this->messages->respond($refusal, json: $request->xhr);

		$this->events->dispatch(new SettingsFormStep(SettingsFormStep::UPDATING, $section, $settings));

		$stored = $this->settings->all();
		foreach ($settings->names() as $name)
		{
			$input = $settings->value($name);

			// Only what changed is stored: a permission as the integer it holds, an empty option as NULL
			if (array_key_exists('p_'.$name, $stored) && $stored['p_'.$name] != $input)
				$this->configuration->updatePermissions(new Setting('p_'.$name, (string) intval($input)));

			if (array_key_exists('o_'.$name, $stored) && $stored['o_'.$name] != $input)
				$this->configuration->updateOptions(new Setting('o_'.$name, $input !== '' ? (string) $input : null));
		}

		$this->configCache->rebuild();

		// The jump list's links are written in the scheme, so a new scheme drops them
		$sef = $settings->value('sef');
		if (!self::isEmpty($stored['o_sef'] ?? '') && !self::isEmpty($sef) && $stored['o_sef'] != $sef)
			$this->quickjump->clear();

		$done = self::string($strings, 'Settings updated');
		$this->flash->info($done);

		$this->events->dispatch(new SettingsFormStep(SettingsFormStep::UPDATED, $section, $settings));

		// A section an extension added may have no address of its own
		$target = $this->urls->has('admin_settings_'.$section) ? 'admin_settings_'.$section : 'admin_settings_setup';

		return $this->redirects->respond($this->urls->link($target)->html, $done, $request->xhr);
	}

	/** @param array<string, Html> $strings */
	private function validateSetup(SubmittedSettings $form, array $strings): ?Html {
		if (($form->value('board_title') ?? '') == '')
			return self::string($strings, 'Error no board title');

		// A pack is named by its directory, so a name leaves no path behind
		foreach (array('default_style', 'default_lang', 'sef') as $name)
			$form->set($name, (string) preg_replace('#[\.\\\/]#', '', (string) ($form->value($name) ?? '')));

		if (!in_array($form->value('default_style'), $this->packs->styles(), true)
			|| !in_array($form->value('default_lang'), $this->packs->languages(), true)
			|| !in_array($form->value('sef'), $this->packs->urlSchemes(), true))
			return $this->language->text('common', 'Bad request');

		self::checkboxes($form, 'default_dst');

		foreach (array('timeout_visit', 'timeout_online', 'redirect_delay') as $name)
			$form->set($name, intval($form->value($name) ?? 0));

		if ($form->value('timeout_online') >= $form->value('timeout_visit'))
			return self::string($strings, 'Error timeout value');

		foreach (array('disp_topics_default', 'disp_posts_default') as $name)
			$form->set($name, intval($form->value($name) ?? 0) > 0 ? intval($form->value($name)) : 1);

		if (($form->value('additional_navlinks') ?? '') != '')
			$form->set('additional_navlinks', (new Html(self::linebreaks($form->value('additional_navlinks'))))->trim()->html);

		return null;
	}

	private static function validateFeatures(SubmittedSettings $form): void {
		self::checkboxes($form, 'search_all_forums', 'ranks', 'censoring', 'quickjump', 'show_version', 'show_moderators', 'users_online',
			'quickpost', 'subscriptions', 'force_guest_email', 'show_dot', 'topic_views', 'show_post_count', 'show_user_info',
			'message_bbcode', 'message_img_tag', 'smilies', 'make_links', 'message_all_caps', 'subject_all_caps');

		foreach (array('indent_num_spaces', 'quote_depth') as $name)
			$form->set($name, intval($form->value($name) ?? 0));

		self::checkboxes($form, 'signatures', 'sig_bbcode', 'sig_img_tag', 'smilies_sig', 'sig_all_caps');

		foreach (array('sig_length', 'sig_lines') as $name)
			$form->set($name, intval($form->value($name) ?? 0));

		self::checkboxes($form, 'avatars');

		// The avatars' directory is joined to a file name with a slash
		$directory = (string) ($form->value('avatars_dir') ?? '');
		if (str_ends_with($directory, '/'))
			$form->set('avatars_dir', substr($directory, 0, -1));

		foreach (array('avatars_width', 'avatars_height', 'avatars_size') as $name)
			$form->set($name, intval($form->value($name) ?? 0));

		self::checkboxes($form, 'check_for_updates', 'check_for_versions', 'mask_passwords', 'gzip');
	}

	/** @param array<string, Html> $strings */
	private function validateEmail(SubmittedSettings $form, array $strings): ?Html {
		foreach (array('admin_email' => 'Error invalid admin e-mail', 'webmaster_email' => 'Error invalid web e-mail') as $name => $error)
		{
			$form->set($name, strtolower((string) ($form->value($name) ?? '')));

			if (!$this->addresses->isValid((string) $form->value($name)))
				return self::string($strings, $error);
		}

		self::checkboxes($form, 'smtp_ssl');

		return null;
	}

	/** @param array<string, Html> $strings */
	private static function validateRegistration(SubmittedSettings $form, array $strings): void {
		self::checkboxes($form, 'regs_allow', 'regs_verify', 'allow_banned_email', 'allow_dupe_email', 'regs_report');

		self::validateMessage($form, 'rules', self::string($strings, 'Rules default'));
	}

	/** A switch and the message it shows: an empty message is the default one. */
	private static function validateMessage(SubmittedSettings $form, string $switch, Html $default): void {
		self::checkboxes($form, $switch);

		$name = $switch.'_message';
		$form->set($name, ($form->value($name) ?? '') != '' ? self::linebreaks($form->value($name)) : $default->html);
	}

	/**
	 * @param array<string, Html> $common
	 * @param array<string, Html> $strings
	 */
	private function form(string $section, array $common, array $strings): Response {
		$action = $this->urls->link('admin_settings_'.$section);

		$values = array(
			'aop'		=> $strings,
			'common'	=> $common,
			'action'	=> $action,
			'token'		=> $this->tokens->token($action->html),
			'config'	=> $this->settings->all(),
		);

		if ($section === 'setup')
			$values += $this->setupValues($strings);
		else if ($section === 'features')
			$values['checksUpdates'] = self::checksUpdates();

		$view = new FormView($section, $values);

		// Maintenance mode is managed, not set up, and has its crumb there
		$management = $section === 'maintenance';

		$crumbs = array(
			new Crumb($this->settings->value('o_board_title'), $this->urls->link('index')),
			new Crumb(self::string($common, 'Forum administration')->html, $this->urls->link('admin_index')),
			$management ? new Crumb(self::string($common, 'Management')->html, $this->urls->link('admin_reports')) : new Crumb(self::string($common, 'Settings')->html, $this->urls->link('admin_settings_setup')),
			new Crumb(self::string($common, self::CRUMBS[$section])->html, $action),
		);

		return $this->pages->respond(new PageHead('admin-settings-'.$section, $crumbs, section: $management ? 'management' : 'settings'), fn (): array => array('main' => $this->main($view, $section)));
	}

	private function main(FormView $view, string $section): Html {
		$at = function (string $position) use ($view, $section): Html {
			[$groups, $items, $fields] = $view->counts();

			$event = new SettingsFormRendering($section, $position, $groups, $items, $fields);
			$this->events->dispatch($event);

			return $view->place($event);
		};

		$skipped = array(SettingsFormRendering::OUTPUT_START, SettingsFormRendering::END);
		if ($section === 'features')
			$skipped = array_merge($skipped, self::checksUpdates() ? self::NO_UPDATE_CHECKS : self::UPDATE_CHECKS);

		$start = $at(SettingsFormRendering::OUTPUT_START);

		foreach (SettingsFormRendering::SECTIONS[$section] as $position)
			if (!in_array($position, $skipped, true))
				$at($position);

		$body = $this->templates->render(self::TEMPLATES.$section.'.phtml', $view->variables());

		$end = $at(SettingsFormRendering::END);

		return (new Html($start->html.$body.$end->html))->trim();
	}

	/**
	 * @param array<string, Html> $strings
	 * @return array<string, mixed> what the setup section shows besides the settings
	 */
	private function setupValues(array $strings): array {
		$profile = $this->language->strings('profile');
		$zone = $this->settings->value('o_default_timezone');

		$timezones = array();
		foreach (TimeZones::OFFSETS as $offset => $label)
			$timezones[] = array('name' => (string) $offset, 'label' => self::string($profile, $label), 'selected' => TimeZones::is($zone, (string) $offset));

		return array(
			'styles'			=> $this->options($this->packs->styles(), 'o_default_style', true),
			'languages'			=> $this->options($this->packs->languages(), 'o_default_lang', false),
			'schemes'			=> $this->options($this->packs->urlSchemes(), 'o_sef', true),
			'timezones'			=> $timezones,
			'timeFormatHelp'	=> Html::format(self::string($strings, 'Current format'), $this->formatter->now(TimeFormat::Time, $this->settings->value('o_time_format')), self::string($strings, 'External format help')),
			'dateFormatHelp'	=> Html::format(self::string($strings, 'Current format'), $this->formatter->now(TimeFormat::Date, $this->settings->value('o_date_format')), self::string($strings, 'External format help')),
		);
	}

	/**
	 * @param list<string> $packs
	 * @param bool $spaced whether a pack's name is shown with spaces for its underscores
	 * @return list<array{name: string, label: string, selected: bool}> the packs as options of a list, the one setting $setting names chosen
	 */
	private function options(array $packs, string $setting, bool $spaced): array {
		$chosen = $this->settings->value($setting);

		return array_map(static fn (string $pack): array => array('name' => $pack, 'label' => $spaced ? str_replace('_', ' ', $pack) : $pack, 'selected' => $pack === $chosen), $packs);
	}

	/** Each checkbox the form left unchecked, or checked with anything but 1, is '0'. */
	private static function checkboxes(SubmittedSettings $form, string ...$names): void {
		foreach ($names as $name)
			if (!$form->has($name) || $form->value($name) != '1')
				$form->set($name, '0');
	}

	/** Whether the board can reach another server to check for updates. */
	private static function checksUpdates(): bool {
		return function_exists('curl_init') || function_exists('stream_socket_client') || in_array(strtolower((string) ini_get('allow_url_fopen')), array('on', 'true', '1'), true);
	}

	/** Whether $value is what empty() takes for nothing. */
	private static function isEmpty(string|int|null $value): bool {
		return in_array($value, array(null, '', '0', 0), true);
	}

	private static function linebreaks(string|int|null $text): string {
		return str_replace(array("\r\n", "\r"), "\n", (string) $text);
	}

	/** @param array<string, Html> $strings */
	private static function string(array $strings, string $key): Html {
		return $strings[$key] ?? new Html('');
	}
}
