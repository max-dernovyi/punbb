<?php
/**
 * Extension code written for the page scripts, running on the pages that moved
 * onto modules, through the bridge, on a scratch forum: what each point sees,
 * what it changes, a query it rewrites, a variable it leaves for a later point,
 * a field it adds to a form, and the redirect and the confirmation form.
 *
 * @copyright (C) 2008-2012 PunBB, partially based on code (C) 2008-2009 FluxBB.org
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package PunBB
 */

use PHPUnit\Framework\TestCase;

require_once FORUM_ROOT.'.dev/tests/UnitTests/Extensions/ScratchForum.php';

class PageBridgeTest extends TestCase {
	private const PROBE = <<<'XML'
<?xml version="1.0" encoding="utf-8"?>
<extension engine="1.0">
	<id>page_probe</id>
	<title>Page probe</title>
	<version>1.0</version>
	<description>Runs at the points of the pages that moved onto modules.</description>
	<author>PunBB test suite</author>
	<minversion>1.4</minversion>
	<maxtestedon>1.5</maxtestedon>
	<install><![CDATA[$forum_db->add_field('groups', 'g_probe', 'INT(10)', false, 5);]]></install>
	<hooks>
		<hook id="ul_start"><![CDATA[$page_probe_started = 'started at ul_start';]]></hook>
		<hook id="ul_qr_get_user_count"><![CDATA[if (isset($_GET['probe_count'])) $query['WHERE'] .= ' AND u.id=2';]]></hook>
		<hook id="ul_qr_get_groups"><![CDATA[$query['WHERE'] .= ' AND g.g_id!=1';]]></hook>
		<hook id="ul_qr_get_users"><![CDATA[$query['SELECT'] .= ', u.email';]]></hook>
		<hook id="ul_pre_header_load"><![CDATA[$forum_page['crumbs'][1] = 'Probed list '.$forum_page['sort_by'];]]></hook>
		<hook id="ul_pre_sort_by"><![CDATA[?>				<div class="sf-set set<?php echo ++$forum_page['item_count'] ?>"><input id="fld<?php echo ++$forum_page['fld_count'] ?>" name="probe" /></div>
<?php]]></hook>
		<hook id="ul_results_pre_header_output"><![CDATA[$forum_page['table_header']['email'] = '<th class="tc'.count($forum_page['table_header']).'" scope="col">E-mail</th>';]]></hook>
		<hook id="ul_results_row_pre_data"><![CDATA[echo '<!-- row of ', $user_data['username'], ' -->';]]></hook>
		<hook id="ul_results_row_pre_data_output"><![CDATA[$forum_page['table_row']['email'] = '<td class="tc'.count($forum_page['table_row']).'">'.forum_htmlencode($user_data['email']).' #'.$forum_page['item_count'].'</td>';]]></hook>
		<hook id="ul_end"><![CDATA[echo '<p id="probe-end">', $page_probe_started, ' of ', count($founded_user_datas), '</p>';]]></hook>
		<hook id="he_start"><![CDATA[$page_probe_help = 'helped';]]></hook>
		<hook id="he_new_bbcode_link"><![CDATA[echo '<p id="probe-link">', $section, ' ', $lang_help['Help'], ' ', $page_probe_help, '</p>';]]></hook>
		<hook id="he_pre_smile_display"><![CDATA[$smilies[':probe:'] = 'probe.png';]]></hook>
		<hook id="mi_new_action"><![CDATA[if ($action == 'probe_message') { define('FORUM_PAGE', 'message'); require FORUM_ROOT.'header.php'; ob_start(); echo '<p id="probe-printed">printed</p>'; message('Probe after header'); }]]></hook>
		<hook id="fn_message_start"><![CDATA[$message .= ' (probed)';]]></hook>
		<hook id="fn_message_pre_header_load"><![CDATA[$forum_page['crumbs'][] = 'Probe crumb';]]></hook>
		<hook id="fn_message_output_end"><![CDATA[echo '<p id="probe-message">', $message, '</p>';]]></hook>
		<hook id="hd_head"><![CDATA[$forum_head[] = '<meta name="probe-appended" />';]]></hook>
		<hook id="in_start"><![CDATA[$page_probe_index = 'index started';]]></hook>
		<hook id="in_qr_get_cats_and_forums"><![CDATA[$query['SELECT'] .= ', f.num_topics AS probe_topics';]]></hook>
		<hook id="in_pre_header_load"><![CDATA[$forum_page['main_title'] = 'Probed '.$forum_page['main_title'];]]></hook>
		<hook id="in_forum_pre_cat_head"><![CDATA[$forum_page['item_header']['info']['probe'] = '<strong>probe '.$forum_page['cat_count'].'</strong>';]]></hook>
		<hook id="in_normal_row_pre_display"><![CDATA[$forum_page['item_body']['info']['probe'] = '<li class="probe">'.$cur_forum['probe_topics'].' topic in row '.$forum_page['item_count'].'</li>';]]></hook>
		<hook id="in_row_pre_display"><![CDATA[$forum_page['item_style'] .= ' probed';]]></hook>
		<hook id="in_stats_pre_info_output"><![CDATA[unset($stats_list['no_of_posts']); echo '<!-- stats of ', $forum_stats['total_users'], ' -->';]]></hook>
		<hook id="in_users_online_pre_online_info_output"><![CDATA[$forum_page['online_info']['probe'] = 'probe online';]]></hook>
		<hook id="in_end"><![CDATA[echo '<p id="probe-index">', $page_probe_index, '</p>';]]></hook>
		<hook id="ain_pre_community"><![CDATA[?>			<div class="ct-set group-item<?php echo ++$forum_page['item_count'] ?>"><p id="probe-box">probe</p></div>
<?php]]></hook>
		<hook id="ain_qr_get_users_online"><![CDATA[$query['WHERE'] .= ' AND 1=0';]]></hook>
		<hook id="ain_end"><![CDATA[echo '<p id="probe-ain">', $num_online, ' online</p>';]]></hook>
		<hook id="dl_qr_get_post_info"><![CDATA[$query['SELECT'] .= ', t.num_views AS probe_views';]]></hook>
		<hook id="dl_pre_permission_check"><![CDATA[$page_probe_admmod = $forum_page['is_admmod'] ? 'moderating' : 'not moderating';]]></hook>
		<hook id="dl_new_post_entry_data"><![CDATA[echo '<p id="probe-entry">', $page_probe_admmod, ' ', ($cur_post['is_topic'] ? 'topic' : 'reply'), ' viewed ', $cur_post['probe_views'], '</p>';]]></hook>
		<hook id="dl_pre_confirm_delete_checkbox"><![CDATA[?>				<div class="sf-set set<?php echo ++$forum_page['item_count'] ?>"><input id="fld<?php echo ++$forum_page['fld_count'] ?>" name="probe" /></div>
<?php]]></hook>
		<hook id="dl_end"><![CDATA[echo '<p id="probe-forum">', $forum_id, '</p>';]]></hook>
		<hook id="fn_redirect_start"><![CDATA[$message .= ' (probed)';]]></hook>
		<hook id="fn_redirect_template_loaded"><![CDATA[$tpl_redir = str_replace('<body>', '<body><!-- probe '.basename($tpl_path).' -->', $tpl_redir);]]></hook>
		<hook id="fn_redirect_head"><![CDATA[$forum_head['probe'] = '<meta name="probe" content="'.forum_htmlencode($message).'" />';]]></hook>
		<hook id="fn_csrf_confirm_form_pre_header_load"><![CDATA[$forum_page['hidden_fields']['probe'] = '<input type="hidden" name="probe" value="'.forum_htmlencode($forum_page['form_action']).'" />';]]></hook>
		<hook id="fn_csrf_confirm_form_end"><![CDATA[echo '<p id="probe-confirm">confirm end</p>';]]></hook>
		<hook id="arp_qr_get_new_reports"><![CDATA[$query['SELECT'] .= ', r.post_id AS probe_post';]]></hook>
		<hook id="arp_new_report_pre_display"><![CDATA[$message = '<em>'.$message.'</em> on post '.$cur_report['probe_post'];]]></hook>
		<hook id="arp_new_report_new_block"><![CDATA[echo '<p id="probe-block">report ', $forum_page['item_num'], ' field ', $forum_page['fld_count'], '</p>';]]></hook>
		<hook id="arp_end"><![CDATA[echo '<p id="probe-arp">', count($unread_reports), ' unread, ', (int) $forum_page['old_reports'], ' read</p>';]]></hook>
		<hook id="ari_qr_find_lowest_post_id"><![CDATA[$query['ORDER BY'] = 'p.id DESC';]]></hook>
		<hook id="ari_pre_rebuild_start_post"><![CDATA[?>				<div class="sf-set set<?php echo ++$forum_page['item_count'] ?>"><input id="fld<?php echo ++$forum_page['fld_count'] ?>" name="probe" /></div>
<?php]]></hook>
		<hook id="ari_cycle_end"><![CDATA[echo '<p id="probe-cycle">cycle from ', $start_at, ' ended at ', $post_id, '</p>';]]></hook>
		<hook id="vf_qr_get_topics"><![CDATA[$query['SELECT'] .= ', t.num_replies AS probe_replies';]]></hook>
		<hook id="vf_pre_header_load"><![CDATA[$forum_page['crumbs'][] = 'Probe crumb '.$forum_page['num_pages'];]]></hook>
		<hook id="vf_main_output_start"><![CDATA[$forum_page['main_head_options']['probe'] = '<span>probe option</span>'; echo '<!-- probe head -->';]]></hook>
		<hook id="vf_pre_topic_loop_start"><![CDATA[echo '<!-- ', count($topics), ' topics -->'; $topics = array_filter($topics, function ($topic) { return $topic['id'] != 99; });]]></hook>
		<hook id="vf_row_pre_display"><![CDATA[$forum_page['item_style'] .= ' probed-'.$cur_topic['probe_replies'];]]></hook>
		<hook id="vf_end"><![CDATA[echo '<p id="probe-vf">forum ', $id, ', moderating ', (int) $forum_page['is_admmod'], '</p>';]]></hook>
		<hook id="apr_pre_prune_forum_loop_start"><![CDATA[echo '<!-- forum ', $forum['fid'], ' after category ', $cur_category, ' -->', "\n";]]></hook>
		<hook id="apr_pre_prune_days"><![CDATA[?>				<div class="sf-set set<?php echo ++$forum_page['item_count'] ?>"><input id="fld<?php echo ++$forum_page['fld_count'] ?>" name="probe" /></div>
<?php]]></hook>
		<hook id="apr_prune_comply_pre_header_load"><![CDATA[$forum_page['crumbs'][] = 'Probe prunes '.$num_topics;]]></hook>
		<hook id="apr_prune_comply_pre_buttons"><![CDATA[echo '<p id="probe-prune">', $forum, '</p>';]]></hook>
		<hook id="ex_qr_get_topics"><![CDATA[$query['SELECT'] .= ', t.num_replies AS probe_replies';]]></hook>
		<hook id="ex_modify_cur_topic_item"><![CDATA[$feed['items'][count($feed['items']) - 1]['title'] .= ' ['.$cur_topic['probe_replies'].' replies]';]]></hook>
		<hook id="ex_add_new_rss_item_info"><![CDATA[echo "\t\t\t<probe>", $item['id'], "</probe>\n";]]></hook>
		<hook id="ex_pre_stats_output"><![CDATA[$stats['total_topics'] = 99;]]></hook>
		<hook id="ex_new_action"><![CDATA[if ($action == 'probe') { echo 'probed action'; exit; }]]></hook>
		<hook id="acs_start"><![CDATA[$page_probe_acs = 'censoring';]]></hook>
		<hook id="acs_add_word_qr_add_censor"><![CDATA[$query['VALUES'] = str_replace('probe-word', 'probed-word', $query['VALUES']);]]></hook>
		<hook id="acs_pre_edit_replace_with"><![CDATA[?>						<div class="mf-field"><input id="fld<?php echo ++$forum_page['fld_count'] ?>" name="probe[<?php echo $cur_word['id'] ?>]" /></div>
<?php]]></hook>
		<hook id="acs_end"><![CDATA[echo '<p id="probe-acs">', $page_probe_acs, ' ', count($forum_censors), '</p>';]]></hook>
		<hook id="ark_add_rank_qr_check_rank_collision"><![CDATA[if ($rank == 'Probe rank') $query['WHERE'] = '1=0';]]></hook>
		<hook id="ark_pre_edit_cur_rank_title"><![CDATA[echo '<!-- rank ', $cur_rank['rank'], ' at ', $forum_page['fld_count'], ' -->', "\n";]]></hook>
		<hook id="aba_main_output_start"><![CDATA[$forum_page['hidden_fields']['probe'] = '<input type="hidden" name="probe" value="'.$forum_page['form_action'].'" />';]]></hook>
		<hook id="aba_view_ban_pre_display"><![CDATA[$forum_page['ban_info']['probe'] = '<li>probe '.$cur_ban['username'].'</li>'; $forum_page['ban_creator'] = 'Creator '.$forum_page['item_num'];]]></hook>
		<hook id="aba_add_ban_qr_get_user_by_id"><![CDATA[$query['SELECT'] = str_replace('u.email', '\'probe@example.com\'', $query['SELECT']);]]></hook>
		<hook id="aba_add_edit_ban_pre_ip"><![CDATA[echo '<!-- ip for ', $user_id, ' in group ', $group_id, ' -->', "\n";]]></hook>
		<hook id="ed_pre_permission_check"><![CDATA[$page_probe_ed = $forum_page['is_admmod'] ? 'moderating' : 'not moderating';]]></hook>
		<hook id="ed_end_validation"><![CDATA[if ($message == 'probe error') $errors[] = 'Probe error in '.$id;]]></hook>
		<hook id="ed_pre_checkbox_display"><![CDATA[$forum_page['checkboxes']['probe'] = '<div class="mf-item"><input id="fld'.(++$forum_page['fld_count']).'" name="probe" /></div>';]]></hook>
		<hook id="ed_end"><![CDATA[echo '<p id="probe-ed">', $page_probe_ed, ' in forum ', $forum_id, '</p>';]]></hook>
		<hook id="vt_qr_get_topic_info"><![CDATA[$query['SELECT'] .= ', t.num_views AS probe_views';]]></hook>
		<hook id="vt_main_output_start"><![CDATA[$forum_page['main_head_options']['probe'] = '<span>probe views '.$cur_topic['probe_views'].'</span>'; if (isset($forum_page['main_foot_options'])) $forum_page['main_foot_options']['probe'] = '<span>probe foot</span>';]]></hook>
		<hook id="vt_row_pre_post_actions_merge"><![CDATA[$forum_page['post_actions']['probe'] = '<span>probe post '.$cur_post['id'].' number '.($forum_page['start_from'] + $forum_page['item_count']).'</span>';]]></hook>
		<hook id="vt_row_new_post_entry_data"><![CDATA[echo '<p class="probe-entry">', $cur_post['username'], '</p>', "\n";]]></hook>
		<hook id="vt_quickpost_pre_display"><![CDATA[$forum_page['hidden_fields']['probe'] = '<input type="hidden" name="probe" />';]]></hook>
		<hook id="vt_qr_increment_num_views"><![CDATA[$query['SET'] = 'num_views=num_views+10';]]></hook>
		<hook id="vt_end"><![CDATA[echo '<p id="probe-vt">topic ', $id, ' with ', count($posts_id), ' posts</p>';]]></hook>
		<hook id="acg_start"><![CDATA[$page_probe_acg = 'categories';]]></hook>
		<hook id="acg_qr_get_categories"><![CDATA[if (isset($_GET['probe'])) $query['WHERE'] = 'c.id=1';]]></hook>
		<hook id="acg_pre_new_category_name"><![CDATA[?>				<div class="sf-set set<?php echo ++$forum_page['item_count'] ?>"><input id="fld<?php echo ++$forum_page['fld_count'] ?>" name="probe" /></div>
<?php]]></hook>
		<hook id="acg_pre_edit_cat_name"><![CDATA[echo '<!-- category ', $cur_category['cat_name'], ' at ', $forum_page['fld_count'], ' -->', "\n";]]></hook>
		<hook id="acg_end"><![CDATA[echo '<p id="probe-acg">', $page_probe_acg, ' ', count($cat_list), '</p>';]]></hook>
		<hook id="li_start"><![CDATA[$page_probe_li = 'login';]]></hook>
		<hook id="li_login_pre_header_load"><![CDATA[$forum_page['crumbs'][] = 'Probe login crumb';]]></hook>
		<hook id="li_login_output_start"><![CDATA[$forum_page['hidden_fields']['probe'] = '<input type="hidden" name="probe" value="'.$forum_page['form_action'].'" />';]]></hook>
		<hook id="li_login_pre_pass"><![CDATA[?>				<div class="sf-set set<?php echo ++$forum_page['item_count'] ?>"><input id="fld<?php echo ++$forum_page['fld_count'] ?>" name="probe" /></div>
<?php]]></hook>
		<hook id="li_end"><![CDATA[echo '<p id="probe-li">', $page_probe_li, '</p>';]]></hook>
		<hook id="li_forgot_pass_pre_group"><![CDATA[echo '<!-- forgot at ', $forum_page['fld_count'], ' -->', "\n";]]></hook>
		<hook id="rg_start"><![CDATA[$page_probe_rg = 'register';]]></hook>
		<hook id="rg_register_pre_username"><![CDATA[?>				<div class="sf-set set<?php echo ++$forum_page['item_count'] ?>"><input id="fld<?php echo ++$forum_page['fld_count'] ?>" name="probe" /></div>
<?php]]></hook>
		<hook id="rg_register_pre_language"><![CDATA[$languages[] = 'Probe';]]></hook>
		<hook id="rg_end"><![CDATA[echo '<p id="probe-rg">', $page_probe_rg, '</p>';]]></hook>
		<hook id="po_start"><![CDATA[$page_probe_po_started = 'started';]]></hook>
		<hook id="po_qr_get_topic_forum_info"><![CDATA[$query['SELECT'] .= ', t.num_views AS probe_views';]]></hook>
		<hook id="po_pre_permission_check"><![CDATA[$page_probe_po = $forum_page['is_admmod'] ? 'moderating' : 'not moderating';]]></hook>
		<hook id="po_end_validation"><![CDATA[if ($message == 'probe error') $errors[] = 'Probe error in '.$tid;]]></hook>
		<hook id="po_pre_add_post"><![CDATA[$post_info['message'] .= ' (probed)';]]></hook>
		<hook id="po_pre_redirect"><![CDATA[$forum_db->query('UPDATE '.$forum_db->prefix.'posts SET message=message || \' #'.$new_pid.'\' WHERE id='.$new_pid);]]></hook>
		<hook id="po_modify_quote_info"><![CDATA[$quote_info['message'] = 'probe quote of '.$qid;]]></hook>
		<hook id="po_pre_optional_fieldset"><![CDATA[$forum_page['checkboxes']['probe'] = '<div class="mf-item"><input id="fld'.(++$forum_page['fld_count']).'" name="probe" /></div>';]]></hook>
		<hook id="po_topic_review_row_pre_display"><![CDATA[$forum_page['post_ident']['probe'] = '<span>probe '.$cur_post['id'].' of '.$forum_page['total_post_count'].'</span>';]]></hook>
		<hook id="po_end"><![CDATA[echo '<p id="probe-po">', $page_probe_po_started, ' ', $page_probe_po, ' in forum ', $forum_id, ' viewed ', ($cur_posting['probe_views'] ?? 'no'), '</p>';]]></hook>
		<hook id="mi_start"><![CDATA[$page_probe_mi = 'misc';]]></hook>
		<hook id="mi_email_qr_get_form_email_data"><![CDATA[$query['SELECT'] .= ', u.registered AS probe_registered';]]></hook>
		<hook id="mi_email_pre_header_load"><![CDATA[$forum_page['crumbs'][] = 'Probe mail crumb';]]></hook>
		<hook id="mi_email_output_start"><![CDATA[$forum_page['hidden_fields']['probe'] = '<input type="hidden" name="probe" value="'.$forum_page['form_action'].'" />';]]></hook>
		<hook id="mi_email_pre_subject"><![CDATA[?>				<div class="sf-set set<?php echo ++$forum_page['item_count'] ?>"><input id="fld<?php echo ++$forum_page['fld_count'] ?>" name="probe" /></div>
<?php]]></hook>
		<hook id="mi_email_end_validation"><![CDATA[if ($message == 'probe error') $errors[] = 'Probe mail error to '.$recipient_info['username'];]]></hook>
		<hook id="mi_email_end"><![CDATA[echo '<p id="probe-mi-email">', $page_probe_mi, ' ', $recipient_id, ' registered ', $recipient_info['probe_registered'], '</p>';]]></hook>
		<hook id="mi_report_add_report"><![CDATA[$query['VALUES'] = str_replace('probe reason', 'probed reason', $query['VALUES']);]]></hook>
		<hook id="mi_report_pre_redirect"><![CDATA[$forum_db->query('UPDATE '.$forum_db->prefix.'reports SET message=message || \' in '.$forum_db->escape($topic_info['subject']).'\' WHERE id=(SELECT MAX(id) FROM '.$forum_db->prefix.'reports) AND post_id='.$post_id);]]></hook>
		<hook id="mi_subscribe_pre_redirect"><![CDATA[$forum_db->query('INSERT INTO '.$forum_db->prefix.'search_words (word) VALUES (\'probe-'.$topic_id.' '.$forum_db->escape($subject).'\')');]]></hook>
		<hook id="mi_unsubscribe_qr_delete_subscription"><![CDATA[$query['WHERE'] .= ' AND 1=0';]]></hook>
		<hook id="aex_qr_get_all_extensions"><![CDATA[$query['SELECT'] .= ', \'probed\' AS probe_note';]]></hook>
		<hook id="aex_section_manage_pre_ext_actions"><![CDATA[$forum_page['ext_actions']['probe'] = '<span>probe '.$id.' '.$ext['probe_note'].'</span>';]]></hook>
		<hook id="aex_new_action"><![CDATA[if (isset($_GET['probe_action'])) message('Probe action in '.$section);]]></hook>
		<hook id="se_start"><![CDATA[$page_probe_se = 'searched';]]></hook>
		<hook id="sf_fn_validate_actions_start"><![CDATA[$valid_actions[] = 'show_probe';]]></hook>
		<hook id="se_additional_quicksearch_variables"><![CDATA[if ($action == 'show_probe') $value = 42;]]></hook>
		<hook id="sf_fn_no_search_results_start"><![CDATA[$forum_page['search_again'] = '<a href="probe">Probe again '.$action.' '.$value.'</a>';]]></hook>
		<hook id="sf_fn_generate_action_search_query_end"><![CDATA[if ($action == 'show_unanswered') $query['WHERE'] .= ' AND t.id=0';]]></hook>
		<hook id="sf_fn_generate_cached_search_query_qr_get_cached_hits_as_posts"><![CDATA[$query['SELECT'] .= ', p.poster_email AS probe_email';]]></hook>
		<hook id="se_results_pre_header_load"><![CDATA[$forum_page['crumbs'][] = 'Probe results crumb';]]></hook>
		<hook id="se_results_output_start"><![CDATA[$forum_page['main_head_options']['probe'] = '<span>Probe option for '.$forum_page['items_info'].'</span>';]]></hook>
		<hook id="se_results_topics_row_pre_item_title_merge"><![CDATA[$forum_page['item_title']['probe'] = '<em>probe '.$cur_set['tid'].' #'.$forum_page['item_count'].'</em>';]]></hook>
		<hook id="se_results_posts_row_pre_display"><![CDATA[$forum_page['post_actions']['probe'] = '<span>probe mail '.var_export($cur_set['probe_email'], true).'</span>';]]></hook>
		<hook id="se_results_end"><![CDATA[echo '<p id="probe-se">', $page_probe_se, ' ', $num_hits, ' ', $show_as, '</p>';]]></hook>
		<hook id="se_pre_header_load"><![CDATA[$forum_page['crumbs'][] = 'Probe form crumb';]]></hook>
		<hook id="se_pre_keywords"><![CDATA[?>				<div class="sf-set set<?php echo ++$forum_page['item_count'] ?>"><input id="fld<?php echo ++$forum_page['fld_count'] ?>" name="probe" /></div>
<?php]]></hook>
		<hook id="se_qr_get_cats_and_forums"><![CDATA[$query['SELECT'] .= ', f.forum_desc AS probe_desc';]]></hook>
		<hook id="se_forum_loop_end"><![CDATA[echo '<!-- probe forum ', $cur_forum['forum_name'], ' ', $cur_forum['probe_desc'], ' of ', count($forums), ' -->', "\n";]]></hook>
		<hook id="afo_start"><![CDATA[$page_probe_afo = 'forums';]]></hook>
		<hook id="afo_qr_get_cats_and_forums"><![CDATA[$query['SELECT'] .= ', f.num_topics AS probe_topics'; if (isset($_GET['probe'])) $query['WHERE'] = 'f.id=0';]]></hook>
		<hook id="afo_pre_new_forum_cat"><![CDATA[?>				<div class="sf-set set<?php echo ++$forum_page['item_count'] ?>"><input id="fld<?php echo ++$forum_page['fld_count'] ?>" name="probe" /></div>
<?php]]></hook>
		<hook id="afo_pre_edit_cur_forum_name"><![CDATA[echo '<!-- listed ', $cur_forum['forum_name'], ' with ', $cur_forum['probe_topics'], ' topics in ', $cur_category, ' -->', "\n";]]></hook>
		<hook id="afo_add_forum_qr_add_forum"><![CDATA[$query['VALUES'] = str_replace('probe forum', 'probed forum', $query['VALUES']);]]></hook>
		<hook id="afo_del_forum_qr_delete_forum"><![CDATA[$forum_db->query('INSERT INTO '.$forum_db->prefix.'config (conf_name, conf_value) VALUES (\'probe_deleted_forum\', \''.$forum_to_delete.'\')');]]></hook>
		<hook id="afo_edit_forum_qr_get_forum_details"><![CDATA[$query['SELECT'] .= ', f.num_posts AS probe_posts';]]></hook>
		<hook id="afo_edit_forum_pre_forum_cat"><![CDATA[echo '<!-- forum ', $forum_id, ' with ', $cur_forum['probe_posts'], ' posts -->', "\n";]]></hook>
		<hook id="afo_edit_forum_pre_permissions_part"><![CDATA[$forum_page['form_info']['probe'] = '<li><span>probe line</span></li>';]]></hook>
		<hook id="afo_edit_forum_pre_cur_group_read_forum_permission"><![CDATA[echo '<!-- group ', $cur_perm['g_title'], ' reads ', (int) $read_forum, ' at ', $forum_page['fld_count'], ' -->', "\n";]]></hook>
		<hook id="afo_save_forum_pre_perms_compare"><![CDATA[if ($cur_group['g_id'] == 3) $perms_new['post_topics'] = 0;]]></hook>
		<hook id="afo_end"><![CDATA[echo '<p id="probe-afo">', $page_probe_afo, ' ', count($forums), '</p>';]]></hook>
		<hook id="agr_start"><![CDATA[$page_probe_agr = 'groups';]]></hook>
		<hook id="agr_qr_get_group_list"><![CDATA[$query['SELECT'] .= ', g.g_user_title AS probe_user_title';]]></hook>
		<hook id="agr_edit_group_row_pre_output"><![CDATA[$forum_page['group_options']['probe'] = '<span>probe '.$cur_group['g_title'].' '.$cur_group['probe_user_title'].'</span>';]]></hook>
		<hook id="agr_pre_add_base_group"><![CDATA[?>				<div class="sf-set set<?php echo ++$forum_page['item_count'] ?>"><input id="fld<?php echo ++$forum_page['fld_count'] ?>" name="probe" /></div>
<?php]]></hook>
		<hook id="agr_add_edit_group_pre_allow_send_email_checkbox"><![CDATA[echo '<!-- probe column ', $group['g_probe'] ?? 'none', ' in ', $mode, ' at ', $forum_page['fld_count'], ' -->', "\n";]]></hook>
		<hook id="agr_add_edit_group_pre_email_interval"><![CDATA[echo '<!-- email interval ', $group_id, ' -->', "\n";]]></hook>
		<hook id="agr_add_end_qr_add_group"><![CDATA[$query['INSERT'] .= ', g_probe'; $query['VALUES'] .= ', 42';]]></hook>
		<hook id="agr_edit_end_qr_update_group"><![CDATA[$query['SET'] .= ', g_probe=g_probe+1';]]></hook>
		<hook id="agr_del_group_output_start"><![CDATA[echo '<!-- removing ', $group_info[0], ' with ', $group_info[1], ' -->', "\n";]]></hook>
		<hook id="agr_del_group_form_submitted"><![CDATA[$forum_db->query('DELETE FROM '.$forum_db->prefix.'config WHERE conf_name=\'probe_removed_group\''); $forum_db->query('INSERT INTO '.$forum_db->prefix.'config (conf_name, conf_value) VALUES (\'probe_removed_group\', \''.$group_id.' '.(isset($_POST['del_group']) ? 'moved' : 'linked').'\')');]]></hook>
		<hook id="agr_set_default_group_pre_redirect"><![CDATA[$forum_db->query('INSERT INTO '.$forum_db->prefix.'config (conf_name, conf_value) VALUES (\'probe_default_group\', \''.$group_id.'\')');]]></hook>
		<hook id="agr_end"><![CDATA[echo '<p id="probe-agr">', $page_probe_agr, '</p>';]]></hook>
		<hook id="aus_start"><![CDATA[$page_probe_aus = 'users';]]></hook>
		<hook id="aus_ip_stats_qr_get_user_ips"><![CDATA[$query['SELECT'] .= ', COUNT(p.topic_id) AS probe_topics';]]></hook>
		<hook id="aus_ip_stats_pre_row_output"><![CDATA[$forum_page['table_row']['probe'] = '<td class="probe">'.$cur_ip['probe_topics'].' topics #'.$forum_page['item_count'].'</td>';]]></hook>
		<hook id="aus_ip_stats_end"><![CDATA[echo '<p id="probe-aus-ips">', $page_probe_aus, ' ', $ip_stats, ' ', count($founded_ips), '</p>';]]></hook>
		<hook id="aus_show_users_qr_get_user_details"><![CDATA[$query['SELECT'] .= ', u.registration_ip AS probe_ip';]]></hook>
		<hook id="aus_show_users_pre_row_output"><![CDATA[if ($user_data) $forum_page['table_row']['probe'] = '<td class="probe">'.$user_data['probe_ip'].' of '.$user['poster'].'</td>';]]></hook>
		<hook id="aus_show_users_pre_moderation_buttons"><![CDATA[$forum_page['mod_options']['probe'] = '<span>probe button for '.$ip.'</span>';]]></hook>
		<hook id="aus_find_user_output_start"><![CDATA[$forum_page['table_header']['probe'] = '<th class="probe">probe of '.$forum_page['num_users'].' by '.$order_by.'</th>';]]></hook>
		<hook id="aus_find_user_qr_find_users"><![CDATA[$query['SELECT'] .= ', u.num_posts AS probe_posts';]]></hook>
		<hook id="aus_find_user_pre_row_output"><![CDATA[$forum_page['table_row']['probe'] = '<td class="probe">'.$user_data['probe_posts'].' posts, '.count($conditions).' condition</td>';]]></hook>
		<hook id="aus_search_form_pre_username"><![CDATA[?>				<div class="sf-set set<?php echo ++$forum_page['item_count'] ?>"><input id="fld<?php echo ++$forum_page['fld_count'] ?>" name="probe" /></div>
<?php]]></hook>
		<hook id="aus_search_form_qr_get_groups"><![CDATA[$query['WHERE'] .= ' AND g.g_id!=1';]]></hook>
		<hook id="aus_delete_users_qr_check_for_admins"><![CDATA[if (isset($_POST['probe_admin'])) $query['WHERE'] = '1=1';]]></hook>
		<hook id="aus_delete_users_output_start"><![CDATA[echo '<!-- deleting ', implode(',', $users), ' -->', "\n";]]></hook>
		<hook id="aus_ban_users_qr_add_ban"><![CDATA[$query['VALUES'] = str_replace('probe ban', 'probed ban of '.$cur_user['username'].' at '.$ban_ip, $query['VALUES']);]]></hook>
		<hook id="aus_ban_users_pre_redirect"><![CDATA[$forum_db->query('INSERT INTO '.$forum_db->prefix.'config (conf_name, conf_value) VALUES (\'probe_banned_users\', \''.implode(',', $users).' '.$forum_db->escape($ban_message).'\')');]]></hook>
		<hook id="aus_change_group_qr_get_groups"><![CDATA[$query['WHERE'] .= ' AND g.g_id!=4';]]></hook>
		<hook id="aus_change_group_pre_redirect"><![CDATA[$forum_db->query('INSERT INTO '.$forum_db->prefix.'config (conf_name, conf_value) VALUES (\'probe_changed_group\', \''.$move_to_group.' '.$group_is_mod.'\')');]]></hook>
		<hook id="mr_start"><![CDATA[$page_probe_mr = 'moderated';]]></hook>
		<hook id="mr_view_ip_qr_get_poster_ip"><![CDATA[$query['SELECT'] = '\'192.0.2.77\'';]]></hook>
		<hook id="mr_qr_get_forum_data"><![CDATA[$query['SELECT'] .= ', f.forum_desc AS probe_desc';]]></hook>
		<hook id="mr_pre_permission_check"><![CDATA[if (isset($_GET['probe_deny'])) $forum_user['g_id'] = 99;]]></hook>
		<hook id="mr_topic_actions_output_start"><![CDATA[$forum_page['main_head_options']['probe'] = '<span>probe '.$cur_forum['probe_desc'].'</span>';]]></hook>
		<hook id="mr_qr_get_topics"><![CDATA[$query['SELECT'] .= ', t.num_replies AS probe_replies';]]></hook>
		<hook id="mr_topic_actions_row_pre_display"><![CDATA[$forum_page['item_style'] .= ' probed-'.$cur_topic['probe_replies'].'-'.$forum_page['fld_count'];]]></hook>
		<hook id="mr_topic_actions_pre_mod_option_output"><![CDATA[unset($forum_page['mod_options']['mod_merge']);]]></hook>
		<hook id="mr_end"><![CDATA[echo '<p id="probe-mr">', $page_probe_mr, ' ', $forum_id, '</p>';]]></hook>
		<hook id="mr_post_actions_qr_get_posts"><![CDATA[$query['SELECT'] .= ', p.poster_ip AS probe_ip';]]></hook>
		<hook id="mr_row_pre_item_ident_merge"><![CDATA[$forum_page['post_ident']['probe'] = '<span>probe '.$cur_post['probe_ip'].' '.$cur_post['username'].'</span>';]]></hook>
		<hook id="mr_post_actions_new_post_entry_data"><![CDATA[echo '<p class="probe-entry">', $forum_page['item_count'], '</p>', "\n";]]></hook>
		<hook id="mr_confirm_split_posts_pre_subject"><![CDATA[echo '<!-- subject of ', implode(',', $posts), ' at ', $forum_page['fld_count'], ' -->', "\n";]]></hook>
		<hook id="mr_confirm_split_posts_qr_add_topic"><![CDATA[$query['VALUES'] = str_replace('probe split', 'probed split', $query['VALUES']);]]></hook>
		<hook id="mr_confirm_split_posts_pre_redirect"><![CDATA[$forum_db->query('INSERT INTO '.$forum_db->prefix.'config (conf_name, conf_value) VALUES (\'probe_split\', \''.$new_tid.' '.$forum_db->escape($new_subject).'\')');]]></hook>
		<hook id="mr_move_topics_forum_loop_start"><![CDATA[echo '<!-- target ', $cur_forum['forum_name'], ' -->', "\n";]]></hook>
		<hook id="mr_confirm_move_topics_qr_verify_topic_ids"><![CDATA[if (isset($_POST['probe_count'])) $query['WHERE'] = '1=0';]]></hook>
		<hook id="mr_open_close_single_topic_qr_get_subject"><![CDATA[$query['SELECT'] = '\'Probe subject\'';]]></hook>
		<hook id="mr_open_close_single_topic_pre_redirect"><![CDATA[$forum_db->query('INSERT INTO '.$forum_db->prefix.'config (conf_name, conf_value) VALUES (\'probe_closed\', \''.$topic_id.' '.$subject.' '.$action.'\')');]]></hook>
		<hook id="mr_stick_topic_qr_stick_topic"><![CDATA[$query['SET'] .= ', num_views=42';]]></hook>
		<hook id="mr_confirm_delete_topics_pre_redirect"><![CDATA[$forum_db->query('INSERT INTO '.$forum_db->prefix.'config (conf_name, conf_value) VALUES (\'probe_deleted_topics\', \''.implode(',', $forum_ids).' '.count($post_ids).'\')');]]></hook>
		<hook id="aop_start"><![CDATA[$page_probe_aop = 'settings';]]></hook>
		<hook id="aop_setup_pre_header_load"><![CDATA[$forum_page['crumbs'][] = 'Probe settings crumb';]]></hook>
		<hook id="aop_setup_pre_board_descrip"><![CDATA[?>					<div class="sf-set set<?php echo ++$forum_page['item_count'] ?>"><input id="fld<?php echo ++$forum_page['fld_count'] ?>" name="probe" /></div>
<?php]]></hook>
		<hook id="aop_announcements_validation"><![CDATA[$form['announcement_heading'] .= ' (validated in '.$section.')';]]></hook>
		<hook id="aop_qr_update_permission_option"><![CDATA[if ($key == 'announcement_heading') $query['SET'] = 'conf_value=\'Rewritten '.$forum_db->escape($input).'\'';]]></hook>
		<hook id="aop_pre_redirect"><![CDATA[$forum_db->query('INSERT INTO '.$forum_db->prefix.'config (conf_name, conf_value) VALUES (\'probe_settings\', \''.$section.' '.$form['announcement'].'\')');]]></hook>
		<hook id="aop_new_section"><![CDATA[if ($section == 'probe') { $forum_page['crumbs'] = array('Probe section'); define('FORUM_PAGE_SECTION', 'settings'); define('FORUM_PAGE', 'admin-settings-probe'); require FORUM_ROOT.'header.php'; ob_start(); echo '<p id="probe-aop-section">own section</p>'; }]]></hook>
		<hook id="aop_end"><![CDATA[echo '<p id="probe-aop">', $page_probe_aop, ' ', $section, '</p>';]]></hook>
		<hook id="pf_start"><![CDATA[$page_probe_pf = 'profile';]]></hook>
		<hook id="pf_qr_get_user_info"><![CDATA[$query['SELECT'] .= ', u.num_posts + 1000 AS probe_posts';]]></hook>
		<hook id="pf_change_details_modify_main_menu"><![CDATA[$forum_page['main_menu']['probe'] = '<li id="probe-menu">'.$section.' '.$user['probe_posts'].'</li>';]]></hook>
		<hook id="pf_change_details_about_pre_header_load"><![CDATA[$forum_page['crumbs'][] = 'Probe profile crumb';]]></hook>
		<hook id="pf_change_details_about_pre_user_private_info"><![CDATA[?>			<div id="probe-private" class="ct-set data-set set<?php echo ++$forum_page['item_count'] ?>"><?php echo $forum_page['own_profile'] ? 'own' : 'other', ' ', $page_probe_pf ?></div>
<?php]]></hook>
		<hook id="pf_change_details_identity_pre_realname"><![CDATA[?>				<div class="sf-set set<?php echo ++$forum_page['item_count'] ?>"><input id="fld<?php echo ++$forum_page['fld_count'] ?>" name="probe" /></div>
<?php]]></hook>
		<hook id="pf_change_details_identity_validation"><![CDATA[if ($form['realname'] == 'probe error') $errors[] = 'Probe identity error for '.$user['username'];]]></hook>
		<hook id="pf_change_details_qr_update_user"><![CDATA[$query['SET'] .= ', location=\'probed\'';]]></hook>
		<hook id="pf_change_details_pre_redirect"><![CDATA[$forum_db->query('INSERT INTO '.$forum_db->prefix.'config (conf_name, conf_value) VALUES (\'probe_profile\', \''.$section.' '.$forum_db->escape($form['realname']).'\')');]]></hook>
		<hook id="pf_change_details_settings_pre_timezone"><![CDATA[echo '<!-- timezone for ', $user['username'], ' at ', $forum_page['fld_count'], ' -->', "\n";]]></hook>
		<hook id="pf_change_details_admin_qr_get_groups"><![CDATA[$query['WHERE'] .= ' AND g.g_id!=4';]]></hook>
		<hook id="pf_change_details_admin_forum_loop_start"><![CDATA[echo '<!-- forum ', $cur_forum['forum_name'], ' at ', $forum_page['fld_count'], ' -->', "\n";]]></hook>
		<hook id="pf_change_pass_normal_qr_update_password"><![CDATA[$query['SET'] .= ', title=\'probed password\'';]]></hook>
		<hook id="pf_change_details_new_section"><![CDATA[if ($section == 'probe') { $forum_page['crumbs'] = array('Probe section'); define('FORUM_PAGE', 'profile-probe'); require FORUM_ROOT.'header.php'; ob_start(); echo '<p id="probe-pf-section">own section of ', $user['username'], ' ', count($forum_page['main_menu']), '</p>'; }]]></hook>
		<hook id="pf_view_details_output_start"><![CDATA[echo '<p id="probe-pf-view">', $user['username'], '</p>';]]></hook>
	</hooks>
</extension>
XML;

