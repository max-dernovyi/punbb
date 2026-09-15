<?php
/**
 * edit.php as a module, built with no forum: who may edit which post, the
 * form for a reply and for a topic, the numbers of its fields and its
 * checkboxes as observers add some, the preview and the errors of what was
 * submitted, and what a valid edit stores and where it sends the visitor.
 *
 * @copyright (C) 2008-2012 PunBB, partially based on code (C) 2008-2009 FluxBB.org
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package PunBB
 */

use PHPUnit\Framework\TestCase;
use PunBB\Module\Edit\Api\Data\EditablePostInterface;
use PunBB\Module\Edit\Api\Data\PostEditInterface;
use PunBB\Module\Edit\Api\EditablePostsInterface;
use PunBB\Module\Edit\Controller\EditController;
use PunBB\Module\Edit\Event\EditCheckboxesAssembling;
use PunBB\Module\Edit\Event\EditPermissionChecking;
use PunBB\Module\Edit\Event\EditPreviewAssembling;
use PunBB\Module\Edit\Event\EditRendering;
use PunBB\Module\Edit\Event\EditRequested;
use PunBB\Module\Edit\Event\PostEditStep;
use PunBB\Module\Edit\Indexing\EditIndexInterface;
use PunBB\Module\Edit\Model\EditablePost;
use PunBB\Module\Edit\Model\Moderator;
use PunBB\Module\Framework\Http\Request;
use PunBB\Module\Layout\View\Html;
use PunBB\Module\Layout\View\TemplateRenderer;
use PunBB\Module\Message\Event\MessageRendering;
use PunBB\Module\Message\Event\MessageShowing;
use PunBB\Module\Message\Event\RedirectHeadAssembling;
use PunBB\Module\Message\Event\RedirectShowing;
use PunBB\Module\Site\Posting\PostRulesInterface;
use PunBB\Module\Site\Posting\PreparsedMessage;
use PunBB\Module\Site\Visitor\GroupPermission;

require_once __DIR__.'/PageFakes.php';

final class FakeEditablePosts implements EditablePostsInterface, EditIndexInterface, PostRulesInterface {
	/** @var array<int, EditablePostInterface> */
	public array $posts = array();

	/** @var list<string> */
	public array $log = array();

	public function find(int $postId, int $groupId): ?EditablePostInterface {
		return $this->posts[$postId] ?? null;
	}

	public function renameTopic(PostEditInterface ...$edits): void {
		foreach ($edits as $edit)
			$this->log[] = 'rename '.$edit->topicId().' '.$edit->subject();
	}

	public function saveMessage(PostEditInterface ...$edits): void {
		foreach ($edits as $edit)
			$this->log[] = 'save '.$edit->postId().' '.$edit->message().' '.(int) $edit->hidesSmilies().' '.($edit->editedAt() !== null ? 'by '.$edit->editedBy() : 'silently');
	}

	public function update(int $postId, string $message, ?string $subject): void {
		$this->log[] = 'index '.$postId.' '.$message.' '.var_export($subject, true);
	}

	public function subjectMaximumLength(): int {
		return 20;
	}

	public function messageMaximumBytes(): int {
		return 40;
	}

	public function preparse(string $text, array $errors): PreparsedMessage {
		if ($errors === array() && str_contains($text, '[bad]'))
			$errors[] = new Html('Bad <b>tag</b>');

		return new PreparsedMessage(str_replace('[B]', '[b]', $text), $errors);
	}

	public function preparseSignature(string $text, array $errors): PreparsedMessage {
		return $this->preparse($text, $errors);
	}
}

class EditControllerTest extends TestCase {
	private PageKit $kit;

	private FakeEditablePosts $posts;

