<?php
/*
 * Sample config file for configuring multiple servers to use in MultiDB Cache Hash plugin
 * 
 * 
 * READ THE COMMENTS below carefully
 * 
 * @author Geza Miklo <geza.miklo@gmail.com>
 * @since v1.6
 */

			//DO NOT CHANGE THIS
			if (!defined('MDBC_REPLICATE'))
			{
				define('MDBC_REPLICATE',0);
				define('MDBC_HASHED',0);
			}


if (!defined('MDBC_POOL_MODE')) 
{
	define('MDBC_POOL_MODE', MDBC_REPLICATE);	// <-- TO SWITCH MODE EDIT THIS (MDBC_REPLICATE|MDBC_HASHED)
}

return array(
	
	//IMPORTANT : Always add the deafult connection with zero index OR start numbering with 1 leave it blank
	0	=> array(
			'host' => 'host0',
			'user' => 'user0',
			'pass' => 'pass0',
			'name' => 'dbname0'
		),
	//0 is a special value if not set here, the normal DB connection will take this place
	
	//From now you can add additional servers
	1	=> array(
			'host' => 'host1',
			'user' => 'user1',
			'pass' => 'pass1',
			'name' => 'dbname1'
		),
);