	private static ?ScratchForum $forum = null;

	public static function setUpBeforeClass(): void {
		if (!class_exists('SQLite3'))
			return;

		self::$forum = new ScratchForum();
		self::$forum->debug(false);
		self::$forum->writeExtension('page_probe', self::PROBE);
		self::$forum->submit('admin/extensions.php', array('install' => 'page_probe'), array('install_comply' => '1'));
	}

	public static function tearDownAfterClass(): void {
		self::$forum?->remove();
		self::$forum = null;
	}

	/**
	 * @param array<string, string> $get
	 * @param array<string, string> $post
	 */
	private function page(string $path, array $get = array(), array $post = array(), bool $submit = false): string {
		if (self::$forum === null)
			$this->markTestSkipped('the scratch forum needs ext-sqlite3');

		$page = $submit ? self::$forum->submit($path, $get, $post) : self::$forum->request($path, $get, $post);

		foreach (array('Fatal error', 'Warning:', 'Deprecated:', 'Notice:', 'Sorry! The page could not be loaded.') as $diagnostic)
			$this->assertStringNotContainsString($diagnostic, $page, $page);

		return $page;
	}

	/**
	 * @param array<string, string> $get
	 */
	private function guestPage(string $path, array $get = array()): string {
		if (self::$forum === null)
			$this->markTestSkipped('the scratch forum needs ext-sqlite3');

		$page = self::$forum->requestAsGuest($path, $get);

		foreach (array('Fatal error', 'Warning:', 'Deprecated:', 'Notice:', 'Sorry! The page could not be loaded.') as $diagnostic)
			$this->assertStringNotContainsString($diagnostic, $page, $page);

		return $page;
	}

