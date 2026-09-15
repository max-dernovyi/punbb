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
use PunBB\Module\Profile\Event\ActivationMailing;
use PunBB\Module\Profile\Event\EmailChangeStep;
use PunBB\Module\Profile\Event\ProfileRendering;
use PunBB\Module\Profile\Model\EmailActivation;
use PunBB\Module\Profile\Model\EmailChange as NewEmail;
use PunBB\Module\Profile\View\FormView;
use PunBB\Module\Site\Config\SettingsInterface;
use PunBB\Module\Site\Language\LanguageInterface;
use PunBB\Module\Site\Mail\EmailAddressesInterface;
use PunBB\Module\Site\Mail\MailerInterface;
use PunBB\Module\Site\Security\CsrfTokensInterface;
use PunBB\Module\Site\Security\PasswordsInterface;
use PunBB\Module\Site\Security\RandomKeysInterface;
use PunBB\Module\Site\Url\UrlsInterface;
use PunBB\Module\Site\Visitor\VisitorInterface;

/**
 * profile.php?action=change_email: a member's new address, given with the
 * visitor's password, and confirmed by the key mailed there where the board
 * verifies addresses.
 */
final class EmailChange {
	private const TEMPLATE = __DIR__.'/../templates/change_email.phtml';

	public function __construct(
		private readonly EventDispatcher $events,
		private readonly PageResponder $pages,
		private readonly TemplateRenderer $templates,
		private readonly MessagePage $messages,
		private readonly RedirectPage $redirects,
		private readonly ProfilesInterface $profiles,
		private readonly PasswordsInterface $passwords,
		private readonly EmailAddressesInterface $addresses,
		private readonly MailerInterface $mailer,
		private readonly RandomKeysInterface $keys,
		private readonly VisitorInterface $visitor,
		private readonly LanguageInterface $language,
		private readonly SettingsInterface $settings,
		private readonly UrlsInterface $urls,
		private readonly CsrfTokensInterface $tokens
	) {}

	/** @param array<string, Html> $strings */
	public function handle(Request $request, ProfileUserInterface $user, array $strings): Response {
		if (!ProfilePage::editable($this->visitor, $user))
			return $this->messages->respond($this->language->text('common', 'No permission'), json: $request->xhr);

		$this->events->dispatch(new EmailChangeStep(EmailChangeStep::SELECTED, $user));

		if (isset($request->post['cancel']))
			return $this->redirects->respond($this->urls->link('profile_about', array($user->id()))->html, $this->language->text('common', 'Cancel redirect'), $request->xhr);

		if (isset($request->query['key']))
			return $this->confirm($request, $user, $strings);

		$errors = array();
		if (isset($request->post['form_sent']))
		{
			$saved = $this->save($request, $user, $strings);
			if ($saved instanceof Response)
				return $saved;

			$errors = $saved;
		}

		return $this->form($request, $user, $strings, $errors);
	}

	/**
	 * The address asked for made the member's, by the key mailed there.
	 *
	 * @param array<string, Html> $strings
	 */
	private function confirm(Request $request, ProfileUserInterface $user, array $strings): Response {
		$key = is_string($request->query['key']) ? $request->query['key'] : '';

		$this->events->dispatch(new EmailChangeStep(EmailChangeStep::KEY_SUPPLIED, $user, $key));

		if ($key === '' || $user->activateKey() === '' || !hash_equals($user->activateKey(), $key))
		{
			$admin = $this->settings->value('o_admin_email');

			return $this->messages->respond(Html::format(ProfilePage::string($strings, 'E-mail key bad'), Html::format('<a href="mailto:%s">%s</a>', $admin, $admin)), json: $request->xhr);
		}

		$this->profiles->confirmEmail($user->id());

		return $this->messages->respond(ProfilePage::string($strings, 'E-mail updated'), json: $request->xhr);
	}

