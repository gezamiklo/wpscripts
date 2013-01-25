<?php

/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */

require_once('namespacedMemcache.php');

$servers = array(
	0 => array(
		'host'	=> '127.0.0.1',
		'port'		=> '11211',
	),
);

$nsm = new namespacedMemcache($servers);
var_dump($nsm);
if (!$nsm->ok)
{
	echo "Nincs memcache kapcsolat";
	exit(1);
}

echo "Setting a non namespaced 'nons' variable nons_something value";
if ($nsm->set('nons', 'nons_something'))
{
	echo "\tOK\n";
	var_dump($nsm->nameSpaces);
	
	echo "\n\n";
}
else
{
	echo "\tFAILED\n";
}

echo "Setting foo namespace bar variable something value";
if ($nsm->set('bar', 'something', null, null, 'foo'))
{
	echo "\tOK\n";
	var_dump($nsm->nameSpaces);
	echo "\n\n";
}
else
{
	echo "\tFAILED\n";
}

echo "Setting foo namespace bar1 variable something1 value";
if ($nsm->set('bar1', 'something1', null, null, 'foo'))
{
	echo "\tOK\n";
	var_dump($nsm->nameSpaces);
	echo "\n\n";
}
else
{
	echo "\tFAILED\n";
}

echo "Setting foo1 namespace foo1bar1 variable foo1bar1something1 value";
if ($nsm->set('foo1bar1', 'foo1bar1something1', null, null, 'foo1'))
{
	echo "\tOK\n";
	var_dump($nsm->nameSpaces);
	echo "\n\n";
}
else
{
	echo "\tFAILED\n";
}

echo "Getting values:\n";
echo "\t foo/bar:\t" . $nsm->get('bar', null, 'foo') . "\n";
echo "\t foo/bar1:\t" . $nsm->get('bar1', null, 'foo') . "\n";
echo "\t foo1/foo1bar1:\t" . $nsm->get('foo1bar1', null, 'foo1') . "\n";
echo "\n";

echo "\nInvalidating foo1 namespace from \n";
var_dump($nsm->nameSpaces);
$nsm->invalidateNamespace('foo1');
echo "\n\nto\n\n";
var_dump($nsm->nameSpaces);
echo "\n\n";
echo "Getting values:\n";
echo "\t nons:\t" . $nsm->get('nons') . "\n";
echo "\t foo/bar:\t" . $nsm->get('bar', null, 'foo') . "\n";
echo "\t foo/bar1:\t" . $nsm->get('bar1', null, 'foo') . "\n";
echo "\t foo1/foo1bar1:\t" . $nsm->get('foo1bar1', null, 'foo1') . "\t\t<-- this should be empty\n";
echo "\n";

