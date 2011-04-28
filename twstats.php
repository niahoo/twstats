<?php

## Attention,
## Les clés section_id et counter_id sont enregistrées dès lors qu'on
## essaie de les lire depuis l'objet
## La lecture de counter_id() déclenche également l'enregistrement de 
## la section
## Les cookies et sessions, ainsi que les valeurs hit/visit/dayvisit
## ne sont modifiées que lors du commit




/**
 * Classe qui sert à recevoir la configuration de la base de données
 * et à distribuer des objets counter / reader
 */
 
class TWStats_Counter {

	public $_conf;

	
	/**
	 * Chemin dans les sections, sous forme de tableau
	 * exemple array('annuaire', 'immolocationoffre', 'ApptT2')
	 */ 
	public $_path;

	public $_key;
	
	public $_counter_id;
	public $_section_id;
	
	
	/**
	 * Champs à incrémenter. une valeur de 3 incrémentera les hits et visites
	 * @var binary,int
	 */
	public $increments;

	/**
	 * valeur de retour possible pour getSectionIDByPath
	 */
   // const unknown_section = false;

	/**
	 * Choix des compteurs à incrémenter
	 */
	const increment_hit = 1;
	const increment_visit = 2;
	const increment_dayvisit = 4;

  
  

	 function __construct($path, $key, $conf) {
		
		// Validation de la configuration
		
		if (!isset($conf['session_hash']) || !isset($conf['cookie_date_hash']) ||
			!isset($conf['cookie_ids_hash']) || !isset($conf['pdo']))
			throw new InvalidArgumentException('Bad configuration');
		
		
		if (!($conf['pdo'] instanceof PDO))
			throw new InvalidArgumentException('conf[pdo] is not a PDO instance');
		
		// Configuration de PDO : échec avec exceptions. L'ancienne conf
		// est remise en place à la destruction de l'objet
		
		$this->_conf = $conf;
		$this->_pdo  = $conf['pdo'];
		$this->_conf['previous_pdo_errmode'] = $this->_pdo->getAttribute(PDO::ATTR_ERRMODE);
		$this->_pdo->setAttribute(
			PDO::ATTR_ERRMODE,
			PDO::ERRMODE_EXCEPTION
		);
		
		// Validation des sections
		
		if (!is_array($path) || count($path) < 1)
			throw new InvalidArgumentException('Bad path or empty path');
		$this->path = $path;

		// Validation de la clé
		
		if ((string) $key == '')
			throw new InvalidArgumentException('Empty key');
		$this->_key  = $key;
		
		$this->increments = 0;
		
		if (!isset($this->_conf['table_prefix']))
			$this->_conf['table_prefix'] = '';
		
		

		
	}

	public function __destruct() {
		$this->_pdo->setAttribute(
			PDO::ATTR_ERRMODE,
			$this->_conf['previous_pdo_errmode']
		);
	}

	public function section_id() {
		if (empty($this->_section_id))
			$this->_section_id = $this->sectionIDFromPath($this->path);
		return $this->_section_id;
		
	}
	
	public function counter_id() {
		if (empty($this->_key_id)) {
			$query = sprintf('select * from %scounters where strkey like
			\'%s\' and section_id = %d',
			$this->conf('table_prefix'),
			$this->_key,
			$this->section_id());
			
			$rs_a = $this->conf('pdo')->query($query)->fetchAll(PDO::FETCH_ASSOC);
			
			$count = count($rs_a);
			if ($count == 0)
				$this->_counter_id = $this->registerNewCounter($this->_key, $this->section_id());
			elseif ($count == 1)
				$this->_counter_id = intval($rs_a[0]['id']);
			else
				throw new Exception("duplicate strkey/section_id .. too bad! ($count found)");
		
		}
		return $this->_counter_id;
	}


//==============================================================================
//=================== CREATION DES CLES ET DES SECTIONS ========================
//==============================================================================


