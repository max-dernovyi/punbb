<?php

declare(strict_types=1);

namespace PunBB\Module\Edit\Controller;

use PunBB\Module\Edit\Api\Data\EditablePostInterface;
use PunBB\Module\Edit\Api\EditablePostsInterface;
use PunBB\Module\Edit\Event\EditPermissionChecking;
use PunBB\Module\Edit\Event\EditPreviewAssembling;
use PunBB\Module\Edit\Event\EditRendering;
use PunBB\Module\Edit\Event\EditRequested;
use PunBB\Module\Edit\Event\PostEditStep;
use PunBB\Module\Edit\Indexing\EditIndexInterface;
use PunBB\Module\Edit\Model\PostEdit;
use PunBB\Module\Edit\View\EditView;
use PunBB\Module\Framework\Event\EventDispatcher;
use PunBB\Module\Framework\Http\Request;
use PunBB\Module\Framework\Http\Response;
use PunBB\Module\Framework\Routing\ControllerInterface;
use PunBB\Module\Layout\Chrome\Crumb;
use PunBB\Module\Layout\Chrome\PageHead;
use PunBB\Module\Layout\Page\PageResponder;
use PunBB\Module\Layout\View\Html;
use PunBB\Module\Layout\View\Parts;
use PunBB\Module\Layout\View\TemplateRenderer;
use PunBB\Module\Message\Page\MessagePage;
use PunBB\Module\Message\Page\RedirectPage;
use PunBB\Module\Site\Config\SettingsInterface;
use PunBB\Module\Site\Format\FormatterInterface;
use PunBB\Module\Site\Format\TimeFormat;
use PunBB\Module\Site\Language\LanguageInterface;
use PunBB\Module\Site\Posting\PostRulesInterface;
use PunBB\Module\Site\Security\CsrfTokensInterface;
use PunBB\Module\Site\Url\UrlsInterface;
use PunBB\Module\Site\Visitor\GroupPermission;
use PunBB\Module\Site\Visitor\VisitorInterface;

/**
 * edit.php?id=: a post's message, and its topic's subject when it opens the
 * topic, in a form to edit, with a preview; the edit stored once the form
 * checks out. Its poster may edit it while the topic is open and their group
 * allows; a moderator of its forum may always, silently if they choose.
 */
final class EditController implements ControllerInterface {
	private const TEMPLATE = __DIR__.'/../templates/edit.phtml';

	/** The separators a word starts after, as ucwords() takes them. */
	private const WORD = '/(^|([\x0c\x09\x0b\x0a\x0d\x20]+))([^\x0c\x09\x0b\x0a\x0d\x20]{1})[^\x0c\x09\x0b\x0a\x0d\x20]*/u';

	public function __construct(
		private readonly EventDispatcher $events,
		private readonly PageResponder $pages,
		private readonly TemplateRenderer $templates,
		private readonly MessagePage $messages,
		private readonly RedirectPage $redirects,
		private readonly EditablePostsInterface $posts,
		private readonly EditIndexInterface $index,
		private readonly VisitorInterface $visitor,
		private readonly LanguageInterface $language,
		private readonly SettingsInterface $settings,
		private readonly UrlsInterface $urls,
		private readonly FormatterInterface $formatter,
		private readonly CsrfTokensInterface $tokens,
		private readonly PostRulesInterface $rules
	) {}

	public function handle(Request $request): Response {
		$this->events->dispatch(new EditRequested());

		if (!$this->visitor->can(GroupPermission::ReadBoard))
			return $this->messages->respond($this->language->text('common', 'No view'), json: $request->xhr);

		$strings = $this->language->strings('post');

		$id = isset($request->query['id']) && is_scalar($request->query['id']) ? intval($request->query['id']) : 0;
		$post = $id > 0 ? $this->posts->find($id, $this->visitor->groupId()) : null;

		if ($post === null)
			return $this->messages->respond($this->language->text('common', 'Bad request'), json: $request->xhr);

		$checking = new EditPermissionChecking($post, $this->visitor->isAdministrator() || ($this->visitor->can(GroupPermission::Moderate) && $this->moderates($post)));
		$this->events->dispatch($checking);
		$moderating = $checking->moderating();

		if ((!$this->visitor->can(GroupPermission::EditPosts) || $post->posterId() !== $this->visitor->id() || $post->topicClosed()) && !$moderating)
			return $this->messages->respond($this->language->text('common', 'No permission'), json: $request->xhr);

		$this->events->dispatch(new PostEditStep(PostEditStep::SELECTED, $post, $moderating));

		$edit = null;
		if (isset($request->post['form_sent']))
		{
			$edit = $this->validate($request, $post, $moderating, $strings);

			if ($edit->errors() === array() && !isset($request->post['preview']))
				return $this->store($request, $edit, $strings);
		}

		return $this->page($request, $post, $moderating, $edit, $strings);
	}

