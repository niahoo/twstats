<?php
/**
 * Compteur twstats
 * Copyright Ludovic Demblans © 2010
 */

/**
 * Classe qui sert à recevoir la configuration de la base de données
 * et à distribuer des objets counter / reader
 */
class TWStats {

    static protected $configuration;

    /**
     * Clé primaire de l'item dans la BDD
     * @var int
     */
    private $keyid;

    /**
     * Champs à incrémenter. une valeur de 3 incrémentera les hits et visites
     * @var binary,int
     */
    private $increments;

    /**
     * valeur de retour possible pour getSectionIDByPath
     */
    const unknown_section = false;

    /**
     * Choix des compteurs à incrémenter
     */
    const increment_hit = 1;
    const increment_visit = 2;
    const increment_dayvisit = 4;

    /**
     * Simples chaînes pour identifier les valeurs, complexes pour éviter un
     * conflit avec d'autres clés sessions/cookies.
     * N'est pas prévu à des fins de sécurité.
     */
    const session_hash =     '349fd6dca2708ecb5f4e4cbe3beaaa46';
    const cookie_date_hash = '25ffa5f154d8a7faccbc38cab3c8DATE';
    const cookie_ids_hash =  '6db14a50110a4db32bf28c245c70dIDS';

//==============================================================================
//===================== CONFIGURATION DE L'OBJET ===============================
//==============================================================================


    public function __construct ($conf) {
        if($conf == null || count($conf) == 0)
            throw new InvalidArgumentException('Configuration missing');
        self::configure($conf);
    }

    static function configure ($conf) {
        self::$configuration = self::check_configuration($conf);
        if( !isset(self::$configuration['table_prefix']))
            self::$configuration['table_prefix'] = '';

    }

