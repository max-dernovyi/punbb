<?php

declare(strict_types=1);

namespace PunBB\Module\Profile\Controller;

use PunBB\Module\Framework\Event\EventDispatcher;
use PunBB\Module\Framework\Http\Request;
use PunBB\Module\Framework\Http\Response;
use PunBB\Module\Layout\View\Html;
use PunBB\Module\Message\Page\MessagePage;
use PunBB\Module\Message\Page\RedirectPage;
use PunBB\Module\Profile\Api\Data\ProfileUserInterface;
use PunBB\Module\Profile\Api\Data\SubmittedDetailsInterface;
use PunBB\Module\Profile\Api\ProfilesInterface;
use PunBB\Module\Profile\Event\DetailsUpdateStep;
use PunBB\Module\Profile\Model\Details;
use PunBB\Module\Profile\Model\ForumModerators;
use PunBB\Module\Profile\Model\Moderator;
use PunBB\Module\Profile\Model\Rename;
use PunBB\Module\Profile\Model\SubmittedDetails;
use PunBB\Module\Site\Account\UsernameRulesInterface;
use PunBB\Module\Site\Cache\BanCacheInterface;
use PunBB\Module\Site\Config\PacksInterface;
use PunBB\Module\Site\Config\SettingsInterface;
use PunBB\Module\Site\Flash\FlashMessagesInterface;
use PunBB\Module\Site\Format\FormatterInterface;
use PunBB\Module\Site\Language\LanguageInterface;
use PunBB\Module\Site\Mail\EmailAddressesInterface;
use PunBB\Module\Site\Posting\PostRulesInterface;
use PunBB\Module\Site\Url\UrlsInterface;
use PunBB\Module\Site\Visitor\GroupPermission;
use PunBB\Module\Site\Visitor\VisitorInterface;

/**
 * A section's form of a profile saved: its fields checked as the section
 * requires, and stored with the member's new name put everywhere the board
 * wrote the old one. The avatar's form stores its upload instead.
 */
final class DetailsUpdate {
	/** @var list<string> the identity's text fields, which the form posts under form[] */
	private const IDENTITY_FIELDS = array('realname', 'url', 'location', 'jabber', 'icq', 'msn', 'aim', 'yahoo', 'facebook', 'twitter', 'linkedin', 'skype');

	/** @var list<string> */
	private const SETTINGS_FIELDS = array('dst', 'timezone', 'language', 'email_setting', 'notify_with_post', 'auto_notify', 'time_format', 'date_format', 'disp_topics', 'disp_posts', 'show_smilies', 'show_img', 'show_img_sig', 'show_avatars', 'show_sig', 'style');

	/** A name the path characters are stripped from, as a pack's directory is named. */
	private const PATH_CHARACTERS = '#[\.\\\/]#';

	/** The separators a word starts after, as ucwords() takes them. */
	private const WORD = '/(^|([\x0c\x09\x0b\x0a\x0d\x20]+))([^\x0c\x09\x0b\x0a\x0d\x20]{1})[^\x0c\x09\x0b\x0a\x0d\x20]*/u';

	/** The fewest and most topics or posts a member may have a page show. */
	private const PER_PAGE = array(3, 75);

	public function __construct(
		private readonly EventDispatcher $events,
		private readonly MessagePage $messages,
		private readonly RedirectPage $redirects,
		private readonly ProfilesInterface $profiles,
		private readonly AvatarUpload $avatars,
		private readonly UsernameRulesInterface $usernames,
		private readonly EmailAddressesInterface $addresses,
		private readonly PostRulesInterface $rules,
		private readonly PacksInterface $packs,
		private readonly BanCacheInterface $bans,
		private readonly VisitorInterface $visitor,
		private readonly LanguageInterface $language,
		private readonly SettingsInterface $settings,
		private readonly UrlsInterface $urls,
		private readonly FormatterInterface $formatter,
		private readonly FlashMessagesInterface $flash
	) {}

