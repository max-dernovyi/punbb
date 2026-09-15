<?php
/**
 * What a page module reads about the site, over plain properties, and a chrome
 * that records the heads it opened: enough to build a module's page with no forum.
 *
 * @copyright (C) 2008-2012 PunBB, partially based on code (C) 2008-2009 FluxBB.org
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package PunBB
 */

use PunBB\Module\Framework\Container\Container;
use PunBB\Module\Framework\Event\EventDispatcher;
use PunBB\Module\Framework\Event\EventInterface;
use PunBB\Module\Framework\Event\ObserverDeclaration;
use PunBB\Module\Layout\Chrome\BareChromeInterface;
use PunBB\Module\Layout\Chrome\ChromeFactoryInterface;
use PunBB\Module\Layout\Chrome\ChromeInterface;
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
use PunBB\Module\Site\Url\WebAddress;
use PunBB\Module\Site\Visitor\GroupPermission;
use PunBB\Module\Site\Visitor\TrackedTopics;
use PunBB\Module\Site\Visitor\VisitorInterface;

final class FakeVisitor implements VisitorInterface {
	/** @var list<GroupPermission> */
	public array $permissions = array(GroupPermission::ReadBoard, GroupPermission::ViewUsers, GroupPermission::SearchUsers);

	public bool $moderating = false;

	public bool $administrator = false;

	public bool $guest = false;

	public int $id = 3;

	public int $lastVisit = 1000;

	public TrackedTopics $tracked;

	public function __construct() { $this->tracked = new TrackedTopics(); }

	public function id(): int { return $this->id; }

	public function username(): string { return 'member'; }

	public function groupId(): int { return 3; }

	public function isGuest(): bool { return $this->guest; }

	public function isAdministrator(): bool { return $this->administrator; }

	public function isModerating(): bool { return $this->moderating; }

	public function can(GroupPermission $permission): bool { return in_array($permission, $this->permissions, true); }

	public function language(): string { return 'English'; }

	public function style(): string { return 'Oxygen'; }

	public function lastVisit(): int { return $this->lastVisit; }

	public function trackedTopics(): TrackedTopics { return $this->tracked; }

	public function previousUrl(): string { return 'http://forum.test/before?a=1&b="2"'; }

	public int $topicsPerPage = 25;

	public int $postsPerPage = 25;

	public function topicsPerPage(): int { return $this->topicsPerPage; }

	public function postsPerPage(): int { return $this->postsPerPage; }

	public bool $avatars = true;

	public bool $signatures = true;

	public bool $autoNotify = false;

	/** @var array<int, int> topic id => when readTopic() recorded it */
	public array $read = array();

	public function showsAvatars(): bool { return $this->avatars; }

	public function showsSignatures(): bool { return $this->signatures; }

	public function subscribesOnReply(): bool { return $this->autoNotify; }

	public function readTopic(int $topicId, int $at): void { $this->read[$topicId] = $at; }

	/** @var array<int, int> forum id => when readForum() recorded it */
	public array $readForums = array();

	public function readForum(int $forumId, int $at): void { $this->readForums[$forumId] = $at; }

	public bool $forgotTracked = false;

	public string $address = '192.0.2.7';

	public ?int $loggedAt = 5000;

	public string $email = 'member@example.com';

	public ?int $lastPostAt = null;

	public int $postFlood = 30;

	public function forgetTrackedTopics(): void { $this->forgotTracked = true; }

	public function address(): string { return $this->address; }

	public function loggedAt(): ?int { return $this->loggedAt; }

	public function email(): string { return $this->email; }

	public function lastPostAt(): ?int { return $this->lastPostAt; }

	public function postFloodInterval(): int { return $this->postFlood; }

	public ?int $lastEmailSentAt = null;

	public int $emailFlood = 60;

	public function lastEmailSentAt(): ?int { return $this->lastEmailSentAt; }

	public function emailFloodInterval(): int { return $this->emailFlood; }

	public ?int $lastSearchAt = null;

	public int $searchFlood = 30;

	public function lastSearchAt(): ?int { return $this->lastSearchAt; }

	public function searchFloodInterval(): int { return $this->searchFlood; }
}

final class FakeSettings implements SettingsInterface {
	/** @param array<string, string> $values */
	public function __construct(public array $values = array('o_board_title' => 'Board & Co')) {}

	public function value(string $name): string { return $this->values[$name] ?? ''; }

	public function enabled(string $name): bool { return $this->value($name) === '1'; }

	public function all(): array { return $this->values; }
}

/** Every string is its key in brackets, unless the pack's file under lang/English/ is loaded for it. */
final class FakeLanguage implements LanguageInterface {
	/** @var list<string> */
	public array $real = array();

