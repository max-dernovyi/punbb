<?php

declare(strict_types=1);

namespace PunBB\Module\Site\Visitor;

/**
 * What a user group allows its members, by the column that stores it.
 */
enum GroupPermission: string {
	case Moderate = 'g_moderator';
	case EditUsers = 'g_mod_edit_users';
	case RenameUsers = 'g_mod_rename_users';
	case ChangePasswords = 'g_mod_change_passwords';
	case BanUsers = 'g_mod_ban_users';
	case ReadBoard = 'g_read_board';
	case ViewUsers = 'g_view_users';
	case PostReplies = 'g_post_replies';
	case PostTopics = 'g_post_topics';
	case EditPosts = 'g_edit_posts';
	case DeletePosts = 'g_delete_posts';
	case DeleteTopics = 'g_delete_topics';
	case SetTitle = 'g_set_title';
	case Search = 'g_search';
	case SearchUsers = 'g_search_users';
	case SendEmail = 'g_send_email';
}