    static function check_configuration ($conf) {
        if( isset($conf['pdo_instance'])
                && $conf['pdo_instance'] instanceof PDO) {
            return $conf;
        }
        /* else */
        if( isset($conf['db_name'])
                && isset($conf['db_host'])
                && isset($conf['db_user'])
                && isset($conf['db_pass'])) {
            try {
                $conf['pdo_instance'] = new PDO(
                        'mysql:host='.$conf['db_host'].
                                ';dbname='.$conf['db_name'],
                        $conf['db_user'],
                        $conf['db_pass']);
                $conf['pdo_instance']->setAttribute(
                        PDO::ATTR_ERRMODE,
                        PDO::ERRMODE_EXCEPTION
                );
                return $conf;
            }
            catch (PDOException $e) {
                throw new InvalidArgumentException(
                'Bad PDO Parameters, '.$e->getMessage
                );
            }
        }
        else {
            throw new InvalidArgumentException('Wrong database access');
        }
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
    public function counter ($sections, $key,
            $register_new_keys=true, $register_new_sections=true) {

        if( !is_array($sections) || count($sections) < 1)
            throw new InvalidArgumentException(
            'Cannot find item\'s section with an empty name'
            );
        if( (string) $key == '')
            throw new InvalidArgumentException(
            'Cannot find item with an empty key'
            );


        $this->increments = 0;

        $section_id = $this->getSectionIDByPath($sections);
        if($section_id === self::unknown_section)
            if($register_new_sections) {
                $section_name = array_pop($sections);
                $section_id = $this->registerNewSection(
                        $section_name,
                        $parent_id = $this->getSectionIDByPath($sections)
                );
            }
            else
                throw new InvalidArgumentException('Bad sections');

        $sql_key_exists = sprintf(
                'select id from %1$sitem as i '.
                'where i.strkey like \'%2$s\' and i.section_id = %3$d',
                self::$configuration['table_prefix'],
                $key,
                $section_id
        );
        $rs = self::$configuration['pdo_instance']->query($sql_key_exists);
        $rows = $rs->fetchAll();
        $num_rows = count($rows);
        if($num_rows == 0)
            if($register_new_keys)
                $this->keyid = $this->registerNewKey($key, $section_id);
            else
                throw new InvalidArgumentException('Bad key');
        elseif($num_rows == 1) {
            $this->keyid = $rows[0][0];
        }

        $this->section_id = $section_id;

        return $this;

    }
//==============================================================================
//=================== CREATION DES CLES ET DES SECTIONS ========================
//==============================================================================

    /**
     * Enregistre une section dans la base de données.
     * Un parent_id 0 donnera une section de premier niveau.
     * Renvoie l'id de la section nouvellement créée.
     * @param string $name
     * @param int $parent_id
     * @return int
     */
    private function registerNewSection ($name, $parent_id) {
        $sql_insert = sprintf(
                'insert into %ssection (name, parent_id) VALUES (\'%s\', %d)',
                self::$configuration['table_prefix'],
                utf8_encode($name),
                $parent_id
        );
        $pdo = self::$configuration['pdo_instance'];
        try {
            $exec = $pdo->exec($sql_insert);
        }
        catch( PDOException $e) {
            throw new PDOException('[registerNewSection] '.$e->getMessage(),
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
    private function registerNewKey ($key, $section_id) {
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
        }
        catch( PDOException $e) {
            throw new PDOException('[registerNewKey] '.$e->getMessage(),
            $e->getCode(), $e->getPrevious());
        }
        return $pdo->lastInsertId();
    }

    /**é
 * Cherche une section dans la base par son nom et le nom de ses parents
 * En cherchant d'abord par le nom, si plusieurs sections de même nom sont
 * trouvées, on tente de trouver la bonne grâce à son parent
 * @param array $sections
     */
    private function getSectionIDByPath ($sections, $register_news=true) {
        if( !is_array($sections) || count($sections) < 1)
            throw new InvalidArgumentException(
            'Cannot find section with an empty name'
            );

        $section_name = array_pop($sections);
        $sql_id_by_name = sprintf(
                'select id, parent_id from %ssection where name like \'%s\'',
                self::$configuration['table_prefix'],
                $section_name
        );
        $rs = self::$configuration['pdo_instance']->query($sql_id_by_name);
        $rows = $rs->fetchAll();
        $num_rows = count($rows);
        if($num_rows == 0)
            if(count($sections) == 0 && $register_news)
                return $this->registerNewSection($section_name, 0);
            else
                return self::unknown_section;
        elseif($num_rows == 1)
            return $rows[0][0];
        else { // $num_rows > 1
            /* deux sections du même nom ont forcément un parent, différent */
            foreach($rows as $row) {
                if($row['parent_id'] === $this->getSectionIDByPath($sections));
                return $row['id'];
            }
            return self::unknown_section;
        }
    }

//==============================================================================
//=================== INCREMENTATION DES COMPTEURS =============================
//==============================================================================

    /**
     * Incrémente le compteur de hits à chaque appel
     */
    public function hit () {
        $this->increments = $this->increments | self::increment_hit;
        return $this;
    }

    /**
     * Incrémente le compteur uniquement si le marqueur n'est pas présent en
     * session
     */
    public function visit () {
        if(isset($_SESSION)
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
    public function dayvisit () {
        $today = (int) date('Ymd');
        /* si le cookie n'est pas du jour */
        if(!isset($_COOKIE[self::cookie_date_hash])
                || (int) $_COOKIE[self::cookie_date_hash] != $today ) {
            $this->increments = $this->increments | self::increment_dayvisit;
            $this->setDateCookie();
            $this->addKey2Cookie();
        }
        /* si le cookie est du jour, on va voir si la page actuelle est vue */
        elseif($this->addKey2Cookie()) {
            $this->increments = $this->increments | self::increment_dayvisit;
        }
        return $this;
    }

    /**
     * Remplace ou crée le cookie self::cookie_date_hash par la date du jour
     */
    private function setDateCookie () {
        $today = (int) date('Ymd');
        $expire = time() + 3600 * 24 ; // 1 jour
        setcookie(self::cookie_date_hash, $today, $expire);
        /* On supprime également les pages du jour précédent */
        setcookie(self::cookie_ids_hash);
    }


    /**
     * Renvoie true si le keyid est ajouté, false s'il y est déjà
     */
    private function addKey2Cookie () {
        $expire = time() + 3600 * 24 ; // 1 jour
        if(isset($_COOKIE[self::cookie_ids_hash]))
            if(strstr($_COOKIE[self::cookie_ids_hash], '_'.$this->keyid))
                return false; // inutile d'ajouter l'id, il y est déjà
            else
                $base_str = $_COOKIE[self::cookie_ids_hash];
        else
            $base_str = '';
        setcookie(self::cookie_ids_hash, $base_str.'_'.$this->keyid, $expire);
        return true;
    }

    /**
     * Donne une pertie de requête SQL destinée à incrémenter les champs du
     * compteur, en fonction des valeurs contenues dans this->increments
     * @return string
     */
    private function getIncrementsQuery () {
        $tabsql = array();
        $this->increments & self::increment_hit
                && $tabsql[] = 'hits = hits + 1';
        $this->increments & self::increment_visit
                && $tabsql[] = 'visits = visits + 1';
        $this->increments & self::increment_dayvisit
                && $tabsql[] = 'day_visits = day_visits + 1';
        if( count($tabsql) > 0)
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
    public function commit ($try_insert=true) {
        $sql_update = sprintf(
                'update %sitem_day'.
                ' set %s where item_id = %s and countday = date(now())',
                self::$configuration['table_prefix'],
                $this->getIncrementsQuery(),
                $this->keyid
        );

        $rowcount = self::$configuration['pdo_instance']->exec($sql_update);
        if($rowcount != 1 && $try_insert) {
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
    private function registerNewCountDay () {
        $sql_insert = sprintf(
                'insert into %sitem_day'.
                ' (item_id, countday, hits,visits, day_visits)'.
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

function twstats ($conf) {
    return new TWStats($conf);
}

class TWStats_UI extends TWStats {

    public function __construct ($conf) {
        parent::__construct($conf);
    }

    /**
     * Renvoie les sous-sections de la section passée en paramètre,
     * on passe l'ID
     * @param integer $parent_section_id
     * @return array The sections
     */
    public function getSubSections_loop ($parent_section_id) {
        $sections = array();
        $sql = sprintf(
                'select * from `%ssection` where `parent_id` = %d',
                self::$configuration['table_prefix'],
                $parent_section_id
        );
        $rs = self::$configuration['pdo_instance']->query($sql);
        $sections = $rs->fetchAll(PDO::FETCH_ASSOC);
        foreach($sections as &$section) {
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
    public function getSectionsTree () {
        return array(
                'name' => 'root',
                'id' => 0,
                'childs' => $this->getSubSections_loop(0)
        );
    }

    public function getItemsFromSection ($section_id) {
        $sql = sprintf(
                'select id,strkey,name from `%sitem` where `section_id` = %d',
                self::$configuration['table_prefix'],
                $section_id
        );
        $rs = self::$configuration['pdo_instance']->query($sql);
        $items = $rs->fetchAll(PDO::FETCH_ASSOC);
        return  $items;
    }

    /**
     * Renvoie les 3 chiffres du mois
     * déclenche la mise en cache si nécéssaire
     * @param int $item_id
     */
    public function readMonth ($item_id, $year, $month) {
        $sql_cache = sprintf(
                'select id,strkey,name from `%sitem` where `item_id` = %d'.
                'and yearmonth = %d%d',
                self::$configuration['table_prefix'],
                $item_id,
                $year,
                $month
        );
        $rs = self::$configuration['pdo_instance']->query($sql_cache);
        $rows = $rs->fetchAll(PDO::FETCH_ASSOC);
        if(count($rows) != 1) {
            $month_stats = $this->compileMonth($item_id, $year, $month);
            if (intval(date('Ym')) < intval($year.$month)) {
                $this->storeMonthCache($month_stats);
            }
            return $month_stats;
        }
        else {
            return $rows[0];
        }
    }

}



