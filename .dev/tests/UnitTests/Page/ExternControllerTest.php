<?php
/**
 * extern.php as a module, built with no forum: who is refused and asked to
 * sign in, a feed of topics and of a topic's posts in each format, what a
 * feed shows of its authors, the forums a feed is narrowed to, who is online,
 * the statistics, an unknown action, and what observers change on the way.
 *
 * @copyright (C) 2008-2012 PunBB, partially based on code (C) 2008-2009 FluxBB.org
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package PunBB
 */

use PHPUnit\Framework\TestCase;
use PunBB\Module\Extern\Api\Data\FeedTopicInterface;
use PunBB\Module\Extern\Api\Data\StatisticsInterface;
use PunBB\Module\Extern\Api\SyndicationInterface;
use PunBB\Module\Extern\Authentication\BasicAuthenticationInterface;
use PunBB\Module\Extern\Controller\ExternController;
use PunBB\Module\Extern\Event\ExternActionRequested;
use PunBB\Module\Extern\Event\ExternRequested;
use PunBB\Module\Extern\Event\FeedAssembling;
use PunBB\Module\Extern\Event\FeedRendering;
use PunBB\Module\Extern\Event\FeedRequested;
use PunBB\Module\Extern\Event\OnlineListAssembling;
use PunBB\Module\Extern\Event\StatisticsShowing;
use PunBB\Module\Extern\Model\FeedEntry;
use PunBB\Module\Extern\Model\FeedItem;
use PunBB\Module\Extern\Model\FeedTopic;
use PunBB\Module\Extern\Model\OnlineVisitor;
use PunBB\Module\Extern\Model\Statistics;
use PunBB\Module\Framework\Http\Request;
use PunBB\Module\Layout\View\TemplateRenderer;
use PunBB\Module\Site\Visitor\GroupPermission;

require_once __DIR__.'/PageFakes.php';

final class FakeSyndication implements SyndicationInterface {
	/** @var list<string> */
	public array $log = array();

	public ?FeedTopic $topic = null;

	public function topic(int $topicId, int $groupId): ?FeedTopicInterface {
		$this->log[] = 'topic '.$topicId.' '.$groupId;

		return $this->topic?->id() === $topicId ? $this->topic : null;
	}

	public function posts(int $topicId, int $limit): array {
		$this->log[] = 'posts '.$topicId.' '.$limit;

		return array(
			new FeedEntry(9, '', 'member<9>', 3, 2000, 'Re: darn ]]> text', true, 'member@example.com', true, ''),
			new FeedEntry(8, '', 'guest', 1, 1000, 'first', false, '', false, 'guest@example.com'),
		);
	}

	public function forumName(int $forumId, int $groupId): ?string {
		$this->log[] = 'forum '.$forumId.' '.$groupId;

		return $forumId === 2 ? 'Forum <2>' : null;
	}

	public function topics(int $groupId, array $forumIds, bool $excluding, bool $byLastPost, int $limit): array {
		$this->log[] = 'topics '.$groupId.' '.implode(',', $forumIds).' '.(int) $excluding.' '.(int) $byLastPost.' '.$limit;

		return array(
			new FeedEntry(4, 'A subject longer than thirty characters & more', 'anna', 5, 3000, 'text of 4', false, 'anna@example.com', false, ''),
			new FeedEntry(2, 'Short <b>', 'bob', 6, 1500, 'text of 2', false, 'bob@example.com', true, ''),
		);
	}

	public function onlineVisitors(): array {
		return array(new OnlineVisitor(1, '192.0.2.1'), new OnlineVisitor(5, 'anna<5>'), new OnlineVisitor(1, '192.0.2.2'), new OnlineVisitor(6, 'bob'));
	}

	public function statistics(): StatisticsInterface {
		return new Statistics(51, 52, 'newest<52>', 5, 12345);
	}
}

final class FakeBasicAuthentication implements BasicAuthenticationInterface {
	/** @var list<string> */
	public array $signedIn = array();

	public function __construct(private FakeVisitor $visitor) {}

	public function authenticate(string $username, string $password): void {
		$this->signedIn[] = $username.':'.$password;

		if ($password === 'right')
			$this->visitor->guest = false;
	}
}

class ExternControllerTest extends TestCase {
	private PageKit $kit;

	private FakeSyndication $syndication;

	private FakeBasicAuthentication $authentication;

	protected function setUp(): void {
		$this->kit = new PageKit(array(ExternRequested::class, FeedRequested::class, FeedAssembling::class, FeedRendering::class, OnlineListAssembling::class, StatisticsShowing::class, ExternActionRequested::class));
		$this->kit->language->real = array('common', 'index');
		$this->kit->settings->values += array('o_censoring' => '1', 'o_show_version' => '0', 'o_cur_version' => '1.5.0');
		$this->syndication = new FakeSyndication();
		$this->syndication->topic = new FeedTopic(7, 'Topic & co', 8);
		$this->authentication = new FakeBasicAuthentication($this->kit->visitor);
	}

