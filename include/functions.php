<?php
function BC_to_file($protocol, $file){
		global $BC;
		
		$buffer = '';
		$count = 0;
		
		$fp = fopen($file, 'w');
		
		// inizializzo cURL
		$ch = curl_init();
		
		// imposto la URL della risorsa remota da scaricare$protocol
		curl_setopt($ch, CURLOPT_URL, $BC['url'].'?name='.$protocol);
		
		// imposto che non vengano scaricati gli header
		curl_setopt($ch, CURLOPT_HEADER, 0);
		
		// evito che il contenuto remoto venga passato a print
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		
		$callback = function ($ch, $str) use ($fp, &$buffer, &$count){

			//if($count<5) file_put_contents("E:/MSSQL/Audit/files/test".$count++.'.txt', $str);
			
			$lines = explode("\r\n", $str);
				
			$lines_num = sizeof($lines);
			$lines[0] = $buffer.$lines[0]; //prefix the partial last line from last time.
			$buffer = $lines[$lines_num-1]; //last partial line. 
			$lines[$lines_num-1] = ''; //empty string so to keep glue after.
			
			$strings = implode("\r\n", $lines);
			
			$strings = clearCommas($strings);
			
			$strings = mb_convert_encoding($strings, 'ISO-8859-1', 'UTF-8');
			
			fwrite($fp, $strings); //adding separator at begin and end.
			
			return strlen($str);//return the exact length
		};
		
		
		curl_setopt($ch, CURLOPT_WRITEFUNCTION, $callback);
		
		
		/*
		// eseguo la chiamata
		$output = curl_exec($ch);
		
		$header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
		$header = substr($output, 0, $header_size);
		$output = substr($output, $header_size);

		//$output = clearCommas($output);
		//$data = mb_convert_encoding($output, 'ISO-8859-1', 'UTF-8');
		
		if(substr($header, 9, 3) == "200") {
			file_put_contents($file, $output);
		}
		*/
		curl_exec($ch);


		// chiudo cURL
		curl_close($ch);
		fclose($fp);
		
}


