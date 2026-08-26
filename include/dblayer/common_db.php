<?php
/**
 * Loads the proper database layer class.
 *
 * @copyright (C) 2008-2012 PunBB, partially based on code (C) 2008-2009 FluxBB.org
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package PunBB
 */


// Make sure no one attempts to run this script "directly"
if (!defined('FORUM'))
	exit;


// Load the appropriate DB layer class
switch ($db_type)
{
	case 'mysqli':
		require FORUM_ROOT.'include/dblayer/mysqli.php';
		break;

	case 'mysqli_innodb':
		require FORUM_ROOT.'include/dblayer/mysqli_innodb.php';
		break;

	case 'pgsql':
		require FORUM_ROOT.'include/dblayer/pgsql.php';
		break;

	case 'sqlite3':
		require FORUM_ROOT.'include/dblayer/sqlite3.php';
		break;

	default:
		$replacement = forum_removed_db_type_replacement($db_type);
		if ($replacement !== null)
			error('The \''.$db_type.'\' database driver was removed along with the PHP extension it needs. Set $db_type to \''.$replacement.'\' in config.php.', __FILE__, __LINE__);

		error('\''.$db_type.'\' is not a valid database type. Supported types are '.implode(', ', forum_supported_db_types()).'. Please check settings in config.php.', __FILE__, __LINE__);
		break;
}


// Create the database adapter object (and open/connect to/select db)
$forum_db = new DBLayer($db_host, $db_username, $db_password, $db_name, $db_prefix, $p_connect);
