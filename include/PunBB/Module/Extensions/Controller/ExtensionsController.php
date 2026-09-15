<?php

declare(strict_types=1);

namespace PunBB\Module\Extensions\Controller;

use PunBB\Module\Extensions\Api\Data\InstalledExtensionInterface;
use PunBB\Module\Extensions\Api\Data\ManifestInterface;
use PunBB\Module\Extensions\Api\ExtensionsInterface;
use PunBB\Module\Extensions\Cache\ExtensionCacheInterface;
use PunBB\Module\Extensions\Event\ExtensionActionsAssembling;
use PunBB\Module\Extensions\Event\ExtensionListRendering;
use PunBB\Module\Extensions\Event\ExtensionsActionRequested;
use PunBB\Module\Extensions\Event\ExtensionsRequested;
use PunBB\Module\Extensions\Event\FlipStep;
use PunBB\Module\Extensions\Event\InstallFormRendering;
use PunBB\Module\Extensions\Event\InstallStep;
use PunBB\Module\Extensions\Event\NoticesRendering;
use PunBB\Module\Extensions\Event\UninstallFormRendering;
use PunBB\Module\Extensions\Event\UninstallStep;
use PunBB\Module\Extensions\Installation\ExtensionCodeInterface;
use PunBB\Module\Extensions\Manifest\ManifestsInterface;
use PunBB\Module\Extensions\Model\ExtensionRecord;
use PunBB\Module\Extensions\Model\ExtensionStatus;
use PunBB\Module\Extensions\Model\HookRecord;
use PunBB\Module\Extensions\Updates\UpdatesInterface;
use PunBB\Module\Extensions\View\ExtensionBoxes;
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
use PunBB\Module\Message\Page\ConfirmPage;
use PunBB\Module\Message\Page\MessagePage;
use PunBB\Module\Message\Page\RedirectPage;
use PunBB\Module\Site\Config\SettingsInterface;
use PunBB\Module\Site\Flash\FlashMessagesInterface;
use PunBB\Module\Site\Language\LanguageInterface;
use PunBB\Module\Site\Security\CsrfTokensInterface;
use PunBB\Module\Site\Url\UrlsInterface;
use PunBB\Module\Site\Visitor\VisitorInterface;

/**
 * admin/extensions.php: the extensions and the hotfixes the board has and
 * could install, installing and uninstalling them once confirmed, and
 * switching them by their links, for administrators. An install checks the
 * manifest, the forum version and the extensions it depends on first; an
 * uninstall or a switch leaves no extension without what it depends on.
 */
final class ExtensionsController implements ControllerInterface {
	private const TEMPLATES = __DIR__.'/../templates/';

	/** An id a hotfix has; everything else is an extension. */
	private const HOTFIX_PREFIX = 'hotfix_';

	/** What an extension's id is stripped to before it names a directory. */
	private const ID = '/[^0-9a-z_]/';

	public function __construct(
		private readonly EventDispatcher $events,
		private readonly PageResponder $pages,
		private readonly TemplateRenderer $templates,
		private readonly MessagePage $messages,
		private readonly RedirectPage $redirects,
		private readonly ConfirmPage $confirmations,
		private readonly ExtensionsInterface $extensions,
		private readonly ManifestsInterface $manifests,
		private readonly ExtensionCodeInterface $code,
		private readonly ExtensionCacheInterface $cache,
		private readonly UpdatesInterface $updates,
		private readonly VisitorInterface $visitor,
		private readonly LanguageInterface $language,
		private readonly SettingsInterface $settings,
		private readonly UrlsInterface $urls,
		private readonly CsrfTokensInterface $tokens,
		private readonly FlashMessagesInterface $flash
	) {}

	public function handle(Request $request): Response {
		$this->events->dispatch(new ExtensionsRequested());

		if (!$this->visitor->isAdministrator())
			return $this->messages->respond($this->language->text('common', 'No permission'), json: $request->xhr);

		$admin = $this->language->strings('admin_common');
		$strings = $this->language->strings('admin_ext');

		$section = is_string($request->query['section'] ?? null) ? $request->query['section'] : '';

		if (isset($request->query['install']) || isset($request->query['install_hotfix']))
			return $this->install($request, $admin, $strings);

		if (isset($request->query['uninstall']))
			return $this->uninstall($request, $admin, $strings);

		if (isset($request->query['flip']))
			return $this->flip($request, $section, $strings);

		$this->events->dispatch(new ExtensionsActionRequested($section));

		$installed = $this->extensions->all();

		if ($section === 'hotfixes')
			return $this->hotfixes($installed, $admin, $strings);

		return $this->manage($installed, $admin, $strings);
	}

