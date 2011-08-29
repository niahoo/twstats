<?php

/*
 * Attention, la structure des sections définies de façon unique par le couple
 * name + parent_id n'est pas une bonne idée.
 * => refaire l'appli plus simplement
 */

class TWStats_Section {

	private $_path;
	private $_name;
	private $_id;
	private $_display_name;
	/**
	 *
	 * @var TWStats_Application
	 */
	private $_app;




	public function __construct(array $path, TWStats_Application $app) {
		//		Validation des sections
		if (!is_array($path))
			throw new InvalidArgumentException('Bad path');

		$this->_path = $path;
		if (!count($path))
			$this->_name = 'ROOT_SECTION';
		else
			$this->_name = array_pop($path);
		$this->_app = $app;
	}

	public function set_id($id) {

		$this->_id = $id;
	}

	public function set_path_from_id() {
		if ($this->_id == 0) {
			$this->_name = 'ROOT_SECTION';
			$this->_path = array();
			return;
		}
		// else
		$sql_id_by_name = sprintf(
				'select * from %s where id = :id',
				$this->_app->get_table_name('sections')
		);
		$statement = $this->_app->pdo_prepare($sql_id_by_name);
		$query_args = array(
		    'id' => $this->_id
		);
		$statement->execute($query_args);
		$rs = $statement->fetch(PDO::FETCH_ASSOC);
		$this->_name = $rs['name'];
		$parent = $this->_app->get_section_by_id($rs['parent_id']);
		$this->_path = array_merge($parent->get_path(), array($this->_name));
	}

	public function get_path() {
		return $this->_path;
	}

	private function set_display_name($str) {

		$this->_display_name = $str;
	}

	public function id($prevent_recurse=false) {

		if (count($this->_path) == 0) { // ROOT section
			return 0;
		}
		elseif (empty($this->_id)) {




			// on récupère la section par son nom et l'id de son parent
			// sachant que deux sections avec le même parent
			// n'auront pas le même nom

			$parent = $this->get_parent();
			$sql_id_by_name = sprintf(
					'select * from %s where name like :self_name
						and parent_id = :parent_id',
					$this->_app->get_table_name('sections')
			);
			$statement = $this->_app->pdo_prepare($sql_id_by_name);
			$query_args = array(
			    'self_name' => $this->_name,
			    'parent_id' => $parent->id()
			);
			$statement->execute($query_args);
			$rows = $statement->fetchAll();
			if (count($rows) == 0) {
				if (!$prevent_recurse) {
					try {
						$this->_id = $this->create();
					}
					catch (Exception $e) {
						echo $e->getMessage();
						exit;
					}
					return $this->id(true);
				}
				else
					throw new Exception('No more recursion allowed in TWStats_Section::id()');
			}
			else {
				$this->_id = $rows[0]['id'];
				return $this->_id;
			}
		}
		return $this->_id;
	}

	public function display_name() {
		return empty($this->_display_name) ? $this->_name :
			$this->_display_name;
	}

	public function create() {
		$parent = $this->get_parent();
		$parent_id = $parent->id();

		$sql_insert = sprintf(
				'insert into %1$s (name, parent_id) values (:name, :parent_id)',
				$this->_app->get_table_name('sections')
		);
		$statement = $this->_app->pdo_prepare($sql_insert);
		$statement->execute(array(
		    'name' => $this->_name,
		    'parent_id' => $parent_id
		));
		return $this->_app->pdo_last_insert_id();
	}

	public function get_parent() {
		$path_copy = $this->_path;
		array_pop($path_copy);
		return $this->_app->get_section($path_copy);
	}

	public function childs() {
		$myid = $this->id();
		$query = sprintf(
				'select * from %s where parent_id = :pid',
				$this->_app->get_table_name('sections')
		);
		$statement = $this->_app->pdo_prepare($query);
		$statement->execute(array('pid' => $myid));

		$sections_a = $statement->fetchAll(PDO::FETCH_ASSOC);
		$sections = array();
		foreach ($sections_a as $section) {

			$child_path = $this->_path;
			array_push($child_path, $section['name']);

			$s = new self($child_path, $this->_app);
			$s->set_id($section['id']);
			$s->set_display_name($section['display_name']);
			$sections[] = $s;
		}

		return $sections;
	}

	public function get_counters() {
		$query = sprintf(
				'select * from %s where section_id = :section_id',
				$this->_app->get_table_name('counters')
		);
		$statement = $this->_app->pdo_prepare($query);
		$statement->execute(array('section_id' => $this->id()));

		$counters_a = $statement->fetchAll(PDO::FETCH_ASSOC);
		$counters = array();
		foreach ($counters_a as $ct) {
			$counter = $this->_app->get_counter($ct['strkey'], $this);
			$counter->set_id($ct['id']);
			$counters[] = $counter;
		}
		return $counters;
	}

	public function get_counters_a() {
		$counters = array();
		foreach ($this->get_counters() as $counter) {
			$counters[] = $counter->dump();
		}
		return $counters;
	}

}

