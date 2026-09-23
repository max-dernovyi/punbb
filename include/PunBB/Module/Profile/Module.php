<?php

declare(strict_types=1);

namespace PunBB\Module\Profile;

use PunBB\Module\Database\Sql\Connection;
use PunBB\Module\Framework\Container\Container;
use PunBB\Module\Framework\Event\EventDispatcher;
use PunBB\Module\Framework\Modules\ModuleInterface;
use PunBB\Module\Framework\Modules\Wiring;
use PunBB\Module\Layout\Page\PageResponder;
use PunBB\Module\Layout\View\TemplateRenderer;
use PunBB\Module\Message\Page\ConfirmPage;
use PunBB\Module\Message\Page\MessagePage;
use PunBB\Module\Message\Page\RedirectPage;
use PunBB\Module\Profile\Api\ProfilesInterface;
use PunBB\Module\Profile\Avatar\AvatarRemovalInterface;
use PunBB\Module\Profile\Avatar\UploadedFilesInterface;
use PunBB\Module\Profile\Controller\AvatarUpload;
use PunBB\Module\Profile\Controller\DetailsUpdate;
use PunBB\Module\Profile\Controller\EmailChange;
use PunBB\Module\Profile\Controller\PasswordChange;
use PunBB\Module\Profile\Controller\ProfileAdministration;
use PunBB\Module\Profile\Controller\ProfileController;
use PunBB\Module\Profile\Controller\ProfileSections;
use PunBB\Module\Profile\Interceptor\ProfilesInterceptor;
use PunBB\Module\Profile\Model\Profiles;
use PunBB\Module\Profile\Model\UploadedFiles;
use PunBB\Module\Site\Account\UsernameRulesInterface;
use PunBB\Module\Site\Cache\BanCacheInterface;
use PunBB\Module\Site\Config\PacksInterface;
use PunBB\Module\Site\Config\SettingsInterface;
use PunBB\Module\Site\Flash\FlashMessagesInterface;
use PunBB\Module\Site\Format\FormatterInterface;
use PunBB\Module\Site\Language\LanguageInterface;
use PunBB\Module\Site\Mail\EmailAddressesInterface;
use PunBB\Module\Site\Mail\MailerInterface;
use PunBB\Module\Site\Moderation\ModeratorListsInterface;
use PunBB\Module\Site\Posting\PostRulesInterface;
use PunBB\Module\Site\Removal\UserRemovalInterface;
use PunBB\Module\Site\Security\CsrfTokensInterface;
use PunBB\Module\Site\Security\PasswordsInterface;
use PunBB\Module\Site\Security\RandomKeysInterface;
use PunBB\Module\Site\Security\SignInInterface;
use PunBB\Module\Site\Url\UrlsInterface;
use PunBB\Module\Site\Visitor\VisitorInterface;

/**
 * The members' profiles: shown, changed section by section, their password
 * and address changed, and the staff's administration of the member. Taking
 * an avatar off the board is AvatarRemovalInterface, which the bootstrap's
 * side wires, with the removal of users, the ban cache and the moderator
 * lists of Site.
 */
final class Module implements ModuleInterface {
	public function name(): string {
		return 'Profile';
	}

	public function dependencies(): array {
		return array('Framework', 'Database', 'Layout', 'Site', 'Message');
	}

	public function loadAfter(): array {
		return array();
	}

	public function version(): string {
		return '2.0.0';
	}