	/**
	 * The form confirming an install once the manifest checks out, and the install once it is confirmed.
	 *
	 * @param array<string, Html> $admin
	 * @param array<string, Html> $strings
	 */
	private function install(Request $request, array $admin, array $strings): Response {
		$hotfix = !isset($request->query['install']);

		$this->events->dispatch(new InstallStep(InstallStep::SELECTED, $hotfix));

		if (isset($request->post['install_cancel']))
			return $this->redirects->respond($this->urls->link($hotfix ? 'admin_extensions_hotfixes' : 'admin_extensions_manage')->html, self::string($admin, 'Cancel redirect'), $request->xhr);

		$id = $request->query['install'] ?? $request->query['install_hotfix'];
		$id = (string) preg_replace(self::ID, '', is_string($id) ? $id : '');

		$reading = $hotfix ? $this->manifests->hotfix($id) : $this->manifests->local($id);
		if ($reading->errors !== array() || $reading->manifest === null)
			return $this->messages->respond($hotfix ? self::string($strings, 'Hotfix download failed') : $this->language->text('common', 'Bad request'), json: $request->xhr);

		$manifest = $reading->manifest;

		if (!$this->manifests->supports($manifest))
			return $this->messages->respond(self::string($strings, 'Maxtestedon error'), json: $request->xhr);

		$enabled = array();
		foreach ($this->extensions->enabledVersions() as $extension)
			$enabled[$extension->id()] = $extension->version();

		$errors = array();
		foreach ($manifest->dependencies() as $dependency)
		{
			if (!array_key_exists($dependency->id(), $enabled))
				$errors[] = Html::format(self::string($strings, 'Missing dependency'), $dependency->id())->html;
			else if ($dependency->minVersion() !== '' && version_compare($dependency->minVersion(), $enabled[$dependency->id()]) > 0)
				$errors[] = Html::format(self::string($strings, 'Version dependency error'), $dependency->id(), $dependency->minVersion())->html;
		}

		$isHotfix = str_starts_with($id, self::HOTFIX_PREFIX);
		$heading = self::string($strings, $isHotfix ? 'Install hotfix' : 'Install extension');
		$crumbs = $this->crumbs($admin, $isHotfix, $heading);

		if (isset($request->post['install_comply']) && $errors === array())
			return $this->installExtension($request, $id, $hotfix, $manifest, $crumbs, $heading, $admin, $strings);

		$head = new PageHead(self::pageId($isHotfix), $crumbs, section: 'extensions', view: 'install');

		return $this->pages->respond($head, fn (): array => array('main' => $this->installForm($request, $id, $manifest, $heading, $errors, $admin, $strings)));
	}

