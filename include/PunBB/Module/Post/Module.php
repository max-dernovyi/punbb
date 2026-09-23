<?php

declare(strict_types=1);

namespace PunBB\Module\Post;

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
use PunBB\Module\Message\Page\RedirectPage;
use PunBB\Module\Post\Api\PostingInterface;
use PunBB\Module\Post\Controller\PostController;
use PunBB\Module\Post\Creation\PostCreationInterface;
use PunBB\Module\Post\Interceptor\PostingInterceptor;
use PunBB\Module\Post\Model\Posting;
use PunBB\Module\Site\Account\UsernameRulesInterface;
use PunBB\Module\Site\Config\SettingsInterface;
use PunBB\Module\Site\Format\FormatterInterface;
use PunBB\Module\Site\Language\LanguageInterface;
use PunBB\Module\Site\Mail\EmailAddressesInterface;
use PunBB\Module\Site\Posting\PostRulesInterface;
use PunBB\Module\Site\Security\CsrfTokensInterface;
use PunBB\Module\Site\Url\UrlsInterface;
use PunBB\Module\Site\Visitor\VisitorInterface;

/**
 * Posting a reply or a new topic. Storing the post is PostCreationInterface,
 * which the bootstrap's side wires.
 */
final class Module implements ModuleInterface, TableOwnerInterface {
	public function name(): string {
		return 'Post';
	}

	public function dependencies(): array {
		return array('Framework', 'Database', 'Layout', 'Site', 'Message');
	}

	public function loadAfter(): array {
		return array();
	}

	public function wire(Wiring $wiring): void {
		$wiring->contract(PostingInterface::class, PostingInterceptor::class, fn (Container $c): object => new Posting($c->get(Connection::class)));

		$wiring->route(array('post.php'), PostController::class, fn (Container $c): object => new PostController(
			$c->get(EventDispatcher::class),
			$c->get(PageResponder::class),
			$c->get(TemplateRenderer::class),
			$c->get(MessagePage::class),
			$c->get(RedirectPage::class),
			$c->get(PostingInterface::class),
			$c->get(PostCreationInterface::class),
			$c->get(VisitorInterface::class),
			$c->get(LanguageInterface::class),
			$c->get(SettingsInterface::class),
			$c->get(UrlsInterface::class),
			$c->get(FormatterInterface::class),
			$c->get(CsrfTokensInterface::class),
			$c->get(PostRulesInterface::class),
			$c->get(UsernameRulesInterface::class),
			$c->get(EmailAddressesInterface::class)
		), checksOwnToken: true);
	}

	public function tables(Platform $platform): array {
		return array(
			new Table('posts', array(
				new Column('id', 'SERIAL'),
				new Column('poster', 'VARCHAR(200)', false, ''),
				new Column('poster_id', 'INT(10) UNSIGNED', false, 1),
				new Column('poster_ip', 'VARCHAR(39)', true),
				new Column('poster_email', 'VARCHAR(80)', true),
				new Column('message', 'TEXT', true),
				new Column('hide_smilies', 'TINYINT(1)', false, 0),
				new Column('posted', 'INT(10) UNSIGNED', false, 0),
				new Column('edited', 'INT(10) UNSIGNED', true),
				new Column('edited_by', 'VARCHAR(200)', true),
				new Column('topic_id', 'INT(10) UNSIGNED', false, 0),
			), array('id'), array(), array(
				'topic_id_idx'	=> array('topic_id'),
				'multi_idx'		=> array('poster_id', 'topic_id'),
				'posted_idx'	=> array('posted'),
			), removedColumns: array('approved'), removedIndexes: array('message_idx')),

			new Table('topics', array(
				new Column('id', 'SERIAL'),
				new Column('poster', 'VARCHAR(200)', false, ''),
				new Column('subject', 'VARCHAR(255)', false, ''),
				new Column('posted', 'INT(10) UNSIGNED', false, 0),
				new Column('first_post_id', 'INT(10) UNSIGNED', false, 0),
				new Column('last_post', 'INT(10) UNSIGNED', false, 0),
				new Column('last_post_id', 'INT(10) UNSIGNED', false, 0),
				new Column('last_poster', 'VARCHAR(200)', true),
				new Column('num_views', 'MEDIUMINT(8) UNSIGNED', false, 0),
				new Column('num_replies', 'MEDIUMINT(8) UNSIGNED', false, 0),
				new Column('closed', 'TINYINT(1)', false, 0),
				new Column('sticky', 'TINYINT(1)', false, 0),
				new Column('moved_to', 'INT(10) UNSIGNED', true),
				new Column('forum_id', 'INT(10) UNSIGNED', false, 0),
			), array('id'), array(), array(
				'forum_id_idx'		=> array('forum_id'),
				'moved_to_idx'		=> array('moved_to'),
				'last_post_idx'		=> array('last_post'),
				'first_post_id_idx'	=> array('first_post_id'),
			), removedIndexes: array('subject_idx')),
		);
	}
}
