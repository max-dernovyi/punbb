<?php
/**
 * A legacy theme's stylesheet script: it registers what Oxygen registered and
 * fills the markers of its own templates — the default templates of 1.4 —
 * into $tpl_main, as Oxygen.php did while those templates were the forum's.
 */

$forum_loader->add_js($base_url. '/style/Oxygen/responsive-nav.min.js', array('weight' => 55, 'async' => false, 'group' => FORUM_JS_GROUP_SYSTEM));
$forum_loader->add_css($base_url.'/style/Oxygen/Oxygen.css', array('type' => 'url', 'group' => FORUM_CSS_GROUP_SYSTEM, 'media' => 'screen'));

if (isset($tpl_main))
{
	$tpl_main = str_replace('<!-- forum_board_title -->', forum_js_escape($forum_config['o_board_title']), $tpl_main);
	$tpl_main = str_replace('<!-- forum_lang_menu_admin -->', $lang_common['Menu admin'], $tpl_main);
	$tpl_main = str_replace('<!-- forum_lang_menu_profile -->', $lang_common['Menu profile'], $tpl_main);
}