	public function testTheMemberListRunsItsPointsWithWhatThePageScriptGaveThem(): void {
		$page = $this->page('userlist.php', array('sort_by' => 'registered'));

		$this->assertStringContainsString('<title>Probed list registered (Page 1) — ', $page, 'ul_pre_header_load saw the search and changed the crumbs');
		$this->assertStringNotContainsString('<option value="1">Administrators</option>', $page, 'the groups query ul_qr_get_groups rewrote answered');
		$this->assertStringContainsString("\t\t\t\t<div class=\"sf-set set3\"><input id=\"fld3\" name=\"probe\" /></div>\n\t\t\t\t<div class=\"sf-set set4\">", $page, 'ul_pre_sort_by added an item and a field');
		$this->assertStringContainsString('<select id="fld4" name="sort_by">', $page, 'the form numbered on from the field ul_pre_sort_by added');
		$this->assertStringContainsString('<th class="tc4" scope="col">E-mail</th>', $page);
		$this->assertStringContainsString('<!-- row of admin -->', $page);
		$this->assertStringContainsString('<td class="tc4">admin@example.com #1</td>', $page, 'the column ul_qr_get_users added reached the row');
		$this->assertStringContainsString('<p id="probe-end">started at ul_start of 1</p>', $page, 'a variable ul_start left is still there at ul_end');
	}