	protected function setUp(): void {
		$this->kit = new PageKit(array(EditRequested::class, EditPermissionChecking::class, PostEditStep::class, EditPreviewAssembling::class, EditRendering::class, EditCheckboxesAssembling::class,
			MessageShowing::class, MessageRendering::class, RedirectShowing::class, RedirectHeadAssembling::class));
		$this->kit->language->real = array('post', 'common');
		$this->kit->settings->values += array('o_redirect_delay' => '0', 'o_smilies' => '1', 'p_message_bbcode' => '1', 'p_message_img_tag' => '0', 'p_subject_all_caps' => '0', 'p_message_all_caps' => '0');
		$this->kit->visitor->permissions = array(GroupPermission::ReadBoard, GroupPermission::EditPosts);

		$this->posts = new FakeEditablePosts();
		$this->posts->posts = array(
			5 => new EditablePost(5, 1, 'Forum & 1', array(new Moderator(7, 'mod')), 2, 'Topic <2>', 4, false, 'member', 3, 'Reply <text>', false),
			4 => new EditablePost(4, 1, 'Forum & 1', array(), 2, 'Topic <2>', 4, false, 'member', 3, 'Opening', true),
			6 => new EditablePost(6, 1, 'Forum & 1', array(new Moderator(3, 'member')), 8, 'Closed', 6, true, 'someone', 9, 'Theirs', false),
		);
	}

	private function page(array $query, array $post = array()): string {
		$controller = new EditController($this->kit->dispatcher, $this->kit->pages(), new TemplateRenderer(), $this->kit->messages(), $this->kit->redirects(), $this->posts, $this->posts,
			$this->kit->visitor, $this->kit->language, $this->kit->settings, $this->kit->urls, $this->kit->formatter, $this->kit->tokens, $this->posts);

		$response = $controller->handle(new Request($post !== array() ? 'POST' : 'GET', '/', 'edit.php', $query, $post));

		return $response->status.' '.($response->headers['Location'] ?? '').' '.$response->body;
	}

	public function testOnlyItsPosterEditsAnOpenPostAndAModeratorAnyPost(): void {
		$this->assertStringContainsString('<p>Bad request.', $this->page(array('id' => '99')));
		$this->assertStringContainsString('<p>You do not have permission to access this page.</p>', $this->page(array('id' => '6')));

		$this->kit->visitor->permissions[] = GroupPermission::Moderate;
		$this->assertStringContainsString('<input type="checkbox" id="fld4" name="silent" value="1" checked="checked" />', $this->page(array('id' => '6')), 'a moderator of the forum');

		$this->kit->visitor->permissions = array(GroupPermission::ReadBoard);
		$this->assertStringContainsString('<p>You do not have permission to access this page.</p>', $this->page(array('id' => '5')), 'a group that may not edit');
		$this->assertSame(array(), $this->posts->log);
	}

	public function testAReplysFormHasItsMessageAndTheSmiliesCheckbox(): void {
		$body = $this->page(array('id' => '5'));

		$head = $this->kit->chromes->opened[0];
		$this->assertSame('postedit', $head->id);
		$this->assertSame(array('Board & Co', 'Forum & 1', 'Topic <2>', 'Edit reply'), array_map(static fn ($crumb): string => $crumb->text, $head->crumbs));

		$action = '/edit/5?a=1&amp;b=2';
		$this->assertStringStartsWith("200  [postedit]<div class=\"main-head\">\n\t\t<h2 class=\"hn\"><span>Edit reply</span></h2>\n\t</div>\n\t<div class=\"main-subhead\">\n\t\t<h2 class=\"hn\"><span>Compose and post your edited reply</span></h2>", $body);
		$this->assertStringContainsString('<p class="ct-options options">You may use: <span class="first-item"><a class="exthelp" href="/help/bbcode?a=1&amp;b=2" title="Help with: BBCode">BBCode</a></span> <span><a class="exthelp" href="/help/smilies?a=1&amp;b=2" title="Help with: Smilies">Smilies</a></span></p>', $body);
		$this->assertStringContainsString("action=\"$action\">\n\t\t\t<div class=\"hidden\">\n\t\t\t\t<input type=\"hidden\" name=\"form_sent\" value=\"1\" />\n\t\t\t\t<input type=\"hidden\" name=\"csrf_token\" value=\"token-for-".md5($action).'" />', $body);
		$this->assertStringContainsString("<legend class=\"group-legend\"><strong>Edit message</strong></legend>\n\t\t\t\t<div class=\"txt-set set1\">", $body);
		$this->assertStringContainsString('<textarea id="fld1" name="req_message" rows="15" cols="95" required spellcheck="true">Reply &lt;text&gt;</textarea>', $body);
		$this->assertStringContainsString("<fieldset class=\"mf-set set2\">\n\t\t\t\t\t<div class=\"mf-box checkbox\">\n\t\t\t\t\t\t<div class=\"mf-item\"><span class=\"fld-input\"><input type=\"checkbox\" id=\"fld2\" name=\"hide_smilies\" value=\"1\" /></span> <label for=\"fld2\">Never show smilies as icons for this post.</label></div>\n\t\t\t\t\t</div>\n\t\t\t\t</fieldset>", $body);
		$this->assertStringContainsString('<input type="submit" name="submit_button" value="Submit reply" />', $body);
		$this->assertStringNotContainsString('req_subject', $body);
		$this->assertSame(array('EditRequested', 'EditPermissionChecking', 'PostEditStep', 'EditRendering:main_output_start', 'EditRendering:pre_main_fieldset', 'EditRendering:pre_subject',
			'EditRendering:pre_message_box', 'EditCheckboxesAssembling', 'EditRendering:pre_checkbox_fieldset_end', 'EditRendering:pre_main_fieldset_end', 'EditRendering:main_fieldset_end', 'EditRendering:end'), $this->kit->events->dispatched);
	}

