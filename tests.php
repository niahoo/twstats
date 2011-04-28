<?php

require_once 'twstats.php';
require_once '../simpletest/autorun.php'; //twstats.php';

$PDO_dsn = 'mysql:dbname=twstats;host=127.0.0.1';
$PDO_user = 'twstats';
$PDO_password = 'twstats';

$twstats_config = array(
	'session_hash'     	=> '7070c9aad470d9f235e5c3d10c5fdSES',
	'cookie_date_hash' 	=> '6cee7172ea0eb6ef0a64b293d0189DAT',
	'cookie_ids_hash'  	=> '2cad6ed683f7ffc0c2d9fce2292b7IDS',
	'pdo' 			   	=>  new PDO($PDO_dsn, $PDO_user, $PDO_password)
);


class TestOfCounter extends UnitTestCase{
	
	
	function test_CLEANUP() {
		global $twstats_config;
		$pdo = $twstats_config['pdo'];
		//$pdo->exec('delete from counters');
		//$pdo->exec('delete from counters_days');
		//$pdo->exec('delete from sections');
	}
	
	function test_counter1 () {
		global $twstats_config;
		$this->assertTrue($twstats_config['pdo'] instanceof PDO);
		$pdo = $twstats_config['pdo'];
		
		// aucune clé pour le moment
		//$this->assertTrue(count($pdo->query('select * from counters')->fetchAll()) == 0);
		$path = array('sectiontest1', 'subtest1', 'sstest1');
		$key  = 'keytest1';
		// création du compteur
		$tw = new TWStats_Counter($path, $key, $twstats_config);
		$this->assertTrue($tw instanceof TWStats_Counter);
		
		// il ne doit pas y avoir de clé pour le moment non plus
		//$this->assertTrue(count($pdo->query('select * from counters')->fetchAll()) == 0);
		
		//$this->assertTrue(is_integer($tw->counter_id()));
		
		//$tw->hit()->visit()->dayvisit()->commit();
		//$tw->hit()->visit()->dayvisit()->commit();
		//$tw->hit()->visit()->dayvisit()->commit();
		//$tw->hit()->visit()->dayvisit()->commit();
	}
	
	function test_many_sections() {
		global $twstats_config;
		
		$keyC = 2;
		
		
		
		$path = array('sectiontest1', 'subtest1', 'sstest1');
		$key  = "keytest$keyC"; $keyC++;
		// création du compteur
		$tw = new TWStats_Counter($path, $key, $twstats_config);
		
		$this->assertTrue(is_integer($tw->counter_id()));
		
		
		
		$path = array('sectiontest1', 'subtest1', 'sstest1');
		$key  = "keytest$keyC"; $keyC++;
		// création du compteur
		$tw = new TWStats_Counter($path, $key, $twstats_config);
		
		$this->assertTrue(is_integer($tw->counter_id()));
		
		
		
		$path = array('sectiontest1', 'subtest2', 'sstest1');
		$key  = "keytest$keyC"; $keyC++;
		// création du compteur
		$tw = new TWStats_Counter($path, $key, $twstats_config);
		
		$this->assertTrue(is_integer($tw->counter_id()));
		
		
		
		$path = array('sectiontest1', 'subtest1', 'sstest1');
		$key  = "keytest$keyC"; $keyC++;
		// création du compteur
		$tw = new TWStats_Counter($path, $key, $twstats_config);
		
		$this->assertTrue(is_integer($tw->counter_id()));
		
		
		
		$path = array('sectiontest1', 'subtest1', 'sstest1');
		$key  = "keytest$keyC"; $keyC++;
		// création du compteur
		$tw = new TWStats_Counter($path, $key, $twstats_config);
		
		$this->assertTrue(is_integer($tw->counter_id()));
		
		
		
	}
	
	
	function test_trees () {
		global $twstats_config;
		$this->assertTrue($twstats_config['pdo'] instanceof PDO);
		$pdo = $twstats_config['pdo'];
		
		$ui = new TWStats_UI($twstats_config);
		
		echo('<pre>'.$ui->CLI_Tree(0));
	}
	
	
}
