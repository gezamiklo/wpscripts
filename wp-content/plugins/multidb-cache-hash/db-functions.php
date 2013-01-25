<?php
/**
 * Cache framework
 * Author: Geza Miklo
 * Based on DB Cache by Dmitry Svarytsevych
 */

/*  Original code Copyright Dmitry Svarytsevych
    Modifications 
 				Copyright 2009  Daniel Frużyński  (email : daniel [A-T] poradnik-webmastera.com)
 				Copyright 2011 Geza Miklo	(email: geza.miklo@gmail.com)

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

// Cache directory
// Need this here, because some people may upgrade manually by overwriting files 
// without deactivating cache
global $current_site, $current_blog;
if ( !defined( 'MDBC_CACHE_DIR' ) ) {
        define( 'MDBC_CACHE_DIR', WP_CONTENT_DIR.'/multidb-cache-hash' );
}

/*
 * Interface for multidb-cache cache class
 */
interface IMdbCache {
	
	// Set storage dir for next operation(s)
	function set_storage( $context = '' );

	// Load data from cache for given tag
	function load( $tag );
	
	// Save data to cache for given tag
	function save( $value, $tag );
	
	// Remove data from cache for given tag
	function remove( $tag = '', $dir = false, $remove_expired_only = false );
	
	//Cleans given part of cache
	function clean( $remove_expired_only = true, $cleanFolder = '' );
	
	// Setup cache dirs
	// Return true on success, false otherwise
	function install( $global = false );
	
	// Remove cache dirs
	// Return true on success, false otherwise
	function uninstall( $global = false );
}

class mdbCache implements IMdbCache
{
	var $cache = null;
	static $cachetype = null;
	var $ok = false;
	
	function mdbCache($servers = null)
	{
		if (!is_null($this->cache)){return $this->cache;}
		
		if (!empty($servers) && count($servers))
		{
			$this->cache = &new mdbMemCache($servers);
			//echo "using memcache :D";
			if ($this->cache->ok)
			{
				$this->cachetype = 'Mem';
				$this->ok = true;
			}
		}
		
		//if no servers or failed to connect filecache
		if ($this->cache == null || $this->cache == false)
		{
			$this->cache = &new mdbFileCache();
			$this->cachetype = 'File';
			$this->ok = true;
			//echo "using filecache :)";
		}
		return $this;
	}
	
	//Returns subdomain from url
	function getSubDomain()
	{
		$this->subDomain = $_SERVER['HTTP_HOST'];
	}

	//Returns subdomain from url
	function setSubDomain($subdomain)
	{
		if(empty($subdomain)) return;
		$this->subDomain = $subdomain;
	}

	// Set storage dir for next operation(s)
	function set_storage( $context = '' ){ return $this->cache->set_storage($context);}
	
	// Load data from cache for given tag
	function load( $tag ){ return $this->cache->load($tag);}
	
	// Save data to cache for given tag
	function save( $value, $tag ){ return $this->cache->save($value, $tag);}

	// Load data from cache for given tag
	function set( $tag ){ $this->set_storage('general'); return $this->cache->load($tag);}

	// Save data to cache for given tag
	function get( $value, $tag ){ $this->set_storage('general'); return $this->cache->save($value, $tag);}


	// Remove data from cache for given tag
	function remove( $tag = '', $dir = false, $remove_expired_only = false ){ return $this->cache->remove($tag, $dir, $remove_expired_only);}
	
	//Cleans given part of cache
	function clean( $remove_expired_only = true,  $cleanFolder = '' ){ return $this->cache->clean($remove_expired_only, $cleanFolder);}
	
	// Setup cache dirs
	// Return true on success, false otherwise
	function install( $global = false ){ return $this->cache->install($global);}
	
	// Remove cache dirs
	// Return true on success, false otherwise
	function uninstall( $global = false ){ return $this->cache->uninstall($global);}
}

/*
 * Implements file cache for multidb-cache
 */
class mdbFileCache extends mdbCache{
	// Cache lifetime - by default 1800 sec = 30 min
	var $lifetime = 1800;
	
	// Base storage path for global data
	var $base_storage_global = null;
	
	// Base storage patch for local data
	var $base_storage = null;
	
	// Path to current storage dir
	var $storage = null;
	
	var $subDomain = '';
	var $ok = true;
	
	// All subdirs
	var $folders = array( 'options', 'links', 'terms', 'users', 'posts', 'comments', '', 'general' );
	
