<?php
/**
 * What the profile's controllers read and change, over plain properties that
 * record each call: the profiles, the files an upload moves, the removals and
 * caches, the packs, and the site's security and mail.
 *
 * @copyright (C) 2008-2012 PunBB, partially based on code (C) 2008-2009 FluxBB.org
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package PunBB
 */

use PunBB\Module\Framework\Http\Request;
use PunBB\Module\Framework\Http\Response;
use PunBB\Module\Layout\View\Html;
use PunBB\Module\Layout\View\TemplateRenderer;
use PunBB\Module\Message\Page\ConfirmPage;
use PunBB\Module\Profile\Api\Data\AvatarInterface;
use PunBB\Module\Profile\Api\Data\DetailsInterface;
use PunBB\Module\Profile\Api\Data\EmailActivationInterface;
use PunBB\Module\Profile\Api\Data\EmailChangeInterface;
use PunBB\Module\Profile\Api\Data\ForumModeratorsInterface;
use PunBB\Module\Profile\Api\Data\ModeratorInterface;
use PunBB\Module\Profile\Api\Data\PasswordInterface;
use PunBB\Module\Profile\Api\Data\ProfileUserInterface;
use PunBB\Module\Profile\Api\Data\RenameInterface;
use PunBB\Module\Profile\Api\ProfilesInterface;
use PunBB\Module\Profile\Avatar\AvatarRemovalInterface;
use PunBB\Module\Profile\Avatar\UploadedFilesInterface;
use PunBB\Module\Profile\Controller\AvatarUpload;
use PunBB\Module\Profile\Controller\DetailsUpdate;
use PunBB\Module\Profile\Controller\EmailChange;
use PunBB\Module\Profile\Controller\PasswordChange;
use PunBB\Module\Profile\Controller\ProfileAdministration;
use PunBB\Module\Profile\Controller\ProfileController;
use PunBB\Module\Profile\Controller\ProfileSections;
use PunBB\Module\Profile\Model\ForumModerators;
use PunBB\Module\Profile\Model\ListedGroup;
use PunBB\Module\Profile\Model\ModeratableForum;
use PunBB\Module\Profile\Model\Moderator;
use PunBB\Module\Profile\Model\ProfileUser;
use PunBB\Module\Site\Account\UsernameRulesInterface;
use PunBB\Module\Site\Cache\BanCacheInterface;
use PunBB\Module\Site\Config\PacksInterface;
use PunBB\Module\Site\Mail\EmailAddressesInterface;
use PunBB\Module\Site\Mail\MailerInterface;
use PunBB\Module\Site\Moderation\ModeratorListsInterface;
use PunBB\Module\Site\Posting\PostRulesInterface;
use PunBB\Module\Site\Posting\PreparsedMessage;
use PunBB\Module\Site\Removal\UserRemovalInterface;
use PunBB\Module\Site\Security\PasswordsInterface;
use PunBB\Module\Site\Security\RandomKeysInterface;
use PunBB\Module\Site\Security\SignInInterface;

require_once __DIR__.'/PageFakes.php';