	/**
	 * The section stored and the browser sent back to it, or what shows the section again.
	 *
	 * @param array<string, Html> $strings
	 */
	public function save(Request $request, ProfileUserInterface $user, string $section, array $strings): Response|Submission {
		if (!ProfilePage::editable($this->visitor, $user))
			return $this->messages->respond($this->language->text('common', 'No permission'), json: $request->xhr);

		$details = new SubmittedDetails();
		$this->events->dispatch(new DetailsUpdateStep(DetailsUpdateStep::SUBMITTED, $section, $user, $details));

		$refusal = null;
		$errors = array();
		$oldName = null;

		switch ($section)
		{
			case 'identity':
				[$errors, $oldName] = $this->identity($request, $user, $details, $strings);
				break;

			case 'settings':
				$refusal = $this->preferences($request, $user, $details);
				break;

			case 'signature':
				if ($this->settings->value('o_signatures') === '0')
					return $this->messages->respond(ProfilePage::string($strings, 'Signatures disabled'), json: $request->xhr);

				$errors = $this->signature($request, $user, $details, $strings);
				break;

			case 'avatar':
				if ($this->settings->value('o_avatars') === '0')
					return $this->messages->respond(ProfilePage::string($strings, 'Avatars disabled'), json: $request->xhr);

				$validating = new DetailsUpdateStep(DetailsUpdateStep::VALIDATING, $section, $user, $details);
				$this->events->dispatch($validating);

				$uploaded = $this->avatars->upload($request, $user, $strings, $validating->errors());
				if ($uploaded instanceof Response)
					return $uploaded;

				[$user, $errors] = $uploaded;
				break;

			default:
				$validating = new DetailsUpdateStep(DetailsUpdateStep::VALIDATING, $section, $user, $details);
				$this->events->dispatch($validating);
				$errors = $validating->errors();
				break;
		}

		if ($refusal !== null)
			return $this->messages->respond($refusal, json: $request->xhr);

		$validated = new DetailsUpdateStep(DetailsUpdateStep::VALIDATED, $section, $user, $details, $errors, array('avatar'));
		$this->events->dispatch($validated);
		$errors = $validated->errors();

		// The avatar's form stored its upload, and shows the section again with it
		if (in_array($section, $validated->skippedSections(), true) || $errors !== array())
			return new Submission($user, $details, $errors);

		$storing = new DetailsUpdateStep(DetailsUpdateStep::STORING, $section, $user, $details, $errors);
		$this->events->dispatch($storing);

		$values = array();
		foreach ($details->names() as $name)
		{
			$value = $details->value($name);
			$values[$name] = $value !== '' && $value !== null ? (string) $value : null;
		}

		if ($values === array())
			return $this->messages->respond($this->language->text('common', 'Bad request'), json: $request->xhr);

		$this->profiles->updateDetails(new Details($user->id(), $values));

		$newName = (string) $details->value('username');
		if ($oldName !== null && $newName !== $oldName)
			$this->rename($user, $details, $oldName, $newName);

		$done = ProfilePage::string($strings, 'Profile redirect');
		$this->flash->info($done);

		$this->events->dispatch(new DetailsUpdateStep(DetailsUpdateStep::UPDATED, $section, $user, $details));

		return $this->redirects->respond($this->urls->link('profile_'.$section, array($user->id()))->html, $done, $request->xhr);
	}