	/**
	 * @param array<string, mixed> $query
	 * @return array{int, array<string, string>, string}
	 */
	private function extern(array $query, ?string $user = null, ?string $password = null): array {
		$controller = new ExternController($this->kit->dispatcher, new TemplateRenderer(), $this->syndication, $this->authentication,
			$this->kit->visitor, $this->kit->language, $this->kit->settings, $this->kit->urls, $this->kit->formatter);

		$response = $controller->handle(new Request('GET', '/', 'extern.php', $query, array(), array(), false, $user, $password));

		return array($response->status, $response->headers, $response->body);
	}

	public function testAGuestWhoMayNotReadIsAskedToSignInAndAMemberRefused(): void {
		$this->kit->visitor->guest = true;
		$this->kit->visitor->permissions = array();

		$this->assertSame(array(401, array('WWW-Authenticate' => 'Basic realm="Board & Co External Syndication"'), 'You do not have permission to view these forums.'), $this->extern(array()));

		$this->kit->visitor->guest = false;
		$this->assertSame(array(200, array(), 'You do not have permission to view these forums.'), $this->extern(array()));
		$this->assertSame(array(), $this->syndication->log);
	}

	public function testAGuestSendingCredentialsIsSignedInFirst(): void {
		$this->kit->visitor->guest = true;

		$this->extern(array('action' => 'online'), 'reader', 'right');
		$this->extern(array('action' => 'online'), 'reader');

		$this->assertSame(array('reader:right'), $this->authentication->signedIn, 'a member already signed in is not signed in again');

		$this->kit->visitor->guest = true;
		$this->extern(array('action' => 'online'));
		$this->extern(array('action' => 'online'), 'reader');
		$this->assertSame(array('reader:right', 'reader:'), $this->authentication->signedIn);
	}

	public function testTheRecentTopicsAsRss(): void {
		[$status, $headers, $body] = $this->extern(array('type' => 'rss'));

		$this->assertSame(200, $status);
		$this->assertSame(array('Content-Type', 'Expires', 'Cache-Control', 'Pragma'), array_keys($headers));
		$this->assertSame(array('text/xml; charset=utf-8', 'must-revalidate, post-check=0, pre-check=0', 'public'), array($headers['Content-Type'], $headers['Cache-Control'], $headers['Pragma']));

		$this->assertSame("<?xml version=\"1.0\" encoding=\"utf-8\"?>\n<rss version=\"2.0\" xmlns:atom=\"http://www.w3.org/2005/Atom\">\n\t<channel>\n".
			"\t\t<title><![CDATA[Board & Co]]></title>\n\t\t<link>/index?a=1&amp;b=2</link>\n".
			"\t\t<atom:link href=\"http://forum.test/current.php?x=1&amp;y=2\" rel=\"self\" type=\"application/rss+xml\" />\n".
			"\t\t<description><![CDATA[The most recent topics at Board & Co.]]></description>\n".
			"\t\t<lastBuildDate>".gmdate('r', 3000)."</lastBuildDate>\n\t\t<generator>PunBB</generator>\n".
			"\t\t<item>\n\t\t\t<title><![CDATA[A subject longer than thirty characters & more]]></title>\n\t\t\t<link>/topic_new_posts/4/slug-a-subject-longer-than-thirty-characters-more?a=1&amp;b=2</link>\n".
			"\t\t\t<description><![CDATA[<p>text of 4</p>]]></description>\n\t\t\t<author><![CDATA[null@example.com (anna)]]></author>\n".
			"\t\t\t<pubDate>".gmdate('r', 3000)."</pubDate>\n\t\t\t<guid>/topic_new_posts/4/slug-a-subject-longer-than-thirty-characters-more?a=1&amp;b=2</guid>\n\t\t</item>\n".
			"\t\t<item>\n\t\t\t<title><![CDATA[Short <b>]]></title>\n\t\t\t<link>/topic_new_posts/2/slug-short-b-?a=1&amp;b=2</link>\n".
			"\t\t\t<description><![CDATA[<p>text of 2</p>]]></description>\n\t\t\t<author><![CDATA[bob@example.com (bob)]]></author>\n".
			"\t\t\t<pubDate>".gmdate('r', 1500)."</pubDate>\n\t\t\t<guid>/topic_new_posts/2/slug-short-b-?a=1&amp;b=2</guid>\n\t\t</item>\n\t</channel>\n</rss>\n", $body);

		$this->assertSame(array('topics 3  0 0 15'), $this->syndication->log);
	}

