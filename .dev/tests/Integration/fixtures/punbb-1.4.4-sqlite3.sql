--
-- PunBB 1.4.4 SQLite fixture: the forum of punbb-1.4.4-mysql.sql, row for row,
-- on the schema 1.4.4's installer created through its sqlite3 driver. Its config
-- rows name the previous release (1.4.3, database revision 4), as there.
--
-- Restored by .dev/tests/Integration/upgrade_path.php, which replaces %PREFIX%
-- with the table prefix of the run. Nothing here is real: no real addresses, no
-- real password hashes, no real IPs.
--

CREATE TABLE %PREFIX%bans (
id INTEGER NOT NULL,
username VARCHAR(200),
ip VARCHAR(255),
email VARCHAR(80),
message VARCHAR(255),
expire INTEGER,
ban_creator INTEGER NOT NULL DEFAULT 0,
PRIMARY KEY (id)
);
CREATE TABLE %PREFIX%categories (
id INTEGER NOT NULL,
cat_name VARCHAR(80) NOT NULL DEFAULT 'New Category',
disp_position INTEGER NOT NULL DEFAULT 0,
PRIMARY KEY (id)
);
CREATE TABLE %PREFIX%censoring (
id INTEGER NOT NULL,
search_for VARCHAR(60) NOT NULL DEFAULT '',
replace_with VARCHAR(60) NOT NULL DEFAULT '',
PRIMARY KEY (id)
);
CREATE TABLE %PREFIX%config (
conf_name VARCHAR(255) NOT NULL DEFAULT '',
conf_value TEXT,
PRIMARY KEY (conf_name)
);
CREATE TABLE %PREFIX%extensions (
id VARCHAR(150) NOT NULL DEFAULT '',
title VARCHAR(255) NOT NULL DEFAULT '',
version VARCHAR(25) NOT NULL DEFAULT '',
description TEXT,
author VARCHAR(50) NOT NULL DEFAULT '',
uninstall TEXT,
uninstall_note TEXT,
disabled INTEGER NOT NULL DEFAULT 0,
dependencies VARCHAR(255) NOT NULL DEFAULT '',
PRIMARY KEY (id)
);
CREATE TABLE %PREFIX%extension_hooks (
id VARCHAR(150) NOT NULL DEFAULT '',
extension_id VARCHAR(50) NOT NULL DEFAULT '',
code TEXT,
installed INTEGER NOT NULL DEFAULT 0,
priority INTEGER NOT NULL DEFAULT 5,
PRIMARY KEY (id,extension_id)
);
CREATE TABLE %PREFIX%forum_perms (
group_id INTEGER NOT NULL DEFAULT 0,
forum_id INTEGER NOT NULL DEFAULT 0,
read_forum INTEGER NOT NULL DEFAULT 1,
post_replies INTEGER NOT NULL DEFAULT 1,
post_topics INTEGER NOT NULL DEFAULT 1,
PRIMARY KEY (group_id,forum_id)
);
CREATE TABLE %PREFIX%forums (
id INTEGER NOT NULL,
forum_name VARCHAR(80) NOT NULL DEFAULT 'New forum',
forum_desc TEXT,
redirect_url VARCHAR(100),
moderators TEXT,
num_topics INTEGER NOT NULL DEFAULT 0,
num_posts INTEGER NOT NULL DEFAULT 0,
last_post INTEGER,
last_post_id INTEGER,
last_poster VARCHAR(200),
sort_by INTEGER NOT NULL DEFAULT 0,
disp_position INTEGER NOT NULL DEFAULT 0,
cat_id INTEGER NOT NULL DEFAULT 0,
PRIMARY KEY (id)
);
CREATE TABLE %PREFIX%groups (
g_id INTEGER NOT NULL,
g_title VARCHAR(50) NOT NULL DEFAULT '',
g_user_title VARCHAR(50),
g_moderator INTEGER NOT NULL DEFAULT 0,
g_mod_edit_users INTEGER NOT NULL DEFAULT 0,
g_mod_rename_users INTEGER NOT NULL DEFAULT 0,
g_mod_change_passwords INTEGER NOT NULL DEFAULT 0,
g_mod_ban_users INTEGER NOT NULL DEFAULT 0,
g_read_board INTEGER NOT NULL DEFAULT 1,
g_view_users INTEGER NOT NULL DEFAULT 1,
g_post_replies INTEGER NOT NULL DEFAULT 1,
g_post_topics INTEGER NOT NULL DEFAULT 1,
g_edit_posts INTEGER NOT NULL DEFAULT 1,
g_delete_posts INTEGER NOT NULL DEFAULT 1,
g_delete_topics INTEGER NOT NULL DEFAULT 1,
g_set_title INTEGER NOT NULL DEFAULT 1,
g_search INTEGER NOT NULL DEFAULT 1,
g_search_users INTEGER NOT NULL DEFAULT 1,
g_send_email INTEGER NOT NULL DEFAULT 1,
g_post_flood INTEGER NOT NULL DEFAULT 30,
g_search_flood INTEGER NOT NULL DEFAULT 30,
g_email_flood INTEGER NOT NULL DEFAULT 60,
PRIMARY KEY (g_id)
);
CREATE TABLE %PREFIX%online (
user_id INTEGER NOT NULL DEFAULT 1,
ident VARCHAR(200) NOT NULL DEFAULT '',
logged INTEGER NOT NULL DEFAULT 0,
idle INTEGER NOT NULL DEFAULT 0,
csrf_token VARCHAR(40) NOT NULL DEFAULT '',
prev_url VARCHAR(255),
last_post INTEGER,
last_search INTEGER,
UNIQUE (user_id,ident)
);
CREATE INDEX %PREFIX%online_ident_idx ON %PREFIX%online(ident);
CREATE INDEX %PREFIX%online_logged_idx ON %PREFIX%online(logged);
CREATE TABLE %PREFIX%posts (
id INTEGER NOT NULL,
poster VARCHAR(200) NOT NULL DEFAULT '',
poster_id INTEGER NOT NULL DEFAULT 1,
poster_ip VARCHAR(39),
poster_email VARCHAR(80),
message TEXT,
hide_smilies INTEGER NOT NULL DEFAULT 0,
posted INTEGER NOT NULL DEFAULT 0,
edited INTEGER,
edited_by VARCHAR(200),
topic_id INTEGER NOT NULL DEFAULT 0,
PRIMARY KEY (id)
);
CREATE INDEX %PREFIX%posts_topic_id_idx ON %PREFIX%posts(topic_id);
CREATE INDEX %PREFIX%posts_multi_idx ON %PREFIX%posts(poster_id,topic_id);
CREATE INDEX %PREFIX%posts_posted_idx ON %PREFIX%posts(posted);
CREATE TABLE %PREFIX%ranks (
id INTEGER NOT NULL,
rank VARCHAR(50) NOT NULL DEFAULT '',
min_posts INTEGER NOT NULL DEFAULT 0,
PRIMARY KEY (id)
);
CREATE TABLE %PREFIX%reports (
id INTEGER NOT NULL,
post_id INTEGER NOT NULL DEFAULT 0,
topic_id INTEGER NOT NULL DEFAULT 0,
forum_id INTEGER NOT NULL DEFAULT 0,
reported_by INTEGER NOT NULL DEFAULT 0,
created INTEGER NOT NULL DEFAULT 0,
message TEXT,
zapped INTEGER,
zapped_by INTEGER,
PRIMARY KEY (id)
);
CREATE INDEX %PREFIX%reports_zapped_idx ON %PREFIX%reports(zapped);
CREATE TABLE %PREFIX%search_cache (
id INTEGER NOT NULL DEFAULT 0,
ident VARCHAR(200) NOT NULL DEFAULT '',
search_data TEXT,
PRIMARY KEY (id)
);
CREATE INDEX %PREFIX%search_cache_ident_idx ON %PREFIX%search_cache(ident);
CREATE TABLE %PREFIX%search_matches (
post_id INTEGER NOT NULL DEFAULT 0,
word_id INTEGER NOT NULL DEFAULT 0,
subject_match INTEGER NOT NULL DEFAULT 0
);
CREATE INDEX %PREFIX%search_matches_word_id_idx ON %PREFIX%search_matches(word_id);
CREATE INDEX %PREFIX%search_matches_post_id_idx ON %PREFIX%search_matches(post_id);
CREATE TABLE %PREFIX%search_words (
id INTEGER NOT NULL,
word VARCHAR(20) NOT NULL DEFAULT '',
PRIMARY KEY (id),
UNIQUE (word)
);
CREATE INDEX %PREFIX%search_words_id_idx ON %PREFIX%search_words(id);
CREATE TABLE %PREFIX%subscriptions (
user_id INTEGER NOT NULL DEFAULT 0,
topic_id INTEGER NOT NULL DEFAULT 0,
PRIMARY KEY (user_id,topic_id)
);
CREATE TABLE %PREFIX%forum_subscriptions (
user_id INTEGER NOT NULL DEFAULT 0,
forum_id INTEGER NOT NULL DEFAULT 0,
PRIMARY KEY (user_id,forum_id)
);
CREATE TABLE %PREFIX%topics (
id INTEGER NOT NULL,
poster VARCHAR(200) NOT NULL DEFAULT '',
subject VARCHAR(255) NOT NULL DEFAULT '',
posted INTEGER NOT NULL DEFAULT 0,
first_post_id INTEGER NOT NULL DEFAULT 0,
last_post INTEGER NOT NULL DEFAULT 0,
last_post_id INTEGER NOT NULL DEFAULT 0,
last_poster VARCHAR(200),
num_views INTEGER NOT NULL DEFAULT 0,
num_replies INTEGER NOT NULL DEFAULT 0,
closed INTEGER NOT NULL DEFAULT 0,
sticky INTEGER NOT NULL DEFAULT 0,
moved_to INTEGER,
forum_id INTEGER NOT NULL DEFAULT 0,
PRIMARY KEY (id)
);
CREATE INDEX %PREFIX%topics_forum_id_idx ON %PREFIX%topics(forum_id);
CREATE INDEX %PREFIX%topics_moved_to_idx ON %PREFIX%topics(moved_to);
CREATE INDEX %PREFIX%topics_last_post_idx ON %PREFIX%topics(last_post);
CREATE INDEX %PREFIX%topics_first_post_id_idx ON %PREFIX%topics(first_post_id);
CREATE TABLE %PREFIX%users (
id INTEGER NOT NULL,
group_id INTEGER NOT NULL DEFAULT 3,
username VARCHAR(200) NOT NULL DEFAULT '',
password VARCHAR(40) NOT NULL DEFAULT '',
salt VARCHAR(12),
email VARCHAR(80) NOT NULL DEFAULT '',
title VARCHAR(50),
realname VARCHAR(40),
url VARCHAR(100),
facebook VARCHAR(100),
twitter VARCHAR(100),
linkedin VARCHAR(100),
skype VARCHAR(100),
jabber VARCHAR(80),
icq VARCHAR(12),
msn VARCHAR(80),
aim VARCHAR(30),
yahoo VARCHAR(30),
location VARCHAR(30),
signature TEXT,
disp_topics INTEGER,
disp_posts INTEGER,
email_setting INTEGER NOT NULL DEFAULT 1,
notify_with_post INTEGER NOT NULL DEFAULT 0,
auto_notify INTEGER NOT NULL DEFAULT 0,
show_smilies INTEGER NOT NULL DEFAULT 1,
show_img INTEGER NOT NULL DEFAULT 1,
show_img_sig INTEGER NOT NULL DEFAULT 1,
show_avatars INTEGER NOT NULL DEFAULT 1,
show_sig INTEGER NOT NULL DEFAULT 1,
access_keys INTEGER NOT NULL DEFAULT 0,
timezone FLOAT NOT NULL DEFAULT 0,
dst INTEGER NOT NULL DEFAULT 0,
time_format INTEGER NOT NULL DEFAULT 0,
date_format INTEGER NOT NULL DEFAULT 0,
language VARCHAR(25) NOT NULL DEFAULT 'English',
style VARCHAR(25) NOT NULL DEFAULT 'Oxygen',
num_posts INTEGER NOT NULL DEFAULT 0,
last_post INTEGER,
last_search INTEGER,
last_email_sent INTEGER,
registered INTEGER NOT NULL DEFAULT 0,
registration_ip VARCHAR(39) NOT NULL DEFAULT '0.0.0.0',
last_visit INTEGER NOT NULL DEFAULT 0,
admin_note VARCHAR(30),
activate_string VARCHAR(80),
activate_key VARCHAR(8),
avatar INTEGER NOT NULL DEFAULT 0,
avatar_width INTEGER NOT NULL DEFAULT 0,
avatar_height INTEGER NOT NULL DEFAULT 0,
PRIMARY KEY (id)
);
CREATE INDEX %PREFIX%users_registered_idx ON %PREFIX%users(registered);
CREATE INDEX %PREFIX%users_username_idx ON %PREFIX%users(username);