	public function text(string $pack, string $key): Html {
		return $this->strings($pack)[$key] ?? new Html('['.$key.']');
	}

	public function strings(string $pack): array {
		if (!in_array($pack, $this->real, true))
			return array();

		$strings = (static function (string $__file): array {
			require $__file;

			foreach (get_defined_vars() as $name => $value)
				if (str_starts_with($name, 'lang_'))
					return $value;

			return array();
		})(FORUM_ROOT.'lang/English/'.$pack.'.php');

		return array_map(static fn (string $text): Html => new Html($text), $strings);
	}

	/** @var array<string, string> template name => its file's contents */
	public array $mailTemplates = array();

	/** @var list<string> */
	public array $languages = array('English');

	public function mailTemplate(string $name): string { return $this->mailTemplates[$name] ?? ''; }

	public function available(): array { return $this->languages; }
}

/** A URL is /<name>, its arguments joined by / after it; a sublink adds ~<sub>=<argument>. */
final class FakeUrls implements UrlsInterface {
	public function base(): string { return 'http://forum.test'; }

	public function link(string $name, array $arguments = array()): Html {
		return new Html('/'.$name.($arguments !== array() ? '/'.implode('/', $arguments) : '').'?a=1&amp;b=2');
	}

	/** @var list<string> the names has() denies */
	public array $missing = array();

	public function has(string $name): bool { return !in_array($name, $this->missing, true); }

	public function sublink(string $name, string $sub, int $subArgument, array $arguments = array()): Html {
		return new Html('/'.$name.($arguments !== array() ? '/'.implode('/', $arguments) : '').'~'.$sub.'='.$subArgument);
	}

	public function pagination(int $pages, int $current, string $name, array $arguments = array(), ?Html $separator = null, bool $pageInQuery = false, string $query = ''): Html {
		return new Html('[pages '.$current.' of '.$pages.' at '.$name.$query.($separator !== null ? ' by '.$separator->html : '').($pageInQuery ? ' in query' : '').']');
	}

	public function webAddress(string $url): WebAddress { return new WebAddress('href:'.$url, 'text:'.$url); }

	public function slug(string $text): string { return 'slug-'.strtolower(preg_replace('/[^A-Za-z0-9]+/', '-', $text) ?? ''); }

	public function current(): string { return 'http://forum.test/current.php?x=1&y=2'; }
}

final class FakeFormatter implements FormatterInterface {
	/** @var array<string, string> */
	public array $smilies = array(':)' => 'smile.png', '=)' => 'smile.png', ':(' => 'sad.png');

	public function time(int $timestamp, TimeFormat $format): Html { return new Html('<time>'.$timestamp.'</time>'); }

	public function number(int|float $number, int $decimals = 0): Html { return new Html(number_format($number, $decimals, '.', '&#160;')); }

	public function memberTitle(string $username, string $title, int $posts, ?int $groupId, ?string $groupTitle): Html {
		return new Html($title !== '' ? htmlspecialchars($title) : '[title of '.htmlspecialchars($username).']');
	}

	public function itemsInfo(Html $label, int $first, int $last, int $total, int $pages): Html {
		return new Html($label->html.': '.$first.'-'.$last.'/'.$total.' in '.$pages);
	}

	public function smilies(): array { return $this->smilies; }

	public function message(string $text, bool $hideSmilies): Html { return new Html('<p>'.htmlspecialchars($text).($hideSmilies ? ' (no smilies)' : '').'</p>'); }

	public function censor(string $text): string { return str_ireplace('darn', 'd*rn', $text); }

	public function signature(string $text): Html { return new Html('<em>'.htmlspecialchars($text).'</em>'); }

	public function avatar(int $userId, int $type, int $width, int $height, string $username, bool $fresh = false): Html {
		return new Html($type > 0 ? '<img src="avatar/'.$userId.($fresh ? '?fresh' : '').'" alt="'.htmlspecialchars($username).'" />' : '');
	}

	public function now(TimeFormat $format, string $pattern): Html { return new Html('<now '.$format->name.' '.htmlspecialchars($pattern).'>'); }

	/** @var array<int, string> */
	public array $timeFormats = array(0 => 'H:i:s', 1 => 'H:i');

	/** @var array<int, string> */
	public array $dateFormats = array(0 => 'Y-m-d', 1 => 'd-m-Y');

	public function timeFormats(): array { return $this->timeFormats; }

	public function dateFormats(): array { return $this->dateFormats; }
}

final class FakeCsrfTokens implements CsrfTokensInterface {
	public bool $confirms = true;

	public function token(string $target): string { return 'token-for-'.md5($target); }

	public function matches(mixed $submitted, string $target): bool { return $submitted === $this->token($target); }

	public function confirms(): bool { return $this->confirms; }
}