	/**
	 * renvoie l'id d'une section selon le path, les sections sont créées
	 * si elles n'existent pas
	 */
	private function sectionIDFromPath($path) {
	  
		if (count($path) == 0)
			return 0;

		$section_name = array_pop($path); // <-- POP!
		$sql_id_by_name = sprintf(
						'select id, parent_id from %ssections where name like \'%s\'',
						$this->conf('table_prefix'),
						$section_name
		);
		$rs = $this->conf('pdo')->query($sql_id_by_name);
		$rows = $rs->fetchAll(PDO::FETCH_ASSOC);
		
		/* Ici la technique est simple :
		 * 	- 1 seule ligne, la section est trouvée
		 *  - X lignes, on compare le parent_id avec une récursion sur la 
		 *  pile $path
		 */
		
		$num_rows = count($rows);
		
		// if ($num_rows == 1)
// 			return $rows[0]['id'];
		// else {
			$parent_id = $this->sectionIDFromPath($path); // <-- see POP
			foreach ($rows as $row) 
				if ($row['parent_id'] == $parent_id)
					return $row['id'];
			// ici aucune correspondance n'a été établie, on crée			
			return $this->registerNewSection($section_name, $parent_id);
		// }	
	}


	/**
	 * Enregistre une section dans la base de données.
	 * Un parent_id 0 donnera une section de premier niveau.
	 * Renvoie l'id de la section nouvellement créée.
	 * @param string $name
	 * @param int $parent_id
	 * @return int
	 */
	private function registerNewSection($name, $parent_id) {
		$sql_insert = sprintf(
						'insert into %ssections (name, parent_id) VALUES (\'%s\', %d)',
						$this->conf('table_prefix'),
						$name,
						$parent_id
		);
		$pdo = $this->conf('pdo');
		try {
			$exec = $pdo->exec($sql_insert);
		} catch (PDOException $e) {
			throw new PDOException('[registerNewSection] ' . $e->getMessage(),
					$e->getCode());
		}
		return $pdo->lastInsertId();
	}

	/**
	 * Enregistre un item dans la base de données.
	 * Renvoie l'id de l'item créé.
	 * @param string $key
	 * @param int $section_id
	 * @return int
	 */
	private function registerNewCounter($strkey, $section_id) {
		
		
		$sql_insert = sprintf(
						'insert into %scounters (`strkey`, section_id) VALUES (\'%s\', %d)',
						$this->conf('table_prefix'),
						$strkey,
						$section_id
		);
// exit($sql_insert);
		$pdo = $this->conf('pdo');
		try {
			$exec = $pdo->exec($sql_insert);
		} catch (PDOException $e) {
			
			throw $e;
		}
		
		
		return intval($pdo->lastInsertId());
	}

	/*	 * é
	 * Cherche une section dans la base par son nom et le nom de ses parents
	 * En cherchant d'abord par le nom, si plusieurs sections de même nom sont
	 * trouvées, on tente de trouver la bonne grâce à son parent
	 * @param array $sections
	 */

	

//==============================================================================
//=================== INCREMENTATION DES COMPTEURS =============================
//==============================================================================

	/**
	 * Incrémente le compteur de hits à chaque appel
	 */
	public function hit() {
		$this->increments = $this->increments | self::increment_hit;
		return $this;
	}

	/**
	 * Incrémente le compteur uniquement si le marqueur n'est pas présent en
	 * session
	 */
	public function visit() {
		
		if (!session_id())
			session_start();
		
		if (isset($_SESSION)
				&& !isset($_SESSION[$this->conf('session_hash')][$this->counter_id()])) {
			$this->increments = $this->increments | self::increment_visit;			
		}
		return $this;
	}

	/**
	 * Incrémente le compteur si la valeur du cookie n'est pas la date du jour
	 * Un seul cookie pour tous les keyid est utilisé, séparé par _
	 * + le cookie pour la date
	 */
	public function dayvisit() {
		$today = (int) date('Ymd');
		/* si le cookie n'est pas du jour */
		if (!$this->already_visited_today() ||
			(int) $_COOKIE[$this->conf('cookie_date_hash')] != $today) {
			$this->increments = $this->increments | self::increment_dayvisit;
		}
		/* si le cookie est du jour, on va voir si la page actuelle est vue */ 
		return $this;
	}

	public function already_visited_today() {
		return (
			isset($_COOKIE[$this->conf('cookie_date_hash')]) && 
			(int) $_COOKIE[$this->conf('cookie_date_hash')] == (int) date('Ymd') &&
			isset($_COOKIE[$this->conf('cookie_ids_hash')]) && 
			strstr($_COOKIE[$this->conf('cookie_ids_hash')], '_' . $this->counter_id()) !== false
		);
	}

	/**
	 * Remplace ou crée le cookie $this->cookie_date_hash par la date du jour
	 */
	private function setDateCookie() {
		$today = (int) date('Ymd');
		$expire = time() + 3600 * 24; // 1 jour
		return 
			setcookie($this->conf('cookie_date_hash'), $today, $expire) &&
			setcookie($this->conf('cookie_ids_hash')); // On supprime également les pages du jour précédent		
	}