--
-- Board configuration. o_cur_version and o_database_revision name the previous
-- release: that is what makes this a fixture to upgrade rather than to serve.
-- o_base_url is absent, as it is on every 1.4 install — base_url lives in config.php.
--
INSERT INTO "%PREFIX%config" ("conf_name", "conf_value") VALUES
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
('o_timeout_online', '300'),
('o_timeout_visit', '5400'),
('o_time_format', 'H:i:s'),
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

INSERT INTO "%PREFIX%groups" ("g_id", "g_title", "g_user_title", "g_moderator", "g_mod_edit_users", "g_mod_rename_users", "g_mod_change_passwords", "g_mod_ban_users", "g_read_board", "g_view_users", "g_post_replies", "g_post_topics", "g_edit_posts", "g_delete_posts", "g_delete_topics", "g_set_title", "g_search", "g_search_users", "g_send_email", "g_post_flood", "g_search_flood", "g_email_flood") VALUES
(1, 'Administrators', 'Administrator', 0, 0, 0, 0, 0, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 0, 0, 0),
(2, 'Guest', NULL, 0, 0, 0, 0, 0, 1, 1, 0, 0, 0, 0, 0, 0, 1, 1, 0, 60, 30, 0),
(3, 'Members', NULL, 0, 0, 0, 0, 0, 1, 1, 1, 1, 1, 1, 1, 0, 1, 1, 1, 60, 30, 60),
(4, 'Moderators', 'Moderator', 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 0, 0, 0);

