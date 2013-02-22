<?php

/*
  Plugin URI: http://miklo.hu/
  Description: Multi Database Caching Module
  Author: Geza Miklo
  Version: 1.60
  Author URI: http://miklo.hu/
  Text Domain: multidb-cache-hash
 */


/**
 * WordPress DB Class
 *
 * Original code from {@link http://php.justinvincent.com Justin Vincent (justin@visunet.ie)}
 *
 * @package WordPress
 * @subpackage Database
 * @since 0.71
 */
/*  Modifications Copyright 2009-2010  Daniel Frużyński  (email : daniel [A-T] poradnik-webmastera.com) 
  2011 Geza Miklo geza.miklo@gmail.com

  This program is free software; you can redistribute it and/or modify
  it under the terms of the GNU General Public License as published by
  the Free Software Foundation; either version 2 of the License, or
  (at your option) any later version.

  This program is distributed in the hope that it will be useful,
  but WITHOUT ANY WARRANTY; without even the implied warranty of
  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
  GNU General Public License for more details.

  You should have received a copy of the GNU General Public License
  along with this program; if not, write to the Free Software
  Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
 */
/**
 * @since 0.71
 */
define('EZSQL_VERSION', 'WP1.25');

/**
 * @since 0.71
 */
define('OBJECT', 'OBJECT', true);

/**
 * @since 2.5.0
 */
define('OBJECT_K', 'OBJECT_K');

/**
 * @since 0.71
 */
define('ARRAY_A', 'ARRAY_A');

/**
 * @since 0.71
 */
define('ARRAY_N', 'ARRAY_N');

global $current_site, $current_blog, $MDBC_optQueries;

/**
 * optimizations queries
 * 
 * @var array
 */
$MDBC_optQueries = array(
	'postmeta' => array(
		"CREATE INDEX meta_key_value ON %s (meta_key,meta_value(15))",
		"CREATE INDEX postidmetakeyvalue ON %s (post_id,  meta_key, meta_value(15))",
		"ANALYZE TABLE %s",
		"OPTIMIZE TABLE %s",
	),
	'options' => array(
		"CREATE INDEX autoloady ON %s (autoload)",
		"ANALYZE TABLE %s",
		"OPTIMIZE TABLE %s",
	),
);



// --- DB Cache Start ---
// wp-settings.php defines this after loading this file, so have to add it here too
if (!defined('WP_PLUGIN_DIR')) {
	define('WP_PLUGIN_DIR', WP_CONTENT_DIR . '/plugins'); // full path, no trailing slash
}
// Path to plugin
if (!defined('MDBC_PATH')) {
	define('MDBC_PATH', WP_PLUGIN_DIR . '/multidb-cache-hash');
}
// Cache directory
if (!defined('MDBC_CACHE_DIR')) {
	define('MDBC_CACHE_DIR', WP_CONTENT_DIR . '/mdb-cache');
}

// DB Module version (one or more digits for major, two digits for minor and revision numbers)
if (!defined('MDBC_DB_MODULE_VER')) {
	define('MDBC_DB_MODULE_VER', 10600);
}

// Check if we have required functions
if (!function_exists('is_multisite')) { // Added in WP 3.0

	function is_multisite() {
		return false;
	}

}

// --- DB Cache End ---


/**
 * WordPress Database Access Abstraction Object
 *
 * It is possible to replace this class with your own
 * by setting the $wpdb global variable in wp-content/db.php
 * file with your class. You can name it wpdb also, since
 * this file will not be included, if the other file is
 * available.
 *
 * @link http://codex.wordpress.org/Function_Reference/wpdb_Class
 *
 * @package WordPress
 * @subpackage Database
 * @since 0.71
 * @final
 */
