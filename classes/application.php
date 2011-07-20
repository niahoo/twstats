<?php

/**
 *  On importe toute l'application sans distinction
 */
require_once dirname(__FILE__) . '/section.php';
require_once dirname(__FILE__) . '/counter.php';
require_once dirname(__FILE__) . '/utils.php';

/**
 * Classe destinée à recevoir la configuration d'une instance de twstats
 * On pourra créer les objets sections et counters
 * Ceux-ci passeront par cette classe pour l'accès à la base de données
 *
 * @author ludovic
 */
class TWStats_Application {

	private $_conf = array();
	/**
	 *
	 * @var PDO 
	 */
	private $_pdo;

	public function __construct($conf) {

//		Validation de la configuration
		if (!isset($conf['session_hash']) || !isset($conf['cookie_date_hash']) ||
			!isset($conf['cookie_ids_hash']) || !isset($conf['pdo']))
			throw new InvalidArgumentException('Bad configuration');


		if (!($conf['pdo'] instanceof PDO))
			throw new InvalidArgumentException('conf[pdo] is not a PDO instance');

		// Configuration de PDO : échec avec exceptions. L'ancienne conf
		// est remise en place à la destruction de l'objet

		$this->_conf = $conf;
		$this->_pdo = $conf['pdo'];
		$this->_conf['previous_pdo_errmode'] = $this->_pdo->getAttribute(PDO::ATTR_ERRMODE);
		$this->_pdo->setAttribute(
			PDO::ATTR_ERRMODE,
			PDO::ERRMODE_EXCEPTION
		);

		if (!isset($this->_conf['table_prefix']))
			$this->_conf['table_prefix'] = '';
	}

	public function get_counter($key, $section) {
//		if (!($section instanceof TWStats_Section))
		return new TWStats_Counter($key, $section, $this);
	}

	/**
	 *
	 * @param <type> $path
	 * @return TWStats_Section 
	 */
	public function get_section($path) {
		return new TWStats_Section($path, $this);
	}

	public function create_section($path) {
		$section_to_create = $this->get_section($path);
		$parent = $section_to_create->get_parent();
		$parent_id = $parent->id();
	}

	public function get_table_name($name) {
		switch ($name) {
			case 'counters':
				return $this->_conf['table_prefix'] . 'counters';
			case 'sections':
				return $this->_conf['table_prefix'] . 'sections';
			case 'counters_days':
				return $this->_conf['table_prefix'] . 'counters_days';
			default:
				throw new InvalidArgumentException('bad table name');
		}
	}

	/**
	 *
	 * @param <type> $query
	 * @return PDOStatement
	 */
	public function pdo_prepare($query) {
		return $this->_pdo->prepare($query);
	}

	public function pdo_last_insert_id($name=null) {
		return $this->_pdo->lastInsertId($name);
	}

	/**
	 * On remet le PDO passé en constructeur dans son état précédent.
	 */
	public function __destruct() {

		/*
		 * marche pas bien avec simpletest
		 */
//		$this->_pdo->setAttribute(
//			PDO::ATTR_ERRMODE,
//			$this->_conf['previous_pdo_errmode']
//		);
	}

}