INSERT INTO "%PREFIX%ranks" ("id", "rank", "min_posts") VALUES
(1, 'New member', 0),
(2, 'Member', 10);

INSERT INTO "%PREFIX%categories" ("id", "cat_name", "disp_position") VALUES
(1, 'Тестовая категория', 0);

INSERT INTO "%PREFIX%forums" ("id", "forum_name", "forum_desc", "redirect_url", "moderators", "num_topics", "num_posts", "last_post", "last_post_id", "last_poster", "sort_by", "disp_position", "cat_id") VALUES
(1, 'Общий форум', 'Обсуждение чего угодно — на любом языке', NULL, 'a:1:{s:9:"moderator";i:4;}', 1, 3, 1700000300, 3, 'moderator', 0, 0, 1),
(2, 'Ärger & Umlauts', 'Ein Forum für Sonderzeichen: äöü ß «ёлка»', NULL, NULL, 1, 1, 1700000400, 4, 'фикстура-юзер', 0, 1, 1);

--
-- Passwords are sha1($salt.sha1($password)) over the throwaway string
-- "fixture-password"; the salts are literals, not generated ones.
--
INSERT INTO "%PREFIX%users" ("id", "group_id", "username", "password", "salt", "email", "title", "realname", "url", "facebook", "twitter", "linkedin", "skype", "jabber", "icq", "msn", "aim", "yahoo", "location", "signature", "disp_topics", "disp_posts", "email_setting", "notify_with_post", "auto_notify", "show_smilies", "show_img", "show_img_sig", "show_avatars", "show_sig", "access_keys", "timezone", "dst", "time_format", "date_format", "language", "style", "num_posts", "last_post", "last_search", "last_email_sent", "registered", "registration_ip", "last_visit", "admin_note", "activate_string", "activate_key", "avatar", "avatar_width", "avatar_height") VALUES
(1, 2, 'Guest', 'Guest', NULL, 'Guest', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, 0, 1, 1, 1, 1, 1, 0, 0, 0, 0, 0, 'English', 'Oxygen', 0, NULL, NULL, NULL, 0, '0.0.0.0', 0, NULL, NULL, NULL, 0, 0, 0),
(2, 1, 'fixture-admin', 'b2e3db229edf83d68057abdecb6ea9ee40400649', 'fixture-salt', 'fixture-admin@example.invalid', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, 0, 1, 1, 1, 1, 1, 0, 0, 0, 0, 0, 'English', 'Oxygen', 1, 1700000100, NULL, NULL, 1700000000, '203.0.113.1', 1700000500, NULL, NULL, NULL, 0, 0, 0),
(3, 3, 'фикстура-юзер', 'b2e3db229edf83d68057abdecb6ea9ee40400649', 'fixture-salt', 'member@example.invalid', NULL, 'Тестовый Пользователь', 'http://пример.испытание/', NULL, NULL, 'example.invalid/in/nobody', NULL, NULL, NULL, NULL, NULL, NULL, 'Мюнхен', 'Подпись с [b]тегами[/b] — и умлаутами: äöü', NULL, NULL, 1, 0, 0, 1, 1, 1, 1, 1, 0, 0, 0, 0, 0, 'English', 'Oxygen', 2, 1700000400, NULL, NULL, 1700000010, '203.0.113.2', 1700000600, NULL, NULL, NULL, 1, 48, 48),
(4, 4, 'moderator', 'b2e3db229edf83d68057abdecb6ea9ee40400649', 'fixture-salt', 'moderator@example.invalid', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 0, 0, 1, 1, 1, 1, 1, 0, 0, 0, 0, 0, 'English', 'Oxygen', 1, 1700000300, NULL, NULL, 1700000020, '203.0.113.3', 1700000700, NULL, NULL, NULL, 0, 0, 0);

