<?php

declare(strict_types=1);

namespace PunBB\Module\Misc;

use PunBB\Module\Database\Schema\Column;
use PunBB\Module\Database\Schema\Table;
use PunBB\Module\Database\Schema\TableOwnerInterface;
use PunBB\Module\Database\Sql\Connection;
use PunBB\Module\Database\Sql\Platform;
use PunBB\Module\Framework\Container\Container;
use PunBB\Module\Framework\Event\EventDispatcher;
use PunBB\Module\Framework\Modules\ModuleInterface;
use PunBB\Module\Framework\Modules\Wiring;
use PunBB\Module\Layout\Page\PageResponder;
use PunBB\Module\Layout\View\TemplateRenderer;
use PunBB\Module\Message\Page\ConfirmPage;
use PunBB\Module\Message\Page\MessagePage;
use PunBB\Module\Message\Page\RedirectPage;
use PunBB\Module\Misc\Api\ReadMarksInterface;
use PunBB\Module\Misc\Api\RecipientsInterface;
use PunBB\Module\Misc\Api\ReportingInterface;
use PunBB\Module\Misc\Api\SubscriptionsInterface;
use PunBB\Module\Misc\Controller\MiscController;
use PunBB\Module\Misc\Interceptor\ReadMarksInterceptor;
use PunBB\Module\Misc\Interceptor\RecipientsInterceptor;
use PunBB\Module\Misc\Interceptor\ReportingInterceptor;
use PunBB\Module\Misc\Interceptor\SubscriptionsInterceptor;
use PunBB\Module\Misc\Model\ReadMarks;
use PunBB\Module\Misc\Model\Recipients;
use PunBB\Module\Misc\Model\Reporting;
use PunBB\Module\Misc\Model\Subscriptions;
use PunBB\Module\Site\Config\SettingsInterface;
use PunBB\Module\Site\Flash\FlashMessagesInterface;
use PunBB\Module\Site\Format\FormatterInterface;
use PunBB\Module\Site\Language\LanguageInterface;
use PunBB\Module\Site\Mail\MailerInterface;
use PunBB\Module\Site\Posting\PostRulesInterface;
use PunBB\Module\Site\Security\CsrfTokensInterface;
use PunBB\Module\Site\Url\UrlsInterface;
use PunBB\Module\Site\Visitor\VisitorInterface;

/**
 * The board's rules, marking read, the search plugin description, mailing a
 * member, reporting a post, and subscriptions. A request for an action leaves
 * no visit behind.
 */
final class Module implements ModuleInterface, TableOwnerInterface {
	public function name(): string {
		return 'Misc';
	}

	public function dependencies(): array {
		return array('Framework', 'Database', 'Layout', 'Site', 'Message');
	}

	public function loadAfter(): array {
		return array();
	}

	public function wire(Wiring $wiring): void {
		$wiring->contract(ReadMarksInterface::class, ReadMarksInterceptor::class, fn (Container $c): object => new ReadMarks($c->get(Connection::class)));
		$wiring->contract(SubscriptionsInterface::class, SubscriptionsInterceptor::class, fn (Container $c): object => new Subscriptions($c->get(Connection::class)));
		$wiring->contract(RecipientsInterface::class, RecipientsInterceptor::class, fn (Container $c): object => new Recipients($c->get(Connection::class)));
		$wiring->contract(ReportingInterface::class, ReportingInterceptor::class, fn (Container $c): object => new Reporting($c->get(Connection::class)));

		$wiring->route(array('misc.php'), MiscController::class, fn (Container $c): object => new MiscController(
			$c->get(EventDispatcher::class),
			$c->get(PageResponder::class),
			$c->get(TemplateRenderer::class),
			$c->get(MessagePage::class),
			$c->get(RedirectPage::class),
			$c->get(ConfirmPage::class),
			$c->get(ReadMarksInterface::class),
			$c->get(SubscriptionsInterface::class),
			$c->get(RecipientsInterface::class),
			$c->get(ReportingInterface::class),
			$c->get(VisitorInterface::class),
			$c->get(LanguageInterface::class),
			$c->get(SettingsInterface::class),
			$c->get(UrlsInterface::class),
			$c->get(FormatterInterface::class),
			$c->get(CsrfTokensInterface::class),
			$c->get(FlashMessagesInterface::class),
			$c->get(MailerInterface::class),
			$c->get(PostRulesInterface::class)
		), quietWith: array('action'));
	}

	public function tables(Platform $platform): array {
		return array(
			new Table('subscriptions', array(
				new Column('user_id', 'INT(10) UNSIGNED', false, 0),
				new Column('topic_id', 'INT(10) UNSIGNED', false, 0),
			), array('user_id', 'topic_id')),

			new Table('forum_subscriptions', array(
				new Column('user_id', 'INT(10) UNSIGNED', false, 0),
				new Column('forum_id', 'INT(10) UNSIGNED', false, 0),
			), array('user_id', 'forum_id')),
		);
	}
}