final class FakeProfileServices implements ProfilesInterface, AvatarRemovalInterface, UploadedFilesInterface, UserRemovalInterface, ModeratorListsInterface, BanCacheInterface,
	PacksInterface, UsernameRulesInterface, EmailAddressesInterface, MailerInterface, RandomKeysInterface, PasswordsInterface, SignInInterface, PostRulesInterface {
	/** @var list<string> every change, in order */
	public array $log = array();

	/** @var array<int, ProfileUser> */
	public array $users = array();

	/** @var array<string, list<string>> address => members registered with it */
	public array $addresses = array();

	/** @var list<int> the groups that moderate */
	public array $moderating = array(1, 4);

	/** @var list<ForumModerators> */
	public array $forums = array();

	/** @var list<ModeratableForum> */
	public array $moderatable = array();

	/** @var list<string> */
	public array $languages = array('English');

	/** @var list<string> */
	public array $styles = array('Oxygen');

	/** @var array<string, array{int, int, int}|null> file => the image it holds */
	public array $images = array();

	public bool $moves = true;

	public string $visitorPassword = 'secret';

	public int $cookieExpiry = 0;

	/** @var list<string> */
	public array $banned = array();

	/** @param array<array-key, mixed> $columns */
	public static function member(array $columns): ProfileUser {
		return new ProfileUser($columns + array(
			'id' => 3, 'group_id' => 3, 'username' => 'member', 'password' => 'hash:old', 'salt' => 'salty', 'email' => 'member@example.com', 'title' => null, 'realname' => null,
			'url' => null, 'facebook' => null, 'twitter' => null, 'skype' => null, 'icq' => null, 'linkedin' => null, 'jabber' => null, 'msn' => null, 'aim' => null, 'yahoo' => null,
			'location' => null, 'signature' => null, 'disp_topics' => null, 'disp_posts' => null, 'email_setting' => 1, 'notify_with_post' => 0, 'auto_notify' => 0,
			'show_smilies' => 1, 'show_img' => 1, 'show_img_sig' => 1, 'show_avatars' => 1, 'show_sig' => 1, 'timezone' => 0, 'dst' => 0, 'time_format' => 0, 'date_format' => 0,
			'language' => 'English', 'style' => 'Oxygen', 'num_posts' => 7, 'last_post' => null, 'last_email_sent' => null, 'registered' => 100, 'registration_ip' => '192.0.2.7',
			'last_visit' => 200, 'admin_note' => null, 'activate_string' => null, 'activate_key' => null, 'avatar' => 0, 'avatar_width' => 0, 'avatar_height' => 0,
			'g_id' => 3, 'g_user_title' => null, 'g_moderator' => 0,
		));
	}

	public function user(int $id): ?ProfileUserInterface {
		return $this->users[$id] ?? null;
	}

	public function resetPassword(PasswordInterface ...$passwords): void {
		foreach ($passwords as $password)
			$this->log[] = 'reset password of '.$password->userId().' to '.$password->hash();
	}

	public function changePassword(PasswordInterface ...$passwords): void {
		foreach ($passwords as $password)
			$this->log[] = 'change password of '.$password->userId().' to '.$password->hash();
	}

	public function confirmEmail(int ...$userIds): void {
		$this->log[] = 'confirm email of '.implode(',', $userIds);
	}

	public function usernamesWithEmail(string $email): array {
		return $this->addresses[$email] ?? array();
	}

	public function changeEmail(EmailChangeInterface ...$changes): void {
		foreach ($changes as $change)
			$this->log[] = 'change email of '.$change->userId().' to '.$change->email();
	}

	public function requestEmailChange(EmailActivationInterface ...$activations): void {
		foreach ($activations as $activation)
			$this->log[] = 'request email of '.$activation->userId().' to '.$activation->email().' with '.$activation->key();
	}

	public function moveToGroup(int $groupId, int ...$userIds): void {
		$this->log[] = 'move '.implode(',', $userIds).' to group '.$groupId;
	}

	public function groupModerates(int $groupId): bool {
		$this->log[] = 'check group '.$groupId;

		return in_array($groupId, $this->moderating, true);
	}

	public function forumModerators(): array {
		return $this->forums;
	}

	public function storeModerators(ForumModeratorsInterface ...$forums): void {
		foreach ($forums as $forum)
			$this->log[] = 'moderators of '.$forum->forumId().': '.self::named($forum->moderators());
	}

	public function storeAvatar(AvatarInterface ...$avatars): void {
		foreach ($avatars as $avatar)
			$this->log[] = 'avatar of '.$avatar->userId().': '.$avatar->type().' '.$avatar->width().'x'.$avatar->height();
	}

	public function updateDetails(DetailsInterface ...$details): void {
		foreach ($details as $section)
		{
			$values = array();
			foreach ($section->columns() as $column)
				$values[] = $column.'='.var_export($section->value($column), true);

			$this->log[] = 'update '.$section->userId().': '.implode(', ', $values);
		}
	}

	public function renamePosts(RenameInterface ...$renames): void {
		$this->rename('posts', $renames);
	}

	public function renameTopics(RenameInterface ...$renames): void {
		$this->rename('topics', $renames);
	}

	public function renameTopicLastPosters(RenameInterface ...$renames): void {
		$this->rename('topic last posters', $renames);
	}

	public function renameForumLastPosters(RenameInterface ...$renames): void {
		$this->rename('forum last posters', $renames);
	}

	public function renameOnline(RenameInterface ...$renames): void {
		$this->rename('online', $renames);
	}

	public function renameEditors(RenameInterface ...$renames): void {
		$this->rename('editors', $renames);
	}

	public function groups(): array {
		return array(new ListedGroup(1, 'Administrators'), new ListedGroup(3, 'Members <m>'), new ListedGroup(4, 'Moderators'));
	}

	public function moderatableForums(): array {
		return $this->moderatable;
	}

	public function remove(int $userId, bool $withPosts = false): void {
		$this->log[] = func_num_args() === 1 ? 'remove avatar of '.$userId : 'remove user '.$userId.($withPosts ? ' with posts' : '');
	}

	public function isUploaded(string $file): bool {
		return $file === '/tmp/php-upload';
	}

	public function move(string $file, string $destination): bool {
		$this->log[] = 'move upload to '.$destination;

		return $this->moves;
	}

	public function imageSize(string $file): ?array {
		return $this->images[$file] ?? null;
	}

	public function delete(string $file): void {
		$this->log[] = 'delete '.$file;
	}

	public function place(string $file, string $destination): void {
		$this->log[] = 'place '.$file.' at '.$destination;
	}

	public function clean(): void {
		$this->log[] = 'clean moderators';
	}

	public function rebuild(): void {
		$this->log[] = 'rebuild bans';
	}

	public function styles(): array {
		return $this->styles;
	}

	public function languages(): array {
		return $this->languages;
	}

	public function urlSchemes(): array {
		return array('Default');
	}

	public function validate(string $username, ?int $exceptUserId = null): array {
		return mb_strlen($username) < 2 ? array(new Html('Username <b>too short</b>')) : array();
	}

	public function isValid(string $address): bool {
		return str_contains($address, '@');
	}

	public function isBanned(string $address): bool {
		return in_array($address, $this->banned, true);
	}

	public function send(string $to, string $subject, string $message, bool $quiet = false, string $replyTo = '', string $replyToName = ''): void {
		$this->log[] = 'mail '.$to.': '.$subject.' | '.$message;
	}

	public function key(int $length, bool $readable = false, bool $hash = false): string {
		return str_repeat('K', $length);
	}

	public function hash(string $password): string {
		return 'hash:'.$password;
	}

	public function verify(string $password, string $hash, string $salt): bool {
		return 'hash:'.$password === $hash;
	}

	public function verifyVisitor(string $password): bool {
		return $password === $this->visitorPassword;
	}

	public function verifyAgainstNobody(string $password): void {}

	public function needsRehash(string $hash): bool {
		return false;
	}

	public function resetKeyLifetime(): int {
		return 3600;
	}

	public function regenerateSession(): void {}

	public function signIn(int $userId, string $passwordHash, string $salt, int $expire): void {
		$this->log[] = 'sign in '.$userId.' with '.$passwordHash.' and '.$salt.' for '.($expire - time());
	}

	public function expiryOf(array $cookies): int {
		return $this->cookieExpiry;
	}

	public function signOut(): void {}

	public function subjectMaximumLength(): int {
		return 70;
	}

	public function messageMaximumBytes(): int {
		return 65535;
	}

	public function preparse(string $text, array $errors): PreparsedMessage {
		return new PreparsedMessage($text, $errors);
	}

	public function preparseSignature(string $text, array $errors): PreparsedMessage {
		if ($errors === array() && str_contains($text, '[bad]'))
			$errors[] = new Html('Bad <b>tag</b>');

		return new PreparsedMessage(str_replace('[B]', '[b]', $text), $errors);
	}

	/** @param list<ModeratorInterface> $moderators */
	public static function named(array $moderators): string {
		return implode(',', array_map(static fn (ModeratorInterface $moderator): string => $moderator->username().'='.$moderator->userId(), $moderators));
	}

	/** @param array<RenameInterface> $renames */
	private function rename(string $what, array $renames): void {
		foreach ($renames as $rename)
			$this->log[] = 'rename '.$what.' of '.$rename->userId().' from '.$rename->oldName().' to '.$rename->newName();
	}
}