INSERT INTO "%PREFIX%topics" ("id", "poster", "subject", "posted", "first_post_id", "last_post", "last_post_id", "last_poster", "num_views", "num_replies", "closed", "sticky", "moved_to", "forum_id") VALUES
(1, 'fixture-admin', 'Приветствие — «первая тема»', 1700000100, 1, 1700000300, 3, 'moderator', 7, 2, 0, 1, NULL, 1),
(2, 'фикстура-юзер', 'Ümlaut-Thema', 1700000400, 4, 1700000400, 4, 'фикстура-юзер', 2, 0, 0, 0, NULL, 2);

INSERT INTO "%PREFIX%posts" ("id", "poster", "poster_id", "poster_ip", "poster_email", "message", "hide_smilies", "posted", "edited", "edited_by", "topic_id") VALUES
(1, 'fixture-admin', 2, '203.0.113.1', NULL, 'Привет, мир! Это [b]первое[/b] сообщение фикстуры — с тире, «кавычками» и умлаутами: äöü ß.', 0, 1700000100, NULL, NULL, 1),
(2, 'фикстура-юзер', 3, '203.0.113.2', NULL, '[quote=fixture-admin]Привет, мир![/quote]' || char(10) || 'Ответ со ссылкой на IDN: [url=http://пример.испытание/путь]пример.испытание[/url] и кодом: [code]echo "日本語";[/code]', 0, 1700000200, 1700000250, 'фикстура-юзер', 1),
(3, 'moderator', 4, '203.0.113.3', NULL, 'Третье сообщение: 中文, ελληνικά, עברית — всё в одной строке.', 0, 1700000300, NULL, NULL, 1),
(4, 'фикстура-юзер', 3, '203.0.113.2', NULL, 'Ein Beitrag über Ärger, Öl und Übermut. Smilies: :) :rolleyes:', 0, 1700000400, NULL, NULL, 2);