	public function testATopicsPostsAsAtom(): void {
		$this->kit->settings->values['o_show_version'] = '1';

		[, $headers, $body] = $this->extern(array('type' => 'atom', 'tid' => '7', 'show' => '3'));

		$this->assertSame('text/xml; charset=utf-8', $headers['Content-Type']);
		$this->assertStringStartsWith("<?xml version=\"1.0\" encoding=\"utf-8\"?>\n<feed xmlns=\"http://www.w3.org/2005/Atom\">\n\t<title type=\"html\"><![CDATA[Board & Co — Topic & co]]></title>\n".
			"\t<link rel=\"self\" href=\"http://forum.test/current.php?x=1&amp;y=2\" />\n\t<updated>".gmdate('Y-m-d\TH:i:s\Z', 2000)."</updated>\n\t<generator version=\"1.5.0\">PunBB</generator>\n\t<id>/topic/7/slug-topic-co?a=1&amp;b=2</id>\n", $body);
		$this->assertStringContainsString("\t\t<entry>\n\t\t\t<title type=\"html\"><![CDATA[Re: Topic & co]]></title>\n\t\t\t<link rel=\"alternate\" href=\"/post/9?a=1&amp;b=2\" />\n".
			"\t\t\t<content type=\"html\"><![CDATA[<p>Re: d*rn ]]&gt; text (no smilies)</p>]]></content>\n\t\t\t<author>\n\t\t\t\t<name><![CDATA[member<9>]]></name>\n".
			"\t\t\t\t<email><![CDATA[member@example.com]]></email>\n\t\t\t\t<uri>/user/3?a=1&amp;b=2</uri>\n\t\t\t</author>\n", $body);
		$this->assertStringContainsString("<title type=\"html\"><![CDATA[Topic & co]]></title>\n\t\t\t<link rel=\"alternate\" href=\"/post/8?a=1&amp;b=2\" />\n\t\t\t<content type=\"html\"><![CDATA[<p>first</p>]]></content>\n".
			"\t\t\t<author>\n\t\t\t\t<name><![CDATA[guest]]></name>\n\t\t\t\t<email><![CDATA[guest@example.com]]></email>\n\t\t\t</author>", $body, 'a guest\'s own address, and no profile');
		$this->assertStringEndsWith("\t\t</entry>\n</feed>\n", $body);
		$this->assertSame(array('topic 7 3', 'posts 7 3'), $this->syndication->log);
	}

	public function testAGuestSeesNoAddressAndTheTopicsAsXml(): void {
		$this->kit->visitor->guest = true;

		[, $headers, $body] = $this->extern(array('type' => 'xml', 'sort' => 'last_post', 'show' => '99'));

		$this->assertSame('application/xml; charset=utf-8', $headers['Content-Type']);
		$this->assertStringStartsWith("<?xml version=\"1.0\" encoding=\"utf-8\"?>\n<source>\n\t<url>/index?a=1&amp;b=2</url>\n\t<topic id=\"4\">\n\t\t<title><![CDATA[A subject longer", $body);
		$this->assertStringContainsString("\t\t<author>\n\t\t\t<name><![CDATA[bob]]></name>\n\t\t\t<uri>/user/6?a=1&amp;b=2</uri>\n\t\t</author>\n\t\t<posted>".gmdate('r', 1500)."</posted>\n\t</topic>\n</source>\n", $body);
		$this->assertStringNotContainsString('<email>', $body);
		$this->assertSame(array('topics 3  0 1 15'), $this->syndication->log, 'sorted by last post, and too many items is the default');
	}

	public function testALinkListCutsLongSubjects(): void {
		[, $headers, $body] = $this->extern(array('type' => 'bogus'));

		$this->assertSame('text/html; charset=utf-8', $headers['Content-Type']);
		$this->assertSame('<li><a href="/topic_new_posts/4/slug-a-subject-longer-than-thirty-characters-more?a=1&amp;b=2" title="A subject longer than thirty characters &amp; more">A subject longer than thi…</a></li>'."\n".
			'<li><a href="/topic_new_posts/2/slug-short-b-?a=1&amp;b=2" title="Short &lt;b&gt;">Short &lt;b&gt;</a></li>'."\n", $body);
	}

	public function testAFeedIsNarrowedToForumsOrExcludesThem(): void {
		[, , $body] = $this->extern(array('type' => 'rss', 'fid' => ' 2 '));
		$this->assertStringContainsString('<title><![CDATA[Board & Co — Forum <2>]]></title>', $body);

		$this->extern(array('type' => 'rss', 'fid' => '2,3'));
		$this->extern(array('type' => 'rss', 'fid' => '9', 'nfid' => '4,x'));

		$this->assertSame(array('forum 2 3', 'topics 3 2 0 0 15', 'topics 3 2,3 0 0 15', 'forum 9 3', 'topics 3 4,0 1 0 15'), $this->syndication->log);
	}

