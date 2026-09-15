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
use PunBB\Module\Message\Page\ConfirmPage;
use PunBB\Module\Message\Page\MessagePage;
use PunBB\Module\Message\Page\RedirectPage;
use PunBB\Module\Profile\Api\Data\ProfileUserInterface;
use PunBB\Module\Profile\Api\ProfilesInterface;
use PunBB\Module\Profile\Avatar\AvatarRemovalInterface;
use PunBB\Module\Profile\Event\AvatarDeletionStep;
use PunBB\Module\Profile\Event\BanRequested;
use PunBB\Module\Profile\Event\GroupMembershipStep;
use PunBB\Module\Profile\Event\ModeratorAssignmentStep;
use PunBB\Module\Profile\Event\ProfileRendering;
use PunBB\Module\Profile\Event\UserDeletionStep;
use PunBB\Module\Profile\Model\ForumModerators;
use PunBB\Module\Profile\Model\Moderator;
use PunBB\Module\Profile\View\FormView;
use PunBB\Module\Site\Config\SettingsInterface;
use PunBB\Module\Site\Flash\FlashMessagesInterface;
use PunBB\Module\Site\Language\LanguageInterface;
use PunBB\Module\Site\Moderation\ModeratorListsInterface;
use PunBB\Module\Site\Removal\UserRemovalInterface;
use PunBB\Module\Site\Security\CsrfTokensInterface;
use PunBB\Module\Site\Url\UrlsInterface;
use PunBB\Module\Site\Visitor\GroupPermission;
use PunBB\Module\Site\Visitor\VisitorInterface;

/**
 * What the board's staff do to a member from their profile: delete them or
 * their avatar, move them into another group, choose the forums they
 * moderate, and send them to the bans page's form.
 */
final class ProfileAdministration {
	private const DELETE_TEMPLATE = __DIR__.'/../templates/delete_user.phtml';

	public function __construct(
		private readonly EventDispatcher $events,
		private readonly PageResponder $pages,
		private readonly TemplateRenderer $templates,
		private readonly MessagePage $messages,
		private readonly RedirectPage $redirects,
		private readonly ConfirmPage $confirmations,
		private readonly ProfilesInterface $profiles,
		private readonly UserRemovalInterface $removal,
		private readonly AvatarRemovalInterface $avatars,
		private readonly ModeratorListsInterface $moderators,
		private readonly VisitorInterface $visitor,
		private readonly LanguageInterface $language,
		private readonly SettingsInterface $settings,
		private readonly UrlsInterface $urls,
		private readonly CsrfTokensInterface $tokens,
		private readonly FlashMessagesInterface $flash
	) {}

	/**
	 * An administrator deletes a member who does not administer, once they confirm it.
	 *
	 * @param array<string, Html> $strings
	 */
	public function deleteUser(Request $request, ProfileUserInterface $user, array $strings): Response {
		if (isset($request->post['cancel']))
			return $this->redirects->respond($this->urls->link('profile_admin', array($user->id()))->html, $this->language->text('common', 'Cancel redirect'), $request->xhr);

		$this->events->dispatch(new UserDeletionStep(UserDeletionStep::SELECTED, $user));

		if (!$this->visitor->isAdministrator())
			return $this->noPermission($request);

		if ($user->isAdministrator())
			return $this->messages->respond(ProfilePage::string($strings, 'Cannot delete admin'), json: $request->xhr);

		if (isset($request->post['delete_user_comply']))
		{
			$withPosts = isset($request->post['delete_posts']);

			$this->events->dispatch(new UserDeletionStep(UserDeletionStep::SUBMITTED, $user, $withPosts));

			$this->removal->remove($user->id(), $withPosts);

			$done = ProfilePage::string($strings, 'User delete redirect');
			$this->flash->info($done);

			$this->events->dispatch(new UserDeletionStep(UserDeletionStep::DELETED, $user, $withPosts));

			return $this->redirects->respond($this->urls->link('index')->html, $done, $request->xhr);
		}

		$crumbs = ProfilePage::crumbs($this->settings, $this->urls, $user, ProfilePage::string($strings, 'Users profile'), 'profile_admin');
		$crumbs[] = new Crumb(ProfilePage::string($strings, 'Delete user')->html);

		$action = $this->urls->link('delete_user', array($user->id()));

		$values = array(
			'profile'	=> $strings,
			'common'	=> $this->language->strings('common'),
			'heading'	=> Html::format(ProfilePage::string($strings, $this->visitor->id() === $user->id() ? 'Profile welcome' : 'Profile welcome user'), $user->username()),
			'action'	=> $action,
			'token'		=> $this->tokens->token($action->html),
			'label'		=> Html::format(ProfilePage::string($strings, 'Delete posts label'), $user->username()),
		);

		$head = new PageHead('dialogue', $crumbs, view: 'delete_user');

		return $this->pages->respond($head, fn (): array => array('main' => ProfilePage::render($this->events, $this->templates, ProfileRendering::DELETE_USER, self::DELETE_TEMPLATE, $user, array(), $values,
			static function (FormView $view, Closure $at): void {
				$at('pre_fieldset');
				$view->numberGroup('group');

				$at('pre_confirm_checkbox');
				$view->numberItem('posts_item');
				$view->numberField('posts');

				$at('pre_fieldset_end');
				$at('fieldset_end');
			}
		)));
	}

