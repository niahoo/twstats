<?php

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

	public function id($prevent_recurse=false) {

		if (count($this->_path) == 0) { // ROOT section
			return 0;
		}
		elseif (empty($this->_id)) {

			echo "
I AM EMPTY MOTHERFUCKER !
";


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

}

