<?php
/**
 * admin/settings.php as a module, with no forum: each section's form, the
 * numbers observers count on from, a section the page does not have, and the
 * settings a form posts validated, stored where they changed and the caches
 * rebuilt.
 *
 * @copyright (C) 2008-2012 PunBB, partially based on code (C) 2008-2009 FluxBB.org
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package PunBB
 */

use PHPUnit\Framework\TestCase;
use PunBB\Module\Framework\Http\Request;
use PunBB\Module\Layout\View\TemplateRenderer;
use PunBB\Module\Message\Event\MessageRendering;
use PunBB\Module\Message\Event\MessageShowing;
use PunBB\Module\Message\Event\RedirectHeadAssembling;
use PunBB\Module\Message\Event\RedirectShowing;
use PunBB\Module\Settings\Api\ConfigurationInterface;
use PunBB\Module\Settings\Api\Data\SettingInterface;
use PunBB\Module\Settings\Controller\SettingsController;
use PunBB\Module\Settings\Event\SettingsFormRendering;
use PunBB\Module\Settings\Event\SettingsFormStep;
use PunBB\Module\Settings\Event\SettingsRequested;
use PunBB\Module\Settings\Event\SettingsSectionRequested;
use PunBB\Module\Site\Cache\ConfigCacheInterface;
use PunBB\Module\Site\Cache\QuickjumpCacheInterface;
use PunBB\Module\Site\Config\PacksInterface;
use PunBB\Module\Site\Mail\EmailAddressesInterface;

require_once __DIR__.'/PageFakes.php';

final class FakeConfiguration implements ConfigurationInterface, PacksInterface, EmailAddressesInterface, ConfigCacheInterface, QuickjumpCacheInterface {
	/** @var list<string> what was stored and rebuilt, in order */
	public array $log = array();

	public function updatePermissions(SettingInterface ...$settings): void {
		foreach ($settings as $setting)
			$this->log[] = 'permission '.$setting->name().'='.var_export($setting->value(), true);
	}

	public function updateOptions(SettingInterface ...$settings): void {
		foreach ($settings as $setting)
			$this->log[] = 'option '.$setting->name().'='.var_export($setting->value(), true);
	}

	public function styles(): array { return array('Oxygen', 'Probe_style'); }

	public function languages(): array { return array('English', 'Deutsch'); }

	public function urlSchemes(): array { return array('Default', 'Folder_based_(fancy)'); }

	public function isValid(string $address): bool { return str_contains($address, '@'); }

	public function isBanned(string $address): bool { return false; }

	public function rebuild(): void { $this->log[] = 'rebuilt'; }

	public function clear(): void { $this->log[] = 'quickjump cleared'; }
}

class SettingsControllerTest extends TestCase {
	private PageKit $kit;

	private FakeConfiguration $configuration;

	protected function setUp(): void {
		$this->kit = new PageKit(array(SettingsRequested::class, SettingsFormStep::class, SettingsSectionRequested::class, SettingsFormRendering::class,
			MessageShowing::class, MessageRendering::class, RedirectShowing::class, RedirectHeadAssembling::class));
		$this->kit->language->real = array('admin_common', 'admin_settings', 'common', 'profile');
		$this->kit->visitor->administrator = true;
		$this->kit->settings->values = array(
			'o_board_title' => 'Board & Co', 'o_board_desc' => '<p>Desc</p>', 'o_default_style' => 'Probe_style', 'o_default_lang' => 'English', 'o_sef' => 'Default',
			'o_default_timezone' => '5.5', 'o_default_dst' => '0', 'o_time_format' => 'H:i', 'o_date_format' => 'Y-m-d', 'o_timeout_visit' => '1800', 'o_timeout_online' => '300',
			'o_redirect_delay' => '0', 'o_disp_topics_default' => '30', 'o_disp_posts_default' => '25', 'o_topic_review' => '15', 'o_report_method' => '1',
			'o_additional_navlinks' => '', 'o_maintenance' => '0', 'o_maintenance_message' => 'Down "now"', 'o_admin_email' => 'admin@example.com',
			'o_webmaster_email' => 'web@example.com', 'o_smtp_ssl' => '0', 'o_mailing_list' => '', 'o_smtp_host' => '', 'o_smtp_user' => '', 'o_smtp_pass' => '',
			'o_announcement' => '0', 'o_announcement_message' => 'Hi', 'o_rules' => '0', 'o_rules_message' => 'Rules', 'p_allow_banned_email' => '1', 'p_sig_length' => '400',
		);

		// Every other setting a form shows, as the board always has it
		foreach (glob(FORUM_ROOT.'include/PunBB/Module/Settings/templates/*.phtml') ?: array() as $template)
			if (preg_match_all('/\$config\[\'([op]_\w+)\'\]/', (string) file_get_contents($template), $matches) > 0)
				$this->kit->settings->values += array_fill_keys($matches[1], '0');

		$this->configuration = new FakeConfiguration();
	}

