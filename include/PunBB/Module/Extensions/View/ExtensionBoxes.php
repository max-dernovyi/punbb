<?php

declare(strict_types=1);

namespace PunBB\Module\Extensions\View;

use PunBB\Module\Extensions\Api\Data\InstalledExtensionInterface;
use PunBB\Module\Extensions\Api\Data\ManifestInterface;
use PunBB\Module\Extensions\Event\ExtensionListRendering;
use PunBB\Module\Extensions\Manifest\LocalManifest;
use PunBB\Module\Extensions\Updates\AvailableHotfix;
use PunBB\Module\Extensions\Updates\LatestVersion;
use PunBB\Module\Layout\View\Html;
use PunBB\Module\Site\Security\CsrfTokensInterface;
use PunBB\Module\Site\Url\UrlsInterface;
use PunBB\Module\Site\Visitor\VisitorInterface;

/**
 * The boxes of the lists of extensions and hotfixes, each one piece of markup
 * observers may replace, and the links offered for an installed one.
 */
final class ExtensionBoxes {
	/** @param array<string, Html> $strings the admin_ext language pack */
	public function __construct(
		private readonly array $strings,
		private readonly UrlsInterface $urls,
		private readonly CsrfTokensInterface $tokens,
		private readonly VisitorInterface $visitor
	) {}

	public function availableExtension(string $id, ManifestInterface $manifest, bool $upgrade): string {
		return Html::format('<div class="ct-box info-box extension available">'."\n\t\t\t".'<h3 class="ct-legend hn">%s <em>%s</em></h3>'."\n\t\t\t".'<ul class="data-list">'."\n\t\t\t\t".'<li><span>%s</span></li>%s'."\n\t\t\t".'</ul>'."\n\t\t\t".'<p class="options"><span class="first-item"><a href="%s">%s</a></span></p>'."\n\t\t".'</div>',
			$manifest->title(),
			$manifest->version(),
			Html::format($this->string('Extension by'), $manifest->author()),
			new Html($manifest->description() !== '' ? Html::format("\n\t\t\t\t".'<li><span>%s</span></li>', $manifest->description())->html : ''),
			new Html($this->urls->base().'/admin/extensions.php?install='.urlencode($id)),
			$this->string($upgrade ? 'Upgrade extension' : 'Install extension')
		)->html;
	}

	/** @param int $number the box's number among the list's boxes */
	public function failed(LocalManifest $local, int $number): string {
		[$reason, $closing] = match ($local->problem) {
			LocalManifest::ILLEGAL_ID	=> array($this->string('Illegal ID'), '</span>'),
			LocalManifest::MISSING		=> array($this->string('Missing manifest'), '<span>'),
			LocalManifest::UNPARSED		=> array($this->string('Failed parse manifest'), '<span>'),
			default						=> array(new Html(implode(' ', $local->errors)), '</span>'),
		};

		return Html::format('<div class="ext-error databox db%s">'."\n\t\t\t\t".'<h3 class="legend"><span>%s%s</h3>'."\n\t\t\t\t".'<p>%s</p>'."\n\t\t\t".'</div>',
			$number, Html::format($this->string('Extension loading error'), $local->directory), new Html($closing), $reason)->html;
	}

	public function availableHotfix(AvailableHotfix $hotfix): string {
		return Html::format('<div class="ct-box info-box hotfix available">'."\n\t\t\t".'<h3 class="ct-legend hn">%s</h3>'."\n\t\t\t".'<ul>'."\n\t\t\t\t".'<li><span>%s</span></li>'."\n\t\t\t\t".'<li><span>%s</span></li>'."\n\t\t\t".'</ul>'."\n\t\t\t\t".'<p class="options"><span class="first-item"><a href="%s">%s</a></span></p>'."\n\t\t".'</div>',
			$hotfix->title,
			Html::format($this->string('Extension by'), 'PunBB'),
			$this->string('Hotfix description'),
			new Html($this->urls->base().'/admin/extensions.php?install_hotfix='.urlencode($hotfix->id)),
			$this->string('Install hotfix')
		)->html;
	}

	public function installedExtension(InstalledExtensionInterface $extension, Html $actions): string {
		$description = new Html($extension->description() !== '' ? Html::format('<li><span>%s</span></li>', $extension->description())->html : '');

		if ($extension->isDisabled())
			return Html::format('<div class="ct-box info-box extension disabled">'."\n\t\t".'<h3 class="ct-legend hn">%s <em>%s</em> (%s)</h3>'."\n\t\t".'<ul class="data-list">'."\n\t\t\t".'<li><span>%s</span></li>'."\n\t\t\t".'%s'."\n\t\t\t".'</ul>'."\n\t\t".'<p class="options">%s</p>'."\n\t".'</div>',
				$extension->title(), $extension->version(), $this->string('Extension disabled'), Html::format($this->string('Extension by'), $extension->author()), $description, $actions)->html;

		return Html::format('<div class="ct-box info-box extension enabled">'."\n\t\t".'<h3 class="ct-legend hn">%s <em>%s</em></h3>'."\n\t\t".'<ul class="data-list">'."\n\t\t\t".'<li><span>%s</span></li>'."\n\t\t\t".'%s'."\n\t\t".'</ul>'."\n\t\t".'<p class="options">%s</p>'."\n\t".'</div>',
			$extension->title(), $extension->version(), Html::format($this->string('Extension by'), $extension->author()), $description, $actions)->html;
	}

	/**
	 * Switching the extension and uninstalling it, from the list $list.
	 *
	 * @return array<string, string>
	 */
	public function actions(string $list, InstalledExtensionInterface $extension): array {
		$base = $this->urls->base().'/admin/extensions.php';
		$id = $extension->id();

		// The hotfix list's uninstall link has always named a section no list has
		$flipSection = $list === ExtensionListRendering::HOTFIXES ? 'hotfixes' : 'manage';
		$uninstallSection = $list === ExtensionListRendering::HOTFIXES ? 'hotfixese' : 'manage';

		return array(
			'flip'		=> Html::format('<span class="first-item"><a href="%s">%s</a></span>',
				new Html($base.'?section='.$flipSection.'&amp;flip='.$id.'&amp;csrf_token='.$this->tokens->token('flip'.$id.$this->visitor->id())),
				$this->string($extension->isDisabled() ? 'Enable' : 'Disable'))->html,
			'uninstall'	=> Html::format('<span><a href="%s">%s</a></span>', new Html($base.'?section='.$uninstallSection.'&amp;uninstall='.$id), $this->string('Uninstall'))->html,
		);
	}

	public function latestVersion(InstalledExtensionInterface $extension, LatestVersion $version): string {
		return Html::format('<span><a href="%s">%s</a></span>', $version->repositoryUrl.'/'.$extension->id().'/'.$extension->id().'.zip', $this->string('Download latest version'))->html;
	}

	private function string(string $key): Html {
		return $this->strings[$key] ?? new Html('');
	}
}
