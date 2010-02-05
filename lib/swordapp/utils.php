<?php
	function sac_clean($string) {
		// Tidy a string
                $string = str_replace("\n", "", $string);
                $string = str_replace("\r", "", $string);
                $string = str_replace("\t", "", $string);

                $string = preg_replace('/\t/', '', $string);
                $string = preg_replace('/\s\s+/', ' ', $string);
	        $string = trim($string);
                return $string;
        }
?>
