--
-- PunBB 1.4.4 MySQL fixture: a small, anonymised forum as it looked before this
-- migration series, with its config rows left at the previous release (1.4.3,
-- database revision 4) so admin/db_update.php has an upgrade to perform.
--
-- Restored by .dev/tests/Integration/upgrade_path.php, which replaces %PREFIX%
-- with the table prefix of the run. Nothing here is real: no real addresses, no
-- real password hashes, no real IPs.
--
-- Text is deliberately non-ASCII but stays inside the BMP — 1.4.4 declares its
-- tables utf8mb3, so 4-byte characters were never storable in this schema.
--

CREATE TABLE `%PREFIX%bans` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `username` varchar(200) DEFAULT NULL,
  `ip` varchar(255) DEFAULT NULL,
  `email` varchar(80) DEFAULT NULL,
  `message` varchar(255) DEFAULT NULL,
  `expire` int unsigned DEFAULT NULL,
  `ban_creator` int unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb3;
CREATE TABLE `%PREFIX%categories` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `cat_name` varchar(80) NOT NULL DEFAULT 'New Category',
  `disp_position` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb3;
CREATE TABLE `%PREFIX%censoring` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `search_for` varchar(60) NOT NULL DEFAULT '',
  `replace_with` varchar(60) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb3;