	/**
	 * @param list<Crumb> $crumbs
	 * @param array<string, Html> $admin
	 * @param array<string, Html> $strings
	 */
	private function installExtension(Request $request, string $id, bool $hotfix, ManifestInterface $manifest, array $crumbs, Html $heading, array $admin, array $strings): Response {
		$this->events->dispatch(new InstallStep(InstallStep::SUBMITTED, $hotfix, $id, $manifest));

		$uninstallNote = '';
		foreach ($manifest->notes() as $note)
			if ($note->type() === 'uninstall' && trim($note->content()) !== '')
				$uninstallNote = (new Html($note->content()))->trim()->html;

		$installedVersion = $this->extensions->installedVersion($id);

		// The code runs first: an upgrade reads the version it replaces
		$notices = $this->code->install($id, $manifest, $installedVersion);

		$dependencies = array_map(static fn ($dependency): string => $dependency->id(), $manifest->dependencies());
		$record = new ExtensionRecord($id, $manifest->title(), $manifest->version(), $manifest->description(), $manifest->author(), $manifest->uninstallCode(), $uninstallNote, $dependencies);

		if ($installedVersion !== null)
		{
			$this->extensions->update($record);
			$this->extensions->clearHooks($id);
		}
		else
			$this->extensions->add($record);

		$hooks = array();
		foreach ($manifest->hooks() as $hook)
			foreach ($hook->points() as $point)
				$hooks[] = new HookRecord($point, $id, $hook->code(), time(), $hook->priority());

		if ($hooks !== array())
			$this->extensions->addHooks(...$hooks);

		$this->cache->clear();
		$this->cache->rebuildHooks();

		$isHotfix = str_starts_with($id, self::HOTFIX_PREFIX);

		if ($notices !== array())
			return $this->noticesPage(new PageHead(self::pageId($isHotfix), $crumbs, section: 'extensions', view: 'install-notices'), true, $id, $heading, $manifest->title(), $notices, $admin, $strings);

		$message = self::string($strings, $isHotfix ? 'Hotfix installed' : 'Extension installed');
		$this->flash->info($message);

		$this->events->dispatch(new InstallStep(InstallStep::INSTALLED, $hotfix, $id, $manifest));

		return $this->redirects->respond($this->urls->link($isHotfix ? 'admin_extensions_hotfixes' : 'admin_extensions_manage')->html, $message, $request->xhr);
	}

	/**
	 * @param list<string> $errors
	 * @param array<string, Html> $admin
	 * @param array<string, Html> $strings
	 */
	private function installForm(Request $request, string $id, ManifestInterface $manifest, Html $heading, array $errors, array $admin, array $strings): Html {
		$start = new InstallFormRendering(InstallFormRendering::OUTPUT_START, $id);
		$this->events->dispatch($start);

		$preErrors = new Html('');
		$listed = null;
		if ($errors !== array())
		{
			$event = new InstallFormRendering(InstallFormRendering::PRE_ERRORS, $id);
			foreach ($errors as $number => $error)
				$event->set((string) $number, '<li class="warn"><span>'.$error.'</span></li>');
			$this->events->dispatch($event);

			$preErrors = new Html($event->markup());
			$lines = array();
			foreach ($event->names() as $name)
				$lines[] = (string) $event->entry($name);

			$listed = new Html(implode("\n\t\t\t\t", $lines));
		}

		$warnings = array();
		foreach ($manifest->notes() as $note)
			if ($note->type() === 'install')
				$warnings[] = Html::format('<li>%s</li>', $note->content())->html;

		if ($this->manifests->outgrows($manifest))
			$warnings[] = '<li>'.self::string($strings, 'Maxtestedon warning')->html.'</li>';

		$action = $this->urls->base().'/admin/extensions.php'.(isset($request->query['install']) ? '?install=' : '?install_hotfix=').$id;
		$isHotfix = str_starts_with($id, self::HOTFIX_PREFIX);

		$body = $this->templates->render(self::TEMPLATES.'install.phtml', array(
			'ext'			=> $strings,
			'admin'			=> $admin,
			'heading'		=> $heading,
			'title'			=> $manifest->title(),
			'preErrors'		=> $preErrors,
			'errors'		=> $listed,
			'action'		=> new Html($action),
			'token'			=> $this->tokens->token($action),
			'version'		=> !$isHotfix ? Html::format(self::string($strings, 'Version'), $manifest->version()) : self::string($strings, 'Hotfix'),
			'author'		=> Html::format(self::string($strings, 'Extension by'), $manifest->author()),
			'description'	=> $manifest->description(),
			'warnings'		=> $warnings !== array() ? new Html(implode("\n\t\t\t\t\t", $warnings)) : null,
			'submit'		=> self::string($strings, $isHotfix ? 'Install hotfix' : 'Install extension'),
		));

		$end = new InstallFormRendering(InstallFormRendering::END, $id);
		$this->events->dispatch($end);

		return (new Html($start->markup().$body.$end->markup()))->trim();
	}

