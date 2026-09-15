<?php

declare(strict_types=1);

namespace PunBB\Module\Profile\Controller;

use PunBB\Module\Framework\Event\EventDispatcher;
use PunBB\Module\Framework\Http\Request;
use PunBB\Module\Framework\Http\Response;
use PunBB\Module\Framework\Routing\ControllerInterface;
use PunBB\Module\Message\Page\MessagePage;
use PunBB\Module\Profile\Api\ProfilesInterface;
use PunBB\Module\Profile\Event\ProfileActionRequested;
use PunBB\Module\Profile\Event\ProfileRequested;
use PunBB\Module\Site\Language\LanguageInterface;
use PunBB\Module\Site\Visitor\GroupPermission;
use PunBB\Module\Site\Visitor\VisitorInterface;

/**
 * profile.php?id=: a member's profile, shown section by section to those who
 * may change it and as a whole to those who may not; changing the member's
 * password or address; and, for the board's staff, deleting the member,
 * their avatar, moving them into another group, choosing the forums they
 * moderate and banning them.
 */
final class ProfileController implements ControllerInterface {
	public function __construct(
		private readonly EventDispatcher $events,
		private readonly MessagePage $messages,
		private readonly ProfilesInterface $profiles,
		private readonly PasswordChange $passwords,
		private readonly EmailChange $emails,
		private readonly ProfileAdministration $administration,
		private readonly DetailsUpdate $details,
		private readonly ProfileSections $sections,
		private readonly VisitorInterface $visitor,
		private readonly LanguageInterface $language
	) {}

	public function handle(Request $request): Response {
		$this->events->dispatch(new ProfileRequested());

		$action = is_string($request->query['action'] ?? null) ? $request->query['action'] : null;
		$section = isset($request->query['section']) ? (is_string($request->query['section']) ? $request->query['section'] : '') : 'about';

		$id = ProfilePage::integer($request->query['id'] ?? 0);
		if ($id < 2)
			return $this->badRequest($request);

		// A guest following the key a reset mail carries reads no profile, and needs no permission to
		if ($action !== 'change_pass' || !isset($request->query['key']))
		{
			if (!$this->visitor->can(GroupPermission::ReadBoard))
				return $this->messages->respond($this->language->text('common', 'No view'), json: $request->xhr);

			if (!$this->visitor->can(GroupPermission::ViewUsers) && ($this->visitor->isGuest() || $this->visitor->id() !== $id))
				return $this->messages->respond($this->language->text('common', 'No permission'), json: $request->xhr);
		}

		$strings = $this->language->strings('profile');

		$user = $this->profiles->user($id);
		if ($user === null)
			return $this->badRequest($request);

		$post = $request->post;

		if ($action === 'change_pass')
			return $this->passwords->handle($request, $user, $strings);

		if ($action === 'change_email')
			return $this->emails->handle($request, $user, $strings);

		if ($action === 'delete_user' || isset($post['delete_user_comply']) || isset($post['cancel']))
			return $this->administration->deleteUser($request, $user, $strings);

		if ($action === 'delete_avatar')
			return $this->administration->deleteAvatar($request, $user, $strings);

		if (isset($post['update_group_membership']))
			return $this->administration->changeGroup($request, $user, $strings);

		if (isset($post['update_forums']))
			return $this->administration->assignModerator($request, $user, $strings);

		if (isset($post['ban']))
			return $this->administration->ban($request, $user, $strings);

		$submission = null;
		if (isset($post['form_sent']))
		{
			$saved = $this->details->save($request, $user, $section, $strings);
			if ($saved instanceof Response)
				return $saved;

			$submission = $saved;
			$user = $saved->user;
		}

		$this->events->dispatch(new ProfileActionRequested($user, $section, $action));

		return $this->sections->handle($request, $user, $section, $strings, $submission);
	}

	private function badRequest(Request $request): Response {
		return $this->messages->respond($this->language->text('common', 'Bad request'), json: $request->xhr);
	}
}