	/**
	 * The identity's fields: the member's details and contacts, and for the
	 * board's staff their name, address, note, title and post count.
	 *
	 * @param array<string, Html> $strings
	 * @return array{list<string>, ?string} the errors, and the name the member had when the visitor may rename them
	 */
	private function identity(Request $request, ProfileUserInterface $user, SubmittedDetailsInterface $details, array $strings): array {
		$form = is_array($request->post['form'] ?? null) ? $request->post['form'] : array();
		foreach ($form as $name => $value)
			if (in_array($name, self::IDENTITY_FIELDS, true))
				$details->set((string) $name, is_string($value) ? $value : '');

		// Every field is a text input: an absent one is an empty value
		foreach (self::IDENTITY_FIELDS as $name)
			if (!$details->has($name))
				$details->set($name, '');

		$validating = new DetailsUpdateStep(DetailsUpdateStep::VALIDATING, 'identity', $user, $details);
		$this->events->dispatch($validating);
		$errors = $validating->errors();

		$post = $request->post;
		$staff = $this->visitor->isModerating();
		$oldName = null;

		if ($staff)
		{
			if ($this->visitor->isAdministrator() || ($this->visitor->can(GroupPermission::Moderate) && $this->visitor->can(GroupPermission::RenameUsers)))
			{
				$username = ProfilePage::text($post['req_username'] ?? null);
				$details->set('username', $username);
				$oldName = ProfilePage::text($post['old_username'] ?? null);

				foreach ($this->usernames->validate($username, $user->id()) as $error)
					$errors[] = $error->html;
			}

			// Only an administrator changes a post count
			if ($this->visitor->isAdministrator())
				$details->set('num_posts', ProfilePage::integer($post['num_posts'] ?? 0));

			$email = strtolower(ProfilePage::text($post['req_email'] ?? null));
			$details->set('email', $email);
			if (!$this->addresses->isValid($email))
				$errors[] = $this->language->text('common', 'Invalid e-mail')->html;

			$details->set('admin_note', ProfilePage::text($post['admin_note'] ?? null));
		}

		if ($this->visitor->isAdministrator())
			$details->set('title', ProfilePage::text($post['title'] ?? null));
		else if ($this->visitor->can(GroupPermission::SetTitle))
		{
			$title = ProfilePage::text($post['title'] ?? null);
			$details->set('title', $title);

			// A title may not pass for one the board gives
			if ($title !== '')
			{
				$forbidden = array('Member', 'Moderator', 'Administrator', 'Banned', 'Guest');
				foreach ($forbidden as $given)
					$forbidden[] = $this->language->text('common', $given)->html;

				if (in_array($title, $forbidden, true))
					$errors[] = ProfilePage::string($strings, 'Forbidden title')->html;
			}
		}

		$url = (string) $details->value('url');
		if ($url !== '' && !self::isWebAddress($url))
			$details->set('url', 'http://'.$url);

		foreach (array('facebook' => array('#https?://(www\.)?facebook.com/.+?#', 'Bad Facebook'), 'twitter' => array('#https?://twitter.com/.+?#', 'Bad Twitter'), 'linkedin' => array('#https?://(www\.)?linkedin.com/.+?#', 'Bad LinkedIn')) as $name => [$pattern, $error])
		{
			$address = (string) $details->value($name);
			if ((str_starts_with($address, 'http://') || str_starts_with($address, 'https://')) && preg_match($pattern, $address) !== 1)
				$errors[] = ProfilePage::string($strings, $error)->html;
		}

		$linkedin = (string) $details->value('linkedin');
		if ($linkedin !== '' && !self::isWebAddress($linkedin))
			$details->set('linkedin', 'http://'.$linkedin);

		// An ICQ number is digits only
		$icq = (string) $details->value('icq');
		if ($icq !== '' && !ctype_digit($icq))
			$errors[] = ProfilePage::string($strings, 'Bad ICQ')->html;

		return array($errors, $oldName);
	}

	/**
	 * The settings' fields: an absent checkbox is off, a number outside what a member may choose its default.
	 *
	 * @return ?Html what refuses the request; null when it checks out
	 */
	private function preferences(Request $request, ProfileUserInterface $user, SubmittedDetailsInterface $details): ?Html {
		$form = is_array($request->post['form'] ?? null) ? $request->post['form'] : array();
		foreach ($form as $name => $value)
			if (in_array($name, self::SETTINGS_FIELDS, true))
				$details->set((string) $name, is_string($value) ? $value : '');

		$validating = new DetailsUpdateStep(DetailsUpdateStep::VALIDATING, 'settings', $user, $details);
		$this->events->dispatch($validating);

		$details->set('dst', $details->has('dst') ? 1 : 0);
		$details->set('time_format', $details->has('time_format') ? intval($details->value('time_format')) : 0);
		$details->set('date_format', $details->has('date_format') ? intval($details->value('date_format')) : 0);
		$details->set('timezone', $details->has('timezone') ? floatval($details->value('timezone')) : $this->settings->value('o_default_timezone'));

		$timezone = (float) $details->value('timezone');
		if ($timezone > 14.0 || $timezone < -12.0)
			return $this->language->text('common', 'Bad request');

		$emailSetting = intval($details->value('email_setting') ?? 0);
		$details->set('email_setting', $emailSetting < 0 || $emailSetting > 2 ? 1 : $emailSetting);

		if ($this->settings->value('o_subscriptions') === '1')
			self::checkboxes($details, 'notify_with_post', 'auto_notify');

		if ($details->has('language'))
		{
			$details->set('language', (string) preg_replace(self::PATH_CHARACTERS, '', (string) $details->value('language')));
			if (!in_array($details->value('language'), $this->packs->languages(), true))
				return $this->language->text('common', 'Bad request');
		}

		// An absent number leaves the column alone, an empty one is the board's default
		foreach (array('disp_topics', 'disp_posts') as $name)
			if ($details->has($name) && $details->value($name) !== '')
				$details->set($name, min(max(intval($details->value($name)), self::PER_PAGE[0]), self::PER_PAGE[1]));

		self::checkboxes($details, 'show_smilies', 'show_img', 'show_img_sig', 'show_avatars', 'show_sig');

		if ($details->has('style'))
		{
			$details->set('style', (string) preg_replace(self::PATH_CHARACTERS, '', (string) $details->value('style')));
			if (!in_array($details->value('style'), $this->packs->styles(), true))
				return $this->language->text('common', 'Bad request');
		}

		return null;
	}