	/**
	 * Renvoie true si le keyid est ajouté, false s'il y est déjà
	 */
	private function addKey2Cookie() {
		$expire = time() + 3600 * 24; // 1 jour
		
		if ($this->already_visited_today())
			return false;
		
		if (isset($_COOKIE[$this->conf('cookie_ids_hash')]))
			$base_str = $_COOKIE[$this->conf('cookie_ids_hash')];
		else 
			$base_str = '';
			
		setcookie($this->conf('cookie_ids_hash'), $base_str . '_' . $this->counter_id(), $expire);		
		return true;
	}

	/**
	 * Donne une pertie de requête SQL destinée à incrémenter les champs du
	 * compteur, en fonction des valeurs contenues dans this->increments
	 * @return string
	 */
	private function getIncrementsQuery() {
		$tabsql = array();
		$this->increments & self::increment_hit
				&& $tabsql[] = 'hits = hits + 1';
		$this->increments & self::increment_visit
				&& $tabsql[] = 'visits = visits + 1';
		$this->increments & self::increment_dayvisit
				&& $tabsql[] = 'day_visits = day_visits + 1';
		if (count($tabsql) > 0)
			return implode(', ', $tabsql);
		else {
			throw new Exception('Can\'t count nothing..');
		}
	}

	/**
	 * Déclenche l'update des compteurs dans la base de données.
	 * Si le compteur et le jour n'existaient pas déjà, mettre $try_insert à
	 * true (valeur par défaut) permet de le créer
	 * Reset la valeur de this->increments à 0;
	 * @param bool $try_insert
	 */
	public function commit($try_insert=true) {
		$sql_update = sprintf(
						'update %scounters_days' .
						' set %s where counter_id = %s and day_date = date(now())',
						$this->conf('table_prefix'),
						$this->getIncrementsQuery(),
						$this->counter_id()
		);

	


		$rowcount = $this->conf('pdo')->exec($sql_update);
		if ($rowcount != 1 && $try_insert) {
			// ici, enregistrer le compteur à la date du jour dans la base,
			// puis réessayer
			$this->registerNewCountDay(); // on crée le compteur
			return $this->commit(false); //:
			/* on passe à false,
			 * si la création ne fonctionne pas
			 * comme ça pas de boucle infinie
			 */
		}
		
		/* Si une visit à été demandée, commit sur la session */
		$_SESSION[$this->conf('session_hash')][$this->counter_id()] = 'increment';
		
		/* Si une dayvisit à été demandée, on commit aussi sur les cookies */
		$this->increments & self::increment_dayvisit &&				
		$this->setDateCookie() && $this->addKey2Cookie();
		
		$this->increments = 0;
		return $this;
	}

	/**
	 * Enregistre dans la base une paire item/jour avec les compteurs à 0
	 */
	private function registerNewCountDay() {
		$sql_insert = sprintf(
						'insert into %scounters_days' .
						' (counter_id, day_date, hits,visits, day_visits)' .
						' VALUES (%d, date(now()), 0,0,0)',
						$this->conf('table_prefix'),
						$this->counter_id()
		);
		$exec = $this->conf('pdo')->exec($sql_insert);
	}

	public function conf($key) { return $this->_conf[$key]; }

//==============================================================================
//========================== LECTURE DES COMPTEURS =============================
//==============================================================================

	/**
	 * $day_date = format Y-m-d
	 * */
	public function read_date($day_date) {
		
		
		$sql = sprintf(
			'select hits, visits, day_visits from %scounters_days ' .
			'where counter_id = :counter_id and day_date = :day_date',
			$this->conf('table_prefix'));
		$statement = $this->conf('pdo')->prepare($sql);				
		$statement->execute(array('counter_id' => $this->counter_id(),
								  'day_date' => $day_date));
		if ($statement->rowCount())
			return $statement->fetch(PDO::FETCH_ASSOC);
		else return array('hits'=>0,'visits'=>0,'day_visits'=>0);
	}