	/**
	 * @param array<string, mixed> $query
	 * @param array<string, mixed> $post
	 */
	private function page(array $query = array(), array $post = array()): string {
		$controller = new SettingsController($this->kit->dispatcher, $this->kit->pages(), new TemplateRenderer(), $this->kit->messages(), $this->kit->redirects(),
			$this->configuration, $this->configuration, $this->configuration, $this->configuration, $this->configuration, $this->kit->visitor, $this->kit->language,
			$this->kit->settings, $this->kit->urls, $this->kit->formatter, $this->kit->tokens, $this->kit->flash);

		$response = $controller->handle(new Request($post !== array() ? 'POST' : 'GET', '/', 'admin/settings.php', $query, $post));

		return $response->status.' '.($response->headers['Location'] ?? '').' '.$response->body;
	}

	public function testOnlyAnAdministratorGetsThePage(): void {
		$this->kit->visitor->administrator = false;

		$this->assertStringContainsString('<p>You do not have permission to access this page.</p>', $this->page(array(), array('form_sent' => '1', 'form' => array('board_title' => 'x'))));
		$this->assertSame(array(), $this->configuration->log);
		$this->assertSame('SettingsRequested', $this->kit->events->dispatched[0]);
	}

	public function testTheSetupFormShowsTheBoardsSettings(): void {
		$body = $this->page();

		$head = $this->kit->chromes->opened[0];
		$this->assertSame(array('admin-settings-setup', 'settings'), array($head->id, $head->section));
		$this->assertSame(array('Board & Co', 'Administration', 'Settings', 'Setup'), array_map(static fn ($crumb): string => $crumb->text, $head->crumbs));

		$token = 'token-for-'.md5('/admin_settings_setup?a=1&amp;b=2');
		$this->assertStringStartsWith("200  [admin-settings-setup]<div class=\"main-content main-frm\">\n\t\t<form class=\"frm-form\" method=\"post\" accept-charset=\"utf-8\" action=\"/admin_settings_setup?a=1&amp;b=2\">\n\t\t\t<div class=\"hidden\">\n\t\t\t\t<input type=\"hidden\" name=\"csrf_token\" value=\"".$token.'" />', $body);
		$this->assertStringContainsString('name="form[board_title]" size="50" maxlength="255" value="Board &amp; Co" />', $body);
		$this->assertStringContainsString('name="form[board_desc]" size="50" maxlength="255" value="&lt;p&gt;Desc&lt;/p&gt;" />', $body);
		$this->assertStringContainsString("<select id=\"fld3\" name=\"form[default_style]\">\n\t\t\t\t\t\t\t\t<option value=\"Oxygen\">Oxygen</option>\n\t\t\t\t\t\t\t\t<option value=\"Probe_style\" selected=\"selected\">Probe style</option>", $body);
		$this->assertStringContainsString("<select id=\"fld4\" name=\"form[default_lang]\">\n\t\t\t\t\t\t\t\t<option value=\"English\" selected=\"selected\">English</option>\n\t\t\t\t\t\t\t\t<option value=\"Deutsch\">Deutsch</option>", $body);
		$this->assertStringContainsString('<option value="5.5" selected="selected">(UTC+05:30) India, Sri Lanka</option>', $body);
		$this->assertSame(1, substr_count($body, 'selected="selected">(UTC'));
		$this->assertStringContainsString('<small>[ Current format: <now Time H:i> ] See <a class="exthelp" href="http://www.php.net/manual/en/function.date.php">here</a> for formatting options.</small>', $body);
		$this->assertStringContainsString('<small>[ Current format: <now Date Y-m-d> ]', $body);
		$this->assertStringContainsString('<input type="radio" id="fld16" name="form[report_method]" value="1" checked="checked" />', $body);
		$this->assertStringContainsString("<option value=\"Folder_based_(fancy)\">Folder based (fancy)</option>", $body);
		$this->assertStringContainsString("<fieldset class=\"frm-group group1\">\n\t\t\t\t\t<legend class=\"group-legend\"><strong>Menu items", $body);
		$this->assertStringContainsString('<div class="txt-set set1">', $body);
		$this->assertStringContainsString('<textarea id="fld19" name="form[additional_navlinks]" rows="3" cols="55"></textarea>', $body);
		$this->assertStringEndsWith("<input type=\"submit\" name=\"save\" value=\"Save changes\" /></span>\n\t\t\t</div>\n\t\t</form>\n\t</div>", $body);
	}