	public function testACountQueryAPointRewroteIsTheOneThatAnswers(): void {
		self::$forum?->rows('INSERT INTO users (group_id, username, password, email, registered) SELECT 3, \'probe-member\', \'x\', \'probe@example.com\', 1 WHERE NOT EXISTS (SELECT 1 FROM users WHERE username=\'probe-member\')');

		$this->assertStringContainsString('<span class="item-info">Users: 2</span>', $this->page('userlist.php'));

		$page = $this->page('userlist.php', array('probe_count' => '1'));
		$this->assertStringContainsString('<span class="item-info">Users: 1</span>', $page);
		$this->assertStringContainsString('<p id="probe-end">started at ul_start of 2</p>', $page, 'the members query was not the one rewritten');
	}

	public function testTheHelpPageRunsItsPoints(): void {
		$this->assertStringContainsString('<p id="probe-link">bbcode Help helped</p>', $this->page('help.php', array('section' => 'bbcode')));
		$this->assertStringContainsString('<p>:probe: <span>produces</span> <img src="http://forum.test/img/smilies/probe.png" width="15" height="15" alt=":probe:" /></p>', $this->page('help.php', array('section' => 'smilies')));
	}

	public function testAMessageRunsItsPoints(): void {
		$page = $this->page('help.php');

		$this->assertStringContainsString('<p>Bad request. The link you followed is incorrect or outdated. (probed)</p>', $page);
		$this->assertStringContainsString('<p id="probe-message">Bad request. The link you followed is incorrect or outdated. (probed)</p>', $page);
		$this->assertStringContainsString('Probe crumb</span>', $page, 'fn_message_pre_header_load added a crumb');
	}

	public function testTheBoardIndexRunsItsPointsOnItsRowsAndItsStatistics(): void {
		$page = $this->page('index.php');

		$this->assertStringContainsString('<h1 class="main-title">Probed My PunBB forum</h1>', $page, 'in_pre_header_load changed the heading');
		$this->assertStringContainsString('<strong class="info-lastpost">last post</strong>, <strong>probe 1</strong></span>', $page);
		$this->assertStringContainsString('class="main-item odd main-first-item probed">', $page);
		$this->assertStringContainsString('<li class="probe">1 topic in row 1</li>', $page, 'the column in_qr_get_cats_and_forums added reached the row');
		$this->assertMatchesRegularExpression('#<!-- stats of [0-9]+ --><div id="brd-stats"#', $page, 'in_stats_pre_info_output saw $forum_stats');
		$this->assertStringNotContainsString('Total number of posts', $page);
		$this->assertStringContainsString(', probe online</span></h3>', $page);
		$this->assertStringContainsString('<p id="probe-index">index started</p>', $page, 'a variable in_start left is still there at in_end');
		$this->assertStringContainsString('<meta name="probe-appended" />', $page, 'an entry appended with [] is named by a number');
	}

