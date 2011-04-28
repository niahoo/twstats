<?php

require_once 'twstats.php';

$PDO_dsn = 'mysql:dbname=twstats;host=127.0.0.1';
$PDO_user = 'twstats';
$PDO_password = 'twstats';

$twstats_config = array(
	'session_hash'     	=> '7070c9aad470d9f235e5c3d10c5fdSES',
	'cookie_date_hash' 	=> '6cee7172ea0eb6ef0a64b293d0189DAT',
	'cookie_ids_hash'  	=> '2cad6ed683f7ffc0c2d9fce2292b7IDS',
	'pdo' 			   	=>  new PDO($PDO_dsn, $PDO_user, $PDO_password)
);


	
	
	
	
	
		
	
	
$path = array('tests', 'webmode');
$key  = 'test1';
$tw = new TWStats_Counter($path, $key, $twstats_config);
$tw->hit()->visit()->dayvisit()->commit();
$ui = new TWStats_UI($twstats_config);		
echo 'ok!',
	'<pre>',
	$ui->CLI_Tree(),
	print_r($tw->read_date(date('Y-m-d')), true),
	'</pre>';