INSERT INTO "%PREFIX%censoring" ("id", "search_for", "replace_with") VALUES
(1, 'плохоеслово', 'хорошееслово');

INSERT INTO "%PREFIX%bans" ("id", "username", "ip", "email", "message", "expire", "ban_creator") VALUES
(1, 'спамер', '198.51.100.7', 'spammer@example.invalid', 'Спам — бан навсегда', NULL, 2);

INSERT INTO "%PREFIX%reports" ("id", "post_id", "topic_id", "forum_id", "reported_by", "created", "message", "zapped", "zapped_by") VALUES
(1, 3, 1, 1, 3, 1700000350, 'Жалоба на сообщение — проверка кодировки', NULL, NULL);

INSERT INTO "%PREFIX%subscriptions" ("user_id", "topic_id") VALUES
(3, 1);

INSERT INTO "%PREFIX%forum_subscriptions" ("user_id", "forum_id") VALUES
(3, 2);

INSERT INTO "%PREFIX%forum_perms" ("group_id", "forum_id", "read_forum", "post_replies", "post_topics") VALUES
(2, 2, 1, 0, 0);

--
-- One installed extension, with the hook row that goes with it. The hook writes
-- a marker into the page head, so the functional pass can see it still runs on
-- the upgraded forum.
--
INSERT INTO "%PREFIX%extensions" ("id", "title", "version", "description", "author", "uninstall", "uninstall_note", "disabled", "dependencies") VALUES
('fixture_ext', 'Фикстурное расширение', '1.0', 'Расширение для проверки обновления', 'Nobody', NULL, NULL, 0, '');

INSERT INTO "%PREFIX%extension_hooks" ("id", "extension_id", "code", "installed", "priority") VALUES
('hd_head', 'fixture_ext', '$forum_head[''fixture''] = ''<meta name="fixture-ext" content="1" />'';', 1700000000, 5);