	/**
	 * The signature, its length and lines checked, a shout toned down and its BBCode tidied.
	 *
	 * @param array<string, Html> $strings
	 * @return list<string>
	 */
	private function signature(Request $request, ProfileUserInterface $user, SubmittedDetailsInterface $details, array $strings): array {
		$validating = new DetailsUpdateStep(DetailsUpdateStep::VALIDATING, 'signature', $user, $details);
		$this->events->dispatch($validating);
		$errors = $validating->errors();

		$signature = str_replace(array("\r\n", "\r"), "\n", ProfilePage::text($request->post['signature'] ?? null));

		$length = (int) $this->settings->value('p_sig_length');
		if (mb_strlen($signature) > $length)
			$errors[] = Html::format(ProfilePage::string($strings, 'Sig too long'), $this->formatter->number($length), $this->formatter->number(mb_strlen($signature) - $length))->html;

		$lines = (int) $this->settings->value('p_sig_lines');
		if (substr_count($signature, "\n") > $lines - 1)
			$errors[] = Html::format(ProfilePage::string($strings, 'Sig too many lines'), $this->formatter->number($lines))->html;

		if ($signature !== '' && $this->settings->value('p_sig_all_caps') === '0' && mb_strtoupper($signature) == $signature && mb_strtolower($signature) != $signature && !$this->visitor->isModerating())
			$signature = (string) preg_replace_callback(self::WORD, static fn (array $match): string => $match[2].mb_strtoupper($match[3]).mb_substr(ltrim($match[0]), 1), mb_strtolower($signature));

		if ($this->settings->value('p_sig_bbcode') === '1' || $this->settings->value('o_make_links') === '1')
		{
			$preparsed = $this->rules->preparseSignature($signature, array_map(static fn (string $error): Html => new Html($error), $errors));
			$signature = $preparsed->text;
			$errors = array_map(static fn (Html $error): string => $error->html, $preparsed->errors);
		}

		$details->set('signature', $signature);

		return $errors;
	}

	/** The member's new name put on their posts, topics, forums and the online list, and on the moderators' lists they are on. */
	private function rename(ProfileUserInterface $user, SubmittedDetailsInterface $details, string $oldName, string $newName): void {
		$this->events->dispatch(new DetailsUpdateStep(DetailsUpdateStep::RENAMED, 'identity', $user, $details, oldName: $oldName));

		$rename = new Rename($user->id(), $oldName, $newName);
		$this->profiles->renamePosts($rename);
		$this->profiles->renameTopics($rename);
		$this->profiles->renameTopicLastPosters($rename);
		$this->profiles->renameForumLastPosters($rename);
		$this->profiles->renameOnline($rename);
		$this->profiles->renameEditors($rename);

		if (!$user->isAdministrator() && !$user->moderates())
			return;

		$forums = array();
		foreach ($this->profiles->forumModerators() as $forum)
		{
			$moderators = Moderator::stored($forum->moderators());
			if (!in_array($user->id(), $moderators, true))
				continue;

			unset($moderators[$oldName]);
			$moderators[$newName] = $user->id();
			ksort($moderators);

			$forums[] = new ForumModerators($forum->forumId(), Moderator::listed($moderators));
		}

		if ($forums !== array())
			$this->profiles->storeModerators(...$forums);

		$this->bans->rebuild();
	}

	/** Each checkbox the form left unchecked, or checked with anything but 1, is '0'. */
	private static function checkboxes(SubmittedDetailsInterface $details, string ...$names): void {
		foreach ($names as $name)
			if (!$details->has($name) || $details->value($name) != '1')
				$details->set($name, '0');
	}

	/** Whether $address starts with its scheme, in any letter case. */
	private static function isWebAddress(string $address): bool {
		$lower = strtolower($address);

		return str_starts_with($lower, 'http://') || str_starts_with($lower, 'https://');
	}
}