	public function testATopicTheVisitorMayNotReadIsABadRequest(): void {
		$this->kit->visitor->guest = true;

		$this->assertSame(array(401, array('WWW-Authenticate' => 'Basic realm="Board & Co External Syndication"'), 'Bad request. The link you followed is incorrect or outdated.'), $this->extern(array('tid' => '99')));
	}

	public function testWhoIsOnline(): void {
		[, $headers, $body] = $this->extern(array('action' => 'online'));
		$this->assertSame('text/html; charset=utf-8', $headers['Content-type']);
		$this->assertSame("Guests online: 2<br />\nUsers online: 2<br />\n", $body);

		[, , $body] = $this->extern(array('action' => 'online_full'));
		$this->assertSame("Guests online: 2<br />\nUsers online: <a href=\"/user/5?a=1&amp;b=2\">anna&lt;5&gt;</a>, <a href=\"/user/6?a=1&amp;b=2\">bob</a><br />\n", $body);

		$this->kit->visitor->permissions = array(GroupPermission::ReadBoard);
		$this->kit->events->observe(OnlineListAssembling::class, function (OnlineListAssembling $event): void {
			$event->remove('1');
			$event->setCounts($event->guests() + 1, $event->memberCount() - 1);
		});

		[, , $body] = $this->extern(array('action' => 'online_full'));
		$this->assertSame("Guests online: 3<br />\nUsers online: anna&lt;5&gt;<br />\n", $body);
	}

	public function testTheStatistics(): void {
		$this->kit->events->observe(StatisticsShowing::class, function (StatisticsShowing $event): void {
			$event->replace(new Statistics($event->statistics()->userCount() + 1, 52, 'newest<52>', 5, 12345));
		});

		[, , $body] = $this->extern(array('action' => 'stats'));

		$this->assertSame("Total number of registered users: 52<br />\nNewest registered user: <a href=\"/user/52?a=1&amp;b=2\">newest&lt;52&gt;</a><br />\nTotal number of topics: 5<br />\nTotal number of posts: 12&#160;345<br />\n", $body);
	}

	public function testAnUnknownActionIsABadRequestItsObserversHear(): void {
		$heard = array();
		$this->kit->events->observe(ExternActionRequested::class, function (ExternActionRequested $event) use (&$heard): void {
			$heard[] = $event->action();
		});

		$this->assertSame(array(200, array(), 'Bad request. The link you followed is incorrect or outdated.'), $this->extern(array('action' => 'portal')));
		$this->extern(array('action' => array('feed')));

		$this->assertSame(array('portal', ''), $heard);
	}

	public function testObserversChangeTheFormatTheItemsAndAddToTheFeed(): void {
		$this->kit->events->observe(FeedRequested::class, function (FeedRequested $event): void {
			$event->setType('xml');
			$event->setCount(2);
		});
		$this->kit->events->observe(FeedAssembling::class, function (FeedAssembling $event): void {
			if ($event->stage() === FeedAssembling::ITEM && $event->entry()?->id() === 2)
				$event->replaceItems(array_slice($event->items(), 1));

			if ($event->stage() === FeedAssembling::COMPLETE)
			{
				$event->setTitle('Probed');
				$items = $event->items();
				$items[] = new FeedItem(77, 'Added', '/added', '<p>added</p>', 'probe', null, null, 5);
				$event->replaceItems($items);
			}
		});
		$this->kit->events->observe(FeedRendering::class, function (FeedRendering $event): void {
			$event->append('<!-- '.$event->position().' '.($event->item()?->id() ?? count($event->items())).' -->'."\n");
		});

		[, , $body] = $this->extern(array('type' => 'rss'));

		$this->assertStringStartsWith("<?xml version=\"1.0\" encoding=\"utf-8\"?>\n<source>\n\t<url>/index?a=1&amp;b=2</url>\n<!-- xml_info 2 -->\n\t<topic id=\"2\">\n\t\t<title><![CDATA[Short <b>]]></title>\n", $body);
		$this->assertStringContainsString("\t\t<posted>".gmdate('r', 1500)."</posted>\n<!-- xml_item 2 -->\n\t</topic>\n\t<topic id=\"77\">\n\t\t<title><![CDATA[Added]]></title>\n\t\t<link>/added</link>", $body);
		$this->assertStringNotContainsString('<topic id="4">', $body);
		$this->assertSame(array('topics 3  0 0 2'), $this->syndication->log);
	}
}