	/**
	 * The form confirming an uninstall, and the uninstall once it is confirmed.
	 *
	 * @param array<string, Html> $admin
	 * @param array<string, Html> $strings
	 */
	private function uninstall(Request $request, array $admin, array $strings): Response {
		if (isset($request->post['uninstall_cancel']))
			return $this->redirects->respond($this->urls->link('admin_extensions_manage')->html, self::string($admin, 'Cancel redirect'), $request->xhr);

		$this->events->dispatch(new UninstallStep(UninstallStep::SELECTED));

		$id = $request->query['uninstall'];
		if (!is_string($id))
			return $this->badRequest($request);

		$id = (string) preg_replace(self::ID, '', $id);

		$extension = $this->extensions->find($id);
		if ($extension === null)
			return $this->badRequest($request);

		$dependent = $this->extensions->dependent($id);
		if ($dependent !== null)
			return $this->messages->respond(Html::format(self::string($strings, 'Uninstall dependency'), $dependent), json: $request->xhr);

		$isHotfix = str_starts_with($id, self::HOTFIX_PREFIX);
		$heading = self::string($strings, $isHotfix ? 'Uninstall hotfix' : 'Uninstall extension');
		$crumbs = $this->crumbs($admin, $isHotfix, $heading);

		if (!isset($request->post['uninstall_comply']))
		{
			$head = new PageHead(self::pageId($isHotfix), $crumbs, section: 'extensions', view: 'uninstall');

			return $this->pages->respond($head, fn (): array => array('main' => $this->uninstallForm($id, $extension, $heading, $admin, $strings)));
		}

		$this->events->dispatch(new UninstallStep(UninstallStep::SUBMITTED, $extension));

		$notices = $this->code->uninstall($id, $extension->uninstallCode());

		$this->extensions->removeHooks($id);
		$this->extensions->remove($id);

		$this->cache->clear();
		$this->cache->rebuildHooks();

		if ($notices !== array())
			return $this->noticesPage(new PageHead(self::pageId(false), $crumbs, section: 'extensions', view: 'uninstall-notices'), false, $id, $heading, $extension->title(), $notices, $admin, $strings);

		$message = self::string($strings, $isHotfix ? 'Hotfix uninstalled' : 'Extension uninstalled');
		$this->flash->info($message);

		$this->events->dispatch(new UninstallStep(UninstallStep::UNINSTALLED, $extension));

		return $this->redirects->respond($this->urls->link($isHotfix ? 'admin_extensions_hotfixes' : 'admin_extensions_manage')->html, $message, $request->xhr);
	}

	/**
	 * @param array<string, Html> $admin
	 * @param array<string, Html> $strings
	 */
	private function uninstallForm(string $id, InstalledExtensionInterface $extension, Html $heading, array $admin, array $strings): Html {
		$start = new UninstallFormRendering(UninstallFormRendering::OUTPUT_START, $extension);
		$this->events->dispatch($start);

		$action = $this->urls->base().'/admin/extensions.php?section=manage&amp;uninstall='.$id;
		$isHotfix = str_starts_with($id, self::HOTFIX_PREFIX);

		$body = $this->templates->render(self::TEMPLATES.'uninstall.phtml', array(
			'ext'			=> $strings,
			'admin'			=> $admin,
			'heading'		=> $heading,
			'title'			=> $extension->title(),
			'action'		=> new Html($action),
			'token'			=> $this->tokens->token($action),
			'version'		=> !$isHotfix ? Html::format(self::string($strings, 'Version'), $extension->version()) : self::string($strings, 'Hotfix'),
			'author'		=> Html::format(self::string($strings, 'Extension by'), $extension->author()),
			'description'	=> $extension->description(),
			'hasNote'		=> $extension->uninstallNote() !== '',
			'note'			=> $extension->uninstallNote(),
			'hotfix'		=> $isHotfix,
		));

		$end = new UninstallFormRendering(UninstallFormRendering::END, $extension);
		$this->events->dispatch($end);

		return (new Html($start->markup().$body.$end->markup()))->trim();
	}