	private function moderates(EditablePostInterface $post): bool {
		foreach ($post->moderators() as $moderator)
			if ($moderator->username() === $this->visitor->username())
				return true;

		return false;
	}

	/**
	 * The submitted subject and message, checked and tidied, with what stops the edit.
	 *
	 * @param array<string, Html> $strings
	 */
	private function validate(Request $request, EditablePostInterface $post, bool $moderating, array $strings): PostEditStep {
		$submitted = new PostEditStep(PostEditStep::SUBMITTED, $post, $moderating);
		$this->events->dispatch($submitted);

		$errors = $submitted->errors();

		$subject = null;
		if ($post->isTopic())
		{
			$subject = self::text($request->post['req_subject'] ?? null);

			if ($subject === '')
				$errors[] = self::string($strings, 'No subject')->html;
			else if (mb_strlen($subject) > $this->rules->subjectMaximumLength())
				$errors[] = Html::format(self::string($strings, 'Too long subject'), $this->rules->subjectMaximumLength())->html;
			else if ($this->settings->value('p_subject_all_caps') === '0' && self::allCaps($subject) && !$moderating)
				$subject = self::capitalise($subject);
		}

		$message = str_replace(array("\r\n", "\r"), "\n", self::text($request->post['req_message'] ?? null));

		if (strlen($message) > $this->rules->messageMaximumBytes())
			$errors[] = Html::format(self::string($strings, 'Too long message'), $this->formatter->number(strlen($message)), $this->formatter->number($this->rules->messageMaximumBytes()))->html;
		else if ($this->settings->value('p_message_all_caps') === '0' && self::allCaps($message) && !$moderating)
			$message = self::capitalise($message);

		if ($this->settings->enabled('p_message_bbcode') || $this->settings->enabled('o_make_links'))
		{
			$preparsed = $this->rules->preparse($message, array_map(static fn (string $error): Html => new Html($error), $errors));
			$message = $preparsed->text;
			$errors = array_map(static fn (Html $error): string => $error->html, $preparsed->errors);
		}

		if ($message === '')
			$errors[] = self::string($strings, 'No message')->html;

		$validated = new PostEditStep(PostEditStep::VALIDATED, $post, $moderating, $subject, $message, isset($request->post['hide_smilies']), $errors);
		$this->events->dispatch($validated);

		return $validated;
	}

	/** @param array<string, Html> $strings */
	private function store(Request $request, PostEditStep $validated, array $strings): Response {
		$post = $validated->post();

		$this->events->dispatch(new PostEditStep(PostEditStep::EDITING, $post, $validated->moderating(), $validated->subject(), $validated->message(), $validated->hidesSmilies()));

		$silent = isset($request->post['silent']) && $validated->moderating();
		$edit = new PostEdit($post->id(), $post->topicId(), $post->isTopic() ? $validated->subject() ?? '' : null, $validated->message(), $validated->hidesSmilies(),
			$silent ? null : time(), $silent ? null : $this->visitor->username());

		if ($edit->subject() !== null)
			$this->posts->renameTopic($edit);

		$this->index->update($post->id(), $edit->message(), $edit->subject());
		$this->posts->saveMessage($edit);

		$this->events->dispatch(new PostEditStep(PostEditStep::EDITED, $post, $validated->moderating(), $edit->subject(), $edit->message(), $edit->hidesSmilies()));

		return $this->redirects->respond($this->urls->link('post', array($post->id()))->html, self::string($strings, 'Edit redirect'), $request->xhr);
	}