if (!class_exists('wpdbDbCache')) {

	class wpdbDbCache extends wpdb {

		/**
		 * Whether to show SQL/DB errors
		 *
		 * @since 0.71
		 * @access private
		 * @var bool
		 */
		var $show_errors = false;

		/**
		 * Whether to suppress errors during the DB bootstrapping.
		 *
		 * @access private
		 * @since 2.5
		 * @var bool
		 */
		var $suppress_errors = false;

		/**
		 * The last error during query.
		 *
		 * @see get_last_error()
		 * @since 2.5
		 * @access private
		 * @var string
		 */
		var $last_error = '';

		/**
		 * Amount of queries made
		 *
		 * @since 1.2.0
		 * @access private
		 * @var int
		 */
		var $num_queries = 0;

		/**
		 * Count of rows returned by previous query
		 *
		 * @since 1.2
		 * @access private
		 * @var int
		 */
		var $num_rows = 0;

		/**
		 * Count of affected rows by previous query
		 *
		 * @since 0.71
		 * @access private
		 * @var int
		 */
		var $rows_affected = 0;

		/**
		 * The ID generated for an AUTO_INCREMENT column by the previous query (usually INSERT).
		 *
		 * @since 0.71
		 * @access public
		 * @var int
		 */
		var $insert_id = 0;

		/**
		 * Saved result of the last query made
		 *
		 * @since 1.2.0
		 * @access private
		 * @var array
		 */
		var $last_query;

		/**
		 * Results of the last query made
		 *
		 * @since 1.0.0
		 * @access private
		 * @var array|null
		 */
		var $last_result;

		/**
		 * Saved info on the table column
		 *
		 * @since 1.2.0
		 * @access private
		 * @var array
		 */
		var $col_info;

		/**
		 * Saved queries that were executed
		 *
		 * @since 1.5.0
		 * @access private
		 * @var array
		 */
		var $queries;

		/**
		 * WordPress table prefix
		 *
		 * You can set this to have multiple WordPress installations
		 * in a single database. The second reason is for possible
		 * security precautions.
		 *
		 * @since 0.71
		 * @access private
		 * @var string
		 */
		var $prefix = '';

		/**
		 * Whether the database queries are ready to start executing.
		 *
		 * @since 2.5.0
		 * @access private
		 * @var bool
		 */
		var $ready = false;

		/**
		 * {@internal Missing Description}}
		 *
		 * @since 3.0.0
		 * @access public
		 * @var int
		 */
		var $blogid = 0;

		/**
		 * {@internal Missing Description}}
		 *
		 * @since 3.0.0
		 * @access public
		 * @var int
		 */
		var $siteid = 0;

		/**
		 * List of WordPress per-blog tables
		 *
		 * @since 2.5.0
		 * @access private
		 * @see wpdb::tables()
		 * @var array
		 */
		var $tables = array('posts', 'comments', 'links', 'options', 'postmeta',
			'terms', 'term_taxonomy', 'term_relationships', 'commentmeta');

		/**
		 * List of deprecated WordPress tables
		 *
		 * categories, post2cat, and link2cat were deprecated in 2.3.0, db version 5539
		 *
		 * @since 2.9.0
		 * @access private
		 * @see wpdb::tables()
		 * @var array
		 */
		var $old_tables = array('categories', 'post2cat', 'link2cat');

		/**
		 * List of WordPress global tables
		 *
		 * @since 3.0.0
		 * @access private
		 * @see wpdb::tables()
		 * @var array
		 */
		var $global_tables = array('users', 'usermeta');

		/**
		 * List of Multisite global tables
		 *
		 * @since 3.0.0
		 * @access private
		 * @see wpdb::tables()
		 * @var array
		 */
		var $ms_global_tables = array('blogs', 'signups', 'site', 'sitemeta',
			'sitecategories', 'registration_log', 'blog_versions');

		/**
		 * WordPress Comments table
		 *
		 * @since 1.5.0
		 * @access public
		 * @var string
		 */
		var $comments;

		/**
		 * WordPress Comment Metadata table
		 *
		 * @since 2.9.0
		 * @access public
		 * @var string
		 */
		var $commentmeta;

		/**
		 * WordPress Links table
		 *
		 * @since 1.5.0
		 * @access public
		 * @var string
		 */
		var $links;

		/**
		 * WordPress Options table
		 *
		 * @since 1.5.0
		 * @access public
		 * @var string
		 */
		var $options;

		/**
		 * WordPress Post Metadata table
		 *
		 * @since 1.5.0
		 * @access public
		 * @var string
		 */
		var $postmeta;

		/**
		 * WordPress Posts table
		 *
		 * @since 1.5.0
		 * @access public
		 * @var string
		 */
		var $posts;

		/**
		 * WordPress Terms table
		 *
		 * @since 2.3.0
		 * @access public
		 * @var string
		 */
		var $terms;

		/**
		 * WordPress Term Relationships table
		 *
		 * @since 2.3.0
		 * @access public
		 * @var string
		 */
		var $term_relationships;

		/**
		 * WordPress Term Taxonomy table
		 *
		 * @since 2.3.0
		 * @access public
		 * @var string
		 */
		var $term_taxonomy;

		/*
		 * Global and Multisite tables
		 */

		/**
		 * WordPress User Metadata table
		 *
		 * @since 2.3.0
		 * @access public
		 * @var string
		 */
		var $usermeta;

		/**
		 * WordPress Users table
		 *
		 * @since 1.5.0
		 * @access public
		 * @var string
		 */
		var $users;

		/**
		 * Multisite Blogs table
		 *
		 * @since 3.0.0
		 * @access public
		 * @var string
		 */
		var $blogs;

		/**
		 * Multisite Blog Versions table
		 *
		 * @since 3.0.0
		 * @access public
		 * @var string
		 */
		var $blog_versions;

		/**
		 * Multisite Registration Log table
		 *
		 * @since 3.0.0
		 * @access public
		 * @var string
		 */
		var $registration_log;

		/**
		 * Multisite Signups table
		 *
		 * @since 3.0.0
		 * @access public
		 * @var string
		 */
		var $signups;

		/**
		 * Multisite Sites table
		 *
		 * @since 3.0.0
		 * @access public
		 * @var string
		 */
		var $site;

		/**
		 * Multisite Sitewide Terms table
		 *
		 * @since 3.0.0
		 * @access public
		 * @var string
		 */
		var $sitecategories;

		/**
		 * Multisite Site Metadata table
		 *
		 * @since 3.0.0
		 * @access public
		 * @var string
		 */
		var $sitemeta;

		/**
		 * Format specifiers for DB columns. Columns not listed here default to %s. Initialized during WP load.
		 *
		 * Keys are column names, values are format types: 'ID' => '%d'
		 *
		 * @since 2.8.0
		 * @see wpdb:prepare()
		 * @see wpdb:insert()
		 * @see wpdb:update()
		 * @see wp_set_wpdb_vars()
		 * @access public
		 * @var array
		 */
		var $field_types = array();

		/**
		 * Database table columns charset
		 *
		 * @since 2.2.0
		 * @access public
		 * @var string
		 */
		var $charset;

		/**
		 * Database table columns collate
		 *
		 * @since 2.2.0
		 * @access public
		 * @var string
		 */
		var $collate;

		/**
		 * Whether to use mysql_real_escape_string
		 *
		 * @since 2.8.0
		 * @access public
		 * @var bool
		 */
		var $real_escape = false;

		/**
		 * Database Username
		 *
		 * @since 2.9.0
		 * @access private
		 * @var string
		 */
		var $dbuser;

		/**
		 * A textual description of the last query/get_row/get_var call
		 *
		 * @since unknown
		 * @access public
		 * @var string
		 */
		var $func_call;
		// --- DB Cache Start ---
		/**
		 * Amount of all queries cached by MultiDB Cache Hash made
		 *
		 * @var int
		 */
		var $num_cachequeries = 0;

		/**
		 * Amount of DML queries
		 *
		 * @var int
		 */
		var $MDBC_num_dml_queries = 0;

		/**
		 * True if caching is active, otherwise false
		 *
		 * @var bool
		 */
		var $MDBC_cacheable = true;

		/**
		 * Array with MultiDB Cache Hash config
		 *
		 * @var array
		 */
		var $MDBC_config = null;

		/**
		 * MultiDB Cache Hash helper
		 *
		 * @var object of pcache
		 */
		var $MDBC_cache = null;

		/**
		 * True if MultiDB Cache Hash should show error in admin section
		 *
		 * @var bool
		 */
		var $MDBC_cachetype = 'n/a';
		var $MDBC_show_error = false;

		/**
		 * MultiDB Cache Hash DB module version
		 *
		 * @var int
		 */
		var $MDBC_version = MDBC_DB_MODULE_VER;

		/**
		 * MultiDB Cache Hash DB module version
		 *
		 * @var array
		 */
		static $MDBC_connectionPool = array();
		var $poolInitialized = false;
		var $lastCalcFoundRowsQuery = '';
		var $lastConnection = null;

		// --- DB Cache End ---

		/**
		 * Connects to the database server and selects a database
		 *
		 * PHP4 compatibility layer for calling the PHP5 constructor.
		 *
		 * @uses wpdb::__construct() Passes parameters and returns result
		 * @since 0.71
		 *
		 * @param string $dbuser MySQL database user
		 * @param string $dbpassword MySQL database password
		 * @param string $dbname MySQL database name
		 * @param string $dbhost MySQL database host
		 */
		function wpdb($dbuser, $dbpassword, $dbname, $dbhost) {
			return $this->__construct($dbuser, $dbpassword, $dbname, $dbhost);
		}

		/**
		 * Connects to the database server and selects a database
		 *
		 * PHP5 style constructor for compatibility with PHP5. Does
		 * the actual setting up of the class properties and connection
		 * to the database.
		 *
		 * @link http://core.trac.wordpress.org/ticket/3354
		 * @since 2.0.8
		 *
		 * @param string $dbuser MySQL database user
		 * @param string $dbpassword MySQL database password
		 * @param string $dbname MySQL database name
		 * @param string $dbhost MySQL database host
		 */
		function __construct($dbuser, $dbpassword, $dbname, $dbhost) {
			register_shutdown_function(array(&$this, '__destruct'));

			if (defined('WP_DEBUG') && WP_DEBUG)
				$this->show_errors();

			if (defined('DB_COLLATE') && DB_COLLATE)
				$this->collate = DB_COLLATE;
			else
				$this->collate = 'utf8_general_ci';

			if (defined('DB_CHARSET'))
				$this->charset = DB_CHARSET;

			$this->dbuser = $dbuser;

			wpdbDbCache::$MDBC_connectionPool[0] = $this->dbh = @mysql_connect($dbhost, $dbuser, $dbpassword, true);

			if (!$this->dbh) {
				$this->bail(sprintf(/* WP_I18N_DB_CONN_ERROR */"
<h1>Error establishing a database connection</h1>
<p>This either means that the username and password information in your <code>wp-config.php</code> file is incorrect or we can't contact the database server at <code>%s</code>. This could mean your host's database server is down.</p>
<ul>
	<li>Are you sure you have the correct username and password?</li>
	<li>Are you sure that you have typed the correct hostname?</li>
	<li>Are you sure that the database server is running?</li>
</ul>
<p>If you're unsure what these terms mean you should probably contact your host. If you still need help you can always visit the <a href='http://wordpress.org/support/'>WordPress Support Forums</a>.</p>
"/* /WP_I18N_DB_CONN_ERROR */, $dbhost), 'db_connect_fail');
				return;
			}

			$this->ready = true;

			if ($this->has_cap('collation') && !empty($this->charset)) {
				if (function_exists('mysql_set_charset')) {
					mysql_set_charset($this->charset, $this->dbh);
					$this->real_escape = true;
				} else {
					$query = $this->prepare('SET NAMES %s', $this->charset);
					if (!empty($this->collate))
						$query .= $this->prepare(' COLLATE %s', $this->collate);
					$this->MDBC_query($query, false);
				}
			}

			$this->select($dbname, $this->dbh);

			// --- DB Cache Start ---
			// Caching
			// require_once would be better, but some people deletes plugin without deactivating it first
			if (@include_once( MDBC_PATH . '/db-functions.php' )) {
				$this->MDBC_config = unserialize(@file_get_contents(WP_CONTENT_DIR . '/db-config.ini'));

//Build db connection pool getServer has to return an integer according to number of pool		
				if (is_file(WP_CONTENT_DIR . '/db-pool-config.php') && !$this->poolInitialized) {
					$this->MDBC_poolConfig = include(WP_CONTENT_DIR . '/db-pool-config.php');
					if (count($this->MDBC_poolConfig) > 0) {
						unset($this->MDBC_poolConfig[0]);
						foreach ($this->MDBC_poolConfig as $ix => $server) {
							unset($conn);
							$conn = mysql_connect($server['host'], $server['user'], $server['pass'], true) or die(mysql_error());
							if (!isset(wpdbDbCache::$MDBC_connectionPool[$ix])
									&& (false !== $conn)
									&& mysql_select_db($server['name'], $conn)
							) {
								if ($this->has_cap('collation') && !empty($this->charset)) {
									if (function_exists('mysql_set_charset')) {
										mysql_set_charset($this->charset, $conn);
										$this->real_escape = true;
									} else {
										$query = $this->prepare('SET NAMES %s', $this->charset);
										if (!empty($this->collate))
											$query .= $this->prepare(' COLLATE %s', $this->collate);
										mysql_query($query, $conn);
									}
								}

								//mysql_select_db( $dbname, $conn ) or die('Cannot select ' . $dbname . ' ' . mysql_error($conn));
								wpdbDbCache::$MDBC_connectionPool[$ix] = &$conn;
							}
							else {
								error_log("#" . $ix . ' ' . implode(' :: ', $server) . " not reacheable");
								if (!is_admin()) {
									die('Az oldal átmenetileg nem elérhető. Az adatbázis-kapcsolat nem jött létre.');
								} else {
									echo '<h4>Az oldal átmenetileg nem elérhető. Az adatbázis-kapcsolat pool nem jött létre.</h4>';
								}
							}
							$this->poolInitialized = true;
						}
					}
				}

				if (!empty($this->MDBC_config['memcache_servers'])) {
					foreach ($this->MDBC_config['memcache_servers'] as $ix => $server) {
						if (!(int) $server['port'] || empty($server['host'])) {
							unset($this->MDBC_config['memcache_servers'][$ix]);
						}
					}
				}
				if ($this->MDBC_config['use_memcache'] && !empty($this->MDBC_config['memcache_servers'])) {
					$this->MDBC_cache = &new mdbCache($this->MDBC_config['memcache_servers']);
				}

				if (false == $this->MDBC_cache->ok || null == $this->MDBC_cache) {
					$this->MDBC_cache = &new mdbCache();
				}

				$this->MDBC_cachetype = $this->MDBC_cache->cachetype;

				$this->MDBC_cache->lifetime = isset($this->MDBC_config['timeout']) ? $this->MDBC_config['timeout'] : 5;

				// Clean unused every 4 hours and clean expired files! With memcache it is not needed
				$dbcheck = date('G') / 4;
				if (
					$dbcheck == intval($dbcheck) && 
					( filemtime(WP_CONTENT_DIR . '/db-config.ini') < time() - 3600 ) &&
					($this->MDBC_cachetype == "File")
				) 
				{
					$this->MDBC_cache->clean();
					touch(WP_CONTENT_DIR . '/db-config.ini');
				}

				// cache only frontside
				if (
						( defined('WP_ADMIN') && WP_ADMIN ) ||
						( defined('DOING_CRON') && DOING_CRON ) ||
						( defined('DOING_AJAX') && DOING_AJAX ) ||
						strpos($_SERVER['REQUEST_URI'], 'wp-admin') ||
						strpos($_SERVER['REQUEST_URI'], 'wp-login') ||
						strpos($_SERVER['REQUEST_URI'], 'wp-register') ||
						strpos($_SERVER['REQUEST_URI'], 'wp-signup')
				) {
					$this->MDBC_cacheable = false;
				}
			} else { // Cannot include db-functions.php
				$this->MDBC_cacheable = false;
				$this->MDBC_show_error = true;
			}
			// --- DB Cache End ---
		}

		/**
		 * PHP5 style destructor and will run when database object is destroyed.
		 *
		 * @see wpdb::__construct()
		 * @since 2.0.8
		 * @return bool true
		 */
		function __destruct() {
//		if (count($this->connectionPool))
//		{
//			foreach ($this->connectionPool as &$conn)
//			{
//				mysql_close($conn);
//			}
//		}
			return true;
		}

		/**
		 * Sets the table prefix for the WordPress tables.
		 *
		 * @since 2.5.0
		 *
		 * @param string $prefix Alphanumeric name for the new prefix.
		 * @return string|WP_Error Old prefix or WP_Error on error
		 */
		function set_prefix($prefix, $set_table_names = true) {

			if (preg_match('|[^a-z0-9_]|i', $prefix))
				return new WP_Error('invalid_db_prefix', /* WP_I18N_DB_BAD_PREFIX */'Invalid database prefix'/* /WP_I18N_DB_BAD_PREFIX */);

			$old_prefix = is_multisite() ? '' : $prefix;

			if (isset($this->base_prefix))
				$old_prefix = $this->base_prefix;

			$this->base_prefix = $prefix;

			if ($set_table_names) {
				foreach ($this->tables('global') as $table => $prefixed_table)
					$this->$table = $prefixed_table;

				if (is_multisite() && empty($this->blogid))
					return $old_prefix;

				$this->prefix = $this->get_blog_prefix();

				foreach ($this->tables('blog') as $table => $prefixed_table)
					$this->$table = $prefixed_table;

				foreach ($this->tables('old') as $table => $prefixed_table)
					$this->$table = $prefixed_table;
			}
			return $old_prefix;
		}

		/**
		 * Sets blog id.
		 *
		 * @since 3.0.0
		 * @access public
		 * @param int $blog_id
		 * @param int $site_id Optional.
		 * @return string previous blog id
		 */
		function set_blog_id($blog_id, $site_id = 0) {
			if (!empty($site_id))
				$this->siteid = $site_id;

			$old_blog_id = $this->blogid;
			$this->blogid = $blog_id;

			$this->prefix = $this->get_blog_prefix();

			foreach ($this->tables('blog') as $table => $prefixed_table)
				$this->$table = $prefixed_table;

			foreach ($this->tables('old') as $table => $prefixed_table)
				$this->$table = $prefixed_table;

			return $old_blog_id;
		}

		/**
		 * Gets blog prefix.
		 *
		 * @uses is_multisite()
		 * @since 3.0.0
		 * @param int $blog_id Optional.
		 * @return string Blog prefix.
		 */
		function get_blog_prefix($blog_id = null) {
			if (is_multisite()) {
				if (null === $blog_id)
					$blog_id = $this->blogid;
				if (defined('MULTISITE') && ( 0 == $blog_id || 1 == $blog_id ))
					return $this->base_prefix;
				else
					return $this->base_prefix . $blog_id . '_';
			} else {
				return $this->base_prefix;
			}
		}

		/**
		 * Returns an array of WordPress tables.
		 *
		 * Also allows for the CUSTOM_USER_TABLE and CUSTOM_USER_META_TABLE to
		 * override the WordPress users and usersmeta tables that would otherwise
		 * be determined by the prefix.
		 *
		 * The scope argument can take one of the following:
		 *
		 * 'all' - returns 'all' and 'global' tables. No old tables are returned.
		 * 'blog' - returns the blog-level tables for the queried blog.
		 * 'global' - returns the global tables for the installation, returning multisite tables only if running multisite.
		 * 'ms_global' - returns the multisite global tables, regardless if current installation is multisite.
		 * 'old' - returns tables which are deprecated.
		 *
		 * @since 3.0.0
		 * @uses wpdb::$tables
		 * @uses wpdb::$old_tables
		 * @uses wpdb::$global_tables
		 * @uses wpdb::$ms_global_tables
		 * @uses is_multisite()
		 *
		 * @param string $scope Optional. Can be all, global, ms_global, blog, or old tables. Defaults to all.
		 * @param bool $prefix Optional. Whether to include table prefixes. Default true. If blog
		 * 	prefix is requested, then the custom users and usermeta tables will be mapped.
		 * @param int $blog_id Optional. The blog_id to prefix. Defaults to wpdb::$blogid. Used only when prefix is requested.
		 * @return array Table names. When a prefix is requested, the key is the unprefixed table name.
		 */
		function tables($scope = 'all', $prefix = true, $blog_id = 0) {
			switch ($scope) {
				case 'all' :
					$tables = array_merge($this->global_tables, $this->tables);
					if (is_multisite())
						$tables = array_merge($tables, $this->ms_global_tables);
					break;
				case 'blog' :
					$tables = $this->tables;
					break;
				case 'global' :
					$tables = $this->global_tables;
					if (is_multisite())
						$tables = array_merge($tables, $this->ms_global_tables);
					break;
				case 'ms_global' :
					$tables = $this->ms_global_tables;
					break;
				case 'old' :
					$tables = $this->old_tables;
					break;
				default :
					return array();
					break;
			}

			if ($prefix) {
				if (!$blog_id)
					$blog_id = $this->blogid;
				$blog_prefix = $this->get_blog_prefix($blog_id);
				$base_prefix = $this->base_prefix;
				$global_tables = array_merge($this->global_tables, $this->ms_global_tables);
				foreach ($tables as $k => $table) {
					if (in_array($table, $global_tables))
						$tables[$table] = $base_prefix . $table;
					else
						$tables[$table] = $blog_prefix . $table;
					unset($tables[$k]);
				}

				if (isset($tables['users']) && defined('CUSTOM_USER_TABLE'))
					$tables['users'] = CUSTOM_USER_TABLE;

				if (isset($tables['usermeta']) && defined('CUSTOM_USER_META_TABLE'))
					$tables['usermeta'] = CUSTOM_USER_META_TABLE;
			}

			return $tables;
		}

		/**
		 * Selects a database using the current database connection.
		 *
		 * The database name will be changed based on the current database
		 * connection. On failure, the execution will bail and display an DB error.
		 *
		 * @since 0.71
		 *
		 * @param string $db MySQL database name
		 * @param resource $dbh Optional link identifier.
		 * @return null Always null.
		 */
		function select($db, $dbh = null) {
			if (is_null($dbh))
				$dbh = $this->dbh;

			if (!@mysql_select_db($db, $dbh)) {
				$this->ready = false;
				$this->bail(sprintf(/* WP_I18N_DB_SELECT_DB */'
<h1>Can&#8217;t select database</h1>
<p>We were able to connect to the database server (which means your username and password is okay) but not able to select the <code>%1$s</code> database.</p>
<ul>
<li>Are you sure it exists?</li>
<li>Does the user <code>%2$s</code> have permission to use the <code>%1$s</code> database?</li>
<li>On some systems the name of your database is prefixed with your username, so it would be like <code>username_%1$s</code>. Could that be the problem?</li>
</ul>
<p>If you don\'t know how to set up a database you should <strong>contact your host</strong>. If all else fails you may find help at the <a href="http://wordpress.org/support/">WordPress Support Forums</a>.</p>'/* /WP_I18N_DB_SELECT_DB */, $db, $this->dbuser), 'db_select_fail');
				return;
			}
		}

		/**
		 * Weak escape, using addslashes()
		 *
		 * @see addslashes()
		 * @since 2.8.0
		 * @access private
		 *
		 * @param string $string
		 * @return string
		 */
		function _weak_escape($string) {
			return addslashes($string);
		}

		/**
		 * Real escape, using mysql_real_escape_string() or addslashes()
		 *
		 * @see mysql_real_escape_string()
		 * @see addslashes()
		 * @since 2.8
		 * @access private
		 *
		 * @param  string $string to escape
		 * @return string escaped
		 */
		function _real_escape($string) {
			if ($this->dbh && $this->real_escape)
				return mysql_real_escape_string($string, $this->dbh);
			else
				return addslashes($string);
		}

		/**
		 * Escape data. Works on arrays.
		 *
		 * @uses wpdb::_escape()
		 * @uses wpdb::_real_escape()
		 * @since  2.8
		 * @access private
		 *
		 * @param  string|array $data
		 * @return string|array escaped
		 */
		function _escape($data) {
			if (is_array($data)) {
				foreach ((array) $data as $k => $v) {
					if (is_array($v))
						$data[$k] = $this->_escape($v);
					else
						$data[$k] = $this->_real_escape($v);
				}
			} else {
				$data = $this->_real_escape($data);
			}

			return $data;
		}

		/**
		 * Escapes content for insertion into the database using addslashes(), for security.
		 *
		 * Works on arrays.
		 *
		 * @since 0.71
		 * @param string|array $data to escape
		 * @return string|array escaped as query safe string
		 */
		function escape($data) {
			if (is_array($data)) {
				foreach ((array) $data as $k => $v) {
					if (is_array($v))
						$data[$k] = $this->escape($v);
					else
						$data[$k] = $this->_weak_escape($v);
				}
			} else {
				$data = $this->_weak_escape($data);
			}

			return $data;
		}

		/**
		 * Escapes content by reference for insertion into the database, for security
		 *
		 * @uses wpdb::_real_escape()
		 * @since 2.3.0
		 * @param string $string to escape
		 * @return void
		 */
		function escape_by_ref(&$string) {
			$string = $this->_real_escape($string);
		}

		/**
		 * Prepares a SQL query for safe execution. Uses sprintf()-like syntax.
		 *
		 * The following directives can be used in the query format string:
		 *   %d (decimal number)
		 *   %s (string)
		 *   %% (literal percentage sign - no argument needed)
		 *
		 * Both %d and %s are to be left unquoted in the query string and they need an argument passed for them.
		 * Literals (%) as parts of the query must be properly written as %%.
		 *
		 * This function only supports a small subset of the sprintf syntax; it only supports %d (decimal number), %s (string).
		 * Does not support sign, padding, alignment, width or precision specifiers.
		 * Does not support argument numbering/swapping.
		 *
		 * May be called like {@link http://php.net/sprintf sprintf()} or like {@link http://php.net/vsprintf vsprintf()}.
		 *
		 * Both %d and %s should be left unquoted in the query string.
		 *
		 * <code>
		 * wpdb::prepare( "SELECT * FROM `table` WHERE `column` = %s AND `field` = %d", 'foo', 1337 )
		 * wpdb::prepare( "SELECT DATE_FORMAT(`field`, '%%c') FROM `table` WHERE `column` = %s", 'foo' );
		 * </code>
		 *
		 * @link http://php.net/sprintf Description of syntax.
		 * @since 2.3.0
		 *
		 * @param string $query Query statement with sprintf()-like placeholders
		 * @param array|mixed $args The array of variables to substitute into the query's placeholders if being called like
		 * 	{@link http://php.net/vsprintf vsprintf()}, or the first variable to substitute into the query's placeholders if
		 * 	being called like {@link http://php.net/sprintf sprintf()}.
		 * @param mixed $args,... further variables to substitute into the query's placeholders if being called like
		 * 	{@link http://php.net/sprintf sprintf()}.
		 * @return null|false|string Sanitized query string, null if there is no query, false if there is an error and string
		 * 	if there was something to prepare
		 */
		function prepare($query = null) { // ( $query, *$args )
			if (is_null($query))
				return;

			$args = func_get_args();
			array_shift($args);
			// If args were passed as an array (as in vsprintf), move them up
			if (isset($args[0]) && is_array($args[0]))
				$args = $args[0];
			$query = str_replace("'%s'", '%s', $query); // in case someone mistakenly already singlequoted it
			$query = str_replace('"%s"', '%s', $query); // doublequote unquoting
			$query = preg_replace('|(?<!%)%s|', "'%s'", $query); // quote the strings, avoiding escaped strings like %%s
			array_walk($args, array(&$this, 'escape_by_ref'));
			return @vsprintf($query, $args);
		}

		/**
		 * Print SQL/DB error.
		 *
		 * @since 0.71
		 * @global array $EZSQL_ERROR Stores error information of query and error string
		 *
		 * @param string $str The error to display
		 * @return bool False if the showing of errors is disabled.
		 */
		function print_error($str = '') {
			global $EZSQL_ERROR;

			if (!$str)
				$str = mysql_error($this->dbh);
			$EZSQL_ERROR[] = array('query' => $this->last_query, 'error_str' => $str);

			if ($this->suppress_errors)
				return false;

			if ($caller = $this->get_caller())
				$error_str = sprintf(/* WP_I18N_DB_QUERY_ERROR_FULL */'WordPress database error %1$s for query %2$s made by %3$s'/* /WP_I18N_DB_QUERY_ERROR_FULL */, $str, $this->last_query, $caller);
			else
				$error_str = sprintf(/* WP_I18N_DB_QUERY_ERROR */'WordPress database error %1$s for query %2$s'/* /WP_I18N_DB_QUERY_ERROR */, $str, $this->last_query);

			if (function_exists('error_log')
					&& ( $log_file = @ini_get('error_log') )
					&& ( 'syslog' == $log_file || @is_writable($log_file) )
			)
				@error_log($error_str);

			// Are we showing errors?
			if (!$this->show_errors)
				return false;

			// If there is an error then take note of it
			if (is_multisite()) {
				$msg = "WordPress database error: [$str]\n{$this->last_query}\n";
				if (defined('ERRORLOGFILE'))
					error_log($msg, 3, ERRORLOGFILE);
				if (defined('DIEONDBERROR'))
					wp_die($msg);
			} else {
				$str = htmlspecialchars($str, ENT_QUOTES);
				$query = htmlspecialchars($this->last_query, ENT_QUOTES);

				print "<div id='error'>
			<p class='wpdberror'><strong>WordPress database error:</strong> [$str]<br />
			<code>$query</code></p>
			</div>";
			}
		}

		/**
		 * Enables showing of database errors.
		 *
		 * This function should be used only to enable showing of errors.
		 * wpdb::hide_errors() should be used instead for hiding of errors. However,
		 * this function can be used to enable and disable showing of database
		 * errors.
		 *
		 * @since 0.71
		 * @see wpdb::hide_errors()
		 *
		 * @param bool $show Whether to show or hide errors
		 * @return bool Old value for showing errors.
		 */
		function show_errors($show = true) {
			$errors = $this->show_errors;
			$this->show_errors = $show;
			return $errors;
		}

		/**
		 * Disables showing of database errors.
		 *
		 * By default database errors are not shown.
		 *
		 * @since 0.71
		 * @see wpdb::show_errors()
		 *
		 * @return bool Whether showing of errors was active
		 */
		function hide_errors() {
			$show = $this->show_errors;
			$this->show_errors = false;
			return $show;
		}

		/**
		 * Whether to suppress database errors.
		 *
		 * By default database errors are suppressed, with a simple
		 * call to this function they can be enabled.
		 *
		 * @since 2.5
		 * @see wpdb::hide_errors()
		 * @param bool $suppress Optional. New value. Defaults to true.
		 * @return bool Old value
		 */
		function suppress_errors($suppress = true) {
			$errors = $this->suppress_errors;
			$this->suppress_errors = (bool) $suppress;
			return $errors;
		}

		/**
		 * Kill cached query results.
		 *
		 * @since 0.71
		 * @return void
		 */
		function flush() {
			$this->last_result = array();
			$this->col_info = null;
			$this->last_query = null;
		}

		function db_connect($query = "SELECT") {
			global $db_list, $global_db_list;
			if (!is_array($db_list))
				return true;

			if ($this->blogs != '' && preg_match("/(" . $this->blogs . "|" . $this->users . "|" . $this->usermeta . "|" . $this->site . "|" . $this->sitemeta . "|" . $this->sitecategories . ")/i", $query)) {
				$action = 'global';
				$details = $global_db_list[mt_rand(0, count($global_db_list) - 1)];
				$this->db_global = $details;
			} elseif (preg_match("/^\\s*(alter table|create|insert|delete|update|replace) /i", $query)) {
				$action = 'write';
				$details = $db_list['write'][mt_rand(0, count($db_list['write']) - 1)];
				$this->db_write = $details;
			} else {
				$action = '';
				$details = $db_list['read'][mt_rand(0, count($db_list['read']) - 1)];
				$this->db_read = $details;
			}

			$dbhname = "dbh" . $action;
			$this->$dbhname = @mysql_connect($details['db_host'], $details['db_user'], $details['db_password']);
			if (!$this->$dbhname) {
				$this->bail(sprintf(/* WP_I18N_DB_CONN_ERROR */"
<h1>Error establishing a database connection</h1>
<p>This either means that the username and password information in your <code>wp-config.php</code> file is incorrect or we can't contact the database server at <code>%s</code>. This could mean your host's database server is down.</p>
<ul>
	<li>Are you sure you have the correct username and password?</li>
	<li>Are you sure that you have typed the correct hostname?</li>
	<li>Are you sure that the database server is running?</li>
</ul>
<p>If you're unsure what these terms mean you should probably contact your host. If you still need help you can always visit the <a href='http://wordpress.org/support/'>WordPress Support Forums</a>.</p>
"/* /WP_I18N_DB_CONN_ERROR */, $details['db_host']), 'db_connect_fail');
			}
			$this->select($details['db_name'], $this->$dbhname);
		}

		/**
		 * Perform a MySQL database query, using current database connection.
		 *
		 * More information can be found on the codex page.
		 *
		 * @since 0.71
		 *
		 * @param string $query Database query
		 * @return int|false Number of rows affected/selected or false on error
		 */
		function query($query) {
			return $this->MDBC_query($query, true);
		}

		function MDBC_query($query, $maybe_cache = true) {
			if (!$this->ready)
				return false;


			global $current_blog;

			$getFoundRows = false;

			// some queries are made before the plugins have been loaded, and thus cannot be filtered with this method
			if (function_exists('apply_filters'))
				$query = apply_filters('query', $query);

			$return_val = 0;

			//Clean up
			$this->flush();

			// Log how the function was called
			$this->func_call = "\$db->query(\"$query\")";

			// Keep track of the last query for debug..
			$this->last_query = $query;

			// Perform the query via std mysql_query function..
			if (( defined('SAVEQUERIES') && SAVEQUERIES ) || ( defined('MDBC_SAVEQUERIES') && MDBC_SAVEQUERIES ))
				$this->timer_start();

			//Holds the db connection for query -> have to find out which server
			unset($dbh);

			//Blog is determines the serverindex to use from pool if a pool is used
			//Calculating blog_id
			$blog_id = 0;

			//Cut the number from the end of table prefix
			$basePrefix = preg_replace('/_\d+_$/', '', $this->prefix);
			//echo '<pre>'.$basePrefix.'</pre>';
			//Looking for a prefixes table in query if any
			if (preg_match('/^.*(TABLE|FROM|INTO).+' . $basePrefix . '(_[0-9A-Za-z_]+)\b/Ui', $query, $matches)) {
				//echo "found by query";
				$matches[2] = preg_replace('/[^0-9]/', '', $matches[2]);
				$blog_id = strlen($matches[2]) ? $matches[2] : 1;
			}
			//If query did not hold any blog_id info, look at the prefix
			elseif (preg_match('/^.+_(\d+)_/Ui', $this->prefix, $matches)) {
				//echo "found by prefix";
				$blog_id = $matches[1];
			}
			//At a last chance we look for it in $current_blog global variable
			if (empty($blog_id)) {
				//echo "found by current_blog";
				$blog_id = $current_blog->blog_id;
			}
			//If nothing found this is the main blog
			if (empty($blog_id))
				$blog_id = defined('BLOG_ID_CURRENT_SITE') ? BLOG_ID_CURRENT_SITE : 1;


			$serverIndex = $this->getDbServer($blog_id);
			//echo '<pre>'.$blog_id. ' @ ' . $serverIndex . '</pre><hr />';
			//Retrieve the appropriate connection for blog's table
			//Check if in pool the index exists (NOTE: 0 always exists)
			//NOTE: pool can be configured in wp-content/db-pool-config.php sample in the plugins folder
			if (!isset(wpdbDbCache::$MDBC_connectionPool[$serverIndex])) {
				$serverIndex = 0;
			}
			$dbh = &wpdbDbCache::$MDBC_connectionPool[$serverIndex]; //set $dbh to the calculated connection	


			$this->last_db_used = "other/read";

			// DB Cache Start
			$MDBC_db = 'global';

			// Caching
			$MDBC_cacheable = false;
			// check if pcache object is in place
			$iv = '1';
			if (!is_null($this->MDBC_cache)) {
				$MDBC_cacheable = $this->MDBC_cacheable && $maybe_cache;

				$getFoundRows = false;

				//Transform SQL_CALC_FOUND_ROWS query if possible
				//GROUP BY queries cannot be converted securely in an automatic way to SELECT count(*)
				if (
						(mb_stripos($query, 'SQL_CALC_FOUND_ROWS') !== false) 
						&& (mb_stripos($query, 'GROUP BY') === false)
					)
				{
					$iv .= "<hr />Transformable CALC query<hr />";
					$query = str_replace(array("\n", "\r"), " ", $query);
					$query = preg_replace('/SQL_CALC_FOUND_ROWS/i', '', $query);
					$queryFR = preg_replace('/^.*\sFROM\s/Ui', 'SELECT COUNT(*) FROM ', $query);
					$queryFR = preg_replace('/ORDER BY.*/', '', $queryFR);
					$this->lastCalcFoundRowsQuery = $queryFR;
					//Save last connection because SELECT FOUND_ROWS() does not contain blog_id info
					$this->lastConnection = $dbh;

					$getFoundRows = true;
				}

				//FOUNDS_ROWS ... GROUP BY queries cannot be converted securely in an automatic way to SELECT count(*)
				if ((mb_stripos($query, 'FOUND_ROWS()') !== false)
						&& !empty($this->lastCalcFoundRowsQuery)
						&& (mb_stripos($this->lastCalcFoundRowsQuery, 'GROUP BY') == false)
				) {
					if (preg_match('/as\s+([^\s]+)/i', $query, $matches)) {
						$query = str_replace('COUNT(*)', 'COUNT(*) as ' . $matches[1], $this->lastCalcFoundRowsQuery);
						//echo $query . ' - ' . $this->lastCalcFoundRowsQuery;
					}
					else
					{
						$query = $this->lastCalcFoundRowsQuery;
					}
					
					$iv .= '<hr />Transformable FOUND_ROWS() query<hr />';

					$dbh = $this->lastConnection;
					$getFoundRows = true;					
				}

				//DETECT if query is cacheable

				/*
				 * NOTE: FOUND_ROWS() cannot be cached, 
				 * 		IF IT HAS BEEN CONVERTED earlier it will not match for FOUND_ROWS
				 */
				if ($MDBC_cacheable) {
					// do not cache non-select and special queries like SELECT FOUND_ROWS();
					//If this is a getFoundRows query it can be cached
					if (preg_match("/\\s*(create|SHOW|insert|delete|update|replace|alter|SET NAMES|FOUND_ROWS|SQL_CALC_FOUND_ROWS|RAND|doing_cron|cron)\\b/sui", $query)
					) {
						$MDBC_cacheable = false;
					}
					// for User-defined cache filters skip cache
					elseif (
							( isset($config['filter']) && ( trim($config['filter']) != '' ) &&
							preg_match("/\\s*(" . $config['filter'] . ")/si", $query))) {
						$MDBC_cacheable = false;
					}
				}

				if (is_admin())
					$MDBC_cacheable = false;
					
				if (
						( defined('WP_ADMIN') && WP_ADMIN ) ||
						( defined('DOING_CRON') && DOING_CRON ) ||
						( defined('DOING_AJAX') && DOING_AJAX ) ||
						strpos($_SERVER['REQUEST_URI'], 'wp-admin') ||
						strpos($_SERVER['REQUEST_URI'], 'wp-login') ||
						strpos($_SERVER['REQUEST_URI'], 'wp-register') ||
						strpos($_SERVER['REQUEST_URI'], 'wp-signup')
				) {
					$this->MDBC_cacheable = $MDBC_cacheable = false;
				}

				if ($MDBC_cacheable) {
					$MDBC_queryid = md5($query);

					if (strpos($query, '_options')) {
						$this->MDBC_cache->set_storage('options');
					} elseif (strpos($query, '_links')) {
						$this->MDBC_cache->set_storage('links');
					} elseif (strpos($query, '_terms')) {
						$this->MDBC_cache->set_storage('terms');
					} elseif (strpos($query, '_user')) {
						$this->MDBC_cache->set_storage('users');
					} elseif (strpos($query, '_comment')) {
						$this->MDBC_cache->set_storage('comments');
					} elseif (strpos($query, '_post')) {
						$this->MDBC_cache->set_storage('posts');
					} else {
						$this->MDBC_cache->set_storage('');
					}
				}

				/* Debug part */
				if (isset($config['debug']) && $config['debug']) {
					if ($MDBC_cacheable) {
						echo "\n<!-- cache: $query -->\n\n";
					} else {
						echo "\n<!-- mysql: $query -->\n\n";
					}
				}
			} elseif ($this->MDBC_show_error) {
				$this->MDBC_show_error = false;
				add_action('admin_notices', array(&$this, '_MDBC_admin_notice'));
			}

			$MDBC_cached = false;

			if ($MDBC_cacheable && empty($_REQUEST['ctimedout'])) {
				// Try to load cached query
				$MDBC_cached = $this->MDBC_cache->load($MDBC_queryid);
			}

			if ($MDBC_cached !== false) {
				// Extract cached query
				++$this->num_cachequeries;
				$this->queries[] = array('cached' => $query, 'iv' => $iv);


				$MDBC_cached = unserialize($MDBC_cached);
				$this->last_error = '';
				$this->last_query = $MDBC_cached['last_query'];
				$this->last_result = $MDBC_cached['last_result'];
				$this->col_info = $MDBC_cached['col_info'];
				$this->num_rows = $MDBC_cached['num_rows'];

				$return_val = $this->num_rows;

				if (defined('MDBC_SAVEQUERIES') && MDBC_SAVEQUERIES) {
					$this->queries[] = array($query, $this->timer_stop(), $this->get_caller(), true);
				}
			} else {
				// Cache not found or query is not cacheable, perform query as usual

				$this->result = @mysql_query($query, $dbh);

				//In replication mode execute queries on all servers except the calculated one, because it has benn done above
				if (
						(!defined(MDBC_POOL_MODE) || MDBC_POOL_MODE == MDBC_REPLICATE )
						&& (preg_match("/^\\s*(create|insert|delete|update|replace|alter)\\b/si", $query))
				) {
					foreach (wpdbDbCache::$MDBC_connectionPool as $sI => $conn) {
						if ($serverIndex != $sI) {
							mysql_query($query, $conn);
						}
					}
				}
				$this->num_queries++;
				$this->queries[] = array('noncached' => $query, 'iv' => $iv);

				if (defined('MDBC_SAVEQUERIES') && MDBC_SAVEQUERIES)
					$this->queries[] = array($query, $this->timer_stop(), $this->get_caller(), false);
				elseif (defined('SAVEQUERIES') && SAVEQUERIES)
					$this->queries[] = array($query, $this->timer_stop(), $this->get_caller());
			}
			if (!$getFoundRows)
				$this->lastCalcFoundRowsQuery = '';
			// If there is an error then take note of it..
			if ($this->last_error = mysql_error($dbh)) {
				$this->print_error();
				return false;
			}


			/*
			 * BEGIN - Table index optimizations
			 */
			global $MDBC_optQueries;
			if (preg_match('/^\\s*create\\s+table\\s+(\\w+)/i', $query, $matches)) {
				if (!empty($matches[1])) {
					$tableName = $matches[1];
					foreach ($MDBC_optQueries as $tablepattern => $queries) {
						if (strpos($tableName, $tablepattern)) {
							foreach ($queries as $q) {
								$qRun = sprintf($q, $tableName);

								mysql_query($qRun, $dbh);
								if ($this->last_error = mysql_error($dbh)) {
									$this->print_error();
									return false;
								}
							}
						}
					}
				}
			}
			/*
			 * END - Table index optimizations
			 */


			if (preg_match("/^\\s*(insert|delete|update|replace|alter) /i", $query)) {
				$this->rows_affected = mysql_affected_rows($dbh);
				// Take note of the insert_id
				if (preg_match("/^\\s*(insert|replace) /i", $query)) {
					$this->insert_id = mysql_insert_id($dbh);
				}
				// Return number of rows affected
				$return_val = $this->rows_affected;

				// --- DB Cache Start ---
				++$this->MDBC_num_dml_queries;
				// --- DB Cache End ---
			} else {
				$i = 0;
				while ($i < @mysql_num_fields($this->result)) {
					$this->col_info[$i] = @mysql_fetch_field($this->result);
					$i++;
				}
				$num_rows = 0;
				while ($row = @mysql_fetch_object($this->result)) {
					$this->last_result[$num_rows] = $row;
					$num_rows++;
				}

				@mysql_free_result($this->result);

				// Log number of rows the query returned
				// and return number of rows selected
				$this->num_rows = $num_rows;
				$return_val = $num_rows;

				// --- DB Cache Start ---
				if ($MDBC_cacheable && ( $MDBC_cached === false )) {
					$MDBC_cached = serialize(array(
						'last_query' => $this->last_query,
						'last_result' => $this->last_result,
						'col_info' => $this->col_info,
						'num_rows' => $this->num_rows,
							));
					$this->MDBC_cache->save($MDBC_cached, $MDBC_queryid);
				}
				// --- DB Cache End ---
			}

			return $return_val;
		}

		/**
		 * Insert a row into a table.
		 *
		 * <code>
		 * wpdb::insert( 'table', array( 'column' => 'foo', 'field' => 'bar' ) )
		 * wpdb::insert( 'table', array( 'column' => 'foo', 'field' => 1337 ), array( '%s', '%d' ) )
		 * </code>
		 *
		 * @since 2.5.0
		 * @see wpdb::prepare()
		 * @see wpdb::$field_types
		 * @see wp_set_wpdb_vars()
		 *
		 * @param string $table table name
		 * @param array $data Data to insert (in column => value pairs).  Both $data columns and $data values should be "raw" (neither should be SQL escaped).
		 * @param array|string $format Optional. An array of formats to be mapped to each of the value in $data. If string, that format will be used for all of the values in $data.
		 * 	A format is one of '%d', '%s' (decimal number, string). If omitted, all values in $data will be treated as strings unless otherwise specified in wpdb::$field_types.
		 * @return int|false The number of rows inserted, or false on error.
		 */
		function insert($table, $data, $format = null) {
			return $this->_insert_replace_helper($table, $data, $format, 'INSERT');
		}

		/**
		 * Replace a row into a table.
		 *
		 * <code>
		 * wpdb::replace( 'table', array( 'column' => 'foo', 'field' => 'bar' ) )
		 * wpdb::replace( 'table', array( 'column' => 'foo', 'field' => 1337 ), array( '%s', '%d' ) )
		 * </code>
		 *
		 * @since 3.0.0
		 * @see wpdb::prepare()
		 * @see wpdb::$field_types
		 * @see wp_set_wpdb_vars()
		 *
		 * @param string $table table name
		 * @param array $data Data to insert (in column => value pairs). Both $data columns and $data values should be "raw" (neither should be SQL escaped).
		 * @param array|string $format Optional. An array of formats to be mapped to each of the value in $data. If string, that format will be used for all of the values in $data.
		 * 	A format is one of '%d', '%s' (decimal number, string). If omitted, all values in $data will be treated as strings unless otherwise specified in wpdb::$field_types.
		 * @return int|false The number of rows affected, or false on error.
		 */
		function replace($table, $data, $format = null) {
			return $this->_insert_replace_helper($table, $data, $format, 'REPLACE');
		}

		/**
		 * Helper function for insert and replace.
		 *
		 * Runs an insert or replace query based on $type argument.
		 *
		 * @access private
		 * @since 3.0.0
		 * @see wpdb::prepare()
		 * @see wpdb::$field_types
		 * @see wp_set_wpdb_vars()
		 *
		 * @param string $table table name
		 * @param array $data Data to insert (in column => value pairs).  Both $data columns and $data values should be "raw" (neither should be SQL escaped).
		 * @param array|string $format Optional. An array of formats to be mapped to each of the value in $data. If string, that format will be used for all of the values in $data.
		 * 	A format is one of '%d', '%s' (decimal number, string). If omitted, all values in $data will be treated as strings unless otherwise specified in wpdb::$field_types.
		 * @return int|false The number of rows affected, or false on error.
		 */
		function _insert_replace_helper($table, $data, $format = null, $type = 'INSERT') {
			if (!in_array(strtoupper($type), array('REPLACE', 'INSERT')))
				return false;
			$formats = $format = (array) $format;
			$fields = array_keys($data);
			$formatted_fields = array();
			foreach ($fields as $field) {
				if (!empty($format))
					$form = ( $form = array_shift($formats) ) ? $form : $format[0];
				elseif (isset($this->field_types[$field]))
					$form = $this->field_types[$field];
				else
					$form = '%s';
				$formatted_fields[] = $form;
			}
			$sql = "{$type} INTO `$table` (`" . implode('`,`', $fields) . "`) VALUES ('" . implode("','", $formatted_fields) . "')";
			return $this->MDBC_query($this->prepare($sql, $data), false);
		}

		/**
		 * Update a row in the table
		 *
		 * <code>
		 * wpdb::update( 'table', array( 'column' => 'foo', 'field' => 'bar' ), array( 'ID' => 1 ) )
		 * wpdb::update( 'table', array( 'column' => 'foo', 'field' => 1337 ), array( 'ID' => 1 ), array( '%s', '%d' ), array( '%d' ) )
		 * </code>
		 *
		 * @since 2.5.0
		 * @see wpdb::prepare()
		 * @see wpdb::$field_types
		 * @see wp_set_wpdb_vars()
		 *
		 * @param string $table table name
		 * @param array $data Data to update (in column => value pairs). Both $data columns and $data values should be "raw" (neither should be SQL escaped).
		 * @param array $where A named array of WHERE clauses (in column => value pairs). Multiple clauses will be joined with ANDs. Both $where columns and $where values should be "raw".
		 * @param array|string $format Optional. An array of formats to be mapped to each of the values in $data. If string, that format will be used for all of the values in $data.
		 * 	A format is one of '%d', '%s' (decimal number, string). If omitted, all values in $data will be treated as strings unless otherwise specified in wpdb::$field_types.
		 * @param array|string $format_where Optional. An array of formats to be mapped to each of the values in $where. If string, that format will be used for all of the items in $where.  A format is one of '%d', '%s' (decimal number, string).  If omitted, all values in $where will be treated as strings.
		 * @return int|false The number of rows updated, or false on error.
		 */
		function update($table, $data, $where, $format = null, $where_format = null) {
			if (!is_array($data) || !is_array($where))
				return false;

			$formats = $format = (array) $format;
			$bits = $wheres = array();
			foreach ((array) array_keys($data) as $field) {
				if (!empty($format))
					$form = ( $form = array_shift($formats) ) ? $form : $format[0];
				elseif (isset($this->field_types[$field]))
					$form = $this->field_types[$field];
				else
					$form = '%s';
				$bits[] = "`$field` = {$form}";
			}

			$where_formats = $where_format = (array) $where_format;
			foreach ((array) array_keys($where) as $field) {
				if (!empty($where_format))
					$form = ( $form = array_shift($where_formats) ) ? $form : $where_format[0];
				elseif (isset($this->field_types[$field]))
					$form = $this->field_types[$field];
				else
					$form = '%s';
				$wheres[] = "`$field` = {$form}";
			}

			$sql = "UPDATE `$table` SET " . implode(', ', $bits) . ' WHERE ' . implode(' AND ', $wheres);
			return $this->MDBC_query($this->prepare($sql, array_merge(array_values($data), array_values($where))), false);
		}

		/**
		 * Retrieve one variable from the database.
		 *
		 * Executes a SQL query and returns the value from the SQL result.
		 * If the SQL result contains more than one column and/or more than one row, this function returns the value in the column and row specified.
		 * If $query is null, this function returns the value in the specified column and row from the previous SQL result.
		 *
		 * @since 0.71
		 *
		 * @param string|null $query Optional. SQL query. Defaults to null, use the result from the previous query.
		 * @param int $x Optional. Column of value to return.  Indexed from 0.
		 * @param int $y Optional. Row of value to return.  Indexed from 0.
		 * @return string|null Database query result (as string), or null on failure
		 */
		function get_var($query = null, $x = 0, $y = 0) {
			$this->func_call = "\$db->get_var(\"$query\", $x, $y)";
			if ($query)
				$this->MDBC_query($query);

			// Extract var out of cached results based x,y vals
			if (!empty($this->last_result[$y])) {
				$values = array_values(get_object_vars($this->last_result[$y]));
			}

			// If there is a value return it else return null
			return ( isset($values[$x]) && $values[$x] !== '' ) ? $values[$x] : null;
		}

		/**
		 * Retrieve one row from the database.
		 *
		 * Executes a SQL query and returns the row from the SQL result.
		 *
		 * @since 0.71
		 *
		 * @param string|null $query SQL query.
		 * @param string $output Optional. one of ARRAY_A | ARRAY_N | OBJECT constants. Return an associative array (column => value, ...),
		 * 	a numerically indexed array (0 => value, ...) or an object ( ->column = value ), respectively.
		 * @param int $y Optional. Row to return. Indexed from 0.
		 * @return mixed Database query result in format specifed by $output or null on failure
		 */
		function get_row($query = null, $output = OBJECT, $y = 0) {
			$this->func_call = "\$db->get_row(\"$query\",$output,$y)";
			if ($query)
				$this->MDBC_query($query);
			else
				return null;

			if (!isset($this->last_result[$y]))
				return null;

			if ($output == OBJECT) {
				return $this->last_result[$y] ? $this->last_result[$y] : null;
			} elseif ($output == ARRAY_A) {
				return $this->last_result[$y] ? get_object_vars($this->last_result[$y]) : null;
			} elseif ($output == ARRAY_N) {
				return $this->last_result[$y] ? array_values(get_object_vars($this->last_result[$y])) : null;
			} else {
				$this->print_error(/* WP_I18N_DB_GETROW_ERROR */" \$db->get_row(string query, output type, int offset) -- Output type must be one of: OBJECT, ARRAY_A, ARRAY_N"/* /WP_I18N_DB_GETROW_ERROR */);
			}
		}

		/**
		 * Retrieve one column from the database.
		 *
		 * Executes a SQL query and returns the column from the SQL result.
		 * If the SQL result contains more than one column, this function returns the column specified.
		 * If $query is null, this function returns the specified column from the previous SQL result.
		 *
		 * @since 0.71
		 *
		 * @param string|null $query Optional. SQL query. Defaults to previous query.
		 * @param int $x Optional. Column to return. Indexed from 0.
		 * @return array Database query result.  Array indexed from 0 by SQL result row number.
		 */
		function get_col($query = null, $x = 0) {
			if ($query)
				$this->MDBC_query($query);

			$new_array = array();
			// Extract the column values
			for ($i = 0, $j = count($this->last_result); $i < $j; $i++) {
				$new_array[$i] = $this->get_var(null, $x, $i);
			}
			return $new_array;
		}

		/**
		 * Retrieve an entire SQL result set from the database (i.e., many rows)
		 *
		 * Executes a SQL query and returns the entire SQL result.
		 *
		 * @since 0.71
		 *
		 * @param string $query SQL query.
		 * @param string $output Optional. Any of ARRAY_A | ARRAY_N | OBJECT | OBJECT_K constants. With one of the first three, return an array of rows indexed from 0 by SQL result row number.
		 * 	Each row is an associative array (column => value, ...), a numerically indexed array (0 => value, ...), or an object. ( ->column = value ), respectively.
		 * 	With OBJECT_K, return an associative array of row objects keyed by the value of each row's first column's value.  Duplicate keys are discarded.
		 * @return mixed Database query results
		 */
		function get_results($query = null, $output = OBJECT) {
			$this->func_call = "\$db->get_results(\"$query\", $output)";

			if ($query)
				$this->MDBC_query($query);
			else
				return null;

			$new_array = array();
			if ($output == OBJECT) {
				// Return an integer-keyed array of row objects
				return $this->last_result;
			} elseif ($output == OBJECT_K) {
				// Return an array of row objects with keys from column 1
				// (Duplicates are discarded)
				foreach ($this->last_result as $row) {
					$key = array_shift(get_object_vars($row));
					if (!isset($new_array[$key]))
						$new_array[$key] = $row;
				}
				return $new_array;
			} elseif ($output == ARRAY_A || $output == ARRAY_N) {
				// Return an integer-keyed array of...
				if ($this->last_result) {
					foreach ((array) $this->last_result as $row) {
						if ($output == ARRAY_N) {
							// ...integer-keyed row arrays
							$new_array[] = array_values(get_object_vars($row));
						} else {
							// ...column name-keyed row arrays
							$new_array[] = get_object_vars($row);
						}
					}
				}
				return $new_array;
			}
			return null;
		}

		/**
		 * Retrieve column metadata from the last query.
		 *
		 * @since 0.71
		 *
		 * @param string $info_type Optional. Type one of name, table, def, max_length, not_null, primary_key, multiple_key, unique_key, numeric, blob, type, unsigned, zerofill
		 * @param int $col_offset Optional. 0: col name. 1: which table the col's in. 2: col's max length. 3: if the col is numeric. 4: col's type
		 * @return mixed Column Results
		 */
		function get_col_info($info_type = 'name', $col_offset = -1) {
			if ($this->col_info) {
				if ($col_offset == -1) {
					$i = 0;
					$new_array = array();
					foreach ((array) $this->col_info as $col) {
						$new_array[$i] = $col->{$info_type};
						$i++;
					}
					return $new_array;
				} else {
					return $this->col_info[$col_offset]->{$info_type};
				}
			}
		}

		/**
		 * Starts the timer, for debugging purposes.
		 *
		 * @since 1.5.0
		 *
		 * @return true
		 */
		function timer_start() {
			$mtime = explode(' ', microtime());
			$this->time_start = $mtime[1] + $mtime[0];
			return true;
		}

		/**
		 * Stops the debugging timer.
		 *
		 * @since 1.5.0
		 *
		 * @return int Total time spent on the query, in milliseconds
		 */
		function timer_stop() {
			$mtime = explode(' ', microtime());
			$time_end = $mtime[1] + $mtime[0];
			$time_total = $time_end - $this->time_start;
			return $time_total;
		}

		/**
		 * Wraps errors in a nice header and footer and dies.
		 *
		 * Will not die if wpdb::$show_errors is true
		 *
		 * @since 1.5.0
		 *
		 * @param string $message The Error message
		 * @param string $error_code Optional. A Computer readable string to identify the error.
		 * @return false|void
		 */
		function bail($message, $error_code = '500') {
			if (!$this->show_errors) {
				if (class_exists('WP_Error'))
					$this->error = new WP_Error($error_code, $message);
				else
					$this->error = $message;
				return false;
			}
			wp_die($message);
		}

		/**
		 * Whether MySQL database is at least the required minimum version.
		 *
		 * @since 2.5.0
		 * @uses $wp_version
		 * @uses $required_mysql_version
		 *
		 * @return WP_Error
		 */
		function check_database_version() {
			global $wp_version, $required_mysql_version;
			// Make sure the server has the required MySQL version
			if (isset($required_mysql_version)) {
				if (version_compare($this->db_version(), $required_mysql_version, '<'))
					return new WP_Error('database_version', sprintf(__('<strong>ERROR</strong>: WordPress %1$s requires MySQL %2$s or higher'), $wp_version, $required_mysql_version));
			} elseif (version_compare($wp_version, '2.9', '<')) { // WP 2.8 requires MySQL 4.0.0
				// Make sure the server has MySQL 4.0
				if (version_compare($this->db_version(), '4.0.0', '<'))
					return new WP_Error('database_version', sprintf(__('<strong>ERROR</strong>: WordPress %s requires MySQL 4.0.0 or higher'), $wp_version));
			} else { // WP 2.9 requires MySQL 4.1.2
				// Make sure the server has MySQL 4.1.2
				if (version_compare($this->db_version(), '4.1.2', '<'))
					return new WP_Error('database_version', sprintf(__('<strong>ERROR</strong>: WordPress %s requires MySQL 4.1.2 or higher'), $wp_version));
			}
		}

		/**
		 * Whether the database supports collation.
		 *
		 * Called when WordPress is generating the table scheme.
		 *
		 * @since 2.5.0
		 *
		 * @return bool True if collation is supported, false if version does not
		 */
		function supports_collation() {
			return $this->has_cap('collation');
		}

		/**
		 * Determine if a database supports a particular feature
		 *
		 * @since 2.7
		 * @see   wpdb::db_version()
		 *
		 * @param string $db_cap the feature
		 * @return bool
		 */
		function has_cap($db_cap) {
			$version = $this->db_version();

			switch (strtolower($db_cap)) {
				case 'collation' :	// @since 2.5.0
				case 'group_concat' : // @since 2.7
				case 'subqueries' :   // @since 2.7
					return version_compare($version, '4.1', '>=');
			};

			return false;
		}

		/**
		 * Retrieve the name of the function that called wpdb.
		 *
		 * Searches up the list of functions until it reaches
		 * the one that would most logically had called this method.
		 *
		 * @since 2.5.0
		 *
		 * @return string The name of the calling function
		 */
		function get_caller() {
			// requires PHP 4.3+
			if (!is_callable('debug_backtrace'))
				return '';

			$trace = array_reverse(debug_backtrace());
			$caller = array();

			foreach ($trace as $call) {
				if (isset($call['class']) && __CLASS__ == $call['class'])
					continue; // Filter out wpdb calls.
				$caller[] = isset($call['class']) ? "{$call['class']}->{$call['function']}" : $call['function'];
			}

			return join(', ', $caller);
		}

		/**
		 * The database version number.
		 *
		 * @return false|string false on failure, version number on success
		 */
		function db_version() {
			return preg_replace('/[^0-9.].*/', '', mysql_get_server_info($this->dbh));
		}

		// Show error message when something is messed with MultiDB Cache Hash plugin
		function _MDBC_admin_notice() {
			// Display error message
			echo '<div id="notice" class="error"><p>';
			printf(__('<b>MultiDB Cache Hash Error:</b> cannot include <code>db-functions.php</code> file. Please either reinstall plugin or remove <code>%s</code> file.', 'multidb-cache-hash'), WP_CONTENT_DIR . '/db.php');
			echo '</p></div>', "\n";
		}

		/* Implements the hash calculation for db_module */

		function getDbServer($blog_id) {
			$ServerNum = count(wpdbDbCache::$MDBC_connectionPool);
			$blog_id = (int) $blog_id;
			if (!$blog_id)
				return 0;

			$hash = ($blog_id - 1) % $ServerNum;
			return $hash;
		}

	}

	//Class wpdbDbCache
} else { // class_exists( 'wpdb' )
	// This should not happen, but I got few error reports regarding this issue
	if (class_exists('ReflectionClass')) {
		// We have Reflection classes - display detailed information
		$ref_class = new ReflectionClass('wpdb');
		define('MDBC_WPDB_EXISTED', $ref_class->getFileName() . ':' . $ref_class->getStartLine());
	} else {
		// No Reflection - just display general info
		define('MDBC_WPDB_EXISTED', true);
	}
}

if (!isset($wpdb)) {
	/**
	 * WordPress Database Object, if it isn't set already in wp-content/db.php
	 * @global object $wpdb Creates a new wpdb object based on wp-config.php Constants for the database
	 * @since 0.71
	 */
	$wpdb = new wpdbDbCache(DB_USER, DB_PASSWORD, DB_NAME, DB_HOST);
}
