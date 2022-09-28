<?php
if(file_exists(".env")){

	$f = fopen(".env","r");

	while(!feof($f)){

		$ln = trim(fgets($f));
		
		$pt = explode("=",$ln,2);

		if(count($pt) == 2){

			$key = trim($pt[0]);
			$vl = trim($pt[1]);

			if(!empty($key) AND !empty($vl)){

				$_ENV[$key] = $vl;
			}
		}
	}
}
?>