	/**
	 * The form, with the preview or the errors of what was submitted.
	 *
	 * @param ?PostEditStep $edit what was submitted, checked; null when nothing was
	 * @param array<string, Html> $strings
	 */
	private function page(Request $request, EditablePostInterface $post, bool $moderating, ?PostEditStep $edit, array $strings): Response {
		$common = $this->language->strings('common');
		$action = $this->urls->link('edit', array($post->id()));

		$hidden = new Parts(array(
			'form_sent'		=> '<input type="hidden" name="form_sent" value="1" />',
			'csrf_token'	=> Html::format('<input type="hidden" name="csrf_token" value="%s" />', $this->tokens->token($action->html))->html,
		));

		$textOptions = new Parts();
		foreach (array('bbcode' => array('p_message_bbcode', 'BBCode'), 'img' => array('p_message_img_tag', 'Images'), 'smilies' => array('o_smilies', 'Smilies')) as $section => [$setting, $label])
			if ($this->settings->enabled($setting))
				$textOptions->set($section, Html::format('<span%s><a class="exthelp" href="%s" title="%s">%s</a></span>', new Html($textOptions->isEmpty() ? ' class="first-item"' : ''),
					$this->urls->link('help', array($section)), Html::format(self::string($common, 'Help page'), self::string($common, $label)), self::string($common, $label))->html);

		$errors = new Parts();
		foreach ($edit?->errors() ?? array() as $number => $error)
			$errors->set((string) $number, '<li><span>'.$error.'</span></li>');

		$editing = self::string($strings, $post->isTopic() ? 'Edit topic' : 'Edit reply');

		$head = new PageHead('postedit', array(
			new Crumb($this->settings->value('o_board_title'), $this->urls->link('index')),
			new Crumb($post->forumName(), $this->urls->link('forum', array($post->forumId(), $this->urls->slug($post->forumName())))),
			new Crumb($post->subject(), $this->urls->link('topic', array($post->topicId(), $this->urls->slug($post->subject())))),
			new Crumb($editing->html),
		));

		$submittedSubject = $request->post['req_subject'] ?? null;

		$view = new EditView($post, $action->html, array(
			'post'				=> $strings,
			'common'			=> $common,
			'heading'			=> $editing,
			'compose'			=> self::string($strings, $post->isTopic() ? 'Compose edited topic' : 'Compose edited reply'),
			'action'			=> $action,
			'canEditSubject'	=> $post->isTopic(),
			'subjectLength'		=> $this->rules->subjectMaximumLength(),
			'subject'			=> $submittedSubject !== null ? (is_scalar($submittedSubject) ? (string) $submittedSubject : '') : $post->subject(),
			'message'			=> $edit?->message() ?? $post->message(),
			'submit'			=> self::string($strings, $post->isTopic() ? 'Submit topic' : 'Submit reply'),
			'previewLabel'		=> self::string($strings, $post->isTopic() ? 'Preview topic' : 'Preview reply'),
		));

		return $this->pages->respond($head, fn (): array => array('main' => $this->main($request, $view, $post, $moderating, $edit, $hidden, $textOptions, $errors, $strings)));
	}