	public function testTheAdministrationIndexNumbersTheBoxAPointAdds(): void {
		$page = $this->page('admin/index.php');

		$this->assertStringContainsString("<div class=\"ct-set group-item2\"><p id=\"probe-box\">probe</p></div>\n\t\t\t<div class=\"ct-set group-item3\">", $page);
		$this->assertStringContainsString('(0 users online)', $page, 'the count ain_qr_get_users_online rewrote answered');
		$this->assertStringContainsString('<p id="probe-ain">0 online</p>', $page);
	}

	public function testTheDeletionPageRunsItsPointsWithThePostThePageScriptKept(): void {
		$page = $this->page('delete.php', array('id' => '1'));

		$this->assertStringContainsString('<p id="probe-entry">moderating topic viewed 0</p>', $page);
		$this->assertStringContainsString("<div class=\"sf-set set1\"><input id=\"fld1\" name=\"probe\" /></div>\n\t\t\t\t<div class=\"sf-set set2\">", $page);
		$this->assertStringContainsString('<input type="checkbox" id="fld2" name="req_confirm"', $page);
		$this->assertStringContainsString('<p id="probe-forum">1</p>', $page, 'the post\'s forum is left in $forum_id');
	}

	public function testARedirectRunsItsPointsOverItsTemplateAndItsHead(): void {
		$page = $this->page('delete.php', array('id' => '1'), array('cancel' => '1'), true);

		$this->assertStringContainsString('<body><!-- probe redirect.phtml -->', $page);
		$this->assertStringContainsString('<meta name="probe" content="Operation cancelled. (probed)" />', $page);
		$this->assertStringContainsString('<span>Operation cancelled. (probed) Redirecting…</span>', $page);
	}

	public function testTheReportsRunTheirPointsWithTheReportsThePageScriptKept(): void {
		self::$forum?->rows('INSERT INTO reports (post_id, topic_id, forum_id, reported_by, created, message) SELECT 1, 1, 1, 2, 1000, \'Probe <report>\' WHERE NOT EXISTS (SELECT 1 FROM reports)');

		$page = $this->page('admin/reports.php');

		$this->assertStringContainsString('<p><em>Probe &lt;report&gt;</em> on post 1</p>', $page, 'the column arp_qr_get_new_reports added reached the report');
		$this->assertStringContainsString("<p id=\"probe-block\">report 1 field 1</p>\t\t\t\t</div>", $page);
		$this->assertStringContainsString('<p id="probe-arp">1 unread, 0 read</p>', $page);
	}

	public function testTheRebuildRunsItsPointsOnTheFormAndTheCycle(): void {
		$page = $this->page('admin/reindex.php');

		$this->assertStringContainsString("<div class=\"sf-set set2\"><input id=\"fld2\" name=\"probe\" /></div>\n\t\t\t\t<div class=\"sf-set set3\">", $page);
		$this->assertStringContainsString('name="i_start_at" size="7" maxlength="7" value="1" />', $page);

		preg_match('#name="csrf_token" value="([0-9a-f]{40})"#', $page, $token);
		$cycle = $this->page('admin/reindex.php', array('i_per_page' => '10', 'i_start_at' => '1', 'csrf_token' => $token[1] ?? ''));

		$this->assertStringContainsString("</p><p id=\"probe-cycle\">cycle from 1 ended at 1</p><script type=\"text/javascript\">", $cycle);
	}

	public function testTheForumRunsItsPointsOnItsTopicsAndTheirRows(): void {
		$page = $this->page('viewforum.php', array('id' => '1'));

		$this->assertStringContainsString('Probe crumb 1</span>', $page, 'vf_pre_header_load saw the page count');
		$this->assertStringContainsString('<span>probe option</span></p>', $page, 'vf_main_output_start added an option above the list');
		$this->assertStringContainsString('<!-- probe head -->', $page);
		$this->assertStringContainsString("<!-- 1 topics -->\t\t<div id=\"topic1\" class=\"main-item odd main-first-item normal probed-0\">", $page, 'the column vf_qr_get_topics added reached the row');
		$this->assertStringContainsString('<p id="probe-vf">forum 1, moderating 1</p>', $page);
	}

	public function testPruningRunsItsPointsOnTheFormAndTheConfirmation(): void {
		$page = $this->page('admin/prune.php');

		$this->assertStringContainsString("<option value=\"all\">All forums</option>\n<!-- forum 1 after category 0 -->\n", $page);
		$this->assertStringContainsString("<div class=\"sf-set set2\"><input id=\"fld2\" name=\"probe\" /></div>\n\t\t\t\t<div class=\"sf-set set3\">", $page);

		$confirm = $this->page('admin/prune.php', array('action' => 'foo'), array('prune' => '1', 'req_prune_days' => '0', 'prune_from' => 'all'), true);

		$this->assertStringContainsString('Probe prunes 1</span>', $confirm, 'apr_prune_comply_pre_header_load saw the count');
		$this->assertStringContainsString('<p id="probe-prune">all forums</p>', $confirm);
	}

	public function testSyndicationRunsItsPointsOnTheFeedAndTheStatistics(): void {
		$feed = $this->page('extern.php', array('action' => 'feed', 'type' => 'rss'));

		$this->assertStringContainsString('<title><![CDATA[Test post [0 replies]]]></title>', $feed, 'the column ex_qr_get_topics added reached the item');
		$this->assertStringContainsString("\t\t\t<probe>1</probe>\n\t\t</item>", $feed);
		$this->assertStringContainsString('Total number of topics: 99<br />', $this->page('extern.php', array('action' => 'stats')));
		$this->assertStringEndsWith("\nprobed action", $this->page('extern.php', array('action' => 'probe')), 'ex_new_action answered and exited');
	}

	public function testAFormWithoutItsTokenIsConfirmedWithTheFieldsAPointAdds(): void {
		$page = $this->page('delete.php', array('id' => '1'), array('cancel' => '1'));

		$this->assertStringContainsString('<input type="hidden" name="cancel" value="1" />'."\n\t\t\t\t".'<input type="hidden" name="probe" value="http://forum.test/delete.php?id=1" />', $page);
		$this->assertStringContainsString("</div>\n<p id=\"probe-confirm\">confirm end</p>", $page);
	}
	public function testCensoringRunsItsPointsOnTheFormTheWordsAndTheStatement(): void {
		$this->page('admin/censoring.php', array('action' => 'foo'), array('add_word' => '1', 'new_search_for' => 'probe-word', 'new_replace_with' => 'p*'), true);

		$page = $this->page('admin/censoring.php');

		$this->assertStringContainsString('name="search_for[1]" value="probed-word"', $page, 'the statement acs_add_word_qr_add_censor changed is the one that ran');
		$this->assertStringContainsString("<div class=\"mf-field\"><input id=\"fld4\" name=\"probe[1]\" /></div>\n\t\t\t\t\t\t<div class=\"mf-field\">\n\t\t\t\t\t\t\t<label for=\"fld5\">", $page);
		$this->assertStringContainsString('<p id="probe-acs">censoring 1</p>', $page);
	}

	public function testRanksRunTheirPointsOnTheCheckAndTheList(): void {
		$this->assertStringContainsString('There is already a rank with a minimum posts value of 10.', $this->page('admin/ranks.php', array('action' => 'foo'), array('add_rank' => '1', 'new_rank' => 'Other rank', 'new_min_posts' => '10'), true));
		$this->page('admin/ranks.php', array('action' => 'foo'), array('add_rank' => '1', 'new_rank' => 'Probe rank', 'new_min_posts' => '10'), true);

		$page = $this->page('admin/ranks.php');

		$this->assertStringContainsString("<!-- rank New member at 2 -->\n", $page);
		$this->assertStringContainsString('value="Probe rank"', $page, 'the check ark_add_rank_qr_check_rank_collision rewrote let the rank in');
	}

	public function testBansRunTheirPointsOnTheListAndTheForm(): void {
		self::$forum?->rows('INSERT INTO users (group_id, username, password, email, registered) SELECT 3, \'probe-member\', \'x\', \'probe@example.com\', 1 WHERE NOT EXISTS (SELECT 1 FROM users WHERE username=\'probe-member\')');
		self::$forum?->rows('INSERT INTO bans (username, ban_creator) SELECT \'probe-banned\', 2 WHERE NOT EXISTS (SELECT 1 FROM bans)');
		$memberId = (int) (self::$forum?->rows('SELECT id FROM users WHERE username=\'probe-member\'')[0]['id'] ?? 0);

		$page = $this->page('admin/bans.php');

		$this->assertStringContainsString('<input type="hidden" name="probe" value="http://forum.test/admin/bans.php?sort_by=1&amp;action=more" />', $page);
		$this->assertStringContainsString("<li><span>Username:</span> <strong>probe-banned</strong></li>\n<li>probe probe-banned</li>", $page);
		$this->assertStringContainsString('<h3><span>Banned by Creator 0</span></h3>', $page);

		$form = $this->page('admin/bans.php', array('add_ban' => (string) $memberId));

		$this->assertStringContainsString('name="ban_email" size="40" maxlength="80" value="probe@example.com" />', $form, 'the lookup aba_add_ban_qr_get_user_by_id rewrote answered');
		$this->assertStringContainsString('<!-- ip for '.$memberId.' in group 3 -->', $form);
	}

	public function testTheEditPageRunsItsPointsOnTheFormAndTheValidation(): void {
		$page = $this->page('edit.php', array('id' => '1'));

		$this->assertStringContainsString("<div class=\"mf-item\"><input id=\"fld5\" name=\"probe\" /></div>\n\t\t\t\t\t</div>", $page, 'the checkbox ed_pre_checkbox_display added numbers on from hide_smilies and silent');
		$this->assertStringContainsString('<p id="probe-ed">moderating in forum 1</p>', $page);

		$errors = $this->page('edit.php', array('id' => '1'), array('form_sent' => '1', 'req_subject' => 'Test post', 'req_message' => 'probe error'), true);
		$this->assertStringContainsString('<li><span>Probe error in 1</span></li>', $errors);
	}

	public function testTheTopicRunsItsPointsOnTheOptionsThePostsAndTheQuickReply(): void {
		$this->page('viewtopic.php', array('id' => '1'));
		$page = $this->page('viewtopic.php', array('id' => '1'));

		$this->assertMatchesRegularExpression('#<span>probe views [1-9][0-9]*0</span></p>#', $page, 'the column vt_qr_get_topic_info added, and the views vt_qr_increment_num_views counted ten at a time');
		$this->assertStringContainsString('<span>probe foot</span></p>', $page);
		$this->assertStringContainsString('<span>probe post 1 number 1</span></p>', $page);
		$this->assertStringContainsString("</div>\n<p class=\"probe-entry\">admin</p>\n\t\t\t\t</div>", $page);
		$this->assertStringContainsString('<p id="probe-vt">topic 1 with 1 posts</p>', $page);
		$this->assertStringContainsString("\n\t\t\t\t<input type=\"hidden\" name=\"probe\" />\n\t\t</div>", $page);
	}

	public function testTheCategoriesRunTheirPointsOnTheFormsAndTheList(): void {
		self::$forum?->rows('INSERT INTO categories (cat_name, disp_position) SELECT \'Probe category\', 5 WHERE NOT EXISTS (SELECT 1 FROM categories WHERE cat_name=\'Probe category\')');

		$page = $this->page('admin/categories.php');

		$this->assertStringContainsString('<p id="probe-acg">categories 2</p>', $page);
		$this->assertStringContainsString('<!-- category Probe category at ', $page);
		$this->assertMatchesRegularExpression('#<input id="fld([0-9]+)" name="probe" /></div>\n\t\t\t\t<div class="sf-set set[0-9]+">\n\t\t\t\t\t<div class="sf-box text">\n\t\t\t\t\t\t<label for="fld(?!\1)[0-9]+"><span>New category name#', $page, 'the category name numbers on from the field acg_pre_new_category_name added');

		$this->assertStringContainsString('<p id="probe-acg">categories 1</p>', $this->page('admin/categories.php', array('probe' => '1')), 'the list acg_qr_get_categories rewrote answered');
	}