final class FakeFlashMessages implements FlashMessagesInterface {
	/** @var list<string> */
	public array $info = array();

	public function info(Html $message): void { $this->info[] = $message->html; }
}

/**
 * Records every page it opens; the page closes into its id and its regions, a
 * bare page into its chrome and its regions.
 */
final class FakeChromeFactory implements ChromeFactoryInterface {
	/** @var list<PageHead> */
	public array $opened = array();

	/** @var list<string> the order the chrome was opened and closed in, among the events */
	public ?array $log = null;

	/** @var array<string, Html> the alerts every chrome says the header raised */
	public array $alerts = array();

	/** @var list<string> the inline scripts the pages registered */
	public array $scripts = array();

	public function open(PageHead $head): ChromeInterface {
		$this->opened[] = $head;
		if ($this->log !== null)
			$this->log[] = 'open';

		return new class($head, $this->alerts, $this) implements ChromeInterface {
			/** @param array<string, Html> $alerts */
			public function __construct(private PageHead $head, private array $alerts, private FakeChromeFactory $factory) {}

			public function close(array $content): string {
				return '['.$this->head->id.']'.($content['main'] ?? new Html(''))->html.(isset($content['info']) ? '[info]'.$content['info']->html : '').(isset($content['qpost']) ? '[qpost]'.$content['qpost']->html : '');
			}

			public function alerts(): array {
				return $this->alerts;
			}

			public function inlineScript(string $code): void {
				$this->factory->scripts[] = $code;
			}
		};
	}

	public function bare(string $chrome): BareChromeInterface {
		if ($this->log !== null)
			$this->log[] = 'bare '.$chrome;

		return new class($chrome) implements BareChromeInterface {
			public function __construct(private string $chrome) {}

			public function themeHead(): array {
				return array(new Html('<!-- theme -->'));
			}

			public function stylesheets(): Html {
				return new Html('<link rel="stylesheet" />'."\n");
			}

			public function close(array $regions): string {
				$page = '['.$this->chrome.']';
				foreach ($regions as $name => $markup)
					$page .= '['.$name.']'.$markup->html;

				return $page;
			}
		};
	}
}

/** An event dispatcher whose observers are closures, keyed by the event class they observe. */
final class TestEvents {
	/** @var array<class-string, list<Closure(EventInterface): void>> */
	private array $observers = array();

	/** @var list<string> the short name of every event dispatched, in order */
	public array $dispatched = array();

	/** @param class-string $event */
	public function observe(string $event, Closure $observe): void {
		$this->observers[$event][] = $observe;
	}

	/** @param list<class-string> $events every event the dispatcher is to record */
	public function dispatcher(array $events): EventDispatcher {
		$declarations = array();
		foreach ($events as $event)
		{
			$recorder = new class($this) {
				public function __construct(private TestEvents $events) {}

				public function observe(EventInterface $event): void {
					$this->events->record($event);
				}
			};

			$declarations[] = new ObserverDeclaration('Probe', $event, $recorder::class, fn (): object => $recorder);
		}

		return new EventDispatcher($declarations, new Container(array()));
	}

	public function record(EventInterface $event): void {
		$this->dispatched[] = (new ReflectionClass($event))->getShortName().(method_exists($event, 'position') ? ':'.$event->position() : '');

		foreach ($this->observers[$event::class] ?? array() as $observe)
			$observe($event);
	}
}

/** The services every page module is built from, with their fakes in reach. */
final class PageKit {
	public TestEvents $events;

	public EventDispatcher $dispatcher;

	public FakeChromeFactory $chromes;

	public FakeVisitor $visitor;

	public FakeSettings $settings;

	public FakeLanguage $language;

	public FakeUrls $urls;

	public FakeFormatter $formatter;

	public FakeCsrfTokens $tokens;

	public FakeFlashMessages $flash;

	/** @param list<class-string> $events */
	public function __construct(array $events) {
		$this->events = new TestEvents();
		$this->dispatcher = $this->events->dispatcher($events);
		$this->chromes = new FakeChromeFactory();
		$this->visitor = new FakeVisitor();
		$this->settings = new FakeSettings();
		$this->language = new FakeLanguage();
		$this->urls = new FakeUrls();
		$this->formatter = new FakeFormatter();
		$this->tokens = new FakeCsrfTokens();
		$this->flash = new FakeFlashMessages();
	}

	public function pages(): PageResponder {
		return new PageResponder($this->chromes);
	}

	public function messages(): MessagePage {
		return new MessagePage($this->dispatcher, $this->pages(), new TemplateRenderer(), $this->language, $this->settings, $this->urls);
	}

	public function redirects(): RedirectPage {
		return new RedirectPage($this->dispatcher, $this->chromes, new TemplateRenderer(), $this->language, $this->settings, $this->urls);
	}
}
