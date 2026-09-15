<?php

declare(strict_types=1);

namespace PunBB\Module\Delete\Controller;

use PunBB\Module\Delete\Api\Data\DeletablePostInterface;
use PunBB\Module\Delete\Api\DeletablePostsInterface;
use PunBB\Module\Delete\Event\DeletionPermissionChecking;
use PunBB\Module\Delete\Event\DeletionRendering;
use PunBB\Module\Delete\Event\DeletionRequested;
use PunBB\Module\Delete\Event\PostDeletionStep;
use PunBB\Module\Delete\Event\PostIdentAssembling;
use PunBB\Module\Delete\Removal\PostRemovalInterface;
use PunBB\Module\Delete\View\DeletionView;
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
use PunBB\Module\Site\Config\SettingsInterface;
use PunBB\Module\Site\Flash\FlashMessagesInterface;
use PunBB\Module\Site\Format\FormatterInterface;
use PunBB\Module\Site\Format\TimeFormat;
use PunBB\Module\Site\Language\LanguageInterface;
use PunBB\Module\Site\Security\CsrfTokensInterface;
use PunBB\Module\Site\Url\UrlsInterface;
use PunBB\Module\Site\Visitor\GroupPermission;
use PunBB\Module\Site\Visitor\VisitorInterface;

/**
 * delete.php?id=: a post, or the topic it opens, shown for the visitor to
 * confirm its deletion, and deleted once they do. Its poster may delete it
 * while the topic is open and their group allows; a moderator of its forum
 * may always.
 */
final class DeleteController implements ControllerInterface {
	private const TEMPLATE = __DIR__.'/../templates/delete.phtml';

	public function __construct(
		private readonly EventDispatcher $events,
		private readonly PageResponder $pages,
		private readonly TemplateRenderer $templates,
		private readonly MessagePage $messages,
		private readonly RedirectPage $redirects,
		private readonly DeletablePostsInterface $posts,
		private readonly PostRemovalInterface $removal,
		private readonly VisitorInterface $visitor,
		private readonly LanguageInterface $language,
		private readonly SettingsInterface $settings,
		private readonly UrlsInterface $urls,
		private readonly FormatterInterface $formatter,
		private readonly CsrfTokensInterface $tokens,
		private readonly FlashMessagesInterface $flash
	) {}

	public function handle(Request $request): Response {
		$this->events->dispatch(new DeletionRequested());

		if (!$this->visitor->can(GroupPermission::ReadBoard))
			return $this->messages->respond($this->language->text('common', 'No view'), json: $request->xhr);

		$strings = $this->language->strings('delete');

		$id = isset($request->query['id']) && is_scalar($request->query['id']) ? intval($request->query['id']) : 0;
		$post = $id > 0 ? $this->posts->find($id, $this->visitor->groupId()) : null;

		if ($post === null)
			return $this->messages->respond($this->language->text('common', 'Bad request'), json: $request->xhr);

		$checking = new DeletionPermissionChecking($post, $this->visitor->isAdministrator() || ($this->visitor->can(GroupPermission::Moderate) && $this->moderates($post)));
		$this->events->dispatch($checking);

		$allowed = $post->isTopic() ? $this->visitor->can(GroupPermission::DeleteTopics) : $this->visitor->can(GroupPermission::DeletePosts);
		if ((!$allowed || $post->posterId() !== $this->visitor->id() || $post->topicClosed()) && !$checking->moderating())
			return $this->messages->respond($this->language->text('common', 'No permission'), json: $request->xhr);

		$this->events->dispatch(new PostDeletionStep(PostDeletionStep::SELECTED, $post));

		if (isset($request->post['cancel']))
			return $this->redirects->respond($this->urls->link('post', array($id))->html, $this->language->text('common', 'Cancel redirect'), $request->xhr);

		if (isset($request->post['delete']))
			return $this->delete($request, $post, $strings);

		return $this->page($post, $strings);
	}

	private function moderates(DeletablePostInterface $post): bool {
		foreach ($post->moderators() as $moderator)
			if ($moderator->username() === $this->visitor->username())
				return true;

		return false;
	}

	/** @param array<string, Html> $strings */
	private function delete(Request $request, DeletablePostInterface $post, array $strings): Response {
		$this->events->dispatch(new PostDeletionStep(PostDeletionStep::SUBMITTED, $post));

		if (!isset($request->post['req_confirm']))
			return $this->redirects->respond($this->urls->link('post', array($post->id()))->html, $this->language->text('common', 'No confirm redirect'), $request->xhr);

		if ($post->isTopic())
		{
			$this->removal->removeTopic($post->topicId(), $post->forumId());
			$this->flash->info(self::string($strings, 'Topic del redirect'));

			$this->events->dispatch(new PostDeletionStep(PostDeletionStep::TOPIC_DELETED, $post));

			return $this->redirects->respond($this->urls->link('forum', array($post->forumId(), $this->urls->slug($post->forumName())))->html, self::string($strings, 'Topic del redirect'), $request->xhr);
		}

		$this->removal->removePost($post->id(), $post->topicId(), $post->forumId());
		$previous = $this->posts->previousPostId($post->topicId(), $post->id());
		$this->flash->info(self::string($strings, 'Post del redirect'));

		$this->events->dispatch(new PostDeletionStep(PostDeletionStep::POST_DELETED, $post, $previous));

		$destination = $previous !== null
			? $this->urls->link('post', array($previous))
			: $this->urls->link('topic', array($post->topicId(), $this->urls->slug($post->subject())));

		return $this->redirects->respond($destination->html, self::string($strings, 'Post del redirect'), $request->xhr);
	}