	public function testTheLoginFormsRunTheirPointsForAGuest(): void {
		$page = $this->guestPage('login.php');

		$this->assertStringContainsString('Probe login crumb</span>', $page, 'li_login_pre_header_load added a crumb');
		$this->assertStringContainsString('<input type="hidden" name="probe" value="http://forum.test/login.php" />', $page);
		$this->assertMatchesRegularExpression('#<input id="fld2" name="probe" /></div>\n\t\t\t\t<div class="sf-set set3">\n\t\t\t\t\t<div class="sf-box text required">\n\t\t\t\t\t\t<label for="fld3"><span>Password</span>#', $page, 'the password numbers on from the field li_login_pre_pass added');
		$this->assertStringContainsString('<p id="probe-li">login</p>', $page, 'a variable li_start left is still there at li_end');

		$this->assertMatchesRegularExpression('#<!-- forgot at [0-9]+ -->\n#', $this->guestPage('login.php', array('action' => 'forget')));
	}

	public function testTheRegistrationRunsItsPointsForAGuest(): void {
		$page = $this->guestPage('register.php', array('agree' => '1', 'req_agreement' => '1'));

		$this->assertMatchesRegularExpression('#<input id="fld2" name="probe" /></div>\n\t\t\t\t<div class="sf-set set3 prepend-top">\n\t\t\t\t\t<div class="sf-box text required">\n\t\t\t\t\t\t<label for="fld3"><span>Username</span>#', $page, 'rg_register_pre_username added an item and a field');
		$this->assertStringContainsString('<option value="Probe">Probe</option>', $page, 'the language rg_register_pre_language offered');
		$this->assertStringContainsString('<p id="probe-rg">register</p>', $page);
	}

	public function testThePostingPageRunsItsPointsOnTheFormTheQuoteAndTheReview(): void {
		$page = $this->page('post.php', array('tid' => '1', 'qid' => '1'));

		$this->assertStringContainsString('spellcheck="true">[quote=admin]probe quote of 1[/quote]'."\n".'</textarea>', $page, 'po_modify_quote_info changed the quote');
		$this->assertStringContainsString("<div class=\"mf-item\"><input id=\"fld4\" name=\"probe\" /></div>\n\t\t\t\t\t</div>", $page, 'the checkbox po_pre_optional_fieldset added numbers on from hide_smilies and subscribe');
		$this->assertMatchesRegularExpression('#<span class="post-link"><a class="permalink" rel="bookmark" title="Permanent link to this post" href="http://forum.test/viewtopic.php\?pid=1\#p1">[^<]+</a></span> <span>probe 1 of [1-9][0-9]*</span></h3>#', $page);
		$this->assertMatchesRegularExpression('#<p id="probe-po">started moderating in forum 1 viewed [0-9]+</p>#', $page, 'the column po_qr_get_topic_forum_info added reached $cur_posting');

		$errors = $this->page('post.php', array('tid' => '1'), array('form_sent' => '1', 'form_user' => 'admin', 'req_message' => 'probe error'), true);
		$this->assertStringContainsString('<li class="warn"><span>Probe error in 1</span></li>', $errors);

		$this->page('post.php', array('tid' => '1'), array('form_sent' => '1', 'form_user' => 'admin', 'req_message' => 'probe reply'), true);
		$stored = self::$forum?->rows('SELECT id, message FROM posts ORDER BY id DESC LIMIT 1')[0] ?? array();

		$this->assertSame('probe reply (probed) #'.($stored['id'] ?? ''), $stored['message'] ?? null, 'the post po_pre_add_post changed was stored, and po_pre_redirect saw its id');
	}

	public function testMiscRunsItsPointsOnTheMailFormTheReportAndTheSubscriptions(): void {
		self::$forum?->rows('INSERT INTO users (group_id, username, password, email, registered) SELECT 3, \'probe-member\', \'x\', \'probe@example.com\', 1 WHERE NOT EXISTS (SELECT 1 FROM users WHERE username=\'probe-member\')');
		$memberId = (string) (self::$forum?->rows('SELECT id FROM users WHERE username=\'probe-member\'')[0]['id'] ?? 0);

		$page = $this->page('misc.php', array('email' => $memberId));

		$this->assertStringContainsString('Probe mail crumb</span>', $page, 'mi_email_pre_header_load added a crumb');
		$this->assertStringContainsString('<input type="hidden" name="probe" value="http://forum.test/misc.php?email='.$memberId.'" />', $page);
		$this->assertMatchesRegularExpression('#<input id="fld1" name="probe" /></div>\n\t\t\t\t<div class="sf-set set2">\n\t\t\t\t\t<div class="sf-box text required longtext">\n\t\t\t\t\t\t<label for="fld2">#', $page, 'the subject numbers on from the field mi_email_pre_subject added');
		$this->assertStringContainsString('<p id="probe-mi-email">misc '.$memberId.' registered 1</p>', $page, 'the column mi_email_qr_get_form_email_data added reached $recipient_info');

		$errors = $this->page('misc.php', array('email' => $memberId), array('form_sent' => '1', 'req_subject' => 'Hi', 'req_message' => 'probe error'), true);
		$this->assertStringContainsString('<li class="warn"><span>Probe mail error to probe-member</span></li>', $errors);

		$this->page('misc.php', array('report' => '1'), array('form_sent' => '1', 'req_reason' => 'probe reason'), true);
		$this->assertSame('probed reason in Test post', self::$forum?->rows('SELECT message FROM reports ORDER BY id DESC LIMIT 1')[0]['message'] ?? null, 'the statement mi_report_add_report changed ran, and mi_report_pre_redirect saw the topic');

		$this->page('misc.php', array('subscribe' => '1'), array(), true);
		$this->assertSame(array(array('word' => 'probe-1 Test post')), self::$forum?->rows('SELECT word FROM search_words WHERE word LIKE \'probe-%\''));

		$this->page('misc.php', array('unsubscribe' => '1'), array(), true);
		$this->assertStringContainsString('You are already subscribed to this topic.', $this->page('misc.php', array('subscribe' => '1'), array(), true), 'the removal mi_unsubscribe_qr_delete_subscription rewrote removed nothing');
	}

	public function testAMessageAfterTheHeaderFollowsWhatThePagePrintedInsideTheMain(): void {
		$page = $this->page('misc.php', array('action' => 'probe_message'));

		$this->assertSame(1, substr_count($page, '<p id="probe-printed">printed</p>'), $page);
		$this->assertSame(1, preg_match('#<!DOCTYPE html>.*<div id="brd-main">.*<p id="probe-printed">printed</p>\s*<div class="main-head">.*<p>Probe after header \(probed\)</p>#s', $page), $page);
	}

	public function testTheExtensionsPageRunsItsPointsOnTheListAndAnActionOfItsOwn(): void {
		$this->assertStringContainsString('<span>probe page_probe probed</span></p>', $this->page('admin/extensions.php', array('section' => 'manage')), 'the column aex_qr_get_all_extensions added reached $ext at aex_section_manage_pre_ext_actions');
		$this->assertStringContainsString('<p>Probe action in manage (probed)</p>', $this->page('admin/extensions.php', array('section' => 'manage', 'probe_action' => '1')), 'aex_new_action answered before the list');
	}

	public function testSearchRunsItsPointsOnTheFormTheResultsAndTheQuickSearches(): void {
		$form = $this->page('search.php', array('advanced' => '1'));

		$this->assertStringContainsString('Probe form crumb</span>', $form, 'se_pre_header_load added a crumb');
		$this->assertStringContainsString('<input id="fld1" name="probe" /></div>'."\n\t\t\t\t".'<div class="sf-set set2">'."\n\t\t\t\t\t".'<div class="sf-box text">'."\n\t\t\t\t\t\t".'<label for="fld2">', $form, 'the keywords number on from the field se_pre_keywords added');
		$this->assertStringContainsString('<label for="fld5">Test forum</label></div>'."\n".'<!-- probe forum Test forum This is just a test forum of 1 -->', $form, 'the column se_qr_get_cats_and_forums added reached $cur_forum');

		$recent = $this->page('search.php', array('action' => 'show_recent', 'value' => '999999999'));

		$this->assertStringContainsString('Probe results crumb</span>', $recent);
		$this->assertStringContainsString('<span class="first-item"><a href="http://forum.test/search.php">User defined search</a></span> <span>Probe option for <span class="item-info">Topics found: 1</span></span>', $recent);
		$this->assertStringContainsString('<em>probe 1 #1</em></h3>', $recent);
		$this->assertStringContainsString('<p id="probe-se">searched 1 topics</p>', $recent);

		$this->assertStringContainsString('There are no unanswered posts in this forum.', $this->page('search.php', array('action' => 'show_unanswered')), 'the query sf_fn_generate_action_search_query_end rewrote answered');
		$this->assertStringContainsString('<a href="probe">Probe again search 42</a>', $this->page('search.php', array('action' => 'show_probe')), 'the action sf_fn_validate_actions_start added took the value se_additional_quicksearch_variables set');

		$this->page('search.php', array('action' => 'search', 'keywords' => 'test'));
		$stored = self::$forum?->rows('SELECT id FROM search_cache WHERE ident=\'admin\'') ?? array();
		$this->assertCount(1, $stored);

		$results = $this->page('search.php', array('search_id' => (string) ($stored[0]['id'] ?? '')));
		$this->assertStringContainsString('<span>probe mail NULL</span></p>', $results, 'the column the cached query point added reached $cur_set');
	}

	public function testTheForumsRunTheirPointsOnTheListTheFormsAndTheStatements(): void {
		$page = $this->page('admin/forums.php');

		$this->assertStringContainsString('<p id="probe-afo">forums 1</p>', $page);
		$this->assertMatchesRegularExpression('#<!-- listed Test forum with [0-9]+ topics in 1 -->\n#', $page, 'the column afo_qr_get_cats_and_forums added reached $cur_forum');
		$this->assertStringContainsString('<input id="fld3" name="probe" /></div>'."\n\t\t\t\t".'<div class="sf-set set4">'."\n\t\t\t\t\t".'<div class="sf-box select">'."\n\t\t\t\t\t\t".'<label for="fld4">', $page, 'the category numbers on from the field afo_pre_new_forum_cat added');
		$this->assertStringContainsString('<p id="probe-afo">forums 0</p>', $this->page('admin/forums.php', array('probe' => '1')), 'the list afo_qr_get_cats_and_forums rewrote answered');

		$this->page('admin/forums.php', array('action' => 'adddel'), array('add_forum' => '1', 'forum_name' => 'probe forum', 'position' => '9', 'add_to_cat' => '1'), true);
		$added = self::$forum?->rows('SELECT id, forum_name FROM forums WHERE disp_position=9') ?? array();
		$this->assertSame('probed forum', $added[0]['forum_name'] ?? null, 'the statement afo_add_forum_qr_add_forum changed is the one that ran');

		$form = $this->page('admin/forums.php', array('edit_forum' => '1'));
		$this->assertMatchesRegularExpression('#<!-- forum 1 with [0-9]+ posts -->\n#', $form, 'the column afo_edit_forum_qr_get_forum_details added reached $cur_forum');
		$this->assertStringContainsString("<li><span>probe line</span></li>\n\t\t\t\t</ul>", $form);
		$this->assertStringContainsString('<!-- group Members reads 1 at 8 -->', $form);

		$defaults = array('2' => '1', '3' => '1', '4' => '1');
		$this->page('admin/forums.php', array('edit_forum' => '1'), array('save' => '1', 'forum_name' => 'Test forum', 'forum_desc' => 'This is just a test forum', 'cat_id' => '1', 'sort_by' => '0',
			'read_forum_old' => $defaults, 'post_replies_old' => array('2' => '0') + $defaults, 'post_topics_old' => array('2' => '0') + $defaults,
			'read_forum_new' => $defaults, 'post_replies_new' => array('3' => '1', '4' => '1'), 'post_topics_new' => array('3' => '1', '4' => '1')), true);
		$this->assertSame(array(array('group_id' => 3, 'read_forum' => 1, 'post_replies' => 1, 'post_topics' => 0)), self::$forum?->rows('SELECT group_id, read_forum, post_replies, post_topics FROM forum_perms WHERE forum_id=1'), 'the permissions afo_save_forum_pre_perms_compare changed were stored');

		$this->page('admin/forums.php', array('edit_forum' => '1'), array('revert_perms' => '1'), true);
		$this->assertSame(array(), self::$forum?->rows('SELECT group_id FROM forum_perms WHERE forum_id=1'));

		$id = (string) ($added[0]['id'] ?? '');
		$this->page('admin/forums.php', array('del_forum' => $id), array('del_forum_comply' => '1'), true);
		$this->assertSame(array(), self::$forum?->rows('SELECT id FROM forums WHERE disp_position=9'));
		$this->assertSame(array(array('conf_value' => $id)), self::$forum?->rows('SELECT conf_value FROM config WHERE conf_name=\'probe_deleted_forum\''), 'afo_del_forum_qr_delete_forum saw the forum to delete');
	}

