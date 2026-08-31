<?php

// Define the version and database revision that this code was written for
define('FORUM_VERSION', '1.5.0');
define('FORUM_DB_REVISION', 6);

// The PHP version this release requires
define('FORUM_MIN_PHP_VERSION', '8.4.0');

// Define a few commonly used constants
define('FORUM_UNVERIFIED', 0);
define('FORUM_ADMIN', 1);
define('FORUM_GUEST', 2);

// Define avatars type
define('FORUM_AVATAR_NONE', 0);
define('FORUM_AVATAR_GIF', 1);
define('FORUM_AVATAR_JPG', 2);
define('FORUM_AVATAR_PNG', 3);

define('FORUM_SUBJECT_MAXIMUM_LENGTH', 70);
define('FORUM_DATABASE_QUERY_MAXIMUM_LENGTH', 140000);

// How long a mailed password-reset key stays usable. It matches the interval
// login.php refuses to send a second mail for, so a key that has expired can
// always be replaced immediately.
define('FORUM_PASSWORD_RESET_TTL', 3600);

// A password_hash() output for a password nobody holds. login.php verifies
// against it when the username matches no row, so the form costs the same
// whether or not the account exists.
define('FORUM_DUMMY_PASSWORD_HASH', '$2y$12$HbVpSQbNoPIL7Jr1pApgwOvr0PRrggX2YiAmD7TL68pTj8/3gpiNu');

define('FORUM_SEARCH_MIN_WORD', 3);
define('FORUM_SEARCH_MAX_WORD', 20);

define('FORUM_PUN_EXTENSION_REPOSITORY_URL', 'https://punbb.informer.com/extensions/1.4');
