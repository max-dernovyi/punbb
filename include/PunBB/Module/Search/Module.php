<?php

declare(strict_types=1);

namespace PunBB\Module\Search;

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
use PunBB\Module\Message\Page\MessagePage;
use PunBB\Module\Search\Api\ResultsInterface;
use PunBB\Module\Search\Api\SearchesInterface;
use PunBB\Module\Search\Controller\SearchController;
use PunBB\Module\Search\Interceptor\ResultsInterceptor;
use PunBB\Module\Search\Interceptor\SearchesInterceptor;
use PunBB\Module\Search\Model\Results;
use PunBB\Module\Search\Model\Searches;
use PunBB\Module\Search\Words\SearchWordsInterface;
use PunBB\Module\Site\Config\SettingsInterface;
use PunBB\Module\Site\Format\FormatterInterface;
use PunBB\Module\Site\Language\LanguageInterface;
use PunBB\Module\Site\Security\CsrfTokensInterface;
use PunBB\Module\Site\Url\UrlsInterface;
use PunBB\Module\Site\Visitor\VisitorInterface;

/**
 * The search page: the form, keyword and author searches stored for their
 * results pages, and the quick searches.
 */
final class Module implements ModuleInterface, TableOwnerInterface {
	public function name(): string {
		return 'Search';
	}

	public function dependencies(): array {
		return array('Framework', 'Database', 'Layout', 'Site', 'Message');
	}

	public function loadAfter(): array {
		return array();
	}

	public function version(): string {
		return '1.4.0';
	}

	public function wire(Wiring $wiring): void {
		$wiring->contract(SearchesInterface::class, SearchesInterceptor::class, fn (Container $c): object => new Searches($c->get(Connection::class)));
		$wiring->contract(ResultsInterface::class, ResultsInterceptor::class, fn (Container $c): object => new Results($c->get(Connection::class)));

		$wiring->route(array('search.php'), SearchController::class, fn (Container $c): object => new SearchController(
			$c->get(EventDispatcher::class),
			$c->get(PageResponder::class),
			$c->get(TemplateRenderer::class),
			$c->get(MessagePage::class),
			$c->get(SearchesInterface::class),
			$c->get(ResultsInterface::class),
			$c->get(SearchWordsInterface::class),
			$c->get(VisitorInterface::class),
			$c->get(LanguageInterface::class),
			$c->get(SettingsInterface::class),
			$c->get(UrlsInterface::class),
			$c->get(FormatterInterface::class),
			$c->get(CsrfTokensInterface::class)
		));
	}

	public function tables(Platform $platform): array {
		$mysql = $platform === Platform::Mysql;
		$sqlite = $platform === Platform::Sqlite;

		return array(
			new Table('search_cache', array(
				new Column('id', 'INT(10) UNSIGNED', false, 0),
				new Column('ident', 'VARCHAR(200)', false, ''),
				new Column('search_data', 'TEXT', true),
			), array('id'), array(), array(
				'ident_idx'	=> array($mysql ? 'ident(8)' : 'ident'),
			)),

			new Table('search_matches', array(
				new Column('post_id', 'INT(10) UNSIGNED', false, 0),
				new Column('word_id', 'INT(10) UNSIGNED', false, 0),
				new Column('subject_match', 'TINYINT(1)', false, 0),
			), array(), array(), array(
				'word_id_idx'	=> array('word_id'),
				'post_id_idx'	=> array('post_id'),
			)),

			// SQLite keys the words by their id
			new Table('search_words', array(
				new Column('id', 'SERIAL'),
				new Column('word', 'VARCHAR(20)', false, '', 'bin'),
			), $sqlite ? array('id') : array('word'), $sqlite ? array('word_idx' => array('word')) : array(), array(
				'id_idx'	=> array('id'),
			)),
		);
	}
}
