<?php

class TWStats_Utils {

	/**
	 * Cette fonction transforme une chaine de façon à ce qu'elle puisse
	 * servir de clé proprement
	 */
	static function string_simplification($str) {

		$str = self::clean_filename($str);
		$str = stripslashes($str);
		$str = self::remove_white_space($str);
		$str = strtolower($str);

		return $str;
	}

	## Les deux fonctions suivantes sont issues de la librairie de
	## ludovic, mercide voir avec lui pour obtenir ou proposer des
	## améliorations.

	static function remove_white_space($s, $replace='') {
		$search = array("\t", "\n", "\r", chr(32), chr(194) . chr(160));
		$replace = (string) $replace;
		return str_replace($search, $replace, $s);
	}

	static function clean_filename($string) {
		$search = array(
		    '@[èéêëÈÉÊË]@i',
		    '@[àáâãäåæÀÁÂÃÄÅÆ]@i',
		    '@[ìíîïÌÍÎÏ]@i',
		    '@[ùúûüÙÚÛÜ]@i',
		    '@[òóôõöøÒÓÔÕÖØ]@i',
		    '@[çÇ]@i',
		    '@[ðÐ]@i',
		    '@[ñÑ]@i',
		    '@[ýþÿÝÞß]@i',
		    '@[ ]@i',
		    '@[^a-zA-Z0-9_\.]@');
		$replace = array('e', 'a', 'i', 'u', 'o', 'c', 'd', 'n', 'y',
		    '-', '-');
		$simplified = preg_replace($search, $replace, $string);
		return $simplified;
	}

}