function uploadFileToSQL($file, $table){
		
		global $sql;
		
		list($connection_result, $conn) = SQLConnect();
		
		if($connection_result){
			
			if(checkFile($file)){
				
				try{
				
					$fieldseparator = isCsv($file) ? $sql['CSVfieldseparator'] : $sql['SAPfieldseparator'];
					$headerLine = getHeader($file);
					$header = explode($fieldseparator, $headerLine);
					
					$headerCount = array();
					
					foreach($header as $k => $h){
						$string = preg_replace('/[^\p{L}\p{N}]/u', '', $h); //remove special chars.
						$string = strtoupper($string);
						
						if(!isset($headerCount[$string])) {
							$headerCount[$string] = 1;
							$header[$k] = $string;
						}
						else $header[$k] = $string.'_'.$headerCount[$string]++;
					}
					
					list($SQLFloatConvert, $OriginalFieldTypes) = getFieldTypes($file, $header);
					//$SQLFloatConvert = array();
					$attempts = 0;
					$count_field_types = array_count_values($OriginalFieldTypes);
					$total_attempts = isset($count_field_types['float']) ? $count_field_types['float'] + 1 : 1;
					
					do {
						//create temp table or real table according to whether SQL checks are to be done eventually.
						$temp_table = $SQLFloatConvert ? '#TEMP_'.$table.$attempts : $table;
						
						//matches labels and field types
						$SQLFields = array_map('combineHeaderTypeFields', $header, $OriginalFieldTypes);
					
						//create table statement with current datatypes
						$createTableStatement = "CREATE TABLE [dbo].[".$temp_table."] (".implode(", ", $SQLFields).")"; 
					
						//runs the query
						$sql_result = sqlsrv_query($conn, dropSQLTableScript($temp_table).$createTableStatement."
						EXEC('BULK INSERT ".$temp_table." from ''".$file."'' 
						with (FIRSTROW=2, FIELDTERMINATOR=''".$fieldseparator."'', ROWTERMINATOR=''\r\n'', CODEPAGE = ''ACP'')');");
	
						//one more attempt done, check for errors
						$attempts++;
						$error = getSQLError();
						
						//if errors and attempts available -> check for the wrong field and convert it to varchar(255). Then restart.
						if($error !== null && ($attempts < $total_attempts)) {
							
							foreach($header as $k=>$h) {
								//checks for the SQL message relatively to the incriminated field -> switch to varchar and try again.
								if(preg_match('/\('.preg_quote($h, '/').'\)/', $error)) {
									$OriginalFieldTypes[$k] = 'varchar(255)';
									break;
								}
							}
						}
					}
					//if error === null query is successful. Never go beyond total attempts.
					while ($error !== null && ($attempts < $total_attempts));
					
					if($error !== null) throw new Exception($error);
					
					//import is complete. If no error, then check out for internal conversion in case of $SQLFloatConvert fields.
					$sql_convert_result = true;
					$sql_convert_error = '';
					
					
					if($SQLFloatConvert && $error === null){
						
						$fields_do_convert = array();
						
						//for each field to be checked against comma, try to select and convert. If no error, then it is float.
						foreach($SQLFloatConvert as $k => $field_to_float){
							$float_result = sqlsrv_query($conn, "SELECT CONVERT(float, REPLACE([".$field_to_float."],',','.')) FROM [".$temp_table."]");
							if($float_result !== false) $fields_do_convert[$k] = $field_to_float;
						}
						
						//if at least one field is available for convertion, prepare SELECT INTO statement from temp table to final table, converting varchar numeric fields to float. Otherwise replicate temp table into final.
						if($fields_do_convert) {
							$select = array();
							foreach($header as $k=>$h){
								if(isset($fields_do_convert[$k])) $select[] = "CONVERT(float, REPLACE([".$h."],',','.')) [".$h."]";
								else $select[] = "[".$h."]";
							}
							
							//run SELECT INTO statement
							$sql_convert_result = sqlsrv_query($conn, "drop table #TEMP_ORIGINALE;".dropSQLTableScript($table)."SELECT ".implode(", ", $select)." INTO [".$table."] FROM [".$temp_table."]");
							
						} else $sql_convert_result = sqlsrv_query($conn, dropSQLTableScript($table)."SELECT * INTO [".$table."] FROM [".$temp_table."]");
						
						$sql_convert_error = getSQLError();
											
						if($sql_convert_error !== null) throw new Exception($sql_convert_error);//$sql_convert_result = false;
							
					}
					
					writeLog("SUCCESS - FILE $file", getFileType($file));
					
				} catch(Exception $e){
					
					writeLog("ERROR - FILE $file - ".$e, getFileType($file));
					
				}
				
				//Log the import.
				/*
				if($error === null || ($SQLFloatConvert && $sql_convert_result)) writeLog("SUCCESS - FILE $file", getFileType($file));
				else if($error !== false) {
					writeLog("ERROR - FILE $file - ".$error, getFileType($file));
				} else writeLog("SUCCESS - FILE $file - ".$sql_convert_error, getFileType($file));*/
			
			}			
			
			sqlsrv_close($conn);
		
		} else writeLog("Unable to connect to SQL: ".$conn, getFileType($file));
		
}

function getFieldTypes($file, $header){
	
	global $sql;
	
	$field_type = array();
	$sql_convert = array();
	$processed = array();
	$row = 0;
	$total_floats = 0;
	
	if($sql['create_float_threshold'] == 0) {

		foreach($header as $k=>$h){
			$field_type[$k] = 'varchar(255)';
		}
		
	} else {
	
		if (($f = fopen($file, "r")) !== FALSE){
			while (($r = fgetcsv($f, 1000,  $sql['CSVfieldseparator'])) !== FALSE) {
				$row++;
				if($row == 1){ continue; } //skip title
				
				foreach($header as $k=>$h){
				//if not varchar yet, then check it out.
				
					if(!in_array($k, $processed)) {
						if(!in_array($k, $sql_convert) && preg_match('/^-?\d+(\.\d+)?$/', $r[$k])) $field_type[$k] = 'float';
						else if(preg_match('/^-?\d+(,\d+)$/', $r[$k])) {
							$field_type[$k] = 'varchar(255)';
							$sql_convert[$k] = $h;
						}
						else {
							$field_type[$k] = 'varchar(255)';
							$processed[] = $k;
							if(in_array($k, $sql_convert)) unset($sql_convert[$k]);
						}
					}
				}
				
				if($row >= $sql['create_float_threshold']) break;
				
			}	
			
			fclose($f);	
		}
	}
	
	return array($sql_convert, $field_type);
	
}

function combineHeaderTypeFields($label, $fieldType){
	
	return "[".$label."] ".$fieldType;

}

function dropSQLTableScript($table){
	return "IF (EXISTS (SELECT * FROM INFORMATION_SCHEMA.TABLES 
			WHERE TABLE_SCHEMA = 'dbo' 
			AND  TABLE_NAME = '".$table."')) 
			DROP TABLE [dbo].[".$table."];";
}