	public function testObserversAddFieldsTheFormNumbersOn(): void {
		$seen = array();
		$this->kit->events->observe(SettingsFormRendering::class, function (SettingsFormRendering $event) use (&$seen): void {
			if ($event->position() === 'pre_board_descrip')
			{
				$event->append('<input id="fld'.($event->fieldCount() + 1).'" />');
				$event->count($event->groupCount(), $event->itemCount() + 1, $event->fieldCount() + 1);
			}

			if (in_array($event->position(), array('output_start', 'pre_local_fieldset', 'personal_fieldset_end', 'end'), true))
				$seen[] = $event->section().' '.$event->position().' at '.$event->groupCount().'/'.$event->itemCount().'/'.$event->fieldCount();
		});

		$body = $this->page(array('section' => 'setup'));

		$this->assertStringContainsString('<input id="fld2" />'."\t\t\t\t\t<div class=\"sf-set set3\">", $body);
		$this->assertStringContainsString('name="form[board_desc]"', $body);
		$this->assertStringContainsString('<label for="fld3">', $body);
		$this->assertStringContainsString('<select id="fld5" name="form[default_lang]">', $body);
		$this->assertSame(array('setup output_start at 0/0/0', 'setup personal_fieldset_end at 1/4/4', 'setup pre_local_fieldset at 0/0/4', 'setup end at 1/1/20'), $seen);
	}

	public function testEachSectionHasItsFormAndItsCrumbs(): void {
		foreach (array('features' => 'Features', 'email' => 'Email', 'announcements' => 'Announcements', 'registration' => 'Registration') as $section => $crumb)
		{
			$body = $this->page(array('section' => $section));
			$head = $this->kit->chromes->opened[count($this->kit->chromes->opened) - 1];

			$this->assertSame(array('admin-settings-'.$section, 'settings', 'Settings', $crumb), array($head->id, $head->section, $head->crumbs[2]->text, $head->crumbs[3]->text));
			$this->assertStringContainsString('action="/admin_settings_'.$section.'?a=1&amp;b=2">', $body);
		}

		$body = $this->page(array('section' => 'maintenance'));
		$head = $this->kit->chromes->opened[count($this->kit->chromes->opened) - 1];
		$this->assertSame(array('admin-settings-maintenance', 'management', 'Management', 'Maintenance mode'), array($head->id, $head->section, $head->crumbs[2]->text, $head->crumbs[3]->text));
		$this->assertStringContainsString('<textarea id="fld2" name="form[maintenance_message]" rows="5" cols="55">Down &quot;now&quot;</textarea>', $body);
	}