	// Constructor
	function mdbFileCache() {
		$this->getSubDomain();
		$this->set_storage();
	}
	

	
	// Set storage dir for next operation(s)
	function set_storage( $context = '') {
		global $current_blog;
		
		$this->base_storage = MDBC_CACHE_DIR . (!empty($this->subDomain) ? '/'.$this->subDomain  : '');
		if (!is_dir($this->base_storage))
		{
			mkdir($this->base_storage, 0755, true);
			foreach( $this->folders as $folder ) {
				$path = $this->base_storage . '/';
				if ( $folder != '' ) {
					$path .= $folder . '/';
					if (!is_dir($path)) mkdir($path, 0755, true);
				}
			}

		}
		$this->storage = MDBC_CACHE_DIR . (!empty($this->subDomain) ? '/'.$this->subDomain  : '');
		if (!is_dir($this->storage))
		{
			mkdir($this->storage, 0755, true);
			foreach( $this->folders as $folder ) {
				$path = $this->storage . '/';
				if ( $folder != '' ) {
					$path .= $folder . '/';
					if (!is_dir($path)) mkdir($path, 0755, true);
				}
			}
		}
		
		// Set per-context path
		if ( $context != '' ) {
			$this->storage .= '/' . $context;
			if (!is_dir($this->storage)) mkdir($this->storage, 0755, true);
		}
	}
	
	// Load data from cache for given tag
	function load( $tag ) {
		if ( $tag == '' ) {
			return false;
		}

		$file = $this->storage.'/'.$tag;
		$result = false;
		
		// If file exists
		if ( $filemtime = @filemtime( $file ) ) {
			$f = @fopen( $file, 'r' );
			if ( $f ) {
				@flock( $f, LOCK_SH );
				// for PHP5
				if ( function_exists( 'stream_get_contents' ) ) {
					$result = unserialize( stream_get_contents( $f ) );
				} else { // for PHP4
					$result = '';
					while ( !feof( $f ) ) {
		  				$result .= fgets( $f, 4096 );
					}
					$result = unserialize( $result );
				}
				@flock( $f, LOCK_UN );
				@fclose( $f );

				// Remove if expired
				if ( ( $filemtime + $this->lifetime - time() ) < 0 ) {
					$this->remove( $tag );
				}
			}
		}

		return $result;
	}
	
	// Save data to cache for given tag
	function save( $value, $tag ) {
		if ( $tag == '' || $value == '' ) {
			return false;
		}
		
		$file = $this->storage.'/'.$tag;
		
		$f = @fopen( $file, 'w' );
		if ( !$f ) {
			return false;
		}
		
		@flock( $f, LOCK_EX );
		@fwrite( $f, serialize( $value ) );
		@flock( $f, LOCK_UN );
		@fclose( $f );
		@chmod( $file, 0644 );

		return true;
	}
	
	// Remove data from cache for given tag
	function remove( $tag = '', $dir = false, $remove_expired_only = false ) {
		if ( $tag == '' ) {
			return false;
		}
		
		if ( $dir === false ) {
			$dir = $this->storage;
		}
		
		$file = $dir.'/'.$tag;

		if ( is_file( $file ) ) {
			if ( $remove_expired_only && ( @filemtime( $file ) + $this->lifetime - time() ) > 0 ) {
				return true;
			}
			if ( @unlink( $file ) ) {
				return true;
			}
		}
		
		return false;
	}
	
	function clean( $remove_expired_only = true, $cleanFolder = '' ) {
		$this->set_storage( '' );

		foreach( $this->folders as $folder ) {
			if (!empty($cleanFolder) && ($folder != $cleanFolder)) 
			{
				continue;
			}
			$path = $this->base_storage . '/';
			if ( $folder != '' ) {
				$path .= $folder . '/';
				if (!is_dir($path)) mkdir($path, 0755);
			}
			
			if ( $dir = @opendir( $path ) ) {
				while ( $tag = readdir( $dir ) ) {
					if ( ( $tag != '.' ) && ( $tag != '..' ) && ( $tag != '.htaccess' ) ) {
						$this->remove( $tag, $path, $remove_expired_only );
					}
				}
				closedir( $dir );
			}
		}
	}
	
	// Setup cache dirs
	// Return true on success, false otherwise
	function install( $global = false ) {
		$this->set_storage( '' );
		
		if (!is_dir($this->base_storage)) mkdir($this->base_storage,0755,true);
		
		foreach( $this->folders as $folder ) {
			$path = $this->base_storage . '/';
			if ( $folder != '' ) {
				$path .= $folder . '/';
			}
			
			if ( $folder != '' ) { // Skip base folder - it is already created
				if (!is_dir($path) && !@mkdir( $path, 0755, true ) ) {
					return false;
				}
			}
			if ( !@copy( MDBC_PATH.'/htaccess', $path.'/.htaccess' ) ) {
				return false;
			}
		}
		
		return true;
	}
	