/** A profile controller built over a page kit and the profile's fakes. */
final class ProfileKit {
	public FakeProfileServices $services;

	public function __construct(public PageKit $kit) {
		$this->services = new FakeProfileServices();
		$kit->language->real = array('profile', 'common');
		$kit->settings->values = array(
			'o_board_title' => 'Board & Co', 'o_redirect_delay' => '0', 'o_avatars' => '1', 'o_signatures' => '1', 'o_subscriptions' => '1', 'o_smilies' => '1', 'o_smilies_sig' => '1',
			'p_message_img_tag' => '1', 'p_sig_img_tag' => '1', 'p_sig_bbcode' => '1', 'o_censoring' => '0', 'o_show_post_count' => '1', 'o_mask_passwords' => '1', 'o_timeout_visit' => '1800',
			'o_admin_email' => 'admin@example.com', 'o_mailing_list' => '', 'p_allow_banned_email' => '1', 'p_allow_dupe_email' => '1', 'o_regs_verify' => '0',
			'p_sig_length' => '400', 'p_sig_lines' => '4', 'p_sig_all_caps' => '0', 'o_make_links' => '1', 'o_default_timezone' => '0', 'o_avatars_dir' => 'img/avatars',
			'o_avatars_width' => '60', 'o_avatars_height' => '60', 'o_avatars_size' => '10240', 'o_default_user_group' => '3',
		);
	}