	/**
	 * What an install or uninstall code asked the administrator to read.
	 *
	 * @param list<string> $notices
	 * @param array<string, Html> $admin
	 * @param array<string, Html> $strings
	 */
	private function noticesPage(PageHead $head, bool $installing, string $id, Html $heading, string $title, array $notices, array $admin, array $strings): Response {
		return $this->pages->respond($head, function () use ($installing, $id, $heading, $title, $notices, $admin, $strings): array {
			$start = new NoticesRendering(NoticesRendering::OUTPUT_START, $installing, $id);
			$this->events->dispatch($start);

			$body = $this->templates->render(self::TEMPLATES.'notices.phtml', array(
				'admin'		=> $admin,
				'heading'	=> $heading,
				'title'		=> $title,
				'info'		=> self::string($strings, $installing ? 'Extension installed info' : 'Extension uninstalled info'),
				'listClass'	=> $installing ? 'data-list' : 'info-list',
				'notices'	=> array_map(static fn (string $notice): Html => new Html($notice), $notices),
				'manage'	=> $this->urls->link('admin_extensions_manage'),
			));

			$end = new NoticesRendering(NoticesRendering::END, $installing, $id);
			$this->events->dispatch($end);

			return array('main' => (new Html($start->markup().$body.$end->markup()))->trim());
		});
	}

	/**
	 * Disables an enabled extension or enables a disabled one, by its link, once
	 * no extension is left without what it depends on.
	 *
	 * @param array<string, Html> $strings
	 */
	private function flip(Request $request, string $section, array $strings): Response {
		$id = $request->query['flip'];
		if (!is_string($id))
			return $this->badRequest($request);

		$id = (string) preg_replace(self::ID, '', $id);

		// A token posted has passed the gate every POST goes through; one in the link is checked here
		if (!isset($request->post['csrf_token']) && !$this->tokens->matches($request->query['csrf_token'] ?? null, 'flip'.$id.$this->visitor->id()))
		{
			$confirmation = $this->confirmations->respond($request->post, $request->xhr);
			if ($confirmation !== null)
				return $confirmation;
		}

		$this->events->dispatch(new FlipStep(FlipStep::SELECTED, $id));

		$disabled = $this->extensions->isDisabled($id);
		if ($disabled === null)
			return $this->badRequest($request);

		$disable = !$disabled;

		if ($disable)
		{
			$dependent = $this->extensions->enabledDependent($id);
			if ($dependent !== null)
				return $this->messages->respond(Html::format(self::string($strings, 'Disable dependency'), $dependent), json: $request->xhr);
		}
		else
		{
			$dependencies = $this->extensions->dependencies($id);
			$enabled = $this->extensions->enabledIds();

			foreach ($dependencies as $dependency)
				if ($dependency !== '0' && !in_array($dependency, $enabled, true))
					return $this->messages->respond(Html::format(self::string($strings, 'Disabled dependency'), $dependency), json: $request->xhr);
		}

		$this->extensions->setDisabled(new ExtensionStatus($id, $disable));
		$this->cache->rebuildHooks();

		$hotfixes = $section === 'hotfixes';
		$message = self::string($strings, $hotfixes ? ($disable ? 'Hotfix disabled' : 'Hotfix enabled') : ($disable ? 'Extension disabled' : 'Extension enabled'));
		$this->flash->info($message);

		$this->events->dispatch(new FlipStep(FlipStep::FLIPPED, $id, $disable));

		return $this->redirects->respond($this->urls->link($hotfixes ? 'admin_extensions_hotfixes' : 'admin_extensions_manage')->html, $message, $request->xhr);
	}

