<?php
/*
Plugin Name: Multi DB Cache Hashed
Plugin URI: http://miklo.hu/
Description: This plugin merges the benefits of hyperDB and db-cache-reloaded.
Author: Geza Miklo <geza.miklo@gmail.com>
Version: 1.60
Author URI: http://miklo.hu/
Text Domain: multi-db-cache
*/

/*  Copyright 2011  Geza miklo  (email : geza.miklo@gmail.com)
	Benchmarks, server side optimalizations: Andor Toth
    
	Based on DB Cache by Dmitry Svarytsevych

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

define( 'MDBC_DEBUG', false );

// Path to plugin
if ( !defined( 'MDBC_PATH' ) ) {
	define( 'MDBC_PATH', dirname( __FILE__ ) );
}
// Cache directory
global $current_blog;
// Cache directory
if ( !defined( 'MDBC_CACHE_DIR' ) ) {
        define( 'MDBC_CACHE_DIR', WP_CONTENT_DIR.'/mdb-cache' );
}

// Check if we have required functions
if ( !function_exists( 'is_multisite' ) ) { // Added in WP 3.0
	function is_multisite() {
		return false;
	}
}

// DB Module version (one or more digits for major, two digits for minor and revision numbers)
define( 'MDBC_CURRENT_DB_MODULE_VER', 10600 );

// Load pcache class if needed
if ( !class_exists('mdbFileCache') ) {
	include MDBC_PATH.'/db-functions.php';
}

if ( !class_exists( 'MultiDBCache' ) ) {

class MultiDBCache {
	var $config = null;
	var $folders = null;
	var $settings_page = false;
	var $MDBC_cache = null;
	var $MDBC_cachetype = 'n/a';
	var $poolLength = 0;
	
	// Constructor
	function MultiDBCache() {
		global $current_blog;
		$this->config = unserialize( @file_get_contents( WP_CONTENT_DIR.'/db-config.ini' ) );
		
		// Load DB Module Wrapper if needed (1st check)
		global $wpdb;
		if ( isset( $this->config['enabled'] ) && $this->config['enabled']
			&& isset( $this->config['wrapper'] ) && $this->config['wrapper'] ) {
			global $MDBC_wpdb;
			include MDBC_PATH.'/db-module-wrapper.php';
			
			// 3rd check to make sure DB Module Wrapper is loaded
			add_filter( 'query_vars', array( &$this, 'query_vars' ) );
		}
		
		// Set our copy of pcache object
		if ( !empty ( $wpdb->MDBC_cache ) ) {
			$this->MDBC_cache = &$wpdb->MDBC_cache;
			$this->MDBC_cachetype = $wpdb->MDBC_cachetype;
		} else {
			
			if (!empty($this->config['use_memcache']) && !empty($this->config['memcache_servers']))
			{
				foreach ($this->config['memcache_servers'] as $ix => $server)
				{
					if (!(int)$server['port'] || empty($server['host']) )
					{
						unset($this->config['memcache_servers'][$ix]);
					}
				}
				$this->MDBC_cache = &new mdbCache($this->config['memcache_servers']);
				$this->MDBC_cachetype = 'Mem';
			}
			
			if (false==$this->MDBC_cache || null==$this->MDBC_cache)
			{
				$this->MDBC_cache = &new mdbCache();
				$this->MDBC_cachetype = 'File';
			}
		}
		
		// Initialise plugin
		add_action( 'init', array( &$this, 'init' ), 50 );
		
		// Install/Upgrade
		add_action( 'activate_'.plugin_basename( __FILE__ ), array( &$this, 'MDBC_install' ) );
		// Uninstall
		add_action( 'deactivate_'.plugin_basename( __FILE__ ), array( &$this, 'MDBC_uninstall' ) );
		
		// Add cleaning on publish and new comment
		// Posts
		//If a post is created or changed have to clear the part of the cache related to posts
		add_action( 'publish_post', array( &$this, 'MDBC_clear_posts' ), 50, 1 );
		add_action( 'edit_post', array( &$this, 'MDBC_clear_posts' ), 50, 1 );
		add_action( 'delete_post', array( &$this, 'MDBC_clear_posts' ), 50, 1 );

		// Comments
		add_action( 'trackback_post', array( &$this, 'MDBC_clear_comments' ), 50, 1 );
		add_action( 'pingback_post', array( &$this, 'MDBC_clear_comments' ), 50, 1 );
		add_action( 'comment_post', array( &$this, 'MDBC_clear_comments' ), 50, 1 );
		add_action( 'edit_comment', array( &$this, 'MDBC_clear_comments' ), 50, 1 );
		add_action( 'wp_set_comment_status', array( &$this, 'MDBC_clear_comments' ), 50, 1 );
		add_action( 'delete_comment', array( &$this, 'MDBC_clear_comments' ), 50, 1 );

		//Theme switching
		add_action( 'switch_theme', array( &$this, 'MDBC_clear' ), 50, 1 );

		//CREATE blog
		add_action( 'activate_blog', array( &$this, 'MDBC_clear_blog' ), 50, 1 );
		add_action( 'deactivate_blog', array( &$this, 'MDBC_clear_blog' ), 50, 1 );
		add_action( 'delete_blog', array( &$this, 'MDBC_clear_blog' ), 50, 1 );
		add_action( 'wpmu_activate_blog', array( &$this, 'MDBC_clear_blog' ), 50, 1 );
		add_action( 'wpmu_new_blog', array( &$this, 'MDBC_clear_blog' ), 50, 1 );
		
		//Delete stored user data, if a user is changed or deleted or added, else he/she won't be able to log in
		add_action( 'delete_user', array( &$this, 'MDBC_clear_users' ), 50, 1 );
		add_action( 'deleted_user', array( &$this, 'MDBC_clear_users' ), 50, 1 );
		add_action( 'personal_options_update', array( &$this, 'MDBC_clear_users' ), 50, 1 );
		add_action( 'edit_user_profile_update', array( &$this, 'MDBC_clear_users' ), 50, 1 );
		add_action( 'profile_update', array( &$this, 'MDBC_clear_users' ), 50, 1 );
		add_action( 'register_post', array( &$this, 'MDBC_clear_users' ), 50, 1 );
		add_action( 'user_register', array( &$this, 'MDBC_clear_users' ), 50, 1 );
		add_action( 'wpmu_activate_user', array( &$this, 'MDBC_clear_users' ), 50, 1 );
		add_action( 'wpmu_activate_blog', array( &$this, 'MDBC_clear_users' ), 50, 1 );
		add_action( 'wpmu_new_blog', array( &$this, 'MDBC_clear_users' ), 50, 1 );
		add_action( 'wpmu_new_user', array( &$this, 'MDBC_clear_users' ), 50, 1 );
		add_action( 'added_existsing_user', array( &$this, 'MDBC_clear_users' ), 50, 1 );
		add_action( 'add_user_to_blog', array( &$this, 'MDBC_clear_users' ), 50, 1 );
		add_action( 'password_reset', array( &$this, 'MDBC_clear_users' ), 50, 1 );
		

		add_action( 'update_wpmu_options', array( &$this, 'MDBC_clear_options' ), 50, 1 );
		add_action( 'wpmu_update_blog_options', array( &$this, 'MDBC_clear_options' ), 50, 1 );
		add_action( 'add_site_option', array( &$this, 'MDBC_clear_options' ), 50, 1 );
		add_action( 'update_site_option', array( &$this, 'MDBC_clear_options' ), 50, 1 );
		add_action( 'added_option', array( &$this, 'MDBC_clear_options' ), 50, 1 );
		add_action( 'updated_option', array( &$this, 'MDBC_clear_options' ), 50, 1 );
		add_action( 'deleted_option', array( &$this, 'MDBC_clear_options' ), 50, 1 );

		
		// Display stats in footer
		add_action( 'wp_footer', 'loadstats', 999999 );
		
		
		if (
				is_admin()

			) {
			// Show warning message to admin
			add_action( 'admin_notices', array( &$this, 'admin_notices' ) );
			
			// Catch options page
			add_action( 'load-settings_page_'.substr( plugin_basename( __FILE__ ), 0, -4 ), array( &$this, 'load_settings_page' ) );
			
			// Create options menu
			if (!is_multisite())
			{
				add_action( 'admin_menu', array( &$this, 'admin_menu' ), 50 );
			}
			else
			{
				add_action( 'network_admin_menu', array( &$this, 'network_admin_menu' ), 50 );
			}
			
			// Clear cache when option is changed
			global $wp_version;
			if ( version_compare( $wp_version, '2.9', '>=' ) ) {
				add_action( 'added_option', array( &$this, 'MDBC_clear_options' ), 0 );
				add_action( 'updated_option', array( &$this, 'MDBC_clear_options' ), 0 );
				add_action( 'deleted_option', array( &$this, 'MDBC_clear_options' ), 0 );
			} else {
				// Hook for all actions
				add_action( 'all', array( &$this, 'all_actions' ) );
			}
			
			// Provide icon for Ozh' Admin Drop Down Menu plugin
			add_action( 'ozh_adminmenu_icon_'.plugin_basename( __FILE__ ), array( &$this, 'ozh_adminmenu_icon' ) );
		}
	}
	
	// Initialise plugin
	function init() {
		load_plugin_textdomain( 'multidb-cache-hash', false, dirname( plugin_basename( __FILE__ ) ).'/lang' );
		
		// 2nd check
		global $wpdb;
		if ( isset( $this->config['enabled'] ) && $this->config['enabled']
			&& isset( $this->config['wrapper'] ) && $this->config['wrapper']
			&& !isset ( $wpdb->MDBC_version ) ) {
			// Looks that other plugin replaced our object in the meantime - need to fix this
			global $MDBC_wpdb;
			$MDBC_wpdb->MDBC_wpdb = $wpdb;
			$wpdb = $MDBC_wpdb;
		}
	}
	
	// Create options menu
	function admin_menu() {
		add_submenu_page( 'options-general.php', 'Multi DB Cache', 'Multi DB Cache', 
			'manage_options', __FILE__, array( &$this, 'options_page' ) );
	}
	
	// Create options menu
	function network_admin_menu() {
		if (is_site_admin()) {

      	if (function_exists('is_network_admin')) {

      		add_submenu_page( 'settings.php', 'Multi DB Cache', 'Multi DB Cache', 'manage_options', __FILE__, array( &$this, 'options_page' ) );

      	}

      	else {

      		add_submenu_page('ms-admin.php', 'Multi DB Cache', 'Multi DB Cache', 'manage_options', __FILE__, array( &$this, 'options_page' ));

      	}

      }
	}

	// 3rd check to make sure DB Module Wrapper is loaded
	function query_vars( $vars ) {
		// 3rd check
		global $wpdb;
		if ( isset( $this->config['enabled'] ) && $this->config['enabled']
			&& isset( $this->config['wrapper'] ) && $this->config['wrapper']
			&& !isset ( $wpdb->MDBC_version ) ) {
			// Looks that other plugin replaced our object in the meantime - need to fix this
			global $MDBC_wpdb;
			$MDBC_wpdb->MDBC_wpdb = $wpdb;
			$wpdb = $MDBC_wpdb;
		}
		
		return $vars;
	}
	
	function admin_notices() {
		global $wpdb;
		if ( defined( 'MDBC_WPDB_EXISTED' ) ) {
			// Display error message
			echo '<div id="notice" class="error"><p>';
			_e('<b>DB Cache Reloaded Error:</b> <code>wpdb</code> class is redefined, plugin cannot work!', 'multidb-cache-hash');
			if ( MDBC_WPDB_EXISTED !== true ) {
				echo '<br />';
				printf( __('Previous definition is at %s.', 'multidb-cache-hash'), MDBC_WPDB_EXISTED );
			}
			echo '</p></div>', "\n";
		}
		
		if ( !$this->settings_page ) {
			if ( ( !isset( $this->config['enabled'] ) || !$this->config['enabled'] ) ) {
				// Caching is disabled - display info message
				if (is_super_admin())
				{
				echo '<div id="notice" class="updated fade"><p>';
				printf( __('<b>DB Cache Reloaded Info:</b> caching is not enabled. Please go to the <a href="%s">Options Page</a> to enable it.', 'multidb-cache-hash'), admin_url( 'options-general.php?page='.plugin_basename( __FILE__ ) ) );
				echo '</p></div>', "\n";
				}
			} elseif ( !isset( $wpdb->num_cachequeries ) ) {
				echo '<div id="notice" class="error"><p>';
				printf( __('<b>DB Cache Reloaded Error:</b> DB Module (<code>wpdb</code> class) is not loaded. Please open the <a href="%1$s">Options Page</a>, disable caching (remember to save options) and enable it again. If this will not help, please check <a href="%2$s">FAQ</a> how to do manual upgrade.', 'multidb-cache-hash'),
					admin_url( 'options-general.php?page='.plugin_basename( __FILE__ ) ), 
					'http://miklo.hu' );
				echo '</p></div>', "\n";
			} else {
				if ( isset ( $wpdb->MDBC_version ) ) {
					$MDBC_db_version = $wpdb->MDBC_version;
				} else {
					$MDBC_db_version = 0;
				}
				
				if ( $MDBC_db_version != MDBC_CURRENT_DB_MODULE_VER ) {
					echo '<div id="notice" class="error"><p>';
					printf( __('<b>DB Cache Reloaded Error:</b> DB Module is not up to date (detected version %1$s instead of %2$s). In order to fix this, please open the <a href="%3$s">Options Page</a>, disable caching (remember to save options) and enable it again.', 'multidb-cache-hash'), 
						$this->format_ver_num( $MDBC_db_version ), 
						$this->format_ver_num( MDBC_CURRENT_DB_MODULE_VER ), 
						admin_url( 'options-general.php?page='.plugin_basename( __FILE__ ) ) );
					echo '</p></div>', "\n";
				}
			}
		}
	}
	
	// Hook for all actions
	// Note: Called in Admin section only
	function all_actions( $hook ) {
		// Clear cache when option is updated or added
		if ( preg_match( '/^(update_option_|add_option_)/', $hook ) ) {
			$this->MDBC_clear();
		}
	}
	
	// Provide icon for Ozh' Admin Drop Down Menu plugin
	function ozh_adminmenu_icon() {
		return plugins_url( 'icon.png', __FILE__ );
	}
	
	function load_settings_page() {
		$this->settings_page = true;
	}

	// Enable cache
	function MDBC_enable( $echo = true ) {
		$status = true;
		
		// Copy DB Module (if needed)
		if ( !isset( $this->config['wrapper'] ) || !$this->config['wrapper'] ) {
			if ( !@copy( MDBC_PATH.'/db-module.php', WP_CONTENT_DIR.'/db.php' ) ) {
				$status = false;
			}
		}
		
		// Create cache dirs and copy .htaccess
		if ( $status ) {
			$status = $this->MDBC_cache->install();
		}
		
		if ( $echo ) {
			if ( $status ) {
				echo '<div id="message" class="updated fade"><p>';
				_e('Caching activated.', 'multidb-cache-hash');
				echo '</p></div>';
			} else {
				echo '<div id="message" class="error"><p>';
				_e('Caching can\'t be activated. Please <a href="http://codex.wordpress.org/Changing_File_Permissions" target="blank">chmod 755</a> <u>wp-content</u> folder', 'multidb-cache-hash');
				echo '</p></div>';
			}
		}
		
		if ( !$status ) {
			$this->MDBC_disable( $echo );
		}
		
		return $status;
	}

	// Disable cache
	function MDBC_disable( $echo = true ) {
		$this->MDBC_uninstall( false );
		if ( $echo ) {
			echo '<div id="message" class="updated fade"><p>';
			_e('Caching deactivated. Cache files deleted.', 'multidb-cache-hash');
			echo '</p></div>';
		}
		
		return true;
	}
	
	// Install plugin
	function MDBC_install() {
		if ( isset( $this->config['enabled'] ) && $this->config['enabled'] ) { // This should be a plugin upgrade
			$this->MDBC_uninstall( false );
			$this->MDBC_enable( false ); // No echo - ob_start()/ob_ob_end_clean() is used in installer
		}
	}

	// Uninstall plugin
	function MDBC_uninstall( $remove_all = true ) {
		$this->MDBC_clear();
		@unlink( WP_CONTENT_DIR.'/db.php' );
		if ( $remove_all ) {
			@unlink( WP_CONTENT_DIR.'/db-config.ini' );
		}
		@unlink( MDBC_CACHE_DIR.'/.htaccess' );
		
		$this->MDBC_cache->uninstall();
		
		@rmdir( MDBC_CACHE_DIR );
	}

	// Clears the cache folder
	function MDBC_clear() {
		$this->MDBC_cache->clean( false );
	}
	

	function MDBC_clear_blog() {
		if (DOMAIN_CURRENT_SITE == $this->MDBC_cache->subDomain)
		{
			$this->MDBC_cache->setSubDomain($_GET['new'].'.'.DOMAIN_CURRENT_SITE);
		}
		$this->MDBC_cache->clean( false );
	}

	function MDBC_clear_posts() {
		$this->MDBC_cache->clean( false, null, 'posts' );
	}

	function MDBC_clear_users() {
		$this->MDBC_cache->clean( false, null, 'users' );
	}
	
	function MDBC_clear_comments() {
		$this->MDBC_cache->clean( false, null, 'comments'  );
	}

	function MDBC_clear_options() {
		$this->MDBC_cache->clean( false, null, 'options' );
	}
	
	// Format version number
	function format_ver_num( $version ) {
		if ( $version % 100 == 0 ) {
			return sprintf( '%d.%d', (int)($version / 10000), (int)($version / 100) % 100 );
		} else {
			return sprintf( '%d.%d.%d', (int)($version / 10000), (int)($version / 100) % 100, $version % 100 );
		}
	}
	
	// Settings page
	function options_page() {
		global $current_blog;
		
		//This is the main blog. All has to use the same settings
		//ONLY FOR MAIN BLOG - ADMIN BLOG --> all will use these settings
		if (is_multisite() && (BLOG_ID_CURRENT_SITE != $current_blog->blog_id) ) return false;
		
		if ( !isset( $this->config['timeout'] ) || intval( $this->config['timeout'] ) == 0) {
			$this->config['timeout'] = 5;
		} else {
			$this->config['timeout'] = intval( $this->config['timeout']/60 );
		}
		if ( !isset( $this->config['enabled'] ) ) {
			$this->config['enabled'] = false;
			$cache_enabled = false;
		} else {
			$cache_enabled = true;
		}
		if ( !isset( $this->config['loadstat'] ) ) {
			$this->config['loadstat'] = __('<!-- Generated in {timer} seconds. Made {queries} queries to database and {cached} cached queries. Memory used - {memory} -->', ' multidb-cache-hash');
		}
		if ( !isset( $this->config['filter'] ) ) {
			$this->config['filter'] = '_posts|_postmeta';
		}
		if ( !isset( $this->config['wrapper'] ) ) {
			$this->config['wrapper'] = false;
		}

		if ( !isset( $this->config['memcache_servers'] ) ) {
			$this->config['memcache_servers'] = array();
		}
		
		if ( defined( 'MDBC_DEBUG' ) && MDBC_DEBUG ) {
			$this->config['debug'] = 1;
		}
		
		if ( isset( $_POST['clear'] ) ) {
			check_admin_referer( 'multidb-cache-hash-update-options' );
			$this->MDBC_cache->clean( false );
			echo '<div id="message" class="updated fade"><p>';
			_e($this->MDBC_cache->cachetype.'cache items deleted.', 'multidb-cache-hash');
			echo '</p></div>';
		} elseif ( isset( $_POST['clearold'] ) ) {
			check_admin_referer( 'multidb-cache-hash-update-options' );
			$this->MDBC_cache->clean();
			echo '<div id="message" class="updated fade"><p>';
			_e('Expired cache files deleted.', 'multidb-cache-hash');
			echo '</p></div>';
		} elseif ( isset( $_POST['save'] ) ) {
			check_admin_referer( 'multidb-cache-hash-update-options' );
			$saveconfig = $this->config = $this->MDBC_request( 'options' );
		
			if ( defined( 'MDBC_DEBUG' ) && MDBC_DEBUG ) {
				$saveconfig['debug'] = 1;
			}
			if ( $saveconfig['timeout'] == '' || !is_numeric( $saveconfig['timeout'] ) ) {
				$this->config['timeout'] = 5;
			}
		
			// Convert to seconds for save
			$saveconfig['timeout'] = intval( $this->config['timeout']*60 );
		
			if ( !isset( $saveconfig['filter'] ) ) {
				$saveconfig['filter'] = '';
			} else {
				$this->config['filter'] = $saveconfig['filter'] = trim( $saveconfig['filter'] );
			}
			
			// Activate/deactivate caching
			if ( !isset( $this->config['enabled'] ) && $cache_enabled ) {
				$this->MDBC_disable();
			} elseif ( isset( $this->config['enabled'] ) && $this->config['enabled'] == 1 && !$cache_enabled ) {
				if ( !$this->MDBC_enable() ) {
					unset( $this->config['enabled'] );
					unset( $saveconfig['enabled'] );
				} else {
					$this->config['lastclean'] = time();
				}
			}
		
			$file = @fopen( WP_CONTENT_DIR."/db-config.ini", 'w+' );
			if ( $file ) {
				fwrite( $file, serialize( $saveconfig ) );
				fclose( $file );
				echo '<div id="message" class="updated fade"><p>';
				_e('Settings saved.', 'multidb-cache-hash');
				echo '</p></div>';
			} else {
				echo '<div id="message" class="error"><p>';
				_e('Settings can\'t be saved. Please <a href="http://codex.wordpress.org/Changing_File_Permissions" target="blank">chmod 755</a> file <u>config.ini</u>', 'multidb-cache-hash');
				echo '</p></div>';
			}
		}
?>
<div class="wrap">
<?php screen_icon(); ?>
<form method="post">
<?php wp_nonce_field('multidb-cache-hash-update-options'); ?>
<h2><?php _e('Multi DB Cache - Options', 'multidb-cache-hash'); ?></h2>
<p class="submit">
	<input class="button" type="submit" name="save" value="<?php _e('Save', 'multidb-cache-hash'); ?>">  
	<input class="button" type="submit" name="clear" value="<?php _e('Clear the cache', 'multidb-cache-hash'); ?>">
	<input class="button" type="submit" name="clearold" value="<?php _e('Clear the expired cache', 'multidb-cache-hash'); ?>">
</p> 
<h3><?php _e('Configuration', 'multidb-cache-hash'); ?></h3>
<table class="form-table">
	<tr valign="top">
		<?php $this->MDBC_field_checkbox( 'enabled', __('Enable', 'multidb-cache-hash') ); ?>
	</tr>
	<tr valign="top">
		<?php $this->MDBC_field_text( 'timeout', __('Expire a cached query after', 'multidb-cache-hash'),
			__('minutes. <em>(Expired files are deleted automatically)</em>', 'multidb-cache-hash'), 'size="5"' ); ?>
	</tr>
</table>

<h3><?php _e('Additional options', 'multidb-cache-hash'); ?></h3>
<table class="form-table">
	<tr valign="top">
		<?php $this->MDBC_field_text( 'filter', __('Cache filter', 'multidb-cache-hash'), 
			'<br/>'.__('Do not cache queries that contains this input contents. Divide different filters with \'|\' (vertical line, e.g. \'_posts|_postmeta\')', 'multidb-cache-hash'), 'size="100"' ); ?>
	</tr>
	<tr valign="top">
		<?php $this->MDBC_field_text( 'loadstat', __('Load stats template', 'multidb-cache-hash'), 
			'<br/>'.__('It shows resources usage statistics in your template footer. To disable view just leave this field empty.<br/>{timer} - generation time, {queries} - count of queries to DB, {cached} - cached queries, {memory} - memory', 'multidb-cache-hash'), 'size="100"' ); ?>
	</tr>
</table>

<h3><?php _e('Memcache servers if available', 'multidb-cache-hash'); ?></h3>
<?php echo __('You can configure memcache servers here. This will be used instead file cache if module can connect to any of those', 'multidb-cache-hash');?><br />
<?php $this->MDBC_field_checkbox( 'use_memcache', '<b>' . __('Use memcache', 'multidb-cache-hash') . '</b>' ); ?>
<table class="form-table">
	<?php 
		for ($i = 0; $i < 10 ; $i++)
		{?>
	<tr valign="top">
		<th>Memcache server[<?php echo $i?>]</th>
		<td>host: <input type="text" name="options[memcache_servers][<?php echo $i?>][host]" value="<?php echo isset($this->config['memcache_servers'][$i]['host']) ?$this->config['memcache_servers'][$i]['host'] : '' ?>" /></td>
		<td>port: <input type="text" name="options[memcache_servers][<?php echo $i?>][port]" value="<?php echo isset($this->config['memcache_servers'][$i]['port']) ?$this->config['memcache_servers'][$i]['port'] : '' ?>" /></td>
	</tr>
	<?php } ?>
</table>


<h3><?php _e('Advanced', 'multidb-cache-hash'); ?></h3>

<p class="submit">
	<input class="button" type="submit" name="save" value="<?php _e('Save', 'multidb-cache-hash'); ?>">  
	<input class="button" type="submit" name="clear" value="<?php _e('Clear the cache', 'multidb-cache-hash'); ?>">
	<input class="button" type="submit" name="clearold" value="<?php _e('Clear the expired cache', 'multidb-cache-hash'); ?>">
</p>      
</form>
</div>
<?php
	}
	
	// Other functions used on options page
	function MDBC_request( $name, $default=null ) {
		if ( !isset( $_POST[$name]) ) {
			return $default;
		}
		
		return $_POST[$name];
	}
	
	function MDBC_field_checkbox( $name, $label='', $tips='', $attrs='' ) {
		echo '<th scope="row">';
		echo '<label for="options[' . $name . ']">' . $label . '</label></th>';
		echo '<td><input type="checkbox" ' . $attrs . ' name="options[' . $name . ']" value="1" ';
		checked( isset( $this->config[$name] ) && $this->config[$name], true );
		echo '/> ' . $tips . '</td>';
	}
	
	function MDBC_field_text($name, $label='', $tips='', $attrs='') {
		if ( strpos($attrs, 'size') === false ) {
			$attrs .= 'size="30"';
		}
		echo '<th scope="row">';
		echo '<label for="options[' . $name . ']">' . $label . '</label></th>';
		echo '<td><input type="text" ' . $attrs . ' name="options[' . $name . ']" value="' . 
			htmlspecialchars($this->config[$name]) . '"/>';
		echo ' ' . $tips;
		echo '</td>';
	}
	
	function MDBC_field_textarea( $name, $label='', $tips='', $attrs='' ) {
		if ( strpos( $attrs, 'cols' ) === false ) {
			$attrs .= 'cols="70"';
		}
		if ( strpos( $attrs, 'rows' ) === false ) {
			$attrs .= 'rows="5"';
		}
		
		echo '<th scope="row">';
		echo '<label for="options[' . $name . ']">' . $label . '</label></th>';
		echo '<td><textarea wrap="off" ' . $attrs . ' name="options[' . $name . ']">' .
			htmlspecialchars($this->config[$name]) . '</textarea>';
		echo '<br />' . $tips;
		echo '</td>';
	}
}

$wp_db_cache_reloaded = new MultiDBCache();

function get_num_cachequeries() {
	global $wpdb, $wp_db_cache_reloaded;
	if ( isset( $wpdb->num_cachequeries ) ) {
		// DB Module loaded
		return $wpdb->num_cachequeries;
	} elseif ( !isset( $wp_db_cache_reloaded->config['enabled'] ) || !$wp_db_cache_reloaded->config['enabled'] ) {
		// Cache disabled
		return 0;
	} else {
		// Probably conflict with another plugin or configuration issue :)
		return -1;
	}
}

function get_num_dml_queries() {
	global $wpdb, $wp_db_cache_reloaded;
	if ( isset( $wpdb->MDBC_num_dml_queries ) ) {
		// DB Module loaded
		return $wpdb->MDBC_num_dml_queries;
	} elseif ( !isset( $wp_db_cache_reloaded->config['enabled'] ) || !$wp_db_cache_reloaded->config['enabled'] ) {
		// Cache disabled
		return 0;
	} else {
		// Probably conflict with another plugin or configuration issue :)
		return -1;
	}
}

/* 
Function to display load statistics
Put in your template <? loadstats(); ?>
*/
function loadstats() {
	global $wp_db_cache_reloaded, $current_blog, $wpdb;

	if ( strlen( $wp_db_cache_reloaded->config['loadstat'] ) > 7 ) {
		$stats['timer'] = timer_stop();
		$replace['timer'] = "{timer}";
		
		$stats['normal'] = get_num_queries();
		$replace['normal'] = "{queries}";
		
		$stats['dml'] = get_num_dml_queries();
		$replace['dml'] = "{dml_queries}";
		
		$stats['cached'] = get_num_cachequeries();
		$replace['cached'] = "{cached}";
		
		if ( function_exists( 'memory_get_usage' ) ) {
			$stats['memory'] = round( memory_get_usage()/1024/1024, 2 ) . 'MB';
		} else {
			$stats['memory'] = 'N/A';
		}
		$replace['memory'] = "{memory}";
		
		$result = str_replace( $replace, $stats, $wp_db_cache_reloaded->config['loadstat'] );
		
		echo $result;
	}
	
	echo "\n<!-- ".$wpdb->MDBC_cachetype."cached by Multi DB Cache Blog id: ".$current_blog->blog_id . " site id: " . $current_blog->site_id ." -->\n";
	//echo var_export($wpdb->queries,true);
}

} // END