	/** @param array<string, Html> $strings */
	private function main(Request $request, EditView $view, EditablePostInterface $post, bool $moderating, ?PostEditStep $edit, Parts $hidden, Parts $textOptions, Parts $errors, array $strings): Html {
		$start = $view->rendering(EditRendering::MAIN_OUTPUT_START, $hidden, new Parts(), $textOptions, $errors);
		$this->events->dispatch($start);
		$view->place($start);

		$view->show('hidden', self::joined($start, EditRendering::HIDDEN_FIELDS, "\n\t\t\t\t"));
		$view->show('attributes', $start->names(EditRendering::FORM_ATTRIBUTES) !== array() ? new Html(' '.self::joined($start, EditRendering::FORM_ATTRIBUTES, ' ')->html) : new Html(''));
		$view->show('textOptions', $start->names(EditRendering::TEXT_OPTIONS) !== array() ? Html::format($this->language->text('common', 'You may use'), self::joined($start, EditRendering::TEXT_OPTIONS, ' ')) : null);
		$view->show('errors', $start->names(EditRendering::ERRORS) !== array() ? self::joined($start, EditRendering::ERRORS, "\n\t\t\t\t") : null);

		$preview = null;
		if ($edit !== null && isset($request->post['preview']) && $start->names(EditRendering::ERRORS) === array())
		{
			$assembling = new EditPreviewAssembling($post, array(
				'num'		=> '<span class="post-num">#</span>',
				'byline'	=> Html::format('<span class="post-byline">%s</span>', Html::format(self::string($strings, $post->isTopic() ? 'Topic byline' : 'Reply byline'), Html::format('<strong>%s</strong>', $post->poster())))->html,
				'link'		=> Html::format('<span class="post-link">%s</span>', $this->formatter->time(time(), TimeFormat::DateTime))->html,
			), $this->formatter->message($edit->message(), $edit->hidesSmilies())->html);
			$this->events->dispatch($assembling);

			$ident = array();
			foreach ($assembling->names() as $name)
				$ident[] = (string) $assembling->entry($name);

			$this->at($view, EditRendering::PREVIEW_NEW_POST_HEAD_OPTION);
			$this->at($view, EditRendering::PREVIEW_NEW_POST_ENTRY_DATA);

			$preview = array(
				'before'	=> new Html($assembling->markup()),
				'heading'	=> self::string($strings, $post->isTopic() ? 'Preview edited topic' : 'Preview edited reply'),
				'ident'		=> new Html(implode(' ', $ident)),
				'message'	=> new Html($assembling->message()),
			);
		}

		$view->show('preview', $preview);

		$this->at($view, EditRendering::PRE_MAIN_FIELDSET);
		$view->numberGroup('group');

		$this->at($view, EditRendering::PRE_SUBJECT);
		if ($post->isTopic())
		{
			$view->numberItem('subject_item');
			$view->numberField('subject_field');
		}

		$this->at($view, EditRendering::PRE_MESSAGE_BOX);
		$view->numberItem('message_item');
		$view->numberField('message_field');

		$checkboxes = array();
		if ($this->settings->enabled('o_smilies'))
			$checkboxes['hide_smilies'] = self::checkbox($view->numberField('hide_smilies'), 'hide_smilies', isset($request->post['hide_smilies']) || $post->hidesSmilies(), self::string($strings, 'Hide smilies'));

		if ($moderating)
			$checkboxes['silent'] = self::checkbox($view->numberField('silent'), 'silent', $edit === null || isset($request->post['silent']), self::string($strings, 'Silent edit'));

		$assembling = $view->checkboxes($checkboxes);
		$this->events->dispatch($assembling);
		$view->placeCheckboxes($assembling);

		$boxes = array();
		foreach ($assembling->names() as $name)
			$boxes[] = (string) $assembling->entry($name);

		$view->show('checkboxesBefore', new Html($assembling->markup()));
		$view->show('checkboxes', $boxes !== array() ? new Html(implode("\n\t\t\t\t\t", $boxes)) : null);

		if ($boxes !== array())
		{
			$view->numberItem('checkboxes_item');
			$this->at($view, EditRendering::PRE_CHECKBOX_FIELDSET_END);
		}

		$this->at($view, EditRendering::PRE_MAIN_FIELDSET_END);
		$this->at($view, EditRendering::MAIN_FIELDSET_END);

		$body = $this->templates->render(self::TEMPLATE, $view->variables());

		$end = $view->rendering(EditRendering::END);
		$this->events->dispatch($end);

		return (new Html($start->markup().$body.$end->markup()))->trim();
	}

	private function at(EditView $view, string $position): void {
		$event = $view->rendering($position);
		$this->events->dispatch($event);
		$view->place($event);
	}

	private static function checkbox(int $field, string $name, bool $checked, Html $label): string {
		return Html::format('<div class="mf-item"><span class="fld-input"><input type="checkbox" id="fld%s" name="%s" value="1"%s /></span> <label for="fld%s">%s</label></div>',
			$field, $name, new Html($checked ? ' checked="checked"' : ''), $field, $label)->html;
	}

	/** The parts of $group, joined with $glue. */
	private static function joined(EditRendering $event, string $group, string $glue): Html {
		$parts = array();
		foreach ($event->names($group) as $name)
			$parts[] = (string) $event->entry($group, $name);

		return new Html(implode($glue, $parts));
	}

	/** Whether $text is written in capitals only, with at least one letter that has a lower case; compared as check_is_all_caps() compares, loosely. */
	private static function allCaps(string $text): bool {
		return mb_strtoupper($text) == $text && mb_strtolower($text) != $text;
	}

	/** $text in lower case with each word capitalised, as the board tones down a shout. */
	private static function capitalise(string $text): string {
		return (string) preg_replace_callback(self::WORD, static fn (array $match): string => $match[2].mb_strtoupper($match[3]).mb_substr(ltrim($match[0]), 1), mb_strtolower($text));
	}

	/** A value the request carries as text, trimmed; anything else is empty. */
	private static function text(mixed $value): string {
		return is_string($value) ? (new Html($value))->trim()->html : '';
	}

	/** @param array<string, Html> $strings */
	private static function string(array $strings, string $key): Html {
		return $strings[$key] ?? new Html('');
	}
}