	/** @param array<string, Html> $strings */
	private function page(DeletablePostInterface $post, array $strings): Response {
		$message = $this->formatter->message($post->message(), $post->hidesSmilies());
		$action = $this->urls->link('delete', array($post->id()));

		$hidden = array(
			'form_sent'		=> '<input type="hidden" name="form_sent" value="1" />',
			'csrf_token'	=> Html::format('<input type="hidden" name="csrf_token" value="%s" />', $this->tokens->token($action->html))->html,
		);

		$info = array(
			Html::format('<li><span>%s:<strong> %s</strong></span></li>', self::string($strings, 'Forum'), $post->forumName()),
			Html::format('<li><span>%s:<strong> %s</strong></span></li>', self::string($strings, 'Topic'), $post->subject()),
		);

		$posted = $this->formatter->time($post->posted(), TimeFormat::DateTime);

		// The permalink has always led to the post numbered as the topic
		$ident = new PostIdentAssembling($post, array(
			'byline'	=> Html::format('<span class="post-byline">%s</span>', Html::format(self::string($strings, $post->isTopic() ? 'Topic byline' : 'Reply byline'), Html::format('<strong>%s</strong>', $post->poster())))->html,
			'link'		=> Html::format('<span class="post-link"><a class="permalink" href="%s">%s</a></span>', $this->urls->link('post', array($post->topicId())), $posted)->html,
		));
		$this->events->dispatch($ident);

		$identParts = array();
		foreach ($ident->names() as $name)
			$identParts[] = new Html((string) $ident->entry($name));

		$legend = self::string($strings, $post->isTopic() ? 'Delete topic' : 'Delete post');

		$head = new PageHead('postdelete', array(
			new Crumb($this->settings->value('o_board_title'), $this->urls->link('index')),
			new Crumb($post->forumName(), $this->urls->link('forum', array($post->forumId(), $this->urls->slug($post->forumName())))),
			new Crumb($post->subject(), $this->urls->link('topic', array($post->topicId(), $this->urls->slug($post->subject())))),
			new Crumb($legend->html),
		));

		$view = new DeletionView($post, array(
			'delete'	=> $strings,
			'info'		=> $info,
			'ident'		=> $identParts,
			'subject'	=> Html::escape(sprintf(self::string($strings, $post->isTopic() ? 'Topic title' : 'Reply title')->html, $post->subject())),
			'message'	=> $message,
			'action'	=> $action,
			'hidden'	=> array_map(static fn (string $field): Html => new Html($field), array_values($hidden)),
			'legend'	=> $legend,
			'label'		=> Html::format(self::string($strings, $post->isTopic() ? 'Delete topic label' : 'Delete post label'), $post->poster(), $posted),
			'cancel'	=> $this->language->text('common', 'Cancel'),
		));

		return $this->pages->respond($head, fn (): array => array('main' => $this->main($view)));
	}

	private function main(DeletionView $view): Html {
		$this->at($view, DeletionRendering::MAIN_OUTPUT_START);
		$this->at($view, DeletionRendering::PRE_POST_DISPLAY);
		$this->at($view, DeletionRendering::NEW_POST_HEAD_OPTION);
		$this->at($view, DeletionRendering::NEW_POST_ENTRY_DATA);

		$this->at($view, DeletionRendering::PRE_CONFIRM_DELETE_FIELDSET);
		$view->numberGroup('group');

		$this->at($view, DeletionRendering::PRE_CONFIRM_DELETE_CHECKBOX);
		$view->numberItem('confirm_item');
		$view->numberField('confirm_field');

		$this->at($view, DeletionRendering::PRE_CONFIRM_DELETE_FIELDSET_END);
		$this->at($view, DeletionRendering::CONFIRM_DELETE_FIELDSET_END);

		$body = $this->templates->render(self::TEMPLATE, $view->variables());

		$end = $view->rendering(DeletionRendering::END);
		$this->events->dispatch($end);

		return (new Html($view->markup(DeletionRendering::MAIN_OUTPUT_START)->html.$body.$end->markup()))->trim();
	}

	private function at(DeletionView $view, string $position): void {
		$event = $view->rendering($position);
		$this->events->dispatch($event);
		$view->place($event);
	}

	/** @param array<string, Html> $strings */
	private static function string(array $strings, string $key): Html {
		return $strings[$key] ?? new Html('');
	}
}
