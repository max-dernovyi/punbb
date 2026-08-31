<?php
/**
 * A database layer class that relies on the MySQLi PHP extension.
 *
 * @copyright (C) 2008-2012 PunBB, partially based on code (C) 2008-2009 FluxBB.org
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package PunBB
 */


// Make sure we have built in support for MySQL
if (!function_exists('mysqli_connect'))
	exit('This PHP environment doesn\'t have Improved MySQL (mysqli) support built in. Improved MySQL support is required if you want to use a MySQL 4.1 (or later) database to run this forum. Consult the PHP documentation for further assistance.');

class DBLayer
{
	public $prefix;
	public $link_id;
	public $query_result;
	public int $in_transaction = 0;

	public array $saved_queries = array();
	public int $num_queries = 0;

	public $error_no = false;
	public $error_msg = '';

	public array $datatype_transformations = array(
		'/^SERIAL$/'	=>	'INT(10) UNSIGNED AUTO_INCREMENT'
	);

	public function __construct($db_host, $db_username, $db_password, $db_name, $db_prefix, $foo)
	{
		$this->prefix = $db_prefix;

		// PHP 8.1 made this the default. Setting it explicitly means the driver
		// behaves the same whatever mysqli.report_mode is set to.
		mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

		// Was a custom port supplied with $db_host? mysqli_connect() types the port
		// as ?int since PHP 8.0, and a TypeError would escape the catch below.
		$db_port = 0;
		if (strpos($db_host, ':') !== false)
		{
			list($db_host, $db_port) = explode(':', $db_host, 2);
			$db_port = (int) $db_port;
		}

		try
		{
			if ($db_port > 0)
				$this->link_id = mysqli_connect($db_host, $db_username, $db_password, $db_name, $db_port);
			else
				$this->link_id = mysqli_connect($db_host, $db_username, $db_password, $db_name);
		}
		catch (mysqli_sql_exception $e)
		{
			$this->error_no = $e->getCode();
			$this->error_msg = $e->getMessage();

			// The driver message names the DB user and host - debug builds only.
			error('Unable to connect to MySQL and select database.'.(defined('FORUM_DEBUG') ? ' MySQL reported: '.forum_htmlencode($e->getMessage()) : ''), __FILE__, __LINE__);
		}

		// mysqli_connect() throws under MYSQLI_REPORT_STRICT, but a false return
		// must not fall through to set_names() and a TypeError carrying the
		// connection arguments.
		if (!$this->link_id)
			error('Unable to connect to MySQL and select database.', __FILE__, __LINE__);

		// Setup the client-server character set (UTF-8)
		if (!defined('FORUM_NO_SET_NAMES'))
			$this->set_names('utf8');

		return $this->link_id;
	}

	public function __destruct()
	{
	    $this->close();
	}


	/**
	 * Render the forum's error page for the failure just recorded.
	 *
	 * The file and line reported are the caller's, not this file's: a failure
	 * has to say which query site produced it, the way the
	 * "or error(__FILE__, __LINE__)" call sites used to.
	 */
	public function report_error()
	{
		foreach (debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS) as $frame)
		{
			if (isset($frame['file']) && $frame['file'] !== __FILE__)
				return error($frame['file'], $frame['line']);
		}