	/**
	 * The hotfixes the update service offers that are not installed, and those installed.
	 *
	 * @param list<InstalledExtensionInterface> $installed
	 * @param array<string, Html> $admin
	 * @param array<string, Html> $strings
	 */
	private function hotfixes(array $installed, array $admin, array $strings): Response {
		$head = new PageHead('admin-extensions-hotfixes', array(
			new Crumb($this->settings->value('o_board_title'), $this->urls->link('index')),
			new Crumb(self::string($admin, 'Forum administration')->html, $this->urls->link('admin_index')),
			new Crumb(self::string($admin, 'Extensions')->html, $this->urls->link('admin_extensions_manage')),
			new Crumb(self::string($admin, 'Manage hotfixes')->html, $this->urls->link('admin_extensions_hotfixes')),
		), section: 'extensions');

		return $this->pages->respond($head, function () use ($installed, $strings): array {
			$boxes = new ExtensionBoxes($strings, $this->urls, $this->tokens, $this->visitor);
			$list = ExtensionListRendering::HOTFIXES;

			$start = new ExtensionListRendering($list, ExtensionListRendering::OUTPUT_START);
			$this->events->dispatch($start);

			$installedIds = array_map(static fn (InstalledExtensionInterface $extension): string => $extension->id(), $installed);

			$available = new Parts();
			foreach ($this->updates->hotfixes() as $hotfix)
				if (!in_array($hotfix->id, $installedIds, true))
					$available->set((string) count($available->names()), $boxes->availableHotfix($hotfix));

			$pre = new ExtensionListRendering($list, ExtensionListRendering::PRE_DISPLAY_AVAILABLE, $available, null, count($available->names()));
			$this->events->dispatch($pre);

			$preInstalled = new ExtensionListRendering($list, ExtensionListRendering::PRE_DISPLAY_INSTALLED);
			$this->events->dispatch($preInstalled);

			$items = array();
			foreach ($installed as $extension)
			{
				if (!str_starts_with($extension->id(), self::HOTFIX_PREFIX))
					continue;

				$actions = $this->actions($list, $extension, $boxes->actions($list, $extension));

				$items[] = array(
					'before'			=> new Html($actions->markup()),
					'status'			=> $extension->isDisabled() ? 'disabled' : 'enabled',
					'title'				=> $extension->title(),
					'disabled'			=> new Html($extension->isDisabled() ? ' ( <span>'.self::string($strings, 'Extension disabled')->html.'</span> )' : ''),
					'author'			=> Html::format(self::string($strings, 'Extension by'), $extension->author()),
					'hasDescription'	=> $extension->description() !== '',
					'description'		=> $extension->description(),
					'actions'			=> self::joined($actions, ' '),
				);
			}

			$body = $this->templates->render(self::TEMPLATES.'hotfixes.phtml', array(
				'ext'			=> $strings,
				'preAvailable'	=> new Html($pre->markup()),
				'available'		=> $pre->availableCount() > 0 ? self::group($pre, ExtensionListRendering::AVAILABLE, "\n\t\t") : null,
				'preInstalled'	=> new Html($preInstalled->markup()),
				'installed'		=> $items,
			));

			$end = new ExtensionListRendering($list, ExtensionListRendering::END);
			$this->events->dispatch($end);

			return array('main' => (new Html($start->markup().$body.$end->markup()))->trim());
		});
	}