	public function wire(Wiring $wiring): void {
		$wiring->contract(ProfilesInterface::class, ProfilesInterceptor::class, fn (Container $c): object => new Profiles($c->get(Connection::class)));
		$wiring->service(UploadedFilesInterface::class, fn (): object => new UploadedFiles());

		$wiring->service(PasswordChange::class, fn (Container $c): object => new PasswordChange(
			$c->get(EventDispatcher::class),
			$c->get(PageResponder::class),
			$c->get(TemplateRenderer::class),
			$c->get(MessagePage::class),
			$c->get(RedirectPage::class),
			$c->get(ProfilesInterface::class),
			$c->get(PasswordsInterface::class),
			$c->get(SignInInterface::class),
			$c->get(VisitorInterface::class),
			$c->get(LanguageInterface::class),
			$c->get(SettingsInterface::class),
			$c->get(UrlsInterface::class),
			$c->get(CsrfTokensInterface::class),
			$c->get(FlashMessagesInterface::class)
		));

		$wiring->service(EmailChange::class, fn (Container $c): object => new EmailChange(
			$c->get(EventDispatcher::class),
			$c->get(PageResponder::class),
			$c->get(TemplateRenderer::class),
			$c->get(MessagePage::class),
			$c->get(RedirectPage::class),
			$c->get(ProfilesInterface::class),
			$c->get(PasswordsInterface::class),
			$c->get(EmailAddressesInterface::class),
			$c->get(MailerInterface::class),
			$c->get(RandomKeysInterface::class),
			$c->get(VisitorInterface::class),
			$c->get(LanguageInterface::class),
			$c->get(SettingsInterface::class),
			$c->get(UrlsInterface::class),
			$c->get(CsrfTokensInterface::class)
		));

		$wiring->service(ProfileAdministration::class, fn (Container $c): object => new ProfileAdministration(
			$c->get(EventDispatcher::class),
			$c->get(PageResponder::class),
			$c->get(TemplateRenderer::class),
			$c->get(MessagePage::class),
			$c->get(RedirectPage::class),
			$c->get(ConfirmPage::class),
			$c->get(ProfilesInterface::class),
			$c->get(UserRemovalInterface::class),
			$c->get(AvatarRemovalInterface::class),
			$c->get(ModeratorListsInterface::class),
			$c->get(VisitorInterface::class),
			$c->get(LanguageInterface::class),
			$c->get(SettingsInterface::class),
			$c->get(UrlsInterface::class),
			$c->get(CsrfTokensInterface::class),
			$c->get(FlashMessagesInterface::class)
		));

		$wiring->service(AvatarUpload::class, fn (Container $c): object => new AvatarUpload(
			$c->get(EventDispatcher::class),
			$c->get(MessagePage::class),
			$c->get(ProfilesInterface::class),
			$c->get(AvatarRemovalInterface::class),
			$c->get(UploadedFilesInterface::class),
			$c->get(LanguageInterface::class),
			$c->get(SettingsInterface::class),
			$c->get(FormatterInterface::class)
		));

		$wiring->service(DetailsUpdate::class, fn (Container $c): object => new DetailsUpdate(
			$c->get(EventDispatcher::class),
			$c->get(MessagePage::class),
			$c->get(RedirectPage::class),
			$c->get(ProfilesInterface::class),
			$c->get(AvatarUpload::class),
			$c->get(UsernameRulesInterface::class),
			$c->get(EmailAddressesInterface::class),
			$c->get(PostRulesInterface::class),
			$c->get(PacksInterface::class),
			$c->get(BanCacheInterface::class),
			$c->get(VisitorInterface::class),
			$c->get(LanguageInterface::class),
			$c->get(SettingsInterface::class),
			$c->get(UrlsInterface::class),
			$c->get(FormatterInterface::class),
			$c->get(FlashMessagesInterface::class)
		));

		$wiring->service(ProfileSections::class, fn (Container $c): object => new ProfileSections(
			$c->get(EventDispatcher::class),
			$c->get(PageResponder::class),
			$c->get(TemplateRenderer::class),
			$c->get(MessagePage::class),
			$c->get(ProfilesInterface::class),
			$c->get(PacksInterface::class),
			$c->get(VisitorInterface::class),
			$c->get(LanguageInterface::class),
			$c->get(SettingsInterface::class),
			$c->get(UrlsInterface::class),
			$c->get(FormatterInterface::class),
			$c->get(CsrfTokensInterface::class)
		));

		$wiring->route(array('profile.php'), ProfileController::class, fn (Container $c): object => new ProfileController(
			$c->get(EventDispatcher::class),
			$c->get(MessagePage::class),
			$c->get(ProfilesInterface::class),
			$c->get(PasswordChange::class),
			$c->get(EmailChange::class),
			$c->get(ProfileAdministration::class),
			$c->get(DetailsUpdate::class),
			$c->get(ProfileSections::class),
			$c->get(VisitorInterface::class),
			$c->get(LanguageInterface::class)
		));
	}
}