	/**
	 * The address submitted, stored or mailed its key once it checks out.
	 *
	 * @param array<string, Html> $strings
	 * @return Response|list<string> the answer, or the errors that stop the form
	 */
	private function save(Request $request, ProfileUserInterface $user, array $strings): Response|array {
		$submitted = new EmailChangeStep(EmailChangeStep::SUBMITTED, $user);
		$this->events->dispatch($submitted);
		$errors = $submitted->errors();

		$password = is_string($request->post['req_password'] ?? null) ? $request->post['req_password'] : '';
		if (!$this->passwords->verifyVisitor($password))
			$errors[] = ProfilePage::string($strings, 'Wrong password')->html;

		$email = strtolower(ProfilePage::text($request->post['req_new_email'] ?? null));
		if (!$this->addresses->isValid($email))
			$errors[] = $this->language->text('common', 'Invalid e-mail')->html;

		$mailingList = $this->settings->value('o_mailing_list');

		if ($this->addresses->isBanned($email))
		{
			$banned = new EmailChangeStep(EmailChangeStep::BANNED, $user, email: $email, errors: $errors);
			$this->events->dispatch($banned);
			$errors = $banned->errors();

			if ($this->settings->value('p_allow_banned_email') === '0')
				$errors[] = ProfilePage::string($strings, 'Banned e-mail')->html;
			else if ($mailingList !== '')
				$this->alert('Alert - Banned e-mail detected', 'User \''.$this->visitor->username().'\' changed to banned e-mail address: '.$email, $user);
		}

		$duplicates = $this->profiles->usernamesWithEmail($email);
		if ($duplicates !== array())
		{
			$duplicate = new EmailChangeStep(EmailChangeStep::DUPLICATE, $user, email: $email, errors: $errors, duplicates: $duplicates);
			$this->events->dispatch($duplicate);
			$errors = $duplicate->errors();

			if ($this->settings->value('p_allow_dupe_email') === '0')
				$errors[] = ProfilePage::string($strings, 'Dupe e-mail')->html;
			else if ($mailingList !== '' && $errors === array())
				$this->alert('Alert - Duplicate e-mail detected', 'User \''.$this->visitor->username().'\' changed to an e-mail address that also belongs to: '.implode(', ', $duplicates), $user);
		}

		if ($errors !== array())
			return $errors;

		// Without verified addresses the new one is the member's at once
		if ($this->settings->value('o_regs_verify') !== '1')
		{
			$this->profiles->changeEmail(new NewEmail($user->id(), $email));

			return $this->redirects->respond($this->urls->link('profile_about', array($user->id()))->html, ProfilePage::string($strings, 'E-mail updated redirect'), $request->xhr);
		}

		$key = $this->keys->key(8, readable: true);
		$this->profiles->requestEmailChange(new EmailActivation($user->id(), $email, $key));

		$template = (new Html($this->language->mailTemplate('activate_email')))->trim()->html;

		// The first line is the subject
		$firstLine = (int) strpos($template, "\n");
		$message = (new Html(substr($template, $firstLine)))->trim()->html;
		$message = str_replace('<username>', $this->visitor->username(), $message);
		$message = str_replace('<base_url>', $this->urls->base().'/', $message);
		$message = str_replace('<activation_url>', str_replace('&amp;', '&', $this->urls->link('change_email_key', array($user->id(), $key))->html), $message);
		$message = str_replace('<board_mailer>', sprintf($this->language->text('common', 'Forum mailer')->html, $this->settings->value('o_board_title')), $message);

		$mailing = new ActivationMailing($user, $email, $key, (new Html(substr($template, 8, $firstLine - 8)))->trim()->html, $message);
		$this->events->dispatch($mailing);

		$this->mailer->send($email, $mailing->subject(), $mailing->message());

		$admin = $this->settings->value('o_admin_email');

		return $this->messages->respond(Html::format(ProfilePage::string($strings, 'Activate e-mail sent'), Html::format('<a href="mailto:%s">%s</a>', $admin, $admin)), json: $request->xhr);
	}

	/** Tells the mailing list what the member's new address raised. */
	private function alert(string $subject, string $lead, ProfileUserInterface $user): void {
		$this->mailer->send($this->settings->value('o_mailing_list'), $subject, $lead."\n\n".'User profile: '.$this->urls->link('user', array($user->id()))->html."\n\n".'-- '."\n".'Forum Mailer'."\n".'(Do not reply to this message)');
	}

	/**
	 * @param array<string, Html> $strings
	 * @param list<string> $errors
	 */
	private function form(Request $request, ProfileUserInterface $user, array $strings, array $errors): Response {
		$own = $this->visitor->id() === $user->id();

		$crumbs = ProfilePage::crumbs($this->settings, $this->urls, $user, ProfilePage::string($strings, 'Users profile'), 'profile_about');
		$crumbs[] = new Crumb($own ? ProfilePage::string($strings, 'Change your e-mail')->html : sprintf(ProfilePage::string($strings, 'Change user e-mail')->html, $user->username()));

		$action = $this->urls->link('change_email', array($user->id()));
		$token = $this->tokens->token($action->html);

		$values = array(
			'profile'	=> $strings,
			'common'	=> $this->language->strings('common'),
			'heading'	=> Html::format(ProfilePage::string($strings, $own ? 'Profile welcome' : 'Profile welcome user'), $user->username()),
			'info'		=> ProfilePage::string($strings, 'E-mail info'),
			'action'	=> $action,
			'masked'	=> $this->settings->value('o_mask_passwords') === '1',
			'email'		=> ProfilePage::submitted($request->post['req_new_email'] ?? null),
			'password'	=> ProfilePage::submitted($request->post['req_password'] ?? null),
		);

		$head = new PageHead('profile-changemail', $crumbs);

		return $this->pages->respond($head, fn (): array => array('main' => ProfilePage::render($this->events, $this->templates, ProfileRendering::CHANGE_EMAIL, self::TEMPLATE, $user,
			array(ProfileRendering::HIDDEN_FIELDS => ProfilePage::hiddenFields($token)), $values,
			function (FormView $view, Closure $at, ProfileRendering $start) use ($errors): void {
				$view->show('hidden', ProfilePage::joined($start, ProfileRendering::HIDDEN_FIELDS, "\n\t\t\t"));
				$view->show('errors', $errors !== array() ? ProfilePage::joined($at('pre_errors', array(ProfileRendering::ERRORS => ProfilePage::errorParts($errors))), ProfileRendering::ERRORS, "\n\t\t\t\t") : null);

				$at('pre_fieldset');
				$view->numberGroup('group');

				$at('pre_new_email');
				$view->numberItem('email_item');
				$view->numberField('email');

				$at('pre_password');
				$view->numberItem('password_item');
				$view->numberField('password');

				$at('pre_fieldset_end');
				$at('fieldset_end');
			}
		)));
	}
}