	/**
	 * The extensions under extensions/ that can be installed or upgraded, those
	 * that failed to load and why, and those installed.
	 *
	 * @param list<InstalledExtensionInterface> $installed
	 * @param array<string, Html> $admin
	 * @param array<string, Html> $strings
	 */
	private function manage(array $installed, array $admin, array $strings): Response {
		$latest = array();
		if ($this->settings->value('o_check_for_versions') === '1')
			foreach ($this->updates->latestVersions($installed) as $version)
				$latest[$version->extensionId] = $version;

		$head = new PageHead('admin-extensions-manage', array(
			new Crumb($this->settings->value('o_board_title'), $this->urls->link('index')),
			new Crumb(self::string($admin, 'Forum administration')->html, $this->urls->link('admin_index')),
			new Crumb(self::string($admin, 'Extensions')->html, $this->urls->link('admin_extensions_manage')),
			new Crumb(self::string($admin, 'Manage extensions')->html, $this->urls->link('admin_extensions_manage')),
		), section: 'extensions');

		return $this->pages->respond($head, function () use ($installed, $latest, $strings): array {
			$boxes = new ExtensionBoxes($strings, $this->urls, $this->tokens, $this->visitor);
			$list = ExtensionListRendering::MANAGE;

			$start = new ExtensionListRendering($list, ExtensionListRendering::OUTPUT_START);
			$this->events->dispatch($start);

			$versions = array();
			foreach ($installed as $extension)
				$versions[$extension->id()] = $extension->version();

			$available = new Parts();
			$failed = new Parts();
			$boxNumber = 1;

			foreach ($this->manifests->directory() as $local)
			{
				if ($local->problem !== null || $local->manifest === null)
					$failed->set((string) count($failed->names()), $boxes->failed($local, ++$boxNumber));
				else if (!array_key_exists($local->directory, $versions) || version_compare($versions[$local->directory], $local->manifest->version(), '!='))
					$available->set((string) count($available->names()), $boxes->availableExtension($local->directory, $local->manifest, array_key_exists($local->directory, $versions)));
			}

			$pre = new ExtensionListRendering($list, ExtensionListRendering::PRE_DISPLAY_AVAILABLE, $available, $failed, count($available->names()), count($failed->names()));
			$this->events->dispatch($pre);

			$preInstalled = new ExtensionListRendering($list, ExtensionListRendering::PRE_DISPLAY_INSTALLED);
			$this->events->dispatch($preInstalled);

			$actionsMarkup = '';
			$items = array();
			foreach ($installed as $extension)
			{
				if (str_starts_with($extension->id(), self::HOTFIX_PREFIX))
					continue;

				$offered = $boxes->actions($list, $extension);
				$version = $latest[$extension->id()] ?? null;
				if ($version !== null && version_compare($extension->version(), $version->version, '<'))
					$offered['latest_ver'] = $boxes->latestVersion($extension, $version);

				$actions = $this->actions($list, $extension, $offered);
				$actionsMarkup .= $actions->markup();
				$items[] = $boxes->installedExtension($extension, self::joined($actions, ' '));
			}

			$body = $this->templates->render(self::TEMPLATES.'manage.phtml', array(
				'ext'			=> $strings,
				'preAvailable'	=> new Html($pre->markup()),
				'available'		=> $pre->availableCount() > 0 ? self::group($pre, ExtensionListRendering::AVAILABLE, "\n\t\t") : null,
				'failed'		=> $pre->failedCount() > 0 ? self::group($pre, ExtensionListRendering::FAILED, "\n\t\t\t") : null,
				'preInstalled'	=> new Html($preInstalled->markup()),
				'actionsMarkup'	=> new Html($actionsMarkup),
				'installed'		=> $items !== array() ? new Html(implode("\n\t", $items)) : null,
			));

			$end = new ExtensionListRendering($list, ExtensionListRendering::END);
			$this->events->dispatch($end);

			return array('main' => (new Html($start->markup().$body.$end->markup()))->trim());
		});
	}

	/** @param array<string, string> $offered */
	private function actions(string $list, InstalledExtensionInterface $extension, array $offered): ExtensionActionsAssembling {
		$event = new ExtensionActionsAssembling($list, $extension, $offered);
		$this->events->dispatch($event);

		return $event;
	}

	/**
	 * @param array<string, Html> $admin
	 * @return list<Crumb>
	 */
	private function crumbs(array $admin, bool $hotfix, Html $last): array {
		return array(
			new Crumb($this->settings->value('o_board_title'), $this->urls->link('index')),
			new Crumb(self::string($admin, 'Forum administration')->html, $this->urls->link('admin_index')),
			new Crumb(self::string($admin, 'Extensions')->html, $this->urls->link('admin_extensions_manage')),
			new Crumb(self::string($admin, $hotfix ? 'Manage hotfixes' : 'Manage extensions')->html, $this->urls->link($hotfix ? 'admin_extensions_hotfixes' : 'admin_extensions_manage')),
			new Crumb($last->html),
		);
	}

	private function badRequest(Request $request): Response {
		return $this->messages->respond($this->language->text('common', 'Bad request'), json: $request->xhr);
	}

	private static function pageId(bool $hotfix): string {
		return $hotfix ? 'admin-extensions-hotfixes' : 'admin-extensions-manage';
	}

	/** The parts of $group, joined with $glue. */
	private static function group(ExtensionListRendering $event, string $group, string $glue): Html {
		$parts = array();
		foreach ($event->names($group) as $name)
			$parts[] = (string) $event->entry($group, $name);

		return new Html(implode($glue, $parts));
	}

	private static function joined(ExtensionActionsAssembling $event, string $glue): Html {
		$parts = array();
		foreach ($event->names() as $name)
			$parts[] = (string) $event->entry($name);

		return new Html(implode($glue, $parts));
	}

	/** @param array<string, Html> $strings */
	private static function string(array $strings, string $key): Html {
		return $strings[$key] ?? new Html('');
	}
}
