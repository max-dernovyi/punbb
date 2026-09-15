<?php

declare(strict_types=1);

namespace PunBB\Module\Help\Controller;

use PunBB\Module\Framework\Event\EventDispatcher;
use PunBB\Module\Framework\Http\Request;
use PunBB\Module\Framework\Http\Response;
use PunBB\Module\Framework\Routing\ControllerInterface;
use PunBB\Module\Help\Event\HelpRendering;
use PunBB\Module\Help\Event\HelpRequested;
use PunBB\Module\Help\Event\SmiliesListing;
use PunBB\Module\Layout\Chrome\Crumb;
use PunBB\Module\Layout\Chrome\PageHead;
use PunBB\Module\Layout\Page\PageResponder;
use PunBB\Module\Layout\View\Html;
use PunBB\Module\Layout\View\TemplateRenderer;
use PunBB\Module\Message\Page\MessagePage;
use PunBB\Module\Site\Config\SettingsInterface;
use PunBB\Module\Site\Format\FormatterInterface;
use PunBB\Module\Site\Language\LanguageInterface;
use PunBB\Module\Site\Url\UrlsInterface;
use PunBB\Module\Site\Visitor\GroupPermission;
use PunBB\Module\Site\Visitor\VisitorInterface;

/**
 * help.php?section=bbcode|img|smilies: one section of the help, with extension
 * markup at its positions. Any other section shows the heading alone.
 */
final class HelpController implements ControllerInterface {
	private const TEMPLATE = __DIR__.'/../templates/help.phtml';

	/** The heading each section has, by the common string naming it. */
	private const SECTIONS = array('bbcode' => 'BBCode', 'img' => 'Images', 'smilies' => 'Smilies');

	public function __construct(
		private readonly EventDispatcher $events,
		private readonly PageResponder $pages,
		private readonly TemplateRenderer $templates,
		private readonly MessagePage $messages,
		private readonly VisitorInterface $visitor,
		private readonly LanguageInterface $language,
		private readonly SettingsInterface $settings,
		private readonly UrlsInterface $urls,
		private readonly FormatterInterface $formatter
	) {}

	public function handle(Request $request): Response {
		$this->events->dispatch(new HelpRequested());

		if (!$this->visitor->can(GroupPermission::ReadBoard))
			return $this->messages->respond($this->language->text('common', 'No view'), json: $request->xhr);

		$help = $this->language->strings('help');

		$section = $request->query['section'] ?? null;
		if (!$section)
			return $this->messages->respond($this->language->text('common', 'Bad request'), json: $request->xhr);

		$head = new PageHead('help', array(
			new Crumb($this->settings->value('o_board_title'), $this->urls->link('help')),
			new Crumb(($help['Help'] ?? new Html(''))->html),
		));

		return $this->pages->respond($head, fn (): array => array('main' => $this->main(is_string($section) ? $section : '', $help)));
	}

	/** @param array<string, Html> $help */
	private function main(string $section, array $help): Html {
		$start = $this->position(HelpRendering::START, $section);

		$positions = array_fill_keys(array(HelpRendering::TEXT_STYLES, HelpRendering::LINKS, HelpRendering::BBCODE, HelpRendering::IMAGES), new Html(''));
		$smilies = array();

		if ($section === 'bbcode')
		{
			$positions[HelpRendering::TEXT_STYLES] = new Html($this->position(HelpRendering::TEXT_STYLES, $section));
			$positions[HelpRendering::LINKS] = new Html($this->position(HelpRendering::LINKS, $section));
			$positions[HelpRendering::BBCODE] = new Html($this->position(HelpRendering::BBCODE, $section));
		}
		else if ($section === 'img')
			$positions[HelpRendering::IMAGES] = new Html($this->position(HelpRendering::IMAGES, $section));
		else if ($section === 'smilies')
			$smilies = $this->smilies();

		$positions[HelpRendering::SECTION] = new Html($this->position(HelpRendering::SECTION, $section));

		$heading = isset(self::SECTIONS[$section]) ? Html::format($help['Help with'] ?? '', $this->language->text('common', self::SECTIONS[$section])) : new Html('');

		$body = $this->templates->render(self::TEMPLATE, array(
			'section'	=> $section,
			'help'		=> $help,
			'heading'	=> $heading,
			'positions'	=> $positions,
			'base'		=> $this->urls->base(),
			'board'		=> $this->settings->value('o_board_title'),
			'wrote'		=> $this->language->text('common', 'wrote'),
			'and'		=> $this->language->text('common', 'and'),
			'smilies'	=> $smilies,
		));

		return (new Html($start.$body.$this->position(HelpRendering::END, $section)))->trim();
	}

	/** @return list<array{texts: list<string>, image: string}> the smilies, one entry for all the texts of each image */
	private function smilies(): array {
		$listing = new SmiliesListing($this->formatter->smilies());
		$this->events->dispatch($listing);

		$groups = array();
		foreach ($listing->texts() as $text)
			$groups[(string) $listing->image($text)][] = $text;

		$smilies = array();
		foreach ($groups as $image => $texts)
			$smilies[] = array('texts' => $texts, 'image' => (string) $image);

		return $smilies;
	}

	private function position(string $position, string $section): string {
		$rendering = new HelpRendering($position, $section);
		$this->events->dispatch($rendering);

		return $rendering->markup();
	}
}
