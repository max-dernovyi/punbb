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
use PunBB\Module\Layout\View\TemplateRenderer;
use PunBB\Module\Message\Page\MessagePage;
use PunBB\Module\Message\Page\RedirectPage;
use PunBB\Module\Profile\Api\Data\ProfileUserInterface;
use PunBB\Module\Profile\Api\ProfilesInterface;
use PunBB\Module\Profile\Event\PasswordChangeStep;
use PunBB\Module\Profile\Event\ProfileRendering;
use PunBB\Module\Profile\Model\Password;
use PunBB\Module\Profile\View\FormView;
use PunBB\Module\Site\Config\SettingsInterface;
use PunBB\Module\Site\Flash\FlashMessagesInterface;
use PunBB\Module\Site\Language\LanguageInterface;
use PunBB\Module\Site\Security\CsrfTokensInterface;
use PunBB\Module\Site\Security\PasswordsInterface;
use PunBB\Module\Site\Security\SignInInterface;
use PunBB\Module\Site\Url\UrlsInterface;
use PunBB\Module\Site\Visitor\GroupPermission;
use PunBB\Module\Site\Visitor\VisitorInterface;

/**
 * profile.php?action=change_pass: a member's new password, set with the old
 * one, by the board's staff, or by a guest following the key a reset mail
 * carries.
 */
final class PasswordChange {
	private const KEY_TEMPLATE = __DIR__.'/../templates/change_pass_key.phtml';

	private const TEMPLATE = __DIR__.'/../templates/change_pass.phtml';

	/** How long a remembered login stays signed in once its password changes: two weeks. */
	private const REMEMBERED_FOR = 1209600;

	/** The shortest password a member may choose, in characters. */
	private const MINIMUM_LENGTH = 4;

	public function __construct(
		private readonly EventDispatcher $events,
		private readonly PageResponder $pages,
		private readonly TemplateRenderer $templates,
		private readonly MessagePage $messages,
		private readonly RedirectPage $redirects,
		private readonly ProfilesInterface $profiles,
		private readonly PasswordsInterface $passwords,
		private readonly SignInInterface $signIn,
		private readonly VisitorInterface $visitor,
		private readonly LanguageInterface $language,
		private readonly SettingsInterface $settings,
		private readonly UrlsInterface $urls,
		private readonly CsrfTokensInterface $tokens,
		private readonly FlashMessagesInterface $flash
	) {}

	/** @param array<string, Html> $strings */
	public function handle(Request $request, ProfileUserInterface $user, array $strings): Response {
		$this->events->dispatch(new PasswordChangeStep(PasswordChangeStep::SELECTED, $user, isset($request->query['key'])));

		if (isset($request->post['cancel']))
			return $this->redirects->respond($this->urls->link('profile_about', array($user->id()))->html, $this->language->text('common', 'Cancel redirect'), $request->xhr);

		if (isset($request->query['key']))
			return $this->withKey($request, $user, $strings);

		$allowed = $this->visitor->id() === $user->id()
			|| $this->visitor->isAdministrator()
			|| ($this->visitor->can(GroupPermission::Moderate) && $this->visitor->can(GroupPermission::EditUsers) && $this->visitor->can(GroupPermission::ChangePasswords) && !$user->isAdministrator() && !$user->moderates());

		if (!$allowed)
			return $this->messages->respond($this->language->text('common', 'No permission'), json: $request->xhr);

		$errors = array();
		if (isset($request->post['form_sent']))
		{
			$submitted = new PasswordChangeStep(PasswordChangeStep::SUBMITTED, $user, false);
			$this->events->dispatch($submitted);
			$errors = $submitted->errors();

			$old = ProfilePage::text($request->post['req_old_password'] ?? null);
			$errors = array_merge($errors, $this->check($request, $strings));

			// Staff set a password without the old one; an account without a password cannot have one set
			$authorized = $user->passwordHash() !== '' && ($this->passwords->verify($old, $user->passwordHash(), $user->salt()) || $this->visitor->isModerating());
			if (!$authorized)
				$errors[] = ProfilePage::string($strings, 'Wrong old password')->html;

			if ($errors === array())
			{
				$hash = $this->passwords->hash(ProfilePage::text($request->post['req_new_password1'] ?? null));
				$this->profiles->changePassword(new Password($user->id(), $hash));

				if ($this->visitor->id() === $user->id())
				{
					$visit = time() + (int) $this->settings->value('o_timeout_visit');
					$this->signIn->signIn($user->id(), $hash, $user->salt(), $this->signIn->expiryOf($request->cookies) > $visit ? time() + self::REMEMBERED_FOR : $visit);
				}

				$done = ProfilePage::string($strings, 'Pass updated redirect');
				$this->flash->info($done);

				$this->events->dispatch(new PasswordChangeStep(PasswordChangeStep::CHANGED, $user, false, hash: $hash));

				return $this->redirects->respond($this->urls->link('profile_about', array($user->id()))->html, $done, $request->xhr);
			}
		}

		return $this->form($request, $user, $strings, $errors, false, '');
	}