	/**
	 * @param array<string, mixed> $query
	 * @param array<string, mixed> $post
	 * @param array<string, mixed> $files
	 */
	public function page(array $query, array $post = array(), array $files = array()): string {
		$response = $this->respond($query, $post, $files);

		return $response->status.' '.($response->headers['Location'] ?? '').' '.$response->body;
	}

	/**
	 * @param array<string, mixed> $query
	 * @param array<string, mixed> $post
	 * @param array<string, mixed> $files
	 */
	public function respond(array $query, array $post = array(), array $files = array()): Response {
		$kit = $this->kit;
		$services = $this->services;
		$templates = new TemplateRenderer();

		$confirmations = new ConfirmPage($kit->dispatcher, $kit->pages(), $templates, $kit->redirects(), $kit->language, $kit->settings, $kit->urls, $kit->visitor, $kit->tokens);

		$controller = new ProfileController(
			$kit->dispatcher,
			$kit->messages(),
			$services,
			new PasswordChange($kit->dispatcher, $kit->pages(), $templates, $kit->messages(), $kit->redirects(), $services, $services, $services, $kit->visitor, $kit->language, $kit->settings, $kit->urls, $kit->tokens, $kit->flash),
			new EmailChange($kit->dispatcher, $kit->pages(), $templates, $kit->messages(), $kit->redirects(), $services, $services, $services, $services, $services, $kit->visitor, $kit->language, $kit->settings, $kit->urls, $kit->tokens),
			new ProfileAdministration($kit->dispatcher, $kit->pages(), $templates, $kit->messages(), $kit->redirects(), $confirmations, $services, $services, $services, $services, $kit->visitor, $kit->language, $kit->settings, $kit->urls, $kit->tokens, $kit->flash),
			new DetailsUpdate($kit->dispatcher, $kit->messages(), $kit->redirects(), $services,
				new AvatarUpload($kit->dispatcher, $kit->messages(), $services, $services, $services, $kit->language, $kit->settings, $kit->formatter),
				$services, $services, $services, $services, $services, $kit->visitor, $kit->language, $kit->settings, $kit->urls, $kit->formatter, $kit->flash),
			new ProfileSections($kit->dispatcher, $kit->pages(), $templates, $kit->messages(), $services, $services, $kit->visitor, $kit->language, $kit->settings, $kit->urls, $kit->formatter, $kit->tokens),
			$kit->visitor,
			$kit->language
		);

		return $controller->handle(new Request($post !== array() ? 'POST' : 'GET', '/', 'profile.php', $query, $post, files: $files));
	}
}
