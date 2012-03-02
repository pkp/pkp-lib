<?php
/* 	
	The Main Helper
	ver 1.00 from 24/08/2009
*/

include("../is_allowed.php");


/* 	
	Latinizer	
	ver 1.01 from 22/08/2009
	UPD: dots allowed in string
*/

function url_title_rus($title) //made for compatibility
{
	$lowercase = array('а', 'б', 'в', 'г', 'д', 'е', 'ё', 'ж', 'з', 'и', 'й', 'к', 'л', 'м', 'н', 'о', 'п', 'р', 'с', 'т', 'у', 
					'ф', 'х', 'ц', 'ч', 'ш', 'щ', 'ь', 'ы', 'ъ', 'э', 'ю', 'я', ' ');
	$uppercase = array('А', 'Б', 'В', 'Г', 'Д', 'Е', 'Ё', 'Ж', 'З', 'И', 'Й', 'К', 'Л', 'М', 'Н', 'О', 'П', 'Р', 'С', 'Т', 'У', 
					'Ф', 'Х', 'Ц', 'Ч', 'Ш', 'Щ', 'Ь', 'Ы', 'Ъ', 'Э', 'Ю', 'Я', ' ');
	$en = array  ('a', 'b', 'v', 'g', 'd', 'e', 'e', 'zh', 'z', 'i', 'y', 'k', 'l', 'm', 'n', 'o', 'p', 'r', 's', 't', 'u',
				    'f', 'h', 'ts', 'ch', 'sh', 'sch', '', 'y', '', 'e', 'yu', 'ya', '-');
					
	
	$title = str_replace($lowercase, $en, $title);
	$title = str_replace($uppercase, $en, $title);
	
	$title = eregi_replace("[^a-z0-9_\.-]", '', $title); //with dots allowed
	
	return $title;
}