	public function testTheFeaturesHaveTheUpdateChecksWhereTheBoardReachesAnotherServer(): void {
		$positions = array();
		$this->kit->events->observe(SettingsFormRendering::class, function (SettingsFormRendering $event) use (&$positions): void {
			$positions[] = $event->position();
		});

		$body = $this->page(array('section' => 'features'));

		$this->assertTrue(function_exists('stream_socket_client'));
		$this->assertStringContainsString('name="form[check_for_updates]"', $body);
		$this->assertContains('pre_updates_checkbox', $positions);
		$this->assertNotContains('post_updates_disabled_box', $positions);
		$this->assertStringContainsString('<input type="checkbox" id="fld38" name="form[gzip]" value="1" />', $body);
		$this->assertSame(array_values(array_diff(SettingsFormRendering::SECTIONS['features'], array('post_updates_disabled_box'))), $positions);
	}

	public function testASectionThePageDoesNotHaveIsABadRequestOnceObserversPassed(): void {
		$sections = array();
		$this->kit->events->observe(SettingsSectionRequested::class, function (SettingsSectionRequested $event) use (&$sections): void {
			$sections[] = $event->section();
		});

		$this->assertStringContainsString('<p>Bad request. The link you followed is incorrect or outdated.</p>', $this->page(array('section' => 'probe')));
		$this->assertSame(array('probe'), $sections);
		$this->assertStringContainsString('name="form[board_title]"', $this->page(array('section' => array('probe'))), 'a section that is not text is the setup');
		$this->assertSame(array('probe'), $sections);
	}

	public function testChangedSettingsAreStoredAndTheCachesRebuilt(): void {
		$steps = array();
		$this->kit->events->observe(SettingsFormStep::class, function (SettingsFormStep $event) use (&$steps): void {
			$steps[] = $event->step().' '.$event->section().' '.var_export($event->settings()->value('timeout_visit'), true);
		});

		$response = $this->page(array('section' => 'setup'), array('form_sent' => '1', 'form' => array(
			'board_title' => ' Board & Co ', 'board_desc' => 'New', 'default_style' => '../Oxygen', 'default_lang' => 'English', 'sef' => 'Folder_based_(fancy)',
			'default_timezone' => '5.5', 'time_format' => 'H:i', 'date_format' => 'Y-m-d', 'timeout_visit' => '1800s', 'timeout_online' => '300', 'redirect_delay' => '2',
			'disp_topics_default' => '-3', 'disp_posts_default' => '25', 'topic_review' => '15', 'report_method' => '1', 'additional_navlinks' => " a\r\nb ", 'probe' => array('x'),
		)));

		$this->assertStringStartsWith('302 /admin_settings_setup?a=1&b=2 [redirect]', $response);
		$this->assertSame(array(
			'option o_board_desc=\'New\'', 'option o_default_style=\'Oxygen\'', 'option o_sef=\'Folder_based_(fancy)\'', 'option o_redirect_delay=\'2\'',
			'option o_disp_topics_default=\'1\'', 'option o_additional_navlinks=\'a'."\n".'b\'', 'rebuilt', 'quickjump cleared',
		), $this->configuration->log);
		$this->assertSame(array('submitted setup \'1800s\'', 'validating setup \'1800s\'', 'updating setup 1800', 'updated setup 1800'), $steps);
		$this->assertSame(array('Settings updated.'), $this->kit->flash->info);
	}

	public function testPermissionsAreStoredAsIntegersAndEmptyOptionsAsNull(): void {
		$this->kit->settings->values['o_smtp_host'] = 'mail.example.com';

		$this->page(array('section' => 'email'), array('form_sent' => '1', 'form' => array('admin_email' => 'Admin@Example.com', 'webmaster_email' => 'web@example.com', 'smtp_host' => '', 'smtp_ssl' => 'on')));
		$this->assertSame(array('option o_smtp_host=NULL', 'rebuilt'), $this->configuration->log);

		$this->configuration->log = array();
		$this->page(array('section' => 'registration'), array('form_sent' => '1', 'form' => array('allow_banned_email' => '', 'rules' => '1', 'rules_message' => '')));
		$this->assertSame(array('permission p_allow_banned_email=\'0\'', 'option o_rules=\'1\'', 'option o_rules_message=\'Enter your rules here.\'', 'rebuilt'), $this->configuration->log);
	}

