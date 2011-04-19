<?php

/**
 * Compteur twstats
 * Copyright Ludovic Demblans © 2010
 */

/**
 * Classe qui sert à recevoir la configuration de la base de données
 * et à distribuer des objets counter / reader
 */
class TWStats_Counter {

	public $_conf;
	/**
	 * Clé primaire de l'item dans la BDD
	 * @var int
	 */
	private $keyid;
	
	/**
	 * Chemin dans les sections, sous forme de tableau
	 * exemple array('annuaire', 'immolocationoffre', 'ApptT2')
	 */ 
	private $path;
	private $section_id;
	private $key;
	/**
	 * Champs à incrémenter. une valeur de 3 incrémentera les hits et visites
	 * @var binary,int
	 */
	private $increments;

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
		$this->key  = $key;
		
		$this->increments = 0;
		
		if (!isset($this->conf['table_prefix']))
			$this->conf['table_prefix'] = '';
		
	}

	public function __destruct() {
		$this->_pdo->setAttribute(
			PDO::ATTR_ERRMODE,
			$this->_conf['previous_pdo_errmode']
		);
	}

//==============================================================================
//=================== CLE DU COMPTEUR ==========================================
//==============================================================================

	/**
	 * Reçoit la clé (key) et le chemin de l'item dans les sections afin de
	 * trouver la clé primaire de l'item.
	 * Par item on entend élément auquel on associe un compteur.
	 * Si les variables $register_new_sections et $register_new_keys sont
	 * passées à false, on déclenche une exception en cas de clé ou de section
	 * inconnues.
	 *
	 * @param array $sections
	 * @param string $key
	 * @param bool $register_new_keys
	 * @param bool $register_new_sections
	 */
	public function counter($sections, $key, $register_new_keys=true, $register_new_sections=true) {

		$section_id = $this->getSectionId($sections);

		$sql_key_exists = sprintf(
						'select id from %1$sitem as i ' .
						'where i.strkey like \'%2$s\' and i.section_id = %3$d',
						self::$configuration['table_prefix'],
						$key,
						$section_id
		);
		$rs = self::$configuration['pdo_instance']->query($sql_key_exists);
		$rows = $rs->fetchAll();
		$num_rows = count($rows);
		if ($num_rows == 0)
			if ($register_new_keys)
				$this->keyid = $this->registerNewKey($key, $section_id);
			else
				throw new InvalidArgumentException('Bad key');
		elseif ($num_rows == 1) {
			$this->keyid = $rows[0][0];
		}

		$this->section_id = $section_id;

		return $this;
	}