	public function testATopicsFormHasItsSubjectOnTheLineTheMessageStarts(): void {
		$body = $this->page(array('id' => '4'));

		$this->assertStringContainsString("\t\t\t\t<div class=\"sf-set set1\">\n\t\t\t\t\t<div class=\"sf-box text required\">\n\t\t\t\t\t\t<label for=\"fld1\"><span>Topic subject</span></label><br />", $body);
		$this->assertStringContainsString('<input id="fld1" type="text" name="req_subject" size="20" maxlength="20" value="Topic &lt;2&gt;" required />', $body);
		$this->assertStringContainsString("</div>\n\t\t\t\t</div>\n\t\t\t\t<div class=\"txt-set set2\">", $body);
		$this->assertStringContainsString('<input type="checkbox" id="fld3" name="hide_smilies" value="1" checked="checked" />', $body);
		$this->assertSame(array('Board & Co', 'Forum & 1', 'Topic <2>', 'Edit topic'), array_map(static fn ($crumb): string => $crumb->text, $this->kit->chromes->opened[0]->crumbs));
	}

	public function testObserversChangeTheFormsPartsTheNumbersAndTheCheckboxes(): void {
		$this->kit->events->observe(EditRendering::class, function (EditRendering $event): void {
			if ($event->position() === EditRendering::MAIN_OUTPUT_START)
			{
				$event->set(EditRendering::HIDDEN_FIELDS, 'probe', '<input type="hidden" name="probe" />');
				$event->set(EditRendering::FORM_ATTRIBUTES, 'probe', 'data-probe="1"');
				$event->remove(EditRendering::TEXT_OPTIONS, 'smilies');
			}

			if ($event->position() === EditRendering::PRE_MESSAGE_BOX)
			{
				$event->append('<!-- probe -->');
				$event->count($event->groupCount(), $event->itemCount() + 1, $event->fieldCount() + 1);
			}
		});
		$this->kit->events->observe(EditCheckboxesAssembling::class, function (EditCheckboxesAssembling $event): void {
			$event->set('probe', '<div class="mf-item"><input id="fld'.($event->fieldCount() + 1).'" /></div>');
			$event->count($event->groupCount(), $event->itemCount(), $event->fieldCount() + 1);
		});

		$body = $this->page(array('id' => '5'));

		$this->assertStringContainsString('action="/edit/5?a=1&amp;b=2" data-probe="1">', $body);
		$this->assertStringContainsString("\n\t\t\t\t<input type=\"hidden\" name=\"probe\" />\n\t\t\t</div>", $body);
		$this->assertStringNotContainsString('section=smilies', $body);
		$this->assertStringNotContainsString('/help/smilies', $body);
		$this->assertStringContainsString("<!-- probe -->\t\t\t\t<div class=\"txt-set set2\">", $body);
		$this->assertStringContainsString('<textarea id="fld2"', $body);
		$this->assertStringContainsString("<div class=\"mf-item\"><input id=\"fld4\" /></div>\n\t\t\t\t\t</div>", $body);
		$this->assertStringContainsString('<fieldset class="mf-set set3">', $body);
	}