	public function testTheGroupsRunTheirPointsOnTheListTheFormsAndTheStatements(): void {
		$page = $this->page('admin/groups.php');

		$this->assertStringContainsString('<p id="probe-agr">groups</p>', $page);
		$this->assertStringContainsString('<span>probe Moderators Moderator</span></p>', $page, 'the column agr_qr_get_group_list added reached $cur_group, and the option agr_edit_group_row_pre_output added is shown');
		$this->assertStringContainsString('<input id="fld1" name="probe" /></div>'."\n\t\t\t\t".'<div class="sf-set set2">'."\n\t\t\t\t\t".'<div class="sf-box select">'."\n\t\t\t\t\t\t".'<label for="fld2">', $page, 'the base group numbers on from the field agr_pre_add_base_group added');

		$form = $this->page('admin/groups.php', array('edit_group' => '4'));
		$this->assertStringContainsString("<!-- probe column 5 in edit at 17 -->\n", $form, 'a column an extension added to the groups table reached $group');
		$this->assertSame(2, substr_count($form, "<!-- email interval 4 -->\n"), 'agr_add_edit_group_pre_email_interval runs at both its sites');

		$this->page('admin/groups.php', array('action' => 'foo'), array('add_edit_group' => '1', 'mode' => 'add', 'base_group' => '3', 'req_title' => 'Probe group', 'read_board' => '1'), true);
		$added = self::$forum?->rows('SELECT g_id, g_probe FROM groups WHERE g_title=\'Probe group\'') ?? array();
		$this->assertSame(42, $added[0]['g_probe'] ?? null, 'the statement agr_add_end_qr_add_group changed is the one that ran');
		$id = (string) ($added[0]['g_id'] ?? '');

		$this->page('admin/groups.php', array('action' => 'foo'), array('add_edit_group' => '1', 'mode' => 'edit', 'group_id' => $id, 'req_title' => 'Probe group', 'read_board' => '1'), true);
		$this->assertSame(array(array('g_probe' => 43)), self::$forum?->rows('SELECT g_probe FROM groups WHERE g_id='.$id), 'the statement agr_edit_end_qr_update_group changed is the one that ran');

		self::$forum?->rows('INSERT INTO users (group_id, username, password, email, registered) SELECT '.$id.', \'probe-grouped\', \'x\', \'grouped@example.com\', 1 WHERE NOT EXISTS (SELECT 1 FROM users WHERE username=\'probe-grouped\')');
		$this->assertStringContainsString("<!-- removing Probe group with 1 -->\n", $this->page('admin/groups.php', array('del_group' => $id)), 'agr_del_group_output_start read $group_info by position');

		$this->page('admin/groups.php', array('del_group' => $id), array('del_group' => '1', 'move_to_group' => '3'), true);
		$this->assertSame(array(), self::$forum?->rows('SELECT g_id FROM groups WHERE g_id='.$id));
		$this->assertSame(array(array('group_id' => 3)), self::$forum?->rows('SELECT group_id FROM users WHERE username=\'probe-grouped\''));
		$this->assertSame(array(array('conf_value' => $id.' moved')), self::$forum?->rows('SELECT conf_value FROM config WHERE conf_name=\'probe_removed_group\''), 'agr_del_group_form_submitted saw the group');
		self::$forum?->rows('DELETE FROM users WHERE username=\'probe-grouped\'');

		$this->page('admin/groups.php', array('action' => 'foo'), array('add_edit_group' => '1', 'mode' => 'add', 'base_group' => '3', 'req_title' => 'Probe empty group'), true);
		$empty = (string) (self::$forum?->rows('SELECT g_id FROM groups WHERE g_title=\'Probe empty group\'')[0]['g_id'] ?? '');

		$this->assertStringContainsString('name="confirm_cancel"', $this->page('admin/groups.php', array('del_group' => $empty)), 'a removal link without its token is confirmed first');
		$this->assertCount(1, self::$forum?->rows('SELECT g_id FROM groups WHERE g_id='.$empty) ?? array());

		$this->assertMatchesRegularExpression('#del_group='.$empty.'&amp;csrf_token=([0-9a-f]{40})"#', $this->page('admin/groups.php'));
		preg_match('#del_group='.$empty.'&amp;csrf_token=([0-9a-f]{40})"#', $this->page('admin/groups.php'), $link);
		$this->page('admin/groups.php', array('del_group' => $empty, 'csrf_token' => $link[1] ?? ''));
		$this->assertSame(array(), self::$forum?->rows('SELECT g_id FROM groups WHERE g_id='.$empty), 'the link the list issued removes the group');
		$this->assertSame(array(array('conf_value' => $empty.' linked')), self::$forum?->rows('SELECT conf_value FROM config WHERE conf_name=\'probe_removed_group\''));

		$this->page('admin/groups.php', array('action' => 'foo'), array('set_default_group' => '1', 'default_group' => '3'), true);
		$this->assertSame(array(array('conf_value' => '3')), self::$forum?->rows('SELECT conf_value FROM config WHERE conf_name=\'probe_default_group\''));
	}

	public function testTheUsersRunTheirPointsOnTheSearchesTheFormsAndTheStatements(): void {
		$addresses = $this->page('admin/users.php', array('ip_stats' => '2'));
		$this->assertStringContainsString('<p id="probe-aus-ips">users 2 1</p>', $addresses);
		$this->assertMatchesRegularExpression('#<td class="probe">[0-9]+ topics \#1</td>\n\t\t\t\t</tr>#', $addresses, 'the column aus_ip_stats_qr_get_user_ips added reached $cur_ip');

		$posters = $this->page('admin/users.php', array('show_users' => '127.0.0.1'));
		$this->assertStringContainsString('<td class="probe">127.0.0.1 of admin</td>', $posters, 'the column aus_show_users_qr_get_user_details added reached $user_data');
		$this->assertStringContainsString('<span>probe button for 127.0.0.1</span></p>', $posters);

		$found = $this->page('admin/users.php', array('find_user' => '1', 'order_by' => 'username', 'direction' => 'ASC', 'form' => array('username' => 'adm*')));
		$this->assertStringContainsString('<th class="probe">probe of 1 by username</th>', $found);
		$this->assertMatchesRegularExpression('#<td class="probe">[0-9]+ posts, 1 condition</td>#', $found, 'the column aus_find_user_qr_find_users added reached $user_data');

		$form = $this->page('admin/users.php');
		$this->assertStringContainsString('<input id="fld1" name="probe" /></div>'."\n\t\t\t\t".'<div class="sf-set set2">'."\n\t\t\t\t\t".'<div class="sf-box text">'."\n\t\t\t\t\t\t".'<label for="fld2"><span>Username</span>', $form, 'the username numbers on from the field aus_search_form_pre_username added');
		$this->assertStringNotContainsString('<option value="1">Administrators</option>', $form, 'the groups aus_search_form_qr_get_groups rewrote answered');
		$this->assertStringContainsString('<option value="3">Members</option>', $form);

		self::$forum?->rows('INSERT INTO users (group_id, username, password, email, registered, registration_ip) SELECT 3, \'probe-user\', \'x\', \'probe-user@example.com\', 1, \'198.51.100.7\' WHERE NOT EXISTS (SELECT 1 FROM users WHERE username=\'probe-user\')');
		$id = (string) (self::$forum?->rows('SELECT id FROM users WHERE username=\'probe-user\'')[0]['id'] ?? '');

		$this->page('admin/users.php', array('action' => 'modify_users'), array('ban_users_comply' => '1', 'users' => $id, 'ban_message' => 'probe ban', 'ban_expire' => ''), true);
		$this->assertSame(array(array('message' => 'probed ban of probe-user at 198.51.100.7')), self::$forum?->rows('SELECT message FROM bans WHERE username=\'probe-user\''), 'the statement aus_ban_users_qr_add_ban changed ran, with $cur_user and $ban_ip');
		$this->assertSame(array(array('conf_value' => $id.' \'probe ban\'')), self::$forum?->rows('SELECT conf_value FROM config WHERE conf_name=\'probe_banned_users\''));
		self::$forum?->rows('DELETE FROM bans WHERE username=\'probe-user\'');

		$this->assertStringNotContainsString('<option value="4">Moderators</option>', $this->page('admin/users.php', array('action' => 'modify_users'), array('change_group' => '1', 'users' => array($id => '1')), true));
		$this->page('admin/users.php', array('action' => 'modify_users'), array('change_group_comply' => '1', 'users' => $id, 'move_to_group' => '4'), true);
		$this->assertSame(array(array('group_id' => 4)), self::$forum?->rows('SELECT group_id FROM users WHERE id='.$id));
		$this->assertSame(array(array('conf_value' => '4 1')), self::$forum?->rows('SELECT conf_value FROM config WHERE conf_name=\'probe_changed_group\''));

		$this->assertStringContainsString('Administrators cannot be deleted', $this->page('admin/users.php', array('action' => 'modify_users'), array('delete_users' => '1', 'users' => $id, 'probe_admin' => '1'), true), 'the check aus_delete_users_qr_check_for_admins rewrote answered');
		$this->assertStringContainsString("<!-- deleting ".$id." -->\n", $this->page('admin/users.php', array('action' => 'modify_users'), array('delete_users' => '1', 'users' => array($id => '1')), true));
		$this->page('admin/users.php', array('action' => 'modify_users'), array('delete_users_comply' => '1', 'users' => $id), true);
		$this->assertSame(array(), self::$forum?->rows('SELECT id FROM users WHERE id='.$id));
	}

