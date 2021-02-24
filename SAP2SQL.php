<?php
include('include/config.php');
include('include/functions.php');

if($protocols = getProtocols($board['protocols_file'], 0)){

	clearFolder($path_SAP);

	foreach($protocols as $p){
			
			$file = $path_SAP.$p.'.txt';
			
			echo 'Updating '.$p.'...'.PHP_EOL;
			
			BC_to_file($p, $file);
			
			uploadFileToSQL($file, $p);
			
			unlink($file);

	}
	
//clearFolder($path_SAP);

}


?>