function clearCommas($string){
	global $BC;
	global $sql;
	
	$string = str_replace('""',"$$**$$",$string); 
	
	$string = str_replace($sql['SAPfieldseparator'],'',$string); //gets rid of the sql separator.
	$string = str_replace($BC['fieldseparator'],$sql['SAPfieldseparator'],$string); 

	$patternSemiCol = '/"[^"]+"/';
	
	$revised_string = preg_replace_callback($patternSemiCol, function($m) { 
		global $BC;
		global $sql;
		
		return str_replace($sql['SAPfieldseparator'],$BC['fieldseparator'],$m[0]); 
	
	}, $string);
	
	$revised_string = str_replace('"','',$revised_string); 

	$revised_string = str_replace('$$**$$','""',$revised_string); 

	return $revised_string;

}

function getProtocols($file, $field){

	global $BC;
	
	$protocols = array();
	$row = $BC['fromBoard'] ? 1 : 0; //change this in order to get the header or not
	
	if (($f = fopen($file, "r")) !== FALSE){
    while (($protocol = fgetcsv($f, 1000, "\t")) !== FALSE) {
		if($row == 1){ $row++; continue; } //skip title
        $protocols[] = $protocol[$field];	
    }	
    fclose($f);	
	}
	 
	return($protocols);
}

	
function getHeader($file, $removeEndingLineFeed = true){
  $fn = fopen($file,"r");
  $result = fgets($fn);
  fclose($fn);
  if($removeEndingLineFeed) $result = str_replace("\r\n", "", $result);
  return $result;
}


function clearFolder($dir){
		$files = glob($dir.'*'); 
		foreach($files as $file){ 
			if(is_file($file))
				unlink($file); 
		}
}

function isCsv($file){
	
	//check if file folder is csv folder.
	global $path_csv;
	
	$file_path = dirname($file);
	
	return strtolower(rtrim($file_path, '/')) == strtolower(rtrim($path_csv, '/'));

}

function getFileType($file){
	
	return isCsv($file) ? CSV : SAP;

}

function checkFile($file) {
	
	global $sql;
	
	$separator = $sql['CSVfieldseparator'] == "\t" ? '\t' : $sql['CSVfieldseparator'];
	
	$type = getFileType($file);
	$result = true;
	
	//check if file exists.
	if(!file_exists($file)) {
		writeLog('I can\'t find file '.$file, $type);
		$result = false;
	}
	
	//check if header is correctly addressed in csv - SAP is assumed correct
	else if(isCsv($file) && preg_match('/('.$separator.'(\r|\n|\r\n))|('.$separator.'{2,})/m', getHeader($file, false))) {
		writeLog('Incomplete Header for file '.$file.': all fields must have a non-empty label!', $type);
		$result = false;
	}
	
	return $result;
	
}



function SQLconnect(){
	global $sql;
		
	//CONNESSIONE DB RELAZIONALE
	$connectionInfo = array( "UID"=>$sql['user'],  
							"PWD"=>$sql['psw'],  
							"Database"=>$sql['db']);  
	
	/* Connect using SQL Server Authentication. */  
	$conn = sqlsrv_connect( $sql['sql'], $connectionInfo);  
	
	if( $conn === false ) return array(false, print_r( sqlsrv_errors(), true)); 
	else return array(true, $conn); 

}

function getSQLError(){
	//print_r(sqlsrv_errors());
	if( ($errors = sqlsrv_errors() ) != null) {
		return $errors[0][ 'message'];
    } else return null;
}

function writeLog($text, $type){
	
	global $path_logs;
	
	$now = date("d/m/Y h:i:s");
	
	if(isset($path_logs[$type]) && file_exists($path_logs[$type])) {
		
		$fp = fopen($path_logs[$type].$type.'2SQL.log', 'a');
		fwrite($fp, $now.' - '.$text.PHP_EOL);  
		fclose($fp);  
		
	} else echo 'Path for logs not found!';
	
}



?>