	public function testModerationRunsItsPointsOnTheListsTheFormsAndTheStatements(): void {
		$forum = $this->page('moderate.php', array('fid' => '1'));
		$this->assertStringContainsString('<p id="probe-mr">moderated 1</p>', $forum);
		$this->assertStringContainsString('<span>probe This is just a test forum</span></p>', $forum, 'the column mr_qr_get_forum_data added reached $cur_forum');
		$this->assertMatchesRegularExpression('#class="main-item odd main-first-item normal (new )?probed-[0-9]+-1"#', $forum, 'the column mr_qr_get_topics added reached $cur_topic, with the checkboxes counted');
		$this->assertStringNotContainsString('name="merge_topics"', $forum);
		$this->assertStringContainsString('You do not have permission', $this->page('moderate.php', array('fid' => '1', 'probe_deny' => '1')), 'the visitor mr_pre_permission_check changed is checked');

		$topic = $this->page('moderate.php', array('fid' => '1', 'tid' => '1'));
		$this->assertStringContainsString('<span>probe 127.0.0.1 admin</span></h3>', $topic, 'the column mr_post_actions_qr_get_posts added reached $cur_post');
		$this->assertStringContainsString("\t\t\t\t\t\t</div>\n<p class=\"probe-entry\">1</p>\n\t\t\t\t\t</div>", $topic);

		$this->assertStringContainsString('The IP address is: 192.0.2.77<br />', $this->page('moderate.php', array('get_host' => '1')), 'the address mr_view_ip_qr_get_poster_ip read answered');

		self::$forum?->rows('INSERT INTO forums (forum_name, cat_id, disp_position) SELECT \'Probe forum\', 1, 9 WHERE NOT EXISTS (SELECT 1 FROM forums WHERE forum_name=\'Probe forum\')');
		$target = (string) (self::$forum?->rows('SELECT id FROM forums WHERE forum_name=\'Probe forum\'')[0]['id'] ?? '');
		self::$forum?->rows('INSERT INTO topics (poster, subject, posted, first_post_id, last_post, last_post_id, last_poster, num_replies, forum_id) VALUES (\'admin\', \'Probe topic\', 1, 0, 2, 0, \'admin\', 1, 1)');
		$tid = (string) (self::$forum?->rows('SELECT MAX(id) AS id FROM topics')[0]['id'] ?? '');
		self::$forum?->rows('INSERT INTO posts (poster, poster_id, poster_ip, message, posted, topic_id) VALUES (\'admin\', 2, \'127.0.0.1\', \'First\', 1, '.$tid.'), (\'admin\', 2, \'127.0.0.1\', \'Second\', 2, '.$tid.')');
		$posts = array_map(static fn (array $row): string => (string) $row['id'], self::$forum?->rows('SELECT id FROM posts WHERE topic_id='.$tid.' ORDER BY id') ?? array());
		self::$forum?->rows('UPDATE topics SET first_post_id='.$posts[0].', last_post_id='.$posts[1].' WHERE id='.$tid);

		$this->assertStringContainsString('<!-- subject of '.$posts[1].' at 0 -->', $this->page('moderate.php', array('fid' => '1', 'tid' => $tid), array('split_posts' => '1', 'posts' => array($posts[1])), true));
		$this->page('moderate.php', array('fid' => '1', 'tid' => $tid), array('split_posts_comply' => '1', 'req_confirm' => '1', 'posts' => $posts[1], 'new_subject' => 'probe split'), true);
		$split = self::$forum?->rows('SELECT id, subject FROM topics WHERE first_post_id='.$posts[1]) ?? array();
		$this->assertSame('probed split', $split[0]['subject'] ?? null, 'the statement mr_confirm_split_posts_qr_add_topic changed is the one that ran');
		$this->assertSame(array(array('conf_value' => ($split[0]['id'] ?? '').' probe split')), self::$forum?->rows('SELECT conf_value FROM config WHERE conf_name=\'probe_split\''));

		preg_match('#moderate\.php\?fid=1&amp;stick='.$tid.'&amp;csrf_token=([0-9a-f]{40})#', $this->page('viewtopic.php', array('id' => $tid)), $stick);
		$this->page('moderate.php', array('fid' => '1', 'stick' => $tid, 'csrf_token' => $stick[1] ?? ''));
		$this->assertSame(array(array('sticky' => 1, 'num_views' => 42)), self::$forum?->rows('SELECT sticky, num_views FROM topics WHERE id='.$tid), 'the statement mr_stick_topic_qr_stick_topic changed is the one that ran');

		preg_match('#moderate\.php\?fid=1&amp;close='.$tid.'&amp;csrf_token=([0-9a-f]{40})#', $this->page('viewtopic.php', array('id' => $tid)), $close);
		$this->page('moderate.php', array('fid' => '1', 'close' => $tid, 'csrf_token' => $close[1] ?? ''));
		$this->assertSame(array(array('conf_value' => $tid.' Probe subject 1')), self::$forum?->rows('SELECT conf_value FROM config WHERE conf_name=\'probe_closed\''), 'the subject mr_open_close_single_topic_qr_get_subject read answered');
		$this->assertSame(array(array('closed' => 1)), self::$forum?->rows('SELECT closed FROM topics WHERE id='.$tid));

		$this->assertStringContainsString("<!-- target Probe forum -->\n", $this->page('moderate.php', array('fid' => '1'), array('move_topics' => '1', 'topics' => array($tid)), true));
		$this->assertStringContainsString('Bad request', $this->page('moderate.php', array('fid' => '1'), array('move_topics_to' => '1', 'topics' => $tid, 'move_to_forum' => $target, 'probe_count' => '1'), true), 'the check mr_confirm_move_topics_qr_verify_topic_ids changed answered');
		$this->page('moderate.php', array('fid' => '1'), array('move_topics_to' => '1', 'topics' => $tid, 'move_to_forum' => $target, 'with_redirect' => '1'), true);
		$this->assertSame(array(array('forum_id' => (int) $target)), self::$forum?->rows('SELECT forum_id FROM topics WHERE id='.$tid));
		$this->assertCount(1, self::$forum?->rows('SELECT id FROM topics WHERE forum_id=1 AND moved_to='.$tid) ?? array());

		$splitId = (string) ($split[0]['id'] ?? '');
		$this->page('moderate.php', array('fid' => '1'), array('delete_topics_comply' => '1', 'req_confirm' => '1', 'topics' => $splitId), true);
		$this->assertSame(array(), self::$forum?->rows('SELECT id FROM topics WHERE id='.$splitId));
		$this->assertSame(array(array('conf_value' => '1 1')), self::$forum?->rows('SELECT conf_value FROM config WHERE conf_name=\'probe_deleted_topics\''), 'mr_confirm_delete_topics_pre_redirect saw the forums synced and the posts deleted');
	}

	public function testTheSettingsRunTheirPointsOnTheFormsTheValidationAndTheStatements(): void {
		$setup = $this->page('admin/settings.php', array('section' => 'setup'));
		$this->assertStringContainsString('<p id="probe-aop">settings setup</p>', $setup);
		$this->assertStringContainsString('Probe settings crumb', $setup, 'aop_setup_pre_header_load changed the crumbs');
		$this->assertStringContainsString('<input id="fld2" name="probe" /></div>'."\n\t\t\t\t\t".'<div class="sf-set set3">'."\n\t\t\t\t\t\t".'<div class="sf-box text">'."\n\t\t\t\t\t\t\t".'<label for="fld3">', $setup, 'the description numbers on from the field aop_setup_pre_board_descrip added');

		$this->page('admin/settings.php', array('section' => 'announcements'), array('form_sent' => '1', 'form' => array('announcement_heading' => 'Probe heading', 'announcement_message' => 'Probe message')), true);
		$this->assertSame(array(array('conf_value' => 'Rewritten Probe heading (validated in announcements)')), self::$forum?->rows('SELECT conf_value FROM config WHERE conf_name=\'o_announcement_heading\''), 'the statement aop_qr_update_permission_option changed ran, over what aop_announcements_validation left');
		$this->assertSame(array(array('conf_value' => 'announcements 0')), self::$forum?->rows('SELECT conf_value FROM config WHERE conf_name=\'probe_settings\''), 'aop_pre_redirect saw the section and the validated form');

		$section = $this->page('admin/settings.php', array('section' => 'probe'));
		$this->assertStringContainsString('<p id="probe-aop-section">own section</p>', $section, 'aop_new_section answered a section of its own');
		$this->assertStringContainsString('<p id="probe-aop">settings probe</p>', $section, 'aop_end ended it');
		$this->assertStringNotContainsString('Bad request', $section);
		$this->assertStringContainsString('Bad request', $this->page('admin/settings.php', array('section' => 'bogus')));
	}

	public function testTheProfileRunsItsPointsOnTheSectionsTheFormsAndTheStatements(): void {
		$about = $this->page('profile.php', array('id' => '2'));
		$this->assertStringContainsString('<li id="probe-menu">about 1', $about, 'the column pf_qr_get_user_info added reached $user, and the menu pf_change_details_modify_main_menu changed is shown');
		$this->assertStringContainsString('Probe profile crumb', $about);
		$this->assertMatchesRegularExpression('#<div id="probe-private" class="ct-set data-set set[0-9]+">own profile</div>#', $about);

		$identity = $this->page('profile.php', array('section' => 'identity', 'id' => '2'));
		$this->assertMatchesRegularExpression('#<input id="fld3" name="probe" /></div>\n\t\t\t\t<div class="sf-set set2">\n\t\t\t\t\t<div class="sf-box text">\n\t\t\t\t\t\t<label for="fld4"><span>Real name</span>#', $identity, 'the real name numbers on from the field pf_change_details_identity_pre_realname added');

		$refused = $this->page('profile.php', array('section' => 'identity', 'id' => '2'), array('form_sent' => '1', 'req_username' => 'admin', 'old_username' => 'admin', 'req_email' => 'admin@example.com', 'form' => array('realname' => 'probe error')), true);
		$this->assertStringContainsString('<li class="warn"><span>Probe identity error for admin</span></li>', $refused);

		$this->page('profile.php', array('section' => 'identity', 'id' => '2'), array('form_sent' => '1', 'req_username' => 'admin', 'old_username' => 'admin', 'req_email' => 'admin@example.com', 'form' => array('realname' => 'Probe Real')), true);
		$this->assertSame(array(array('realname' => 'Probe Real', 'location' => 'probed')), self::$forum?->rows('SELECT realname, location FROM users WHERE id=2'), 'the statement pf_change_details_qr_update_user changed is the one that ran');
		$this->assertSame(array(array('conf_value' => 'identity Probe Real')), self::$forum?->rows('SELECT conf_value FROM config WHERE conf_name=\'probe_profile\''));

		$this->assertMatchesRegularExpression('#<!-- timezone for admin at [0-9]+ -->#', $this->page('profile.php', array('section' => 'settings', 'id' => '2')));

		self::$forum?->rows('INSERT INTO users (group_id, username, password, email, registered) SELECT 3, \'probe-profile\', \'x\', \'probe-profile@example.com\', 1 WHERE NOT EXISTS (SELECT 1 FROM users WHERE username=\'probe-profile\')');
		$id = (string) (self::$forum?->rows('SELECT id FROM users WHERE username=\'probe-profile\'')[0]['id'] ?? '');

		$admin = $this->page('profile.php', array('section' => 'admin', 'id' => $id));
		$this->assertStringNotContainsString('<option value="4"', $admin, 'the groups pf_change_details_admin_qr_get_groups rewrote answered');
		$this->assertStringContainsString('<option value="3" selected="selected">Members</option>', $admin);

		self::$forum?->rows('UPDATE users SET group_id=4 WHERE id='.$id);
		$this->assertMatchesRegularExpression('#<!-- forum Test forum at [0-9]+ -->\n#', $this->page('profile.php', array('section' => 'admin', 'id' => $id)), 'a moderator\'s forums run pf_change_details_admin_forum_loop_start with $cur_forum');

		$this->page('profile.php', array('action' => 'change_pass', 'id' => $id), array('form_sent' => '1', 'req_new_password1' => 'probe-pass', 'req_new_password2' => 'probe-pass'), true);
		$this->assertSame(array(array('title' => 'probed password')), self::$forum?->rows('SELECT title FROM users WHERE id='.$id), 'the statement pf_change_pass_normal_qr_update_password changed is the one that ran');

		$section = $this->page('profile.php', array('section' => 'probe', 'id' => $id));
		$this->assertStringContainsString('<p id="probe-pf-section">own section of probe-profile 7</p>', $section, 'pf_change_details_new_section answered a section of its own, with the menu');
		$this->assertStringNotContainsString('Bad request', $section);

		$this->assertStringContainsString('<p id="probe-pf-view">admin</p>', $this->guestPage('profile.php', array('id' => '2')));
		self::$forum?->rows('DELETE FROM users WHERE id='.$id);
	}
}