	public function testAPreviewShowsTheCheckedMessageAboveTheForm(): void {
		$body = $this->page(array('id' => '5'), array('form_sent' => '1', 'preview' => '1', 'req_message' => "  A [B]reply[/B]\r\nhere  ", 'hide_smilies' => '1'));

		$this->assertStringContainsString("<div class=\"main-subhead\">\n\t\t<h2 class=\"hn\"><span>Preview of your edited reply</span></h2>", $body);
		$this->assertStringContainsString('<h3 class="hn"><span class="post-num">#</span> <span class="post-byline"><span>Reply by </span><strong>member</strong></span> <span class="post-link"><time>', $body);
		$this->assertStringContainsString("<div class=\"entry-content\">\n\t\t\t\t\t\t<p>A [b]reply[/B]\nhere (no smilies)</p>\n\t\t\t\t\t</div>", $body);
		$this->assertStringContainsString("spellcheck=\"true\">A [b]reply[/B]\nhere</textarea>", $body);
		$this->assertStringContainsString('name="hide_smilies" value="1" checked="checked" />', $body);
		$this->assertSame(array(), $this->posts->log);
	}

	public function testWhatStopsAnEditIsListedAndStoresNothing(): void {
		$this->kit->events->observe(PostEditStep::class, function (PostEditStep $event): void {
			if ($event->step() === PostEditStep::VALIDATED)
				$event->setErrors(array_merge($event->errors(), array('Probe <em>error</em>')));
		});

		$body = $this->page(array('id' => '4'), array('form_sent' => '1', 'req_subject' => str_repeat('s', 21), 'req_message' => str_repeat('m', 41)));

		$this->assertStringContainsString("<ul class=\"error-list\">\n\t\t\t\t<li><span>Subjects cannot be longer than 20 characters.</span></li>\n\t\t\t\t<li><span>Your post length is 41 bytes. This exceeds the 40 bytes limit.</span></li>\n\t\t\t\t<li><span>Probe <em>error</em></span></li>\n\t\t\t</ul>", $body);
		$this->assertStringContainsString('value="'.str_repeat('s', 21).'" required />', $body, 'the subject as it was submitted');
		$this->assertStringNotContainsString('post-preview', $body);
		$this->assertSame(array(), $this->posts->log);

		$this->assertStringContainsString('<li><span>Bad <b>tag</b></span></li>', $this->page(array('id' => '5'), array('form_sent' => '1', 'req_message' => '[bad]')));
		$this->assertStringContainsString('<li><span>You must enter a message.</span></li>', $this->page(array('id' => '5'), array('form_sent' => '1', 'req_message' => '   ')));
	}

	public function testAnEditRenamesTheTopicReindexesAndStoresTheMessage(): void {
		$steps = array();
		$this->kit->events->observe(PostEditStep::class, function (PostEditStep $event) use (&$steps): void {
			$steps[] = $event->step().' '.var_export($event->subject(), true).' '.count($this->posts->log);
		});

		$response = $this->page(array('id' => '4'), array('form_sent' => '1', 'req_subject' => 'SHOUTED SUBJECT', 'req_message' => 'QUIET NOW'));

		$this->assertStringStartsWith('302 /post/4?a=1&b=2 [redirect]', $response);
		$this->assertSame(array('rename 2 Shouted Subject', "index 4 Quiet Now 'Shouted Subject'", 'save 4 Quiet Now 0 by member'), $this->posts->log);
		$this->assertSame(array('selected NULL 0', 'submitted NULL 0', "validated 'Shouted Subject' 0", "editing 'Shouted Subject' 0", "edited 'Shouted Subject' 3"), $steps);
	}

	public function testAModeratorsSilentEditLeavesTheLastEditAndTheirCapitals(): void {
		$this->kit->visitor->permissions[] = GroupPermission::Moderate;

		$this->page(array('id' => '6'), array('form_sent' => '1', 'req_subject' => 'CLOSED', 'req_message' => 'LOUD', 'silent' => '1'));
		$this->page(array('id' => '6'), array('form_sent' => '1', 'req_subject' => 'CLOSED', 'req_message' => 'LOUD'));

		$this->assertSame(array('rename 8 CLOSED', "index 6 LOUD 'CLOSED'", 'save 6 LOUD 0 silently', 'rename 8 CLOSED', "index 6 LOUD 'CLOSED'", 'save 6 LOUD 0 by member'), $this->posts->log);
	}
}