	/**
	 * A guest's new password, set with the key the reset mail carried.
	 *
	 * @param array<string, Html> $strings
	 */
	private function withKey(Request $request, ProfileUserInterface $user, array $strings): Response {
		$key = is_string($request->query['key']) ? $request->query['key'] : '';

		// A member signed in has their password changed from their profile, not from a mail
		if (!$this->visitor->isGuest())
			return $this->messages->respond(ProfilePage::string($strings, 'Pass logout'), json: $request->xhr);

		$this->events->dispatch(new PasswordChangeStep(PasswordChangeStep::KEY_SUPPLIED, $user, true, $key));

		// The login page writes the key and when it mailed it in one statement, so the mail's time is what the key expires from
		$sent = $user->lastEmailSent() ?? 0;
		$expired = $sent > 0 && time() - $sent >= $this->passwords->resetKeyLifetime();

		if ($key === '' || $user->activateKey() === '' || !hash_equals($user->activateKey(), $key) || $expired)
		{
			$admin = $this->settings->value('o_admin_email');

			return $this->messages->respond(Html::format(ProfilePage::string($strings, 'Pass key bad'), Html::format('<a href="mailto:%s">%s</a>', $admin, $admin)), json: $request->xhr);
		}

		$errors = array();
		if (isset($request->post['form_sent']))
		{
			$submitted = new PasswordChangeStep(PasswordChangeStep::SUBMITTED, $user, true, $key);
			$this->events->dispatch($submitted);
			$errors = array_merge($submitted->errors(), $this->check($request, $strings));

			if ($errors === array())
			{
				$hash = $this->passwords->hash(ProfilePage::text($request->post['req_new_password1'] ?? null));
				$this->profiles->resetPassword(new Password($user->id(), $hash));

				$done = ProfilePage::string($strings, 'Pass updated');
				$this->flash->info($done);

				$this->events->dispatch(new PasswordChangeStep(PasswordChangeStep::CHANGED, $user, true, $key, hash: $hash));

				return $this->redirects->respond($this->urls->link('index')->html, $done, $request->xhr);
			}
		}

		return $this->form($request, $user, $strings, $errors, true, $key);
	}

	/**
	 * What stops the new password: too short, or not what its confirmation says where passwords are masked.
	 *
	 * @param array<string, Html> $strings
	 * @return list<string>
	 */
	private function check(Request $request, array $strings): array {
		$password = ProfilePage::text($request->post['req_new_password1'] ?? null);
		$confirmation = $this->masked() ? ProfilePage::text($request->post['req_new_password2'] ?? null) : $password;

		if (mb_strlen($password) < self::MINIMUM_LENGTH)
			return array(ProfilePage::string($strings, 'Pass too short')->html);

		if ($password !== $confirmation)
			return array(ProfilePage::string($strings, 'Pass not match')->html);

		return array();
	}

	/**
	 * @param array<string, Html> $strings
	 * @param list<string> $errors
	 */
	private function form(Request $request, ProfileUserInterface $user, array $strings, array $errors, bool $withKey, string $key): Response {
		$own = $this->visitor->id() === $user->id();
		$heading = $own ? ProfilePage::string($strings, 'Change your password') : Html::format(ProfilePage::string($strings, 'Change user password'), $user->username());

		$crumbs = ProfilePage::crumbs($this->settings, $this->urls, $user, ProfilePage::string($strings, 'Users profile'), 'profile_about');
		$crumbs[] = new Crumb($own ? ProfilePage::string($strings, 'Change your password')->html : sprintf(ProfilePage::string($strings, 'Change user password')->html, $user->username()));

		$action = $withKey ? $this->urls->link('change_password_key', array($user->id(), $key)) : $this->urls->link('change_password', array($user->id()));
		$token = $this->tokens->token($action->html);

		$values = array(
			'profile'		=> $strings,
			'common'		=> $this->language->strings('common'),
			'heading'		=> $heading,
			'action'		=> $action,
			'token'			=> $token,
			'masked'		=> $this->masked(),
			'oldPassword'	=> ProfilePage::submitted($request->post['req_old_password'] ?? null),
			'password'		=> ProfilePage::submitted($request->post['req_new_password1'] ?? null),
			'confirmation'	=> ProfilePage::submitted($request->post['req_new_password2'] ?? null),
		);

		$page = $withKey ? ProfileRendering::CHANGE_PASS_KEY : ProfileRendering::CHANGE_PASS;
		$hidden = $withKey ? array() : array(ProfileRendering::HIDDEN_FIELDS => ProfilePage::hiddenFields($token));
		$asksOld = !$withKey && (!$this->visitor->isModerating() || $own);

		$head = new PageHead('profile-changepass', $crumbs, view: $withKey ? 'key' : null);

		return $this->pages->respond($head, fn (): array => array('main' => ProfilePage::render($this->events, $this->templates, $page, $withKey ? self::KEY_TEMPLATE : self::TEMPLATE, $user, $hidden, $values,
			function (FormView $view, Closure $at, ProfileRendering $start) use ($errors, $withKey, $asksOld): void {
				if (!$withKey)
					$view->show('hidden', ProfilePage::joined($start, ProfileRendering::HIDDEN_FIELDS, "\n\t\t\t\t"));

				$view->show('errors', $errors !== array() ? ProfilePage::joined($at('pre_errors', array(ProfileRendering::ERRORS => ProfilePage::errorParts($errors))), ProfileRendering::ERRORS, "\n\t\t\t\t") : null);
				$view->show('asksOld', $asksOld);

				$at('pre_fieldset');
				$view->numberGroup('group');

				if (!$withKey)
				{
					$at('pre_old_password');
					if ($asksOld)
					{
						$view->numberItem('old_item');
						$view->numberField('old');
					}
				}

				$at('pre_new_password');
				$view->numberItem('password_item');
				$view->numberField('password');

				$at('pre_new_password_confirm');
				if ($this->masked())
				{
					$view->numberItem('confirmation_item');
					$view->numberField('confirmation');
				}

				$at('pre_fieldset_end');
				$at('fieldset_end');
			}
		)));
	}

	private function masked(): bool {
		return $this->settings->value('o_mask_passwords') === '1';
	}
}
