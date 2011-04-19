<?php
session_start();
ob_start(); {
    ini_set('html_errors','on');
    ini_set('display_errors','on');
    error_reporting( E_ALL  );



    # CONF
    require '/var/www/shared/twstats/twstats.php';
    $conf = array(
            'db_name' => 'toulou_stats',
            'db_user' => 'stats',
            'db_pass' => '827b4857b451c9',
            'db_host' => 'localhost'
    );


    # CHOIX DU COMPTEUR
    $PATH = explode('|', $_GET['p']); // donne le path demandé découpé


    //var_dump($PATH); 
    $maincat = array_shift($PATH);   // donne la première partie du path


    switch($maincat) {
        case 'annuaire':
            $sections = array('annuaire');
            $rubrique = array_shift($PATH);
            if($rubrique == null || $rubrique == '') {
                /* pas de rubrique, donc page accueil de l'annuaire */
                $key = 'accueil annuaire';
            }
            else {
                array_push($sections, $rubrique);
                $srub = array_shift($PATH);            
                if($srub == null || $srub == '')
                    $key = 'tout-afficher';
                else
                    $key = $srub;
            }
            break;
        default:
            exit;
            break;
    }

    # APPEL DU COMPTEUR
    try {
        twstats($conf)->counter($sections, $key)->hit()->visit()->dayvisit()->commit();
    }
    catch( Exception $e) {
        if(file_exists("error-logs/$maincat.log"))
            $f = fopen("error-logs/$maincat.log", 'a');
        else
            $f = fopen("error-logs/$maincat.log", 'w');
        $error_log_message = sprintf(
            "[%s] %s \n %s",
            date('d/m/Y'),
            $e->getMessage(),
            $e->getPrevious()
        );
        fwrite($f, $error_log_message);
        fclose($f);
    }
} ob_end_clean();
# AFFICHAGE IMAGE TRANSPARENTE
header('content-type: image/gif');
imagegif(imagecreatefromgif('stats.gif'));

