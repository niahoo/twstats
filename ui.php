<?php
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
	
	
	public function getHtmlSectionsTree_cached($template) {
		$tree = $this->getSectionsTree();
		print_r($tree);
	}
	
	
	
	
	
	public function conf($key) { return $this->_conf[$key]; }
}

