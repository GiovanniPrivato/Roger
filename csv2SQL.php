<?php
include('include/config.php');
include('include/functions.php');

$files = glob($path_csv.'*.csv'); 

if($files){

	foreach($files as $file){
		
		if(file_exists($file)){
			
			echo 'Updating '.basename($file).'...'.PHP_EOL;
		
			uploadFileToSQL($file, strtoupper(pathinfo($file)['filename']));
		
			rename($file, $path_csv_processed.basename($file));
	
		}
		
		
	}
}


?>