	// Remove cache dirs
	// Return true on success, false otherwise
	function uninstall( $global = false ) {
		$this->set_storage( '' );
		
		$this->clean( false );
		
		foreach( $this->folders as $folder ) {
			$path = $this->base_storage . '/';
			if ( $folder != '' ) {
				$path .= $folder . '/';
			}
			
			@unlink( $path.'.htaccess' );
			@rmdir( $path );
		}
	}
}


/*** MEMCACHE ***/

class mdbMemCache implements IMdbCache{
	// Cache lifetime - by default 1800 sec = 30 min
	var $lifetime = 1800;
	
	// Base storage path for global data
	var $base_storage_global = null;
	
	// Base storage patch for local data
	var $base_storage = null;
	
	// Path to current storage dir
	var $storage = null;
	
	var $subDomain = '';
	var $ok = false;
	
	// All subdirs
	var $folders = array( 'options', 'links', 'terms', 'users', 'posts', 'comments', '', 'general' );
	
	var $nsMemcache = null;
	
	// Constructor
	function mdbMemCache($servers=null) {
		
		if (!class_exists('namespacedMemcache'))
		{
			require_once(MDBC_PATH . '/namespacedMemcache.php');
		}
		
		$this->nsMemcache = &new namespacedMemcache($servers);
		
		if ($this->nsMemcache->mcache == null)
		{
			$this->ok = false;
			return false;
		}

		$this->getSubDomain();
		$this->set_storage('');
		$this->ok = true;
		return $this;
	}
	
	function getSubDomain()
	{
			$this->subDomain = $_SERVER['HTTP_HOST'];
	}
	
	// Set storage dir for next operation(s)
	function set_storage( $context = '' ) {
		//global $current_blog;
		
		$this->base_storage = MDBC_CACHE_DIR . (!empty($this->subDomain) ? '/'.$this->subDomain  : '');

		$this->storage = MDBC_CACHE_DIR . (!empty($this->subDomain) ? '/'.$this->subDomain  : '');
		
		$this->storage .= '/' . $context;

		//echo '<hr />'.$this->storage;

		
	}
	
	// Load data from cache for given tag
	function load( $tag ) {
		//echo '<hr />'.$this->storage;
		if ( $tag == '' ) {
			return false;
		}
		if (empty($this->nsMemcache)) return false;

		$file = $this->storage.'/'.$tag;
		
		$result = $this->nsMemcache->get($tag,null,$this->storage);

		return $result;
	}
	
	// Save data to cache for given tag
	function save( $value, $tag ) {
		//echo '<hr />'.$this->storage;
		if ( $tag == '' || $value == '' ) {
			return false;
		}
		if (empty($this->nsMemcache)) return false;
		
		//$file = $this->storage.'/'.$tag;
		
		$result = $this->nsMemcache->set($tag, $value, null, null, $this->storage);

		return $result;

	}
	
	// Remove data from cache for given tag
	function remove( $tag = '', $dir = false, $remove_expired_only = false ) {
		if ( $tag == '' ) {
			return false;
		}
		if (empty($this->nsMemcache)) return false;

		if ( $dir === false ) {
			$dir = $this->storage;
		}
		
		$file = $dir.'/'.$tag;

		$this->nsMemcache->delete($tag,null,$this->storage);
		
		return false;
	}
	
	function clean( $remove_expired_only = true, $cleanFolder = '' ) {
		if (empty($this->nsMemcache)) return false;
		$this->set_storage( );

		if ($cleanFolder == '')
		{
			$this->nsMemcache->flush();
			return "flushed";
		}
		else
		{
			$this->nsMemcache->invalidateNameSpace($this->storage.$cleanFolder);
			return "invalidated";
		}
		
	}
	
	// Setup cache dirs
	// Return true on success, false otherwise
	function install( $global = false ) {
		$this->set_storage( '' );
		
		if (!is_dir($this->base_storage)) mkdir($this->base_storage,0755,true);
		
		foreach( $this->folders as $folder ) {
			$path = $this->base_storage . '/';
			if ( $folder != '' ) {
				$path .= $folder . '/';
			}
			
			if ( $folder != '' ) { // Skip base folder - it is already created
				if (!is_dir($path) && !@mkdir( $path, 0755, true ) ) {
					return false;
				}
			}
			if ( !@copy( MDBC_PATH.'/htaccess', $path.'/.htaccess' ) ) {
				return false;
			}
		}
		
		return true;
	}
	
	// Remove cache dirs
	// Return true on success, false otherwise
	function uninstall( $global = false ) {
		$this->set_storage( '' );
		
		$this->clean( false );
		
		foreach( $this->folders as $folder ) {
			$path = $this->base_storage . '/';
			if ( $folder != '' ) {
				$path .= $folder . '/';
			}
			
			@unlink( $path.'.htaccess' );
			@rmdir( $path );
		}
	}
}

