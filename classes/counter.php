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
	const increment_hit = 1;
	const increment_visit = 2;
	const increment_dayvisit = 4;

	private $_strkey;
	private $_id;
	private $_display_name;
	private $_section;
	private $_increments = 0;
	/**
	 *
	 * @var TWStats_Application
	 */
	private $_app;

	public function __construct($key, TWStats_Section $section, TWStats_Application $app) {



//		Validation de la clé
		if ((string) $key == '')
			throw new InvalidArgumentException('Empty key');
		$this->_strkey = TWStats_Utils::string_simplification($key);

		$this->_app = $app;

		$this->_section = $section;
	}

	public function hit() {
		$this->_increments = $this->_increments | self::increment_hit;
		return $this;
	}

	private function getIncrementsQuery() {
		$tabsql = array();
		$this->_increments & self::increment_hit
			&& $tabsql[] = 'hits = hits + 1';
		$this->_increments & self::increment_visit
			&& $tabsql[] = 'visits = visits + 1';
		$this->_increments & self::increment_dayvisit
			&& $tabsql[] = 'day_visits = day_visits + 1';
		if (count($tabsql) > 0)
			return implode(', ', $tabsql);
		else {
			throw new Exception('Can\'t count nothing..');
		}
	}

	public function commit($new_day_if_not_exists=true) {
		$table_name = $this->_app->get_table_name('counters_days');
		$sql_update = sprintf(
				'update %s' .
				' set %s where counter_id = :counter_id and day_date = date(now())',
				$table_name,
				$this->getIncrementsQuery()
		);
		$statement = $this->_app->pdo_prepare($sql_update);
		$statement->execute(array('counter_id' => $this->id()));
		$rowcount = $statement->rowCount();

		if ($rowcount != 1)
			if ($new_day_if_not_exists) {
// ici, enregistrer le compteur à la date du jour dans la base,
// puis réessayer
				$this->create_today(); // on crée le compteur
				return $this->commit(false); //:
				/* on passe à false,
				 * si la création ne fonctionne pas
				 * comme ça pas de boucle infinie
				 */
			}
			else
				throw new Exception('Impossible de créer un nouveau jour pour ce compteur');

		/* Si une visit à été demandée, commit sur la session */
//		$this->_increments & self::increment_visit &&
//			$_SESSION[$this->conf('session_hash')][$this->counter_id()] = 'increment';

		/* Si une dayvisit à été demandée, on commit aussi sur les cookies */
//		$this->_increments & self::increment_dayvisit &&
//			$this->setDateCookie() && $this->addKey2Cookie();

		$this->_increments = 0;
		return $this;
	}

	private function name() {
		$this->id(); // on s'assure de la création en BDD
		return $this->_display_name;
	}

	public function set_id($id) {

		$this->_id = $id;
	}

	public function get_id() {
		return $this->id();
	}

	private function id() {
		if (empty($this->_id)) {
			$section_id = $this->_section->id();
			$query = sprintf(
					'select * from %s where strkey like :key and section_id = :s_id',
					$this->_app->get_table_name('counters')
			);
			$statement = $this->_app->pdo_prepare($query);
			$statement->execute(array(
			    'key' => $this->_strkey,
			    's_id' => $section_id
			));
			$rs_a = $statement->fetchAll(PDO::FETCH_ASSOC);

			$count = count($rs_a);
			if ($count == 0)
				$this->_id = $this->create();
			elseif ($count == 1) {
				$this->_id = intval($rs_a[0]['id']);
				$this->_display_name = intval($rs_a[0]['display_name']);
			}
			else
				throw new Exception("duplicate strkey/section_id .. too bad! ($count found)");
		}
		return $this->_id;
	}

	public function create() {
		$section_id = $this->_section->id();
		$display_name = isset($this->_display_name) ? $this->_display_name : null;
		$sql_insert = sprintf(
				'insert into %s (strkey, display_name, section_id) values (:strkey, :display_name, :section_id)',
				$this->_app->get_table_name('counters')
		);
		$statement = $this->_app->pdo_prepare($sql_insert);
		$args = array(
		    'strkey' => $this->_strkey,
		    'display_name' => $display_name,
		    'section_id' => $section_id
		);
		$statement->execute($args);
		return $this->_app->pdo_last_insert_id();
	}

	private function create_today() {
		$sql_insert = sprintf(
				'insert into %s' .
				' (counter_id, day_date, hits,visits, day_visits)' .
				' VALUES (:id, date(now()), 0,0,0)',
				$this->_app->get_table_name('counters_days')
		);
		$statement = $this->_app->pdo_prepare($sql_insert);
		$statement->execute(array('id' => $this->id()));
	}

	public function dump() {
		return array(
		    'strkey' => $this->_strkey,
		    'name' => $this->display_name(),
		    'display_name' => $this->_display_name,
		    'id' => $this->id(),
		    'parent_id' => $this->_section->id()
		);
	}

	public function display_name() {

		return empty($this->_display_name) ? $this->_strkey :
			$this->_display_name;
	}

	public function stats($first_day, $last_day, $data_grain) {
		//@todo mettre ça dans une config, c'est side effect donc ça n'a
		// rien à faire dans une classe
		setlocale(LC_ALL, 'fr_FR.utf8');
		date_default_timezone_set('Europe/Paris');
		if ($data_grain == 'day')
			return $this->stats_day($first_day, $last_day);
		else {


			return $this->stats_grain($first_day, $last_day, $data_grain);
		}
	}

	private function stats_day($first_day, $last_day) {
		$q = '
		select	hits,
			visits,
			day_visits,
			day_date as datekey
		from ' . $this->_app->get_table_name('counters_days') . '
		where	counter_id = :counter_id and
			day_date >= :first_day and
			day_date <= :last_day
		order by day_date
		';
		$statement = $this->_app->pdo_prepare($q);
		$statement->execute(array(
		    'counter_id' => $this->id(),
		    'first_day' => $first_day,
		    'last_day' => $last_day
		));
		$rs = $statement->fetchAll(PDO::FETCH_ASSOC);

		foreach ($rs as &$v) { //!&
			$v['datetitle'] = strftime("%A %d %b", strtotime($v['datekey']));
		}
		return $rs;
	}

	private function stats_grain($first_day, $last_day, $data_grain) {


		$grains = array(
		    'month' => 'y,m',
		    'year' => 'y'
		);
		$grain = $grains[$data_grain];
		$q = '
		select	sum(hits) as hits,
			sum(visits) as visits,
			sum(day_visits) as day_visits,
			min(day_date) as mindate,
			YEAR(day_date) as y,
			MONTH(day_date) as m
		from ' . $this->_app->get_table_name('counters_days') . '
		where	counter_id = :counter_id and
			day_date >= :first_day and
			day_date <= :last_day
		group by ' . $grain . '
		order by ' . $grain . '
		';
		$statement = $this->_app->pdo_prepare($q);
		$statement->execute(array(
		    'counter_id' => $this->id(),
		    'first_day' => $first_day,
		    'last_day' => $last_day
		));
		$rs = $statement->fetchAll(PDO::FETCH_ASSOC);

		if ($data_grain == 'month') {
			foreach ($rs as &$v) { //!&
				$v['datekey'] = $v['y'] . '-' . $v['m'] . '-01';
				$v['datetitle'] = strftime("%b %Y", strtotime($v['mindate']));
			}
		}
		elseif ($data_grain == 'year') {
			foreach ($rs as &$v) { //!&
				$v['datekey'] = $v['y'] . '-01-01';
				$v['datetitle'] = strftime("%Y", strtotime($v['mindate']));
			}
		}
		return $rs;
	}

}