//==============================================================================
//=================== CREATION DES CLES ET DES SECTIONS ========================
//==============================================================================


	/**
	 * renvoie l'id d'une section selon le path, les sections sont créées
	 * si elles n'existent pas
	 */
	private function getSectionId($path) {
	  
		if (count($path) == 0)
			return 0;

		$section_name = array_pop($sections);
		$sql_id_by_name = sprintf(
						'select id, parent_id from %ssections where name like \'%s\'',
						self::$configuration['table_prefix'],
						$section_name
		);
		$rs = self::$configuration['pdo_instance']->query($sql_id_by_name);
		$rows = $rs->fetchAll(PDO::FETCH_ASSOC);
		
		/* Ici la technique est simple :
		 * 	- 0 la section est créée, avec un parent si le tableau n'est 
		 *  pas vide
		 * 	- 1 seule ligne, la section est trouvée
		 *  - X lignes, on compare le parent_id avec une récursion sur la 
		 *  case suivante du tableau $path
		 */
		
		$num_rows = count($rows);
		if ($num_rows == 0)
				$parent_id_to_register = count($path) ? 
					$this->getSectionId($path) : 0;
		elseif ($num_rows == 1)
			return $rows[0]['id'];
		else 
			foreach ($rows as $row) 
				if ($row['parent_id'] === $this->getSectionId($sections));
					return $row['id'];
				   
		
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
						'insert into %ssection (name, parent_id) VALUES (\'%s\', %d)',
						self::$configuration['table_prefix'],
						utf8_encode($name),
						$parent_id
		);
		$pdo = self::$configuration['pdo_instance'];
		try {
			$exec = $pdo->exec($sql_insert);
		} catch (PDOException $e) {
			throw new PDOException('[registerNewSection] ' . $e->getMessage(),
					$e->getCode(), $e->getPrevious());
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
	private function registerNewKey($key, $section_id) {
		$sql_insert = sprintf(
						'insert into %sitem (`strkey`, section_id) VALUES (\'%s\', %d)',
						self::$configuration['table_prefix'],
						utf8_encode($key),
						$section_id
		);
// exit($sql_insert);
		$pdo = self::$configuration['pdo_instance'];
		try {
			$exec = $pdo->exec($sql_insert);
		} catch (PDOException $e) {
			throw new PDOException('[registerNewKey] ' . $e->getMessage(),
					$e->getCode(), $e->getPrevious());
		}
		return $pdo->lastInsertId();
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
		if (isset($_SESSION)
				&& !isset($_SESSION[self::session_hash][$this->keyid])) {
			$this->increments = $this->increments | self::increment_visit;
			$_SESSION[self::session_hash][$this->keyid] = 'increment';
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
		if (!isset($_COOKIE[self::cookie_date_hash])
				|| (int) $_COOKIE[self::cookie_date_hash] != $today) {
			$this->increments = $this->increments | self::increment_dayvisit;
			$this->setDateCookie();
			$this->addKey2Cookie();
		}
		/* si le cookie est du jour, on va voir si la page actuelle est vue */ elseif ($this->addKey2Cookie()) {
			$this->increments = $this->increments | self::increment_dayvisit;
		}
		return $this;
	}

	/**
	 * Remplace ou crée le cookie self::cookie_date_hash par la date du jour
	 */
	private function setDateCookie() {
		$today = (int) date('Ymd');
		$expire = time() + 3600 * 24; // 1 jour
		setcookie(self::cookie_date_hash, $today, $expire);
		/* On supprime également les pages du jour précédent */
		setcookie(self::cookie_ids_hash);
	}

	/**
	 * Renvoie true si le keyid est ajouté, false s'il y est déjà
	 */
	private function addKey2Cookie() {
		$expire = time() + 3600 * 24; // 1 jour
		if (isset($_COOKIE[self::cookie_ids_hash]))
			if (strstr($_COOKIE[self::cookie_ids_hash], '_' . $this->keyid))
				return false; // inutile d'ajouter l'id, il y est déjà
 else
				$base_str = $_COOKIE[self::cookie_ids_hash];
		else
			$base_str = '';
		setcookie(self::cookie_ids_hash, $base_str . '_' . $this->keyid, $expire);
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
						'update %sitem_day' .
						' set %s where item_id = %s and countday = date(now())',
						self::$configuration['table_prefix'],
						$this->getIncrementsQuery(),
						$this->keyid
		);

		$rowcount = self::$configuration['pdo_instance']->exec($sql_update);
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

		$this->increments = 0;
		return $this;
	}

	/**
	 * Enregistre dans la base une paire item/jour avec les compteurs à 0
	 */
	private function registerNewCountDay() {
		$sql_insert = sprintf(
						'insert into %sitem_day' .
						' (item_id, countday, hits,visits, day_visits)' .
						' VALUES (%d, date(now()), 0,0,0)',
						self::$configuration['table_prefix'],
						$this->keyid
		);
		$exec = self::$configuration['pdo_instance']->exec($sql_insert);
	}

//==============================================================================
//=================== LECTURE DES COMPTEURS ====================================
//==============================================================================
}

function twstats($conf) {
	return new TWStats($conf);
}

class TWStats_UI extends TWStats {

	public function __construct($conf) {
		parent::__construct($conf);
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
						'select * from `%ssection` where `parent_id` = %d',
						self::$configuration['table_prefix'],
						$parent_section_id
		);
		$rs = self::$configuration['pdo_instance']->query($sql);
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
						self::$configuration['table_prefix'],
						$section_id
		);
		$rs = self::$configuration['pdo_instance']->query($sql);
		$items = $rs->fetchAll(PDO::FETCH_ASSOC);
		return $items;
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
						self::$configuration['table_prefix'],
						$item_id,
						$year,
						$month
		);
		$rs = self::$configuration['pdo_instance']->query($sql_cache);
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

}

