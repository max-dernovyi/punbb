<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Page\Post;

use PunBB\Module\LegacyBridge\Layout\LegacyScope;
use PunBB\Module\LegacyBridge\Layout\Markers;
use PunBB\Module\LegacyBridge\Page\PageScope;
use PunBB\Module\Post\Event\PostingStep;

/**
 * Runs the point at each step of posting, with the post in the variables
 * post.php held it in: $errors, read back until the form is checked; once it
 * is $username, $email, $subject for a new topic, $message, $hide_smilies,
 * $subscribe and $now, read back; $post_info, read back before the post is
 * stored, and $new_pid and $new_tid once it is.
 */
final class PostingStepObserver {
	public function __construct(private readonly PageScope $scope) {}

	public function observe(PostingStep $event): void {
		$reply = $event->location()->topicId() > 0;

		$point = match ($event->step()) {
			PostingStep::SELECTED	=> 'po_posting_location_selected',
			PostingStep::SUBMITTED	=> 'po_form_submitted',
			PostingStep::VALIDATED	=> 'po_end_validation',
			PostingStep::ADDING		=> $reply ? 'po_pre_add_post' : 'po_pre_add_topic',
			default					=> 'po_pre_redirect',
		};

		if ($event->step() === PostingStep::SELECTED)
		{
			$this->scope->observe($point, $event);
			return;
		}

		$GLOBALS['errors'] = $event->errors();

		if ($event->step() === PostingStep::VALIDATED)
		{
			if (!$reply)
				$GLOBALS['subject'] = $event->subject() ?? '';

			$GLOBALS['username'] = $event->username();
			$GLOBALS['email'] = $event->email();
			$GLOBALS['message'] = $event->message();
			$GLOBALS['hide_smilies'] = $event->hidesSmilies() ? 1 : 0;
			$GLOBALS['subscribe'] = $event->subscribes() ? 1 : 0;
			$GLOBALS['now'] = time();
		}

		$post = $event->post();
		if ($post !== null)
			$GLOBALS['post_info'] = NewPostRows::row($post, self::global('post_info'));

		if ($event->step() === PostingStep::ADDED)
		{
			$GLOBALS['new_pid'] = $event->postId();
			if (!$reply)
				$GLOBALS['new_tid'] = $event->topicId();
		}

		if (!LegacyScope::attached($point))
			return;

		$this->scope->observe($point, $event);

		if ($event->step() === PostingStep::VALIDATED)
			$event->change(Markers::markup(self::global('username')), Markers::markup(self::global('email')), $reply ? null : Markers::markup(self::global('subject')),
				Markers::markup(self::global('message')), !empty($GLOBALS['hide_smilies']), !empty($GLOBALS['subscribe']));

		if (in_array($event->step(), array(PostingStep::SUBMITTED, PostingStep::VALIDATED), true))
			$event->setErrors(array_values(Markers::entries(self::global('errors'))));

		if ($event->step() === PostingStep::ADDING && $post !== null)
			$event->replacePost(NewPostRows::post(self::global('post_info'), $post));
	}

	/** The global $name as extension code left it, which may have unset it. */
	private static function global(string $name): mixed {
		return $GLOBALS[$name] ?? null;
	}
}