	/**
	 * The avatar deleted by the link the avatar section issued, or once the visitor confirms it.
	 *
	 * @param array<string, Html> $strings
	 */
	public function deleteAvatar(Request $request, ProfileUserInterface $user, array $strings): Response {
		if (!ProfilePage::editable($this->visitor, $user))
			return $this->noPermission($request);

		// A token posted with the request was checked on the way in; one in the link is checked here
		if (!isset($request->post['csrf_token']) && !$this->tokens->matches($request->query['csrf_token'] ?? null, 'delete_avatar'.$user->id().$this->visitor->id()))
		{
			$confirmation = $this->confirmations->respond($request->post, $request->xhr);
			if ($confirmation !== null)
				return $confirmation;
		}

		$this->events->dispatch(new AvatarDeletionStep(AvatarDeletionStep::SELECTED, $user));

		$this->avatars->remove($user->id());

		$done = ProfilePage::string($strings, 'Avatar deleted redirect');
		$this->flash->info($done);

		$this->events->dispatch(new AvatarDeletionStep(AvatarDeletionStep::DELETED, $user));

		return $this->redirects->respond($this->urls->link('profile_avatar', array($user->id()))->html, $done, $request->xhr);
	}

	/**
	 * An administrator moves the member into another group; one leaving moderation leaves the forums' lists.
	 *
	 * @param array<string, Html> $strings
	 */
	public function changeGroup(Request $request, ProfileUserInterface $user, array $strings): Response {
		if (!$this->visitor->isAdministrator())
			return $this->noPermission($request);

		$this->events->dispatch(new GroupMembershipStep(GroupMembershipStep::SUBMITTED, $user));

		$groupId = ProfilePage::integer($request->post['group_id'] ?? 0);

		$this->profiles->moveToGroup($groupId, $user->id());
		$moderates = $this->profiles->groupModerates($groupId);

		if (($user->isAdministrator() || $user->moderates()) && $groupId !== ProfileUserInterface::ADMINISTRATORS && !$moderates)
			$this->moderators->clean();

		$done = ProfilePage::string($strings, 'Group membership redirect');
		$this->flash->info($done);

		$this->events->dispatch(new GroupMembershipStep(GroupMembershipStep::CHANGED, $user, $groupId));

		return $this->redirects->respond($this->urls->link('profile_admin', array($user->id()))->html, $done, $request->xhr);
	}

	/**
	 * An administrator chooses the forums the member moderates: every forum's list is stored again.
	 *
	 * @param array<string, Html> $strings
	 */
	public function assignModerator(Request $request, ProfileUserInterface $user, array $strings): Response {
		if (!$this->visitor->isAdministrator())
			return $this->noPermission($request);

		$this->events->dispatch(new ModeratorAssignmentStep(ModeratorAssignmentStep::SUBMITTED, $user));

		$chosen = is_array($request->post['moderator_in'] ?? null) ? array_map(intval(...), array_keys($request->post['moderator_in'])) : array();

		$forums = array();
		$assigned = array();
		foreach ($this->profiles->forumModerators() as $forum)
		{
			$moderators = Moderator::stored($forum->moderators());
			$listed = in_array($user->id(), $moderators, true);
			$chosenHere = in_array($forum->forumId(), $chosen, true);

			if ($chosenHere && !$listed)
			{
				$moderators[$user->username()] = $user->id();
				ksort($moderators);
			}
			else if (!$chosenHere && $listed)
				unset($moderators[$user->username()]);

			$forums[] = new ForumModerators($forum->forumId(), Moderator::listed($moderators));
			if ($chosenHere)
				$assigned[] = $forum->forumId();
		}

		$this->profiles->storeModerators(...$forums);

		$done = ProfilePage::string($strings, 'Moderate forums redirect');
		$this->flash->info($done);

		$this->events->dispatch(new ModeratorAssignmentStep(ModeratorAssignmentStep::UPDATED, $user, $assigned));

		return $this->redirects->respond($this->urls->link('profile_admin', array($user->id()))->html, $done, $request->xhr);
	}

	/**
	 * The staff allowed to ban are sent to the bans page's form for the member.
	 *
	 * @param array<string, Html> $strings
	 */
	public function ban(Request $request, ProfileUserInterface $user, array $strings): Response {
		if (!$this->visitor->isAdministrator() && (!$this->visitor->can(GroupPermission::Moderate) || !$this->visitor->can(GroupPermission::BanUsers)))
			return $this->noPermission($request);

		$this->events->dispatch(new BanRequested($user));

		return $this->redirects->respond($this->urls->link('admin_bans')->html.'&amp;add_ban='.$user->id(), ProfilePage::string($strings, 'Ban redirect'), $request->xhr);
	}

	private function noPermission(Request $request): Response {
		return $this->messages->respond($this->language->text('common', 'No permission'), json: $request->xhr);
	}
}