		return error(__FILE__, __LINE__);
	}


	public function start_transaction()
	{
		++$this->in_transaction;

		try
		{
			return mysqli_query($this->link_id, 'START TRANSACTION');
		}
		catch (Throwable $e)
		{
			--$this->in_transaction;
			$this->error_no = $e->getCode();
			$this->error_msg = $e->getMessage();

			$this->report_error();

			return false;
		}
	}

	public function end_transaction()
	{
		--$this->in_transaction;

		try
		{
			if (mysqli_query($this->link_id, 'COMMIT'))
				return true;

			$this->error_no = mysqli_errno($this->link_id);
			$this->error_msg = mysqli_error($this->link_id);
		}
		catch (Throwable $e)
		{
			$this->error_no = $e->getCode();
			$this->error_msg = $e->getMessage();
		}

		// A commit that did not go through leaves the transaction open; roll it
		// back before the error page ends the request.
		try
		{
			mysqli_query($this->link_id, 'ROLLBACK');
		}
		catch (Throwable $e)
		{
		}

		$this->report_error();

		return false;
	}

	public function query($sql, $unbuffered = false)
	{
		if (strlen($sql) > FORUM_DATABASE_QUERY_MAXIMUM_LENGTH)
			exit('Insane query. Aborting.');

		if (defined('FORUM_SHOW_QUERIES') || defined('FORUM_DEBUG'))
			$q_start = forum_microtime();

		// Since PHP 8.1 mysqli throws mysqli_sql_exception instead of returning
		// false. Left uncaught it prints a stack trace with the connection
		// arguments in it, so every failure is routed to the forum error page.
		try
		{
			$this->query_result = mysqli_query($this->link_id, $sql);
		}
		catch (Throwable $e)
		{
			$this->query_result = false;
			$this->error_no = $e->getCode();
			$this->error_msg = $e->getMessage();

			if (defined('FORUM_SHOW_QUERIES') || defined('FORUM_DEBUG'))
				$this->saved_queries[] = array($sql, 0);

			if ($this->in_transaction)
			{
				try
				{
					mysqli_query($this->link_id, 'ROLLBACK');
				}
				catch (Throwable $rollback_error)
				{
				}
			}

			--$this->in_transaction;

			$this->report_error();

			return false;
		}

		if (defined('FORUM_SHOW_QUERIES') || defined('FORUM_DEBUG'))
			$this->saved_queries[] = array($sql, sprintf('%.5f', forum_microtime() - $q_start));

		++$this->num_queries;

		return $this->query_result;
	}

	public function query_build($query, $return_query_string = false, $unbuffered = false)
	{
		$sql = '';

		if (isset($query['SELECT']))
		{
			$sql = 'SELECT '.$query['SELECT'].' FROM '.$this->quote_table_reference((isset($query['PARAMS']['NO_PREFIX']) ? '' : $this->prefix).$query['FROM']);

			if (isset($query['JOINS']))
			{
				foreach ($query['JOINS'] as $cur_join)
					$sql .= ' '.key($cur_join).' '.$this->quote_table_reference((isset($query['PARAMS']['NO_PREFIX']) ? '' : $this->prefix).current($cur_join)).' ON '.$cur_join['ON'];
			}

			if (!empty($query['WHERE']))
				$sql .= ' WHERE '.$query['WHERE'];
			if (!empty($query['GROUP BY']))
				$sql .= ' GROUP BY '.$query['GROUP BY'];
			if (!empty($query['HAVING']))
				$sql .= ' HAVING '.$query['HAVING'];
			if (!empty($query['ORDER BY']))
				$sql .= ' ORDER BY '.$query['ORDER BY'];
			if (!empty($query['LIMIT']))
				$sql .= ' LIMIT '.$query['LIMIT'];
		}
		else if (isset($query['INSERT']))
		{
			$sql = 'INSERT INTO '.$this->quote_table_reference((isset($query['PARAMS']['NO_PREFIX']) ? '' : $this->prefix).$query['INTO']);

			if (!empty($query['INSERT']))
				$sql .= ' ('.$query['INSERT'].')';

			if (is_array($query['VALUES']))
				$sql .= ' VALUES('.implode('),(', $query['VALUES']).')';
			else
				$sql .= ' VALUES('.$query['VALUES'].')';
		}
		else if (isset($query['UPDATE']))
		{
			$query['UPDATE'] = $this->quote_table_reference((isset($query['PARAMS']['NO_PREFIX']) ? '' : $this->prefix).$query['UPDATE']);

			$sql = 'UPDATE '.$query['UPDATE'].' SET '.$query['SET'];

			if (!empty($query['WHERE']))
				$sql .= ' WHERE '.$query['WHERE'];
		}
		else if (isset($query['DELETE']))
		{
			$sql = 'DELETE FROM '.$this->quote_table_reference((isset($query['PARAMS']['NO_PREFIX']) ? '' : $this->prefix).$query['DELETE']);

			if (!empty($query['WHERE']))
				$sql .= ' WHERE '.$query['WHERE'];
		}
		else if (isset($query['REPLACE']))
		{
			$sql = 'REPLACE INTO '.$this->quote_table_reference((isset($query['PARAMS']['NO_PREFIX']) ? '' : $this->prefix).$query['INTO']);

			if (!empty($query['REPLACE']))
				$sql .= ' ('.$query['REPLACE'].')';

			$sql .= ' VALUES('.$query['VALUES'].')';
		}

		return ($return_query_string) ? $sql : $this->query($sql, $unbuffered);
	}


	// mysqli_query() returns bool(true) for every non-SELECT statement, and a
	// truthiness test lets that reach the fetch functions, where PHP 8 raises a
	// TypeError that @ cannot suppress. Every reader below tests for a result.

	public function result($query_id = 0, $row = 0, $col = 0)
	{
		if ($query_id instanceof mysqli_result)
		{
			if ($row)
				@mysqli_data_seek($query_id, $row);

			$cur_row = @mysqli_fetch_row($query_id);

			// No such row: false, like the no-result-set branch below
			return is_array($cur_row) ? $cur_row[$col] : false;
		}
		else
			return false;
	}

	public function fetch_assoc($query_id = 0)
	{
		return ($query_id instanceof mysqli_result) ? @mysqli_fetch_assoc($query_id) : false;
	}

	public function fetch_row($query_id = 0)
	{
		return ($query_id instanceof mysqli_result) ? @mysqli_fetch_row($query_id) : false;
	}

	public function num_rows($query_id = 0)
	{
		return ($query_id instanceof mysqli_result) ? @mysqli_num_rows($query_id) : false;
	}

	public function affected_rows()
	{
		return ($this->link_id) ? @mysqli_affected_rows($this->link_id) : false;
	}

	public function insert_id()
	{
		return ($this->link_id) ? @mysqli_insert_id($this->link_id) : false;
	}

	public function get_num_queries()
	{
		return $this->num_queries;
	}

	public function get_saved_queries()
	{
		return $this->saved_queries;
	}

	public function free_result($query_id = false)
	{
		// mysqli_query() returns true for non-SELECT statements, and PHP 8.0+ throws
		// on an already-freed result where ext/mysqli used to warn. @ does not suppress it.
		if (!($query_id instanceof mysqli_result))
			return false;

		try
		{
			return @mysqli_free_result($query_id);
		}
		catch (Throwable $e)
		{
			return false;
		}
	}

	public function escape($str)
	{
		return is_array($str) ? '' : mysqli_real_escape_string($this->link_id, $str);
	}

	public function error()
	{
		$last_query = end($this->saved_queries);

		return array(
			'error_sql'	=> is_array($last_query) ? current($last_query) : '',
			'error_no'	=> $this->error_no,
			'error_msg'	=> $this->error_msg
		);
	}

	public function close()
	{
		if ($this->link_id)
		{
			if ($this->in_transaction)
			{
				if (defined('FORUM_SHOW_QUERIES') || defined('FORUM_DEBUG'))
					$this->saved_queries[] = array('COMMIT', 0);

				// Shutdown path: the page is already rendered, so a failing
				// commit has nowhere to go but the error log.
				try
				{
					mysqli_query($this->link_id, 'COMMIT');
				}
				catch (Throwable $e)
				{
				}
			}
		    		    
			$query_result = $this->query_result;
			$link_id = $this->link_id;
			$this->query_result = null;
			$this->link_id = null;
			$this->in_transaction = 0;

			$this->free_result($query_result);

			// PHP 8.0+ throws on an already-closed mysqli object where ext/mysqli used to warn.
			// close() is called explicitly (footer.php) and again from __destruct().
			try
			{
				return @mysqli_close($link_id);
			}
			catch (Throwable $e)
			{
				return false;
			}
		}
		else
			return false;
	}

	/**
	 * Set the connection charset. This has to go through mysqli_set_charset():
	 * a bare `SET NAMES` query changes the charset the server decodes by while
	 * the client keeps escaping by the one it connected with, and every one of
	 * escape()'s callers depends on the two agreeing.
	 */
	public function set_names($names)
	{
		try
		{
			return mysqli_set_charset($this->link_id, $names);
		}
		catch (Throwable $e)
		{
			$this->error_no = $e->getCode();
			$this->error_msg = $e->getMessage();

			$this->report_error();

			return false;
		}
	}

	public function get_version()
	{
		$result = $this->query('SELECT VERSION()');

		return array(
			'name'		=> 'MySQL Improved (InnoDB)',
			'version'	=> preg_replace('/^([^-]+).*$/', '\\1', $this->result($result))
		);
	}


	/**
	 * Quote an identifier. MySQL 8.0 made GROUPS and RANK reserved words and
	 * the schema uses both, so every generated identifier is quoted rather
	 * than checked against a keyword list.
	 */
	public function quote_identifier($name)
	{
		return '`'.str_replace('`', '``', $name).'`';
	}


	/**
	 * Quote a list of index columns. An entry may carry a prefix length
	 * ("ident(40)"), which belongs outside the quotes.
	 */
	public function quote_index_fields($fields)
	{
		$quoted = array();

		foreach ($fields as $cur_field)
		{
			if (preg_match('/^(.+)\((\d+)\)$/', $cur_field, $matches))
				$quoted[] = $this->quote_identifier($matches[1]).'('.$matches[2].')';
			else
				$quoted[] = $this->quote_identifier($cur_field);
		}

		return implode(',', $quoted);
	}


	/**
	 * Quote the tables in a comma separated list of "table" or "table AS alias"
	 * references. Anything more complex is returned untouched: query_build()
	 * takes raw SQL from callers and extensions, and this is not a parser.
	 */
	public function quote_table_reference($reference)
	{
		$quoted = array();

		foreach (explode(',', $reference) as $cur_reference)
		{
			if (preg_match('/^(\s*)([A-Za-z0-9_$]+)(\s+(?:AS\s+)?[A-Za-z0-9_$]+)?(\s*)$/i', $cur_reference, $matches))
				$quoted[] = $matches[1].$this->quote_identifier($matches[2]).(isset($matches[3]) ? $matches[3] : '').(isset($matches[4]) ? $matches[4] : '');
			else
				$quoted[] = $cur_reference;
		}

		return implode(',', $quoted);
	}


	public function table_exists($table_name, $no_prefix = false)
	{
		$result = $this->query('SHOW TABLES LIKE \''.($no_prefix ? '' : $this->prefix).$this->escape($table_name).'\'');
		return $this->num_rows($result) > 0;
	}

	public function field_exists($table_name, $field_name, $no_prefix = false)
	{
		$result = $this->query('SHOW COLUMNS FROM '.$this->quote_identifier(($no_prefix ? '' : $this->prefix).$table_name).' LIKE \''.$this->escape($field_name).'\'');
		return $this->num_rows($result) > 0;
	}

	public function index_exists($table_name, $index_name, $no_prefix = false)
	{
		$exists = false;

		$result = $this->query('SHOW INDEX FROM '.$this->quote_identifier(($no_prefix ? '' : $this->prefix).$table_name));
		while ($cur_index = $this->fetch_assoc($result))
		{
			if ($cur_index['Key_name'] == ($no_prefix ? '' : $this->prefix).$table_name.'_'.$index_name)
			{
				$exists = true;
				break;
			}
		}

		return $exists;
	}

	public function create_table($table_name, $schema, $no_prefix = false)
	{
		if ($this->table_exists($table_name, $no_prefix))
			return;

		$query = 'CREATE TABLE '.$this->quote_identifier(($no_prefix ? '' : $this->prefix).$table_name)." (\n";

		// Go through every schema element and add it to the query
		foreach ($schema['FIELDS'] as $field_name => $field_data)
		{
			$field_data['datatype'] = preg_replace(array_keys($this->datatype_transformations), array_values($this->datatype_transformations), $field_data['datatype']);

			$query .= $this->quote_identifier($field_name).' '.$field_data['datatype'];

			if (isset($field_data['collation']))
				$query .= 'CHARACTER SET utf8 COLLATE utf8_'.$field_data['collation'];

			if (!$field_data['allow_null'])
				$query .= ' NOT NULL';

			if (isset($field_data['default']))
				$query .= ' DEFAULT '.$field_data['default'];

			$query .= ",\n";
		}

		// If we have a primary key, add it
		if (isset($schema['PRIMARY KEY']))
			$query .= 'PRIMARY KEY ('.$this->quote_index_fields($schema['PRIMARY KEY']).'),'."\n";

		// Add unique keys
		if (isset($schema['UNIQUE KEYS']))
		{
			foreach ($schema['UNIQUE KEYS'] as $key_name => $key_fields)
				$query .= 'UNIQUE KEY '.$this->quote_identifier(($no_prefix ? '' : $this->prefix).$table_name.'_'.$key_name).'('.$this->quote_index_fields($key_fields).'),'."\n";
		}

		// Add indexes
		if (isset($schema['INDEXES']))
		{
			foreach ($schema['INDEXES'] as $index_name => $index_fields)
				$query .= 'KEY '.$this->quote_identifier(($no_prefix ? '' : $this->prefix).$table_name.'_'.$index_name).'('.$this->quote_index_fields($index_fields).'),'."\n";
		}

		// We remove the last two characters (a newline and a comma) and add on the ending
		$query = substr($query, 0, strlen($query) - 2)."\n".') ENGINE = '.(isset($schema['ENGINE']) ? $schema['ENGINE'] : 'InnoDB').' CHARACTER SET utf8';

		$this->query($query) or error(__FILE__, __LINE__);
	}

	public function drop_table($table_name, $no_prefix = false)
	{
		if (!$this->table_exists($table_name, $no_prefix))
			return;

		$this->query('DROP TABLE '.$this->quote_identifier(($no_prefix ? '' : $this->prefix).$table_name)) or error(__FILE__, __LINE__);
	}

	public function add_field($table_name, $field_name, $field_type, $allow_null, $default_value = null, $after_field = null, $no_prefix = false)
	{
		if ($this->field_exists($table_name, $field_name, $no_prefix))
			return;

		$field_type = preg_replace(array_keys($this->datatype_transformations), array_values($this->datatype_transformations), $field_type);

		if ($default_value !== null && !is_int($default_value) && !is_float($default_value))
			$default_value = '\''.$this->escape($default_value).'\'';

		$this->query('ALTER TABLE '.$this->quote_identifier(($no_prefix ? '' : $this->prefix).$table_name).' ADD '.$this->quote_identifier($field_name).' '.$field_type.($allow_null ? ' ' : ' NOT NULL').($default_value !== null ? ' DEFAULT '.$default_value : ' ').($after_field !== null ? ' AFTER '.$this->quote_identifier($after_field) : '')) or error(__FILE__, __LINE__);
	}

	public function alter_field($table_name, $field_name, $field_type, $allow_null, $default_value = null, $after_field = null, $no_prefix = false)
	{
		if (!$this->field_exists($table_name, $field_name, $no_prefix))
			return;

		$field_type = preg_replace(array_keys($this->datatype_transformations), array_values($this->datatype_transformations), $field_type);

		if ($default_value !== null && !is_int($default_value) && !is_float($default_value))
			$default_value = '\''.$this->escape($default_value).'\'';

		$this->query('ALTER TABLE '.$this->quote_identifier(($no_prefix ? '' : $this->prefix).$table_name).' MODIFY '.$this->quote_identifier($field_name).' '.$field_type.($allow_null ? ' ' : ' NOT NULL').($default_value !== null ? ' DEFAULT '.$default_value : ' ').($after_field !== null ? ' AFTER '.$this->quote_identifier($after_field) : '')) or error(__FILE__, __LINE__);
	}

	public function drop_field($table_name, $field_name, $no_prefix = false)
	{
		if (!$this->field_exists($table_name, $field_name, $no_prefix))
			return;

		$this->query('ALTER TABLE '.$this->quote_identifier(($no_prefix ? '' : $this->prefix).$table_name).' DROP '.$this->quote_identifier($field_name)) or error(__FILE__, __LINE__);
	}

	public function add_index($table_name, $index_name, $index_fields, $unique = false, $no_prefix = false)
	{
		if ($this->index_exists($table_name, $index_name, $no_prefix))
			return;

		$this->query('ALTER TABLE '.$this->quote_identifier(($no_prefix ? '' : $this->prefix).$table_name).' ADD '.($unique ? 'UNIQUE ' : '').'INDEX '.$this->quote_identifier(($no_prefix ? '' : $this->prefix).$table_name.'_'.$index_name).' ('.$this->quote_index_fields($index_fields).')') or error(__FILE__, __LINE__);
	}

	public function drop_index($table_name, $index_name, $no_prefix = false)
	{
		if (!$this->index_exists($table_name, $index_name, $no_prefix))
			return;

		$this->query('ALTER TABLE '.$this->quote_identifier(($no_prefix ? '' : $this->prefix).$table_name).' DROP INDEX '.$this->quote_identifier(($no_prefix ? '' : $this->prefix).$table_name.'_'.$index_name)) or error(__FILE__, __LINE__);
	}
}
