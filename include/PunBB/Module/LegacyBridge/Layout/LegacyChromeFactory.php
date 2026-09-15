<?php

declare(strict_types=1);

namespace PunBB\Module\LegacyBridge\Layout;

use PunBB\Module\LegacyBridge\Hook\PointEvaluator;
use PunBB\Module\LegacyBridge\Page\PageScope;
use PunBB\Module\Layout\Chrome\BareChromeInterface;
use PunBB\Module\Layout\Chrome\ChromeException;
use PunBB\Module\Layout\Chrome\ChromeFactoryInterface;
use PunBB\Module\Layout\Chrome\ChromeInterface;
use PunBB\Module\Layout\Chrome\Layout;
use PunBB\Module\Layout\Chrome\PageHead;
use PunBB\Module\Layout\View\Html;

/**
 * Opens a module's page the way a page script included header.php: the head is
 * published as $forum_page and the page constants, the point the page ran
 * before its header runs, and the header is built through the template
 * protocol, so a legacy .tpl theme and every header point see what they
 * always saw.
 */
final class LegacyChromeFactory implements ChromeFactoryInterface {
	public function __construct(
		private readonly TemplateProtocol $protocol,
		private readonly PageScope $scope,
		private readonly Layout $layout,
		private readonly PointEvaluator $points
	) {}

