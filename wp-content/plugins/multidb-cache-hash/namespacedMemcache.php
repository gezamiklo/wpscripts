<?php

/*
 * @abstract 2011.11.22. Memcache class based on the idea from: http://code.google.com/p/memcached/wiki/FAQ#Deleting_by_Namespace
 * 
		Namespaces
		memcached does not support namespaces. However, there are some options to simulate them.

		Simulating Namespaces with key prefixes
		If you simply want to avoid key colision between different types of data, simply prefix your key with a useful string. For example: "user_12345", "article_76890".

		Deleting by Namespace
		While memcached does not support any type of wildcard deleting or deletion by namespace (since there are not namespaces), there are some tricks that can be used to simulate this. They do require extra trips to the memcached servers however.

		Example, in PHP, for using a namespace called foo:

		$ns_key = $memcache->get("foo_namespace_key");
		// if not set, initialize it
		if($ns_key===false) $memcache->set("foo_namespace_key", rand(1, 10000));
		// cleverly use the ns_key
		$my_key = "foo_".$ns_key."_12345";
		$my_val = $memcache->get($my_key);

		//To clear the namespace do:
		$memcache->increment("foo_namespace_key");
 * @author Geza Miklo (geza.miklo@gmail.com)
 * @since v1.1
 */

class namespacedMemcache extends Memcache{
	
	//Variable for namespaces
	var $nameSpaces = array();
	
	//Memcache object used
	var $mcache = null;
	var $loaded = false;
	
	var $nsKeyExpires = 86400;
	var $lastNsKey = '';
	var $ok = false;
	
	//Constructor
	public function namespacedMemcache($servers = array())
	{
		if ($this->loaded) return $this;
		if (empty($servers)) return false;
		
		$connected = false;
		$this->mcache = &new Memcache;
		foreach($servers as $server)
		{
			
			if ( ($test = memcache_connect($server['host'], $server['port'])) && $this->mcache->addServer($server['host'], $server['port']) )
			{
				$connected = true;
				$test->close();
			}
		}
		
		if (!$connected)
		{
			$this->mcache = null;
			return false;
		}
		
		$this->ok = true;
		$this->loaded = true;
		return $this;
	}
	
	//Sets a value in namespace on given key
	public function set($key, $data, $flags=null, $expire = null,  $nameSpace = '')
	{
		if (!$this->loaded) return false;
		$nsKey = $this->getNamespacedKey($key, $nameSpace);

		if (!(int)$expire) $expire = 900;
		
		return $this->mcache->set($nsKey, $data, $flags, $expire);
	}

	public function get($key, $flags = null, $nameSpace = '')
	{
		if (!$this->loaded) return false;
		$nsKey = $this->getNamespacedKey($key, $nameSpace);
		return $this->mcache->get($nsKey, $flags);		
	}
	
	public function delete($key, $timeout = null, $nameSpace = '')
	{
		if (!$this->loaded) return false;
		$nsKey = $this->getNamespacedKey($key, $nameSpace);
		return $this->mcache->delete($nsKey, $timout);		
	}
	
	public function getNamespacedKey($key, $nameSpace = '')
	{
		$this->lastNsKey = 'unloaded';
		if (!$this->loaded) return false;
		$nameSpace = trim($nameSpace);
		$key = trim($key);
		
		if (empty($nameSpace) && empty($key)) return '';
		
		if (empty($nameSpace) && !empty($key)) return $key;
		
		//If no namespace key was given before generate one
		if (false === ($nsPrefix = $this->mcache->get($nameSpace)) )
		{
			$nsPrefix = rand(1,10000);
			$this->mcache->set($nameSpace, $nsPrefix, null, $this->nsKeyExpires);
		}
		
		$this->nameSpaces[$nameSpace] = $nsPrefix;
		
		$this->lastNsKey ='NS_'.$nsPrefix . '_' . $key;
		
		return $this->lastNsKey;
	}
	
	public function flush()
	{
		if (!$this->loaded) return false;
		$this->mcache->flush();
	}

	public function invalidateNamespace($nameSpace)
	{
		if (!$this->loaded) return false;
		if (empty($nameSpace)) return false;

		//file_put_contents(MDBC_CACHE_DIR.'/clean.log', $nameSpace."\n" . var_export($this->nameSpaces,true)."\n\n", FILE_APPEND);
		
		$this->mcache->increment($nameSpace);
		$this->nameSpaces[$nameSpace]++;
	}
}