	/*
	 * both date params included
	 * */
	public function read_interval($day_date_start, $day_date_end) {
		
		
		$sql = sprintf(
			'select hits, visits, day_visits from %scounters_days ' .
			'where counter_id = :counter_id and 
			day_date >= :day_date_start and 
			day_date <= :day_date_end',
			$this->conf('table_prefix'));
		$statement = $this->conf('pdo')->prepare($sql);				
		$statement->execute(array('counter_id' => $this->counter_id(),
								  'day_date_start' => $day_date_start,
								  'day_date_end' => $day_date_end));
		return $statement->fetchAll(PDO::FETCH_ASSOC);
	}

}



class TWStats_UI {

	private $_conf;

	public function __construct($conf) {
		if (!($conf['pdo'] instanceof PDO))
			throw new InvalidArgumentException('conf[pdo] is not a PDO instance');
			
		$this->_conf = $conf;	
			
		if (!isset($this->_conf['table_prefix']))
			$this->_conf['table_prefix'] = '';
		
		$this->_pdo  = $conf['pdo'];
		$this->_conf['previous_pdo_errmode'] = $this->_pdo->getAttribute(PDO::ATTR_ERRMODE);
		$this->_pdo->setAttribute(
			PDO::ATTR_ERRMODE,
			PDO::ERRMODE_EXCEPTION
		);	
	}
	
	
	public function __destruct() {
		$this->_pdo->setAttribute(
			PDO::ATTR_ERRMODE,
			$this->_conf['previous_pdo_errmode']
		);
	}
	/**
	 * Renvoie les sous-sections de la section passée en paramètre,
	 * on passe l'ID
	 * @param integer $parent_section_id
	 * @return array The sections
	 */
	public function getSubSections_loop($parent_section_id) {
		$sections = array();
		$sql = sprintf(
						'select * from `%ssections` where `parent_id` = %d',
						$this->conf('table_prefix'),
						$parent_section_id
		);
		$rs = $this->conf('pdo')->query($sql);
		$sections = $rs->fetchAll(PDO::FETCH_ASSOC);
		foreach ($sections as &$section) {
			$section['childs'] = $this->getSubSections_loop($section['id']);
		}
		unset($section);
		return $sections;
	}

	/**
	 * Renvoie l'arbre complet des sections
	 *
	 * @return array The sections
	 */
	public function getSectionsTree() {
		return array(
			'name' => 'root',
			'id' => 0,
			'childs' => $this->getSubSections_loop(0)
		);
	}

	public function getItemsFromSection($section_id) {
		$sql = sprintf(
						'select id,strkey,name from `%sitem` where `section_id` = %d',
						$this->conf('table_prefix'),
						$section_id
		);
		$rs = $this->conf('pdo')->query($sql);
		$items = $rs->fetchAll(PDO::FETCH_ASSOC);
		return $items;
	}

	public function CLI_Tree($section_id=0) {
		$data = $this->getSubSections_loop($section_id);		
		return $this->CLI_Tree_loop(0, $data);
	}
	
	private function CLI_Tree_loop($level, $items, $last=false) {
		$tree = '';
		$decoration = '';
		$margin = $last ? '    ' : '|   ';
		if ($level)
			$decoration = str_pad('', 4*($level-1), $margin).'|--';
		
		if (count($items)) {
			$last = array_pop($items);
		
			foreach($items as $item => $info) {
				$tree .= $decoration.$info['name']."\n";
				$tree .= $this->CLI_Tree_loop($level+1, $info['childs']);
			}		
			
			$decoration = '';
			if ($level)
				$decoration = str_pad('', 4*($level-1), $margin).'`--';
			$tree .= $decoration.$last['name']."\n";
			$tree .= $this->CLI_Tree_loop($level+1, $last['childs'], true);
		}
		
		return $tree;
	} 
	
	


	/**
	 * Renvoie les 3 chiffres du mois
	 * déclenche la mise en cache si nécéssaire
	 * @param int $item_id
	 */
	public function readMonth($item_id, $year, $month) {
		$sql_cache = sprintf(
						'select id,strkey,name from `%sitem` where `item_id` = %d' .
						'and yearmonth = %d%d',
						$this->conf('table_prefix'),
						$item_id,
						$year,
						$month
		);
		$rs = $this->conf('pdo')->query($sql_cache);
		$rows = $rs->fetchAll(PDO::FETCH_ASSOC);
		if (count($rows) != 1) {
			$month_stats = $this->compileMonth($item_id, $year, $month);
			if (intval(date('Ym')) < intval($year . $month)) {
				$this->storeMonthCache($month_stats);
			}
			return $month_stats;
		} else {
			return $rows[0];
		}
	}
	public function conf($key) { return $this->_conf[$key]; }
}