	public function testObserversChangeWhatIsValidatedAndStored(): void {
		$this->kit->events->observe(SettingsFormStep::class, function (SettingsFormStep $event): void {
			if ($event->step() === SettingsFormStep::VALIDATING)
				$event->settings()->set('probe_option', 'probed');

			if ($event->step() === SettingsFormStep::UPDATING)
				$event->settings()->remove('maintenance');
		});
		$this->kit->settings->values['o_probe_option'] = '';

		$this->page(array('section' => 'maintenance'), array('form_sent' => '1', 'form' => array('maintenance' => '1', 'maintenance_message' => "Back\r\nsoon")));

		$this->assertSame(array('option o_maintenance_message=\'Back'."\n".'soon\'', 'option o_probe_option=\'probed\'', 'rebuilt'), $this->configuration->log);
	}

	public function testAnInvalidFormIsRefusedAndNothingStored(): void {
		$this->assertStringContainsString('<p>You must enter a board title.</p>', $this->page(array('section' => 'setup'), array('form_sent' => '1', 'form' => array('board_title' => ' '))));
		$this->assertStringContainsString('Bad request', $this->page(array('section' => 'setup'), array('form_sent' => '1', 'form' => array('board_title' => 'B', 'default_style' => 'Nope', 'default_lang' => 'English', 'sef' => 'Default'))));
		$this->assertStringContainsString('Bad request', $this->page(array('section' => 'setup'), array('form_sent' => '1', 'form' => array('board_title' => 'B', 'default_style' => 'oxygen', 'default_lang' => 'English', 'sef' => 'Default'))));
		$this->assertStringContainsString('<p>The value of "Online timeout" must be smaller', $this->page(array('section' => 'setup'), array('form_sent' => '1', 'form' => array('board_title' => 'B', 'default_style' => 'Oxygen', 'default_lang' => 'English', 'sef' => 'Default', 'timeout_visit' => '10', 'timeout_online' => '10'))));
		$this->assertStringContainsString('<p>The admin email address you entered is invalid.</p>', $this->page(array('section' => 'email'), array('form_sent' => '1', 'form' => array('admin_email' => 'nobody'))));
		$this->assertStringContainsString('Bad request', $this->page(array('section' => 'email'), array('form_sent' => '1', 'form' => 'x')));

		$this->assertSame(array(), $this->configuration->log);
	}

	public function testTheFeaturesStoreUncheckedBoxesAndTheAvatarsDirectoryWithoutItsSlash(): void {
		$this->kit->settings->values = array_merge($this->kit->settings->values, array('o_ranks' => '1', 'o_avatars_dir' => 'img/avatars', 'o_gzip' => '0', 'o_quote_depth' => '3'));

		$this->page(array('section' => 'features'), array('form_sent' => '1', 'form' => array('ranks' => 'yes', 'gzip' => '1', 'avatars_dir' => 'img/avatars/', 'quote_depth' => '3')));

		$this->assertSame(array('option o_ranks=\'0\'', 'option o_gzip=\'1\'', 'permission p_sig_length=\'0\'', 'rebuilt'), $this->configuration->log, 'a number the form left out is stored as 0');
	}

	public function testASectionAnExtensionAddedIsSavedAndSentToTheSetup(): void {
		$this->kit->settings->values['o_probe'] = 'old';
		$this->kit->urls->missing = array('admin_settings_probe');

		$response = $this->page(array('section' => 'probe'), array('form_sent' => '1', 'form' => array('probe' => 'new')));

		$this->assertStringStartsWith('302 /admin_settings_setup?a=1&b=2 ', $response);
		$this->assertSame(array('option o_probe=\'new\'', 'rebuilt'), $this->configuration->log);
	}
}