	public function open(PageHead $head): ChromeInterface {
		$page = isset($GLOBALS['forum_page']) && is_array($GLOBALS['forum_page']) ? $GLOBALS['forum_page'] : array();

		$page['crumbs'] = array();
		foreach ($head->crumbs as $crumb)
			$page['crumbs'][] = $crumb->link !== null ? array($crumb->text, $crumb->link->html) : $crumb->text;

		if ($head->page !== null)
			$page['page'] = $head->page;

		if ($head->pageCount !== null)
			$page['main_head_pages'] = $head->pageCount->html;

		if ($head->mainTitle !== null)
			$page['main_title'] = $head->mainTitle->html;

		foreach (array('page_post' => $head->pagePost, 'nav' => $head->navigation) as $key => $entries)
		{
			if ($entries === array())
				continue;

			$page[$key] = is_array($page[$key] ?? null) ? $page[$key] : array();
			foreach ($entries as $name => $markup)
				$page[$key][$name] = $markup->html;
		}

		// The menu is the page's own, as its point left it
		if ($head->menu !== array())
			$page['main_menu'] = array_map(static fn (Html $markup): string => $markup->html, $head->menu);

		$GLOBALS['forum_page'] = $page;

		if ($head->scripts !== array())
		{
			$loader = $GLOBALS['forum_loader'] ?? null;
			if (!$loader instanceof \Loader)
				throw new ChromeException('The legacy bootstrap has no loader in $forum_loader');

			foreach ($head->scripts as $script)
				$loader->add_js($script->code, $script->inline ? array('type' => 'inline') : null);
		}

		// The points pages ran last before their header, which see the head as the page left it
		match ($head->id.($head->view !== null ? ':'.$head->view : '')) {
			'message'				=> $this->scope->run('fn_message_pre_header_load'),
			'userlist'				=> $this->scope->run('ul_pre_header_load'),
			'admin-information'		=> $this->scope->run('ain_pre_header_load'),
			'postdelete'			=> $this->scope->run('dl_pre_header_load'),
			'index'					=> $this->scope->run('in_pre_header_load'),
			'admin-reports'			=> $this->scope->run('arp_pre_header_load'),
			'admin-reindex'			=> $this->scope->run('ari_pre_header_load'),
			'viewforum'				=> $this->scope->run('vf_pre_header_load'),
			'admin-prune'			=> $this->scope->run('apr_pre_header_load'),
			'admin-prune:confirm'	=> $this->scope->run('apr_prune_comply_pre_header_load'),
			'admin-censoring'		=> $this->scope->run('acs_pre_header_load'),
			'admin-ranks'			=> $this->scope->run('ark_pre_header_load'),
			'admin-bans'			=> $this->scope->run('aba_pre_header_load'),
			'admin-bans:form'		=> $this->scope->run('aba_add_edit_ban_pre_header_load'),
			'postedit'				=> $this->scope->run('ed_pre_header_load'),
			'viewtopic'				=> $this->scope->run('vt_pre_header_load'),
			'admin-categories'		=> $this->scope->run('acg_pre_header_load'),
			'admin-categories:delete'	=> $this->scope->run('acg_del_cat_pre_header_load'),
			'login'					=> $this->scope->run('li_login_pre_header_load'),
			'reqpass'				=> $this->scope->run('li_forgot_pass_pre_header_load'),
			'rules-register'		=> $this->scope->run('rg_rules_pre_header_load'),
			'register'				=> $this->scope->run('rg_register_pre_header_load'),
			'post'					=> $this->scope->run('po_pre_header_load'),
			'rules'					=> $this->scope->run('mi_rules_pre_header_load'),
			'formemail'				=> $this->scope->run('mi_email_pre_header_load'),
			'report'				=> $this->scope->run('mi_report_pre_header_load'),
			'admin-extensions-manage'	=> $this->scope->run('aex_section_manage_pre_header_load'),
			'admin-extensions-hotfixes'	=> $this->scope->run('aex_section_hotfixes_pre_header_load'),
			'admin-extensions-manage:install', 'admin-extensions-hotfixes:install'	=> $this->scope->run('aex_install_pre_header_load'),
			'admin-extensions-manage:install-notices', 'admin-extensions-hotfixes:install-notices'	=> $this->scope->run('aex_install_notices_pre_header_load'),
			'admin-extensions-manage:uninstall', 'admin-extensions-hotfixes:uninstall'	=> $this->scope->run('aex_uninstall_pre_header_load'),
			'admin-extensions-manage:uninstall-notices'	=> $this->scope->run('aex_uninstall_notices_pre_header_load'),
			'search'				=> $this->scope->run('se_pre_header_load'),
			'searchposts', 'searchtopics', 'searchforums'	=> $this->scope->run('se_results_pre_header_load'),
			'admin-forums'			=> $this->scope->run('afo_pre_header_load'),
			'admin-forums:delete'	=> $this->scope->run('afo_del_forum_pre_header_load'),
			'admin-forums:edit'		=> $this->scope->run('afo_edit_forum_pre_header_load'),
			'admin-groups'			=> $this->scope->run('agr_pre_header_load'),
			'admin-groups:form'		=> $this->scope->run('agr_add_edit_group_pre_header_load'),
			'admin-groups:remove'	=> $this->scope->run('agr_del_group_pre_header_load'),
			'admin-users'			=> $this->scope->run('aus_search_form_pre_header_load'),
			'admin-users:delete'	=> $this->scope->run('aus_delete_users_pre_header_load'),
			'admin-users:ban'		=> $this->scope->run('aus_ban_users_pre_header_load'),
			'admin-users:change_group'	=> $this->scope->run('aus_change_group_pre_header_load'),
			'admin-iresults'		=> $this->scope->run('aus_ip_stats_pre_header_load'),
			'admin-uresults:show_users'	=> $this->scope->run('aus_show_users_pre_header_load'),
			'admin-uresults:find_user'	=> $this->scope->run('aus_find_user_pre_header_load'),
			'dialogue:delete_posts'	=> $this->scope->run('mr_confirm_delete_posts_pre_header_load'),
			'dialogue:split_posts'	=> $this->scope->run('mr_confirm_split_posts_pre_header_load'),
			'dialogue:move_topics'	=> $this->scope->run('mr_move_topics_pre_header_load'),
			'dialogue:merge_topics'	=> $this->scope->run('mr_merge_topics_pre_header_load'),
			'dialogue:delete_topics'	=> $this->scope->run('mr_delete_topics_pre_header_load'),
			'modtopic'				=> $this->scope->run('mr_post_actions_pre_header_load'),
			'modforum'				=> $this->scope->run('mr_topic_actions_pre_header_load'),
			'admin-settings-setup'	=> $this->scope->run('aop_setup_pre_header_load'),
			'admin-settings-features'	=> $this->scope->run('aop_features_pre_header_load'),
			'admin-settings-email'	=> $this->scope->run('aop_email_pre_header_load'),
			'admin-settings-announcements'	=> $this->scope->run('aop_announcements_pre_header_load'),
			'admin-settings-registration'	=> $this->scope->run('aop_registration_pre_header_load'),
			'admin-settings-maintenance'	=> $this->scope->run('aop_maintenance_pre_header_load'),
			'profile'				=> $this->scope->run('pf_view_details_pre_header_load'),
			'profile-about'			=> $this->scope->run('pf_change_details_about_pre_header_load'),
			'profile-identity'		=> $this->scope->run('pf_change_details_identity_pre_header_load'),
			'profile-settings'		=> $this->scope->run('pf_change_details_settings_pre_header_load'),
			'profile-signature'		=> $this->scope->run('pf_change_details_signature_pre_header_load'),
			'profile-avatar'		=> $this->scope->run('pf_change_details_avatar_pre_header_load'),
			'profile-admin'			=> $this->scope->run('pf_change_details_admin_pre_header_load'),
			'profile-changepass'	=> $this->scope->run('pf_change_pass_normal_pre_header_load'),
			'profile-changepass:key'	=> $this->scope->run('pf_change_pass_key_pre_header_load'),
			'profile-changemail'	=> $this->scope->run('pf_change_email_normal_pre_header_load'),
			'dialogue:delete_user'	=> $this->scope->run('pf_delete_user_pre_header_load'),
			default					=> null,
		};

		if ($head->indexable && !defined('FORUM_ALLOW_INDEX'))
			define('FORUM_ALLOW_INDEX', 1);

		if ($head->section !== null && !defined('FORUM_PAGE_SECTION'))
			define('FORUM_PAGE_SECTION', $head->section);

		if (!defined('FORUM_PAGE'))
			define('FORUM_PAGE', $head->id);

		// The headers go out here, as header.php sent them: a page served unbuffered may print before its response is sent
		return new LegacyPageChrome($this->protocol, $this->protocol->header());
	}

	public function bare(string $chrome): BareChromeInterface {
		return new LegacyBareChrome($this->layout, $this->points, $chrome);
	}
}
