<?php

// --- DB Cache Start ---
// Path to plugin
if ( !defined( 'MDBC_PATH' ) ) {
	define( 'MDBC_PATH', WP_PLUGIN_DIR.'/'.dirname( __FILE__ ) );
}
// Cache directory
if ( !defined( 'MDBC_CACHE_DIR' ) ) {
	define( 'MDBC_CACHE_DIR', WP_CONTENT_DIR.'/mdb-cache' );
}

// DB Module version (one or more digits for major, two digits for minor and revision numbers)
if ( !defined( 'MDBC_DB_MODULE_VER' ) ) {
	define( 'MDBC_DB_MODULE_VER', 10600 );
}

// HACK: need to enable SAVEQUERY in order to save extended query data
if ( defined( 'MDBC_SAVEQUERIES' ) && MDBC_SAVEQUERIES && !defined ( 'SAVEQUERIES' ) ) {
	define( 'SAVEQUERIES', true );
}

// Check if we have required functions
if ( !function_exists( 'is_multisite' ) ) { // Added in WP 3.0
	function is_multisite() {
		return false;
	}
}

// --- DB Cache End ---


if ( !class_exists( 'MDBC_WPDB_Wrapper' ) ) {
class MDBC_WPDB_Wrapper {

	// --- DB Cache Start ---
	/**
	 * Aggregated WPDB object
	 *
	 * @var object of wpdb|null
	 */
	var $MDBC_wpdb = null;
	/**
	 * Amount of queries cached by DB Cache Reloaded made
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
	 * Array with DB Cache Reloaded config
	 *
	 * @var array
	 */
	var $MDBC_config = null;
	/**
	 * DB Cache Reloaded helper
	 *
	 * @var object of pcache
	 */
	var $MDBC_cache = null;
	/**
	 * True if DB Cache Reloaded should show error in admin section
	 *
	 * @var bool
	 */
	var $MDBC_show_error = false;
	/**
	 * DB Cache Reloaded DB module version
	 *
	 * @var int
	 */
	var $MDBC_version = MDBC_DB_MODULE_VER;
	// --- DB Cache End ---
	
	/**
	 * Connects to the database server and selects a database
	 *
	 * PHP5 style constructor for compatibility with PHP5. Does
	 * the actual setting up of the class properties and connection
	 * to the database.
	 *
	 * PHP4 incompatibility is intentional, because of using PHP5 object extensions.
	 */
	function __construct($wpdb) {
		$this->MDBC_wpdb = $wpdb;
		
		// --- DB Cache Start ---
		// Caching
		// require_once would be better, but some people deletes plugin without deactivating it first
		if ( @include_once( MDBC_PATH.'/db-functions.php' ) ) {
			$this->MDBC_config = unserialize( @file_get_contents( WP_CONTENT_DIR.'/db-config.ini' ) );
			
			foreach ($this->MDBC_config['memcache_servers'] as $ix => $server)
			{
				if (!(int)$server['port'] || empty($server['host']) )
				{
					unset($this->MDBC_config['memcache_servers'][$ix]);
				}
				else echo "found memcache config";
			}
			
			if ($this->MDBC_config['use_memcache'] && !empty($this->MDBC_config['memcache_servers']))
			{
				$this->MDBC_cache = &new mdbCache($this->MDBC_config['memcache_servers']);
			}
			else
			{
				$this->MDBC_cache = &new mdbCache();
			}
			
			$this->MDBC_cache->lifetime = isset( $this->MDBC_config['timeout'] ) ? $this->MDBC_config['timeout'] : 5;
			
			// Clean unused
			$dbcheck = date('G')/4;
			if ( $dbcheck == intval( $dbcheck ) && ( !isset( $this->MDBC_config['lastclean'] ) 
				|| $this->MDBC_config['lastclean'] < time() - 3600 ) ) {
				$this->MDBC_cache->clean();
				$this->MDBC_config['lastclean'] = time();
				$file = fopen(WP_CONTENT_DIR.'/db-config.ini', 'w+');
				if ($file) {
					fwrite($file, serialize($this->MDBC_config));
					fclose($file);
				}
			}
			
			// cache only frontside
			if (
				( defined( 'WP_ADMIN' ) && WP_ADMIN ) ||
			 	( defined( 'DOING_CRON' ) && DOING_CRON ) || 
			 	( defined( 'DOING_AJAX' ) && DOING_AJAX ) || 
				strpos( $_SERVER['REQUEST_URI'], 'wp-admin' ) || 
				strpos( $_SERVER['REQUEST_URI'], 'wp-login' ) || 
				strpos( $_SERVER['REQUEST_URI'], 'wp-register' ) || 
				strpos( $_SERVER['REQUEST_URI'], 'wp-signup' )
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
		return $this->MDBC_query( $query, true );
	}
	
	function MDBC_query( $query, $maybe_cache = true ) {
		if ( ! $this->MDBC_wpdb->ready )
			return false;

		// --- DB Cache Start ---
		if ( defined('MDBC_SAVEQUERIES') && MDBC_SAVEQUERIES )
			$this->MDBC_wpdb->timer_start();
		
		$MDBC_db = 'local';
		// --- DB Cache End ---
		
		if( defined( 'WP_USE_MULTIPLE_DB' ) && WP_USE_MULTIPLE_DB ) {
			if( $this->MDBC_wpdb->blogs != '' && preg_match("/(" . $this->MDBC_wpdb->blogs . "|" . $this->MDBC_wpdb->users . "|" . $this->MDBC_wpdb->usermeta . "|" . $this->MDBC_wpdb->site . "|" . $this->MDBC_wpdb->sitemeta . "|" . $this->MDBC_wpdb->sitecategories . ")/i",$query) ) {
				// --- DB Cache Start ---
				$MDBC_db = 'global';
				// --- DB Cache End ---
			}
		} else {
			// DB Cache Start
			if( $this->MDBC_wpdb->blogs != '' && preg_match("/(" . $this->MDBC_wpdb->blogs . "|" . $this->MDBC_wpdb->users . "|" . $this->MDBC_wpdb->usermeta . "|" . $this->MDBC_wpdb->site . "|" . $this->MDBC_wpdb->sitemeta . "|" . $this->MDBC_wpdb->sitecategories . ")/i",$query) ) {
				$MDBC_db = 'global';
			}
			// DB Cache End
		}
		
		// --- DB Cache Start ---
		// Caching
		$MDBC_cacheable = false;
		// check if pcache object is in place
		if ( !is_null( $this->MDBC_cache ) ) {
			$MDBC_cacheable = $this->MDBC_cacheable && $maybe_cache;
			
			if ( $MDBC_cacheable ) {
				// do not cache non-select queries
				if ( preg_match( "/\\s*(insert|delete|update|replace|alter|SET NAMES|FOUND_ROWS|RAND)\\b/si", $query ) ) {
					$MDBC_cacheable = false;
				} elseif ( // For hard queries - skip them
					!preg_match( "/\\s*(JOIN | \* |\*\,)/si", $query ) ||
					// User-defined cache filters
					( isset( $config['filter'] ) && ( $config['filter'] != '' ) &&
					preg_match( "/\\s*(".$config['filter'].")/si", $query ) ) ) {
					$MDBC_cacheable = false;
				}
			}
			
			if ( $MDBC_cacheable ) {
				$MDBC_queryid = md5( $query );
				
				if ( strpos( $query, '_options' ) ) {
					$this->MDBC_cache->set_storage( $MDBC_db, 'options' );
				} elseif ( strpos( $query, '_links' ) ) {
					$this->MDBC_cache->set_storage( $MDBC_db, 'links' );
				} elseif ( strpos( $query, '_terms' ) ) {
					$this->MDBC_cache->set_storage( $MDBC_db, 'terms' );
				} elseif ( strpos( $query, '_user' ) ) {
					$this->MDBC_cache->set_storage( $MDBC_db, 'users' );
				} elseif ( strpos( $query, '_post' ) ) {
					$this->MDBC_cache->set_storage( $MDBC_db, 'posts' );
				} else {
					$this->MDBC_cache->set_storage( $MDBC_db, '' );
				}
			}
			
			/* Debug part */
			if ( isset( $config['debug'] ) && $config['debug'] ) {
				if ( $MDBC_cacheable ) {
					echo "\n<!-- cache: $query -->\n\n";
				} else {
					echo "\n<!-- mysql: $query -->\n\n";
				}
			}
		} elseif ( $this->MDBC_show_error ) {
			$this->MDBC_show_error = false;
			add_action( 'admin_notices', array( &$this, '_MDBC_admin_notice' ) );
		}
		
		$MDBC_cached = false;
		if ( $MDBC_cacheable ) {
			// Try to load cached query
			$MDBC_cached = $this->MDBC_cache->load( $MDBC_queryid );
		}
		
		if ( $MDBC_cached !== false ) {
			// Extract cached query
			++$this->num_cachequeries;
			
			$MDBC_cached = unserialize( $MDBC_cached );
			$this->MDBC_wpdb->last_error = '';
			$this->MDBC_wpdb->last_query = $MDBC_cached['last_query'];
			$this->MDBC_wpdb->last_result = $MDBC_cached['last_result'];
			$this->MDBC_wpdb->col_info = $MDBC_cached['col_info'];
			$this->MDBC_wpdb->num_rows = $MDBC_cached['num_rows'];
			
			$return_val = $this->MDBC_wpdb->num_rows;
			
			if ( defined('MDBC_SAVEQUERIES') && MDBC_SAVEQUERIES ) {
				$this->MDBC_wpdb->queries[] = array( $query, $this->MDBC_wpdb->timer_stop(), $this->MDBC_wpdb->get_caller(), true );
			}
		} else {
			// Cache not found or query not cacheable, perform query as usual
			$return_val = $this->MDBC_wpdb->query( $query );
			if ( $return_val === false ) { // error executing sql query
				return false;
			}
			
			if ( defined('MDBC_SAVEQUERIES') && MDBC_SAVEQUERIES ) {
				$this->MDBC_wpdb->queries[count( $this->MDBC_wpdb->queries ) - 1][3] = false;
			}
		}
		
		if ( preg_match( "/^\\s*(insert|delete|update|replace|alter) /i", $query ) ) {
			// --- DB Cache Start ---
			++$this->MDBC_num_dml_queries;
			// --- DB Cache End ---
		} else {
			// --- DB Cache Start ---
			if ( $MDBC_cacheable && ( $MDBC_cached === false ) ) {
				$MDBC_cached = serialize( array(
					'last_query' => $this->MDBC_wpdb->last_query,
					'last_result' => $this->MDBC_wpdb->last_result,
					'col_info' => $this->MDBC_wpdb->col_info,
					'num_rows' => $this->MDBC_wpdb->num_rows,
				) );
				$this->MDBC_cache->save( $MDBC_cached, $MDBC_queryid );
			}
			// DB Cache End
		}
		
		return $return_val;
	}
	
	// Show error message when something is messed with DB Cache Reloaded plugin
	function _MDBC_admin_notice() {
		// Display error message
		echo '<div id="notice" class="error"><p>';
		printf( __('<b>DB Cache Reloaded Error:</b> cannot include <code>db-functions.php</code> file. Please either reinstall plugin or remove <code>%s</code> file.', 'db-cache-reloaded'), WP_CONTENT_DIR.'/db.php' );
		echo '</p></div>', "\n";
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
	function get_var( $query = null, $x = 0, $y = 0 ) {
		$this->MDBC_wpdb->func_call = "\$db->get_var(\"$query\", $x, $y)";
		if ( $query )
			$this->MDBC_query( $query );

		// Extract var out of cached results based x,y vals
		if ( !empty( $this->MDBC_wpdb->last_result[$y] ) ) {
			$values = array_values( get_object_vars( $this->MDBC_wpdb->last_result[$y] ) );
		}

		// If there is a value return it else return null
		return ( isset( $values[$x] ) && $values[$x] !== '' ) ? $values[$x] : null;
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
	function get_row( $query = null, $output = OBJECT, $y = 0 ) {
		$this->MDBC_wpdb->func_call = "\$db->get_row(\"$query\",$output,$y)";
		if ( $query )
			$this->MDBC_query( $query );
		else
			return null;

		if ( !isset( $this->MDBC_wpdb->last_result[$y] ) )
			return null;

		if ( $output == OBJECT ) {
			return $this->MDBC_wpdb->last_result[$y] ? $this->MDBC_wpdb->last_result[$y] : null;
		} elseif ( $output == ARRAY_A ) {
			return $this->MDBC_wpdb->last_result[$y] ? get_object_vars( $this->MDBC_wpdb->last_result[$y] ) : null;
		} elseif ( $output == ARRAY_N ) {
			return $this->MDBC_wpdb->last_result[$y] ? array_values( get_object_vars( $this->MDBC_wpdb->last_result[$y] ) ) : null;
		} else {
			$this->MDBC_wpdb->print_error(/*WP_I18N_DB_GETROW_ERROR*/" \$db->get_row(string query, output type, int offset) -- Output type must be one of: OBJECT, ARRAY_A, ARRAY_N"/*/WP_I18N_DB_GETROW_ERROR*/);
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
	function get_col( $query = null , $x = 0 ) {
		if ( $query )
			$this->MDBC_query( $query );

		$new_array = array();
		// Extract the column values
		for ( $i = 0, $j = count( $this->MDBC_wpdb->last_result ); $i < $j; $i++ ) {
			$new_array[$i] = $this->get_var( null, $x, $i );
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
	function get_results( $query = null, $output = OBJECT ) {
		$this->MDBC_wpdb->func_call = "\$db->get_results(\"$query\", $output)";

		if ( $query )
			$this->MDBC_query( $query );
		else
			return null;

		$new_array = array();
		if ( $output == OBJECT ) {
			// Return an integer-keyed array of row objects
			return $this->MDBC_wpdb->last_result;
		} elseif ( $output == OBJECT_K ) {
			// Return an array of row objects with keys from column 1
			// (Duplicates are discarded)
			foreach ( $this->MDBC_wpdb->last_result as $row ) {
				$key = array_shift( get_object_vars( $row ) );
				if ( ! isset( $new_array[ $key ] ) )
					$new_array[ $key ] = $row;
			}
			return $new_array;
		} elseif ( $output == ARRAY_A || $output == ARRAY_N ) {
			// Return an integer-keyed array of...
			if ( $this->MDBC_wpdb->last_result ) {
				foreach( (array) $this->MDBC_wpdb->last_result as $row ) {
					if ( $output == ARRAY_N ) {
						// ...integer-keyed row arrays
						$new_array[] = array_values( get_object_vars( $row ) );
					} else {
						// ...column name-keyed row arrays
						$new_array[] = get_object_vars( $row );
					}
				}
			}
			return $new_array;
		}
		return null;
	}
	
	// Wrappers for members of aggregated class
	function __get( $name ) {
		return $this->MDBC_wpdb->$name;
	}
	
	function __set( $name, $value ) {
		$this->MDBC_wpdb->$name = $value;
	}
	
	function __isset( $name ) {
		return isset( $this->MDBC_wpdb->$name );
	}
	
	function __unset( $name ) {
		unset( $this->MDBC_wpdb->$name );
	}
	
	function __call( $name, $args ) {
		return call_user_func_array( array( $this->MDBC_wpdb, $name ), $args );
	}
}

$wpdb = new MDBC_WPDB_Wrapper( $wpdb );
$MDBC_wpdb = $wpdb;

}

?>