CREATE TABLE `%PREFIX%config` (
  `conf_name` varchar(255) NOT NULL DEFAULT '',
  `conf_value` text,
  PRIMARY KEY (`conf_name`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb3;
CREATE TABLE `%PREFIX%extension_hooks` (
  `id` varchar(150) NOT NULL DEFAULT '',
  `extension_id` varchar(50) NOT NULL DEFAULT '',
  `code` text,
  `installed` int unsigned NOT NULL DEFAULT '0',
  `priority` tinyint unsigned NOT NULL DEFAULT '5',
  PRIMARY KEY (`id`,`extension_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb3;
CREATE TABLE `%PREFIX%extensions` (
  `id` varchar(150) NOT NULL DEFAULT '',
  `title` varchar(255) NOT NULL DEFAULT '',
  `version` varchar(25) NOT NULL DEFAULT '',
  `description` text,
  `author` varchar(50) NOT NULL DEFAULT '',
  `uninstall` text,
  `uninstall_note` text,
  `disabled` tinyint(1) NOT NULL DEFAULT '0',
  `dependencies` varchar(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb3;
CREATE TABLE `%PREFIX%forum_perms` (
  `group_id` int NOT NULL DEFAULT '0',
  `forum_id` int NOT NULL DEFAULT '0',
  `read_forum` tinyint(1) NOT NULL DEFAULT '1',
  `post_replies` tinyint(1) NOT NULL DEFAULT '1',
  `post_topics` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`group_id`,`forum_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb3;
CREATE TABLE `%PREFIX%forum_subscriptions` (
  `user_id` int unsigned NOT NULL DEFAULT '0',
  `forum_id` int unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`user_id`,`forum_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb3;
CREATE TABLE `%PREFIX%forums` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `forum_name` varchar(80) NOT NULL DEFAULT 'New forum',
  `forum_desc` text,
  `redirect_url` varchar(100) DEFAULT NULL,
  `moderators` text,
  `num_topics` mediumint unsigned NOT NULL DEFAULT '0',
  `num_posts` mediumint unsigned NOT NULL DEFAULT '0',
  `last_post` int unsigned DEFAULT NULL,
  `last_post_id` int unsigned DEFAULT NULL,
  `last_poster` varchar(200) DEFAULT NULL,
  `sort_by` tinyint(1) NOT NULL DEFAULT '0',
  `disp_position` int NOT NULL DEFAULT '0',
  `cat_id` int unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb3;
CREATE TABLE `%PREFIX%groups` (
  `g_id` int unsigned NOT NULL AUTO_INCREMENT,
  `g_title` varchar(50) NOT NULL DEFAULT '',
  `g_user_title` varchar(50) DEFAULT NULL,
  `g_moderator` tinyint(1) NOT NULL DEFAULT '0',
  `g_mod_edit_users` tinyint(1) NOT NULL DEFAULT '0',
  `g_mod_rename_users` tinyint(1) NOT NULL DEFAULT '0',
  `g_mod_change_passwords` tinyint(1) NOT NULL DEFAULT '0',
  `g_mod_ban_users` tinyint(1) NOT NULL DEFAULT '0',
  `g_read_board` tinyint(1) NOT NULL DEFAULT '1',
  `g_view_users` tinyint(1) NOT NULL DEFAULT '1',
  `g_post_replies` tinyint(1) NOT NULL DEFAULT '1',
  `g_post_topics` tinyint(1) NOT NULL DEFAULT '1',
  `g_edit_posts` tinyint(1) NOT NULL DEFAULT '1',
  `g_delete_posts` tinyint(1) NOT NULL DEFAULT '1',
  `g_delete_topics` tinyint(1) NOT NULL DEFAULT '1',
  `g_set_title` tinyint(1) NOT NULL DEFAULT '1',
  `g_search` tinyint(1) NOT NULL DEFAULT '1',
  `g_search_users` tinyint(1) NOT NULL DEFAULT '1',
  `g_send_email` tinyint(1) NOT NULL DEFAULT '1',
  `g_post_flood` smallint NOT NULL DEFAULT '30',
  `g_search_flood` smallint NOT NULL DEFAULT '30',
  `g_email_flood` smallint NOT NULL DEFAULT '60',
  PRIMARY KEY (`g_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb3;
CREATE TABLE `%PREFIX%online` (
  `user_id` int unsigned NOT NULL DEFAULT '1',
  `ident` varchar(200) NOT NULL DEFAULT '',
  `logged` int unsigned NOT NULL DEFAULT '0',
  `idle` tinyint(1) NOT NULL DEFAULT '0',
  `csrf_token` varchar(40) NOT NULL DEFAULT '',
  `prev_url` varchar(255) DEFAULT NULL,
  `last_post` int unsigned DEFAULT NULL,
  `last_search` int unsigned DEFAULT NULL,
  UNIQUE KEY `%PREFIX%online_user_id_ident_idx` (`user_id`,`ident`(40)),
  KEY `%PREFIX%online_ident_idx` (`ident`(40)),
  KEY `%PREFIX%online_logged_idx` (`logged`)
) ENGINE=MEMORY DEFAULT CHARSET=utf8mb3;
CREATE TABLE `%PREFIX%posts` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `poster` varchar(200) NOT NULL DEFAULT '',
  `poster_id` int unsigned NOT NULL DEFAULT '1',
  `poster_ip` varchar(39) DEFAULT NULL,
  `poster_email` varchar(80) DEFAULT NULL,
  `message` text,
  `hide_smilies` tinyint(1) NOT NULL DEFAULT '0',
  `posted` int unsigned NOT NULL DEFAULT '0',
  `edited` int unsigned DEFAULT NULL,
  `edited_by` varchar(200) DEFAULT NULL,
  `topic_id` int unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `%PREFIX%posts_topic_id_idx` (`topic_id`),
  KEY `%PREFIX%posts_multi_idx` (`poster_id`,`topic_id`),
  KEY `%PREFIX%posts_posted_idx` (`posted`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb3;
CREATE TABLE `%PREFIX%ranks` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `rank` varchar(50) NOT NULL DEFAULT '',
  `min_posts` mediumint unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb3;
CREATE TABLE `%PREFIX%reports` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `post_id` int unsigned NOT NULL DEFAULT '0',
  `topic_id` int unsigned NOT NULL DEFAULT '0',
  `forum_id` int unsigned NOT NULL DEFAULT '0',
  `reported_by` int unsigned NOT NULL DEFAULT '0',
  `created` int unsigned NOT NULL DEFAULT '0',
  `message` text,
  `zapped` int unsigned DEFAULT NULL,
  `zapped_by` int unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `%PREFIX%reports_zapped_idx` (`zapped`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb3;
CREATE TABLE `%PREFIX%search_cache` (
  `id` int unsigned NOT NULL DEFAULT '0',
  `ident` varchar(200) NOT NULL DEFAULT '',
  `search_data` text,
  PRIMARY KEY (`id`),
  KEY `%PREFIX%search_cache_ident_idx` (`ident`(8))
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb3;
CREATE TABLE `%PREFIX%search_matches` (
  `post_id` int unsigned NOT NULL DEFAULT '0',
  `word_id` int unsigned NOT NULL DEFAULT '0',
  `subject_match` tinyint(1) NOT NULL DEFAULT '0',
  KEY `%PREFIX%search_matches_word_id_idx` (`word_id`),
  KEY `%PREFIX%search_matches_post_id_idx` (`post_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb3;
CREATE TABLE `%PREFIX%search_words` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `word` varchar(20) CHARACTER SET utf8mb3 COLLATE utf8mb3_bin NOT NULL DEFAULT '',
  PRIMARY KEY (`word`),
  KEY `%PREFIX%search_words_id_idx` (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb3;
CREATE TABLE `%PREFIX%subscriptions` (
  `user_id` int unsigned NOT NULL DEFAULT '0',
  `topic_id` int unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`user_id`,`topic_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb3;
CREATE TABLE `%PREFIX%topics` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `poster` varchar(200) NOT NULL DEFAULT '',
  `subject` varchar(255) NOT NULL DEFAULT '',
  `posted` int unsigned NOT NULL DEFAULT '0',
  `first_post_id` int unsigned NOT NULL DEFAULT '0',
  `last_post` int unsigned NOT NULL DEFAULT '0',
  `last_post_id` int unsigned NOT NULL DEFAULT '0',
  `last_poster` varchar(200) DEFAULT NULL,
  `num_views` mediumint unsigned NOT NULL DEFAULT '0',
  `num_replies` mediumint unsigned NOT NULL DEFAULT '0',
  `closed` tinyint(1) NOT NULL DEFAULT '0',
  `sticky` tinyint(1) NOT NULL DEFAULT '0',
  `moved_to` int unsigned DEFAULT NULL,
  `forum_id` int unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `%PREFIX%topics_forum_id_idx` (`forum_id`),
  KEY `%PREFIX%topics_moved_to_idx` (`moved_to`),
  KEY `%PREFIX%topics_last_post_idx` (`last_post`),
  KEY `%PREFIX%topics_first_post_id_idx` (`first_post_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb3;
CREATE TABLE `%PREFIX%users` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `group_id` int unsigned NOT NULL DEFAULT '3',
  `username` varchar(200) NOT NULL DEFAULT '',
  `password` varchar(40) NOT NULL DEFAULT '',
  `salt` varchar(12) DEFAULT NULL,
  `email` varchar(80) NOT NULL DEFAULT '',
  `title` varchar(50) DEFAULT NULL,
  `realname` varchar(40) DEFAULT NULL,
  `url` varchar(100) DEFAULT NULL,
  `facebook` varchar(100) DEFAULT NULL,
  `twitter` varchar(100) DEFAULT NULL,
  `linkedin` varchar(100) DEFAULT NULL,
  `skype` varchar(100) DEFAULT NULL,
  `jabber` varchar(80) DEFAULT NULL,
  `icq` varchar(12) DEFAULT NULL,
  `msn` varchar(80) DEFAULT NULL,
  `aim` varchar(30) DEFAULT NULL,
  `yahoo` varchar(30) DEFAULT NULL,
  `location` varchar(30) DEFAULT NULL,
  `signature` text,
  `disp_topics` tinyint unsigned DEFAULT NULL,
  `disp_posts` tinyint unsigned DEFAULT NULL,
  `email_setting` tinyint(1) NOT NULL DEFAULT '1',
  `notify_with_post` tinyint(1) NOT NULL DEFAULT '0',
  `auto_notify` tinyint(1) NOT NULL DEFAULT '0',
  `show_smilies` tinyint(1) NOT NULL DEFAULT '1',
  `show_img` tinyint(1) NOT NULL DEFAULT '1',
  `show_img_sig` tinyint(1) NOT NULL DEFAULT '1',
  `show_avatars` tinyint(1) NOT NULL DEFAULT '1',
  `show_sig` tinyint(1) NOT NULL DEFAULT '1',
  `access_keys` tinyint(1) NOT NULL DEFAULT '0',
  `timezone` float NOT NULL DEFAULT '0',
  `dst` tinyint(1) NOT NULL DEFAULT '0',
  `time_format` int unsigned NOT NULL DEFAULT '0',
  `date_format` int unsigned NOT NULL DEFAULT '0',
  `language` varchar(25) NOT NULL DEFAULT 'English',
  `style` varchar(25) NOT NULL DEFAULT 'Oxygen',
  `num_posts` int unsigned NOT NULL DEFAULT '0',
  `last_post` int unsigned DEFAULT NULL,
  `last_search` int unsigned DEFAULT NULL,
  `last_email_sent` int unsigned DEFAULT NULL,
  `registered` int unsigned NOT NULL DEFAULT '0',
  `registration_ip` varchar(39) NOT NULL DEFAULT '0.0.0.0',
  `last_visit` int unsigned NOT NULL DEFAULT '0',
  `admin_note` varchar(30) DEFAULT NULL,
  `activate_string` varchar(80) DEFAULT NULL,
  `activate_key` varchar(8) DEFAULT NULL,
  `avatar` tinyint unsigned NOT NULL DEFAULT '0',
  `avatar_width` tinyint unsigned NOT NULL DEFAULT '0',
  `avatar_height` tinyint unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `%PREFIX%users_registered_idx` (`registered`),
  KEY `%PREFIX%users_username_idx` (`username`(8))
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb3;

--
-- Board configuration. o_cur_version and o_database_revision name the previous
-- release: that is what makes this a fixture to upgrade rather than to serve.
-- o_base_url is absent, as it is on every 1.4 install — base_url lives in config.php.
--
INSERT INTO `%PREFIX%config` (`conf_name`, `conf_value`) VALUES
('o_additional_navlinks', ''),
('o_admin_email', 'admin@example.invalid'),
('o_announcement', '0'),
('o_announcement_heading', 'Объявление'),
('o_announcement_message', '<p>Текст объявления.</p>'),
('o_avatars', '1'),
('o_avatars_dir', 'img/avatars'),
('o_avatars_height', '60'),
('o_avatars_size', '15360'),
('o_avatars_width', '60'),
('o_board_desc', 'Фикстура для проверки обновления — Grüße aus dem Archiv'),
('o_board_title', 'Фикстура PunBB'),
('o_censoring', '1'),
('o_check_for_updates', '0'),
('o_check_for_versions', '0'),
('o_cur_version', '1.4.3'),
('o_database_revision', '4'),
('o_date_format', 'Y-m-d'),
('o_default_dst', '0'),
('o_default_email_setting', '1'),
('o_default_lang', 'English'),
('o_default_style', 'Oxygen'),
('o_default_timezone', '0'),
('o_default_user_group', '3'),
('o_disp_posts_default', '25'),
('o_disp_topics_default', '30'),
('o_gzip', '0'),
('o_indent_num_spaces', '4'),
('o_mailing_list', 'admin@example.invalid'),
('o_maintenance', '0'),
('o_maintenance_message', 'Форум закрыт на обслуживание.'),
('o_make_links', '1'),
('o_mask_passwords', '1'),
('o_quickjump', '1'),
('o_quickpost', '1'),
('o_quote_depth', '3'),
('o_ranks', '1'),
('o_redirect_delay', '0'),
('o_regs_allow', '1'),
('o_regs_report', '0'),
('o_regs_verify', '0'),
('o_report_method', '0'),
('o_rules', '0'),
('o_rules_message', 'Правила форума.'),
('o_search_all_forums', '1'),
('o_sef', 'Default'),
('o_show_dot', '0'),
('o_show_moderators', '0'),
('o_show_post_count', '1'),
('o_show_user_info', '1'),
('o_show_version', '0'),
('o_signatures', '1'),
('o_smilies', '1'),
('o_smilies_sig', '1'),
('o_smtp_host', NULL),
('o_smtp_pass', NULL),
('o_smtp_ssl', '0'),
('o_smtp_user', NULL),
('o_subscriptions', '1'),
('o_time_format', 'H:i:s'),
('o_timeout_online', '300'),
('o_timeout_visit', '5400'),
('o_topic_review', '15'),
('o_topic_views', '1'),
('o_users_online', '1'),
('o_webmaster_email', 'admin@example.invalid'),
('p_allow_banned_email', '1'),
('p_allow_dupe_email', '0'),
('p_force_guest_email', '1'),
('p_message_all_caps', '1'),
('p_message_bbcode', '1'),
('p_message_img_tag', '1'),
('p_sig_all_caps', '1'),
('p_sig_bbcode', '1'),
('p_sig_img_tag', '0'),
('p_sig_length', '400'),
('p_sig_lines', '4'),
('p_subject_all_caps', '1');

INSERT INTO `%PREFIX%groups` (`g_id`, `g_title`, `g_user_title`, `g_moderator`, `g_mod_edit_users`, `g_mod_rename_users`, `g_mod_change_passwords`, `g_mod_ban_users`, `g_read_board`, `g_view_users`, `g_post_replies`, `g_post_topics`, `g_edit_posts`, `g_delete_posts`, `g_delete_topics`, `g_set_title`, `g_search`, `g_search_users`, `g_send_email`, `g_post_flood`, `g_search_flood`, `g_email_flood`) VALUES
(1, 'Administrators', 'Administrator', 0, 0, 0, 0, 0, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 0, 0, 0),
(2, 'Guest', NULL, 0, 0, 0, 0, 0, 1, 1, 0, 0, 0, 0, 0, 0, 1, 1, 0, 60, 30, 0),
(3, 'Members', NULL, 0, 0, 0, 0, 0, 1, 1, 1, 1, 1, 1, 1, 0, 1, 1, 1, 60, 30, 60),
(4, 'Moderators', 'Moderator', 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 0, 0, 0);

INSERT INTO `%PREFIX%ranks` (`id`, `rank`, `min_posts`) VALUES
(1, 'New member', 0),
(2, 'Member', 10);

INSERT INTO `%PREFIX%categories` (`id`, `cat_name`, `disp_position`) VALUES
(1, 'Тестовая категория', 0);

INSERT INTO `%PREFIX%forums` (`id`, `forum_name`, `forum_desc`, `redirect_url`, `moderators`, `num_topics`, `num_posts`, `last_post`, `last_post_id`, `last_poster`, `sort_by`, `disp_position`, `cat_id`) VALUES
(1, 'Общий форум', 'Обсуждение чего угодно — на любом языке', NULL, 'a:1:{s:9:"moderator";i:4;}', 1, 3, 1700000300, 3, 'moderator', 0, 0, 1),
(2, 'Ärger & Umlauts', 'Ein Forum für Sonderzeichen: äöü ß «ёлка»', NULL, NULL, 1, 1, 1700000400, 4, 'фикстура-юзер', 0, 1, 1);

--
-- Passwords are sha1($salt.sha1($password)) over the throwaway string
-- "fixture-password"; the salts are literals, not generated ones.
--
INSERT INTO `%PREFIX%users` (`id`, `group_id`, `username`, `password`, `salt`, `email`, `title`, `realname`, `url`, `facebook`, `twitter`, `linkedin`, `skype`, `jabber`, `icq`, `msn`, `aim`, `yahoo`, `location`, `signature`, `disp_topics`, `disp_posts`, `email_setting`, `notify_with_post`, `auto_notify`, `show_smilies`, `show_img`, `show_img_sig`, `show_avatars`, `show_sig`, `access_keys`, `timezone`, `dst`, `time_format`, `date_format`, `language`, `style`, `num_posts`, `last_post`, `last_search`, `last_email_sent`, `registered`, `registration_ip`, `last_visit`, `admin_note`, `activate_string`, `activate_key`, `avatar`, `avatar_width`, `avatar_height`) VALUES
(1, 2, 'Guest', 'Guest', NULL, 'Guest', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, 0, 1, 1, 1, 1, 1, 0, 0, 0, 0, 0, 'English', 'Oxygen', 0, NULL, NULL, NULL, 0, '0.0.0.0', 0, NULL, NULL, NULL, 0, 0, 0),
(2, 1, 'fixture-admin', 'b2e3db229edf83d68057abdecb6ea9ee40400649', 'fixture-salt', 'fixture-admin@example.invalid', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, 0, 1, 1, 1, 1, 1, 0, 0, 0, 0, 0, 'English', 'Oxygen', 1, 1700000100, NULL, NULL, 1700000000, '203.0.113.1', 1700000500, NULL, NULL, NULL, 0, 0, 0),
(3, 3, 'фикстура-юзер', 'b2e3db229edf83d68057abdecb6ea9ee40400649', 'fixture-salt', 'member@example.invalid', NULL, 'Тестовый Пользователь', 'http://пример.испытание/', NULL, NULL, 'example.invalid/in/nobody', NULL, NULL, NULL, NULL, NULL, NULL, 'Мюнхен', 'Подпись с [b]тегами[/b] — и умлаутами: äöü', NULL, NULL, 1, 0, 0, 1, 1, 1, 1, 1, 0, 0, 0, 0, 0, 'English', 'Oxygen', 2, 1700000400, NULL, NULL, 1700000010, '203.0.113.2', 1700000600, NULL, NULL, NULL, 1, 48, 48),
(4, 4, 'moderator', 'b2e3db229edf83d68057abdecb6ea9ee40400649', 'fixture-salt', 'moderator@example.invalid', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, 0, 1, 1, 1, 1, 1, 0, 0, 0, 0, 0, 'English', 'Oxygen', 1, 1700000300, NULL, NULL, 1700000020, '203.0.113.3', 1700000700, NULL, NULL, NULL, 0, 0, 0);

INSERT INTO `%PREFIX%topics` (`id`, `poster`, `subject`, `posted`, `first_post_id`, `last_post`, `last_post_id`, `last_poster`, `num_views`, `num_replies`, `closed`, `sticky`, `moved_to`, `forum_id`) VALUES
(1, 'fixture-admin', 'Приветствие — «первая тема»', 1700000100, 1, 1700000300, 3, 'moderator', 7, 2, 0, 1, NULL, 1),
(2, 'фикстура-юзер', 'Ümlaut-Thema', 1700000400, 4, 1700000400, 4, 'фикстура-юзер', 2, 0, 0, 0, NULL, 2);

INSERT INTO `%PREFIX%posts` (`id`, `poster`, `poster_id`, `poster_ip`, `poster_email`, `message`, `hide_smilies`, `posted`, `edited`, `edited_by`, `topic_id`) VALUES
(1, 'fixture-admin', 2, '203.0.113.1', NULL, 'Привет, мир! Это [b]первое[/b] сообщение фикстуры — с тире, «кавычками» и умлаутами: äöü ß.', 0, 1700000100, NULL, NULL, 1),
(2, 'фикстура-юзер', 3, '203.0.113.2', NULL, '[quote=fixture-admin]Привет, мир![/quote]\nОтвет со ссылкой на IDN: [url=http://пример.испытание/путь]пример.испытание[/url] и кодом: [code]echo "日本語";[/code]', 0, 1700000200, 1700000250, 'фикстура-юзер', 1),
(3, 'moderator', 4, '203.0.113.3', NULL, 'Третье сообщение: 中文, ελληνικά, עברית — всё в одной строке.', 0, 1700000300, NULL, NULL, 1),
(4, 'фикстура-юзер', 3, '203.0.113.2', NULL, 'Ein Beitrag über Ärger, Öl und Übermut. Smilies: :) :rolleyes:', 0, 1700000400, NULL, NULL, 2);

INSERT INTO `%PREFIX%censoring` (`id`, `search_for`, `replace_with`) VALUES
(1, 'плохоеслово', 'хорошееслово');

INSERT INTO `%PREFIX%bans` (`id`, `username`, `ip`, `email`, `message`, `expire`, `ban_creator`) VALUES
(1, 'спамер', '198.51.100.7', 'spammer@example.invalid', 'Спам — бан навсегда', NULL, 2);

INSERT INTO `%PREFIX%reports` (`id`, `post_id`, `topic_id`, `forum_id`, `reported_by`, `created`, `message`, `zapped`, `zapped_by`) VALUES
(1, 3, 1, 1, 3, 1700000350, 'Жалоба на сообщение — проверка кодировки', NULL, NULL);

INSERT INTO `%PREFIX%subscriptions` (`user_id`, `topic_id`) VALUES (3, 1);

INSERT INTO `%PREFIX%forum_subscriptions` (`user_id`, `forum_id`) VALUES (3, 2);

INSERT INTO `%PREFIX%forum_perms` (`group_id`, `forum_id`, `read_forum`, `post_replies`, `post_topics`) VALUES
(2, 2, 1, 0, 0);

--
-- One installed extension, with the hook row that goes with it. The hook writes
-- a marker into the page head, so the functional pass can see it still runs on
-- the upgraded forum.
--
INSERT INTO `%PREFIX%extensions` (`id`, `title`, `version`, `description`, `author`, `uninstall`, `uninstall_note`, `disabled`, `dependencies`) VALUES
('fixture_ext', 'Фикстурное расширение', '1.0', 'Расширение для проверки обновления', 'Nobody', NULL, NULL, 0, '');

INSERT INTO `%PREFIX%extension_hooks` (`id`, `extension_id`, `code`, `installed`, `priority`) VALUES
('hd_head', 'fixture_ext', '$forum_head[\'fixture\'] = \'<meta name="fixture-ext" content="1" />\';', 1700000000, 5);
