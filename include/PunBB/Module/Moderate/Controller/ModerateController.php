<?php

declare(strict_types=1);

namespace PunBB\Module\Moderate\Controller;

use PunBB\Module\Framework\Event\EventDispatcher;
use PunBB\Module\Framework\Http\Request;
use PunBB\Module\Framework\Http\Response;
use PunBB\Module\Framework\Routing\ControllerInterface;
use PunBB\Module\Layout\View\Html;
use PunBB\Module\Message\Page\MessagePage;
use PunBB\Module\Message\Page\RedirectPage;
use PunBB\Module\Moderate\Api\Data\ModeratedForumInterface;
use PunBB\Module\Moderate\Api\ModeratedPostsInterface;
use PunBB\Module\Moderate\Api\ModeratedTopicsInterface;
use PunBB\Module\Moderate\Event\HostLookupStep;
use PunBB\Module\Moderate\Event\ModerationRequested;
use PunBB\Module\Moderate\Event\ModeratorChecking;
use PunBB\Module\Moderate\Network\HostnameLookupInterface;
use PunBB\Module\Site\Language\LanguageInterface;
use PunBB\Module\Site\Url\UrlsInterface;
use PunBB\Module\Site\Visitor\GroupPermission;
use PunBB\Module\Site\Visitor\TrackedTopics;
use PunBB\Module\Site\Visitor\VisitorInterface;

/**
 * moderate.php, for administrators and a forum's moderators: the name an
 * address resolves to, for any of them; and for a forum they moderate, its
 * topics or a topic's posts and the changes made to them.
 */
final class ModerateController implements ControllerInterface {
	/** An address as the page script accepted it: a whole dotted quad, or a whole IPv6 address. */
	private const IPV4 = '/^[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}$/';

	private const IPV6 = '/^((([0-9A-Fa-f]{1,4}:){7}[0-9A-Fa-f]{1,4})|(([0-9A-Fa-f]{1,4}:){6}:[0-9A-Fa-f]{1,4})|(([0-9A-Fa-f]{1,4}:){5}:([0-9A-Fa-f]{1,4}:)?[0-9A-Fa-f]{1,4})|(([0-9A-Fa-f]{1,4}:){4}:([0-9A-Fa-f]{1,4}:){0,2}[0-9A-Fa-f]{1,4})|(([0-9A-Fa-f]{1,4}:){3}:([0-9A-Fa-f]{1,4}:){0,3}[0-9A-Fa-f]{1,4})|(([0-9A-Fa-f]{1,4}:){2}:([0-9A-Fa-f]{1,4}:){0,4}[0-9A-Fa-f]{1,4})|(([0-9A-Fa-f]{1,4}:){6}((\b((25[0-5])|(1\d{2})|(2[0-4]\d)|(\d{1,2}))\b)\.){3}(\b((25[0-5])|(1\d{2})|(2[0-4]\d)|(\d{1,2}))\b))|(([0-9A-Fa-f]{1,4}:){0,5}:((\b((25[0-5])|(1\d{2})|(2[0-4]\d)|(\d{1,2}))\b)\.){3}(\b((25[0-5])|(1\d{2})|(2[0-4]\d)|(\d{1,2}))\b))|(::([0-9A-Fa-f]{1,4}:){0,5}((\b((25[0-5])|(1\d{2})|(2[0-4]\d)|(\d{1,2}))\b)\.){3}(\b((25[0-5])|(1\d{2})|(2[0-4]\d)|(\d{1,2}))\b))|([0-9A-Fa-f]{1,4}::([0-9A-Fa-f]{1,4}:){0,5}[0-9A-Fa-f]{1,4})|(::([0-9A-Fa-f]{1,4}:){0,6}[0-9A-Fa-f]{1,4})|(([0-9A-Fa-f]{1,4}:){1,7}:))$/';

	public function __construct(
		private readonly EventDispatcher $events,
		private readonly MessagePage $messages,
		private readonly RedirectPage $redirects,
		private readonly ModeratedPostsInterface $posts,
		private readonly ModeratedTopicsInterface $topics,
		private readonly PostsModeration $postsModeration,
		private readonly TopicsModeration $topicsModeration,
		private readonly HostnameLookupInterface $hostnames,
		private readonly VisitorInterface $visitor,
		private readonly LanguageInterface $language,
		private readonly UrlsInterface $urls
	) {}

	public function handle(Request $request): Response {
		$this->events->dispatch(new ModerationRequested());

		$misc = $this->language->strings('misc');

		// Any administrator or moderator may look up an address, whatever forum they moderate
		if (isset($request->query['get_host']))
			return $this->hostname($request, $misc);

		$forumId = Moderation::integer($request->query['fid'] ?? 0);
		$forum = $forumId >= 1 ? $this->topics->forum($forumId, $this->visitor->groupId()) : null;
		if ($forum === null || $forum->redirectUrl() !== '')
			return $this->badRequest($request);

		$checking = new ModeratorChecking($forum, $this->visitor->isAdministrator() || ($this->visitor->can(GroupPermission::Moderate) && $this->lists($forum)));
		$this->events->dispatch($checking);

		if (!$checking->moderating())
			return $this->messages->respond($this->language->text('common', 'No permission'), json: $request->xhr);

		$tracked = !$this->visitor->isGuest() ? $this->visitor->trackedTopics() : new TrackedTopics();

		if (isset($request->post['cancel']))
			return $this->redirects->respond($this->urls->link('forum', array($forum->id(), $this->urls->slug($forum->name())))->html, $this->language->text('common', 'Cancel redirect'), $request->xhr);

		if (isset($request->query['tid']))
			return $this->postsModeration->handle($request, $forum, $misc);

		return $this->topicsModeration->handle($request, $forum, $tracked, $misc);
	}

	/**
	 * The name an address resolves to: the address asked for, or the one a post was written from.
	 *
	 * @param array<string, Html> $misc
	 */
	private function hostname(Request $request, array $misc): Response {
		if (!$this->visitor->isModerating())
			return $this->messages->respond($this->language->text('common', 'No permission'), json: $request->xhr);

		$asked = $request->query['get_host'];
		if (!is_string($asked))
			return $this->badRequest($request);

		$this->events->dispatch(new HostLookupStep(HostLookupStep::SELECTED, $asked));

		if (preg_match(self::IPV4, $asked) === 1 || preg_match(self::IPV6, $asked) === 1)
			$address = $asked;
		else
		{
			$postId = intval($asked);
			$address = $postId >= 1 ? $this->posts->posterAddress($postId) : null;

			if ($address === null || !Moderation::found($address))
				return $this->badRequest($request);
		}

		$this->events->dispatch(new HostLookupStep(HostLookupStep::SHOWING, $address));

		return $this->messages->respond(Html::format(Moderation::string($misc, 'Hostname lookup'), $address, $this->hostnames->hostname($address),
			Html::format('<a href="%s?show_users=%s">%s</a>', $this->urls->link('admin_users'), $address, Moderation::string($misc, 'Show more users'))), json: $request->xhr);
	}

	/** Whether the forum lists the visitor as its moderator. */
	private function lists(ModeratedForumInterface $forum): bool {
		foreach ($forum->moderators() as $moderator)
			if ($moderator->username() === $this->visitor->username())
				return true;

		return false;
	}

	private function badRequest(Request $request): Response {
		return $this->messages->respond($this->language->text('common', 'Bad request'), json: $request->xhr);
	}
}
