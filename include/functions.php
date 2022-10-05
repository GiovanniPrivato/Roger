<?php
function BC_to_file($protocol, $file)
{
    global $BC;

    $buffer = '';
    $count = 0;

    $fp = fopen($file, 'w');

    // inizializzo cURL
    $ch = curl_init();

    // imposto la URL della risorsa remota da scaricare$protocol
    curl_setopt($ch, CURLOPT_URL, $BC['url'] . '?name=' . $protocol);

    // imposto che non vengano scaricati gli header
    curl_setopt($ch, CURLOPT_HEADER, 0);

    // evito che il contenuto remoto venga passato a print
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $callback = function ($ch, $str) use ($fp, &$buffer, &$count) {

        $lines = explode("\r\n", $str);

        $lines_num = sizeof($lines);
        $lines[0] = $buffer . $lines[0]; //prefix the partial last line from last time.
        $buffer = $lines[$lines_num - 1]; //last partial line.
        $lines[$lines_num - 1] = ''; //empty string so to keep glue after.

        $strings = implode("\r\n", $lines);

        $strings = clearCommas($strings);

        $strings = mb_convert_encoding($strings, 'ISO-8859-1', 'UTF-8');

        fwrite($fp, $strings); //adding separator at begin and end.

        return strlen($str); //return the exact length
    };

    curl_setopt($ch, CURLOPT_WRITEFUNCTION, $callback);

    curl_exec($ch);

    // chiudo cURL
    curl_close($ch);
    fclose($fp);
}

function uploadFileToSQL($file, $table)
{

    global $sql;

    $table = preg_replace('/\s/', '_', $table);

    list($connection_result, $conn) = SQLConnect();

    if ($connection_result) {

        if (checkFile($file)) {

            try {

                $fieldseparator = isCsv($file) ? $sql['CSVfieldseparator'] : $sql['SAPfieldseparator'];
                // $headerLine = getHeader($file);
                $i = 0;
                // $headerLine = preg_replace_callback('/(' . $fieldseparator . '(\r|\n|\r\n))|(' . $fieldseparator . '{2,})/m', function () use (&$i, $fieldseparator) {
                //     $i++;
                //     return $fieldseparator . "Column$i" . $fieldseparator;
                // }, getHeader($file));
                $regex = $fieldseparator === "\t" ? '\t' : $fieldseparator;
                $headerLine = preg_replace_callback('/(^' . $regex . ')|(' . $regex . '(?=' . $regex . '))|(' . $regex . '$)/i', function ($matches) use (&$i) {
                    $i++;
                    $field = "Field$i";
                    if (isset($matches[1]) && $matches[1] == $fieldseparator) {
                        return "Field$i" . $fieldseparator;
                    }
                    return $fieldseparator . "Field$i";
                }, getHeader($file));

                $header = explode($fieldseparator, $headerLine);

                $headerCount = array();

                foreach ($header as $k => $h) {
                    $string = preg_replace('/[^\p{L}\p{N}_]/u', '', utf8_encode($h)); //remove special chars.
                    $unwanted_array = array('Š' => 'S', 'š' => 's', 'Ž' => 'Z', 'ž' => 'z', 'À' => 'A', 'Á' => 'A', 'Â' => 'A', 'Ã' => 'A', 'Ä' => 'A', 'Å' => 'A', 'Æ' => 'A', 'Ç' => 'C', 'È' => 'E', 'É' => 'E',
                        'Ê' => 'E', 'Ë' => 'E', 'Ì' => 'I', 'Í' => 'I', 'Î' => 'I', 'Ï' => 'I', 'Ñ' => 'N', 'Ò' => 'O', 'Ó' => 'O', 'Ô' => 'O', 'Õ' => 'O', 'Ö' => 'O', 'Ø' => 'O', 'Ù' => 'U',
                        'Ú' => 'U', 'Û' => 'U', 'Ü' => 'U', 'Ý' => 'Y', 'Þ' => 'B', 'ß' => 'Ss', 'à' => 'a', 'á' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a', 'å' => 'a', 'æ' => 'a', 'ç' => 'c',
                        'è' => 'e', 'é' => 'e', 'ê' => 'e', 'ë' => 'e', 'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i', 'ð' => 'o', 'ñ' => 'n', 'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'õ' => 'o',
                        'ö' => 'o', 'ø' => 'o', 'ù' => 'u', 'ú' => 'u', 'û' => 'u', 'ý' => 'y', 'þ' => 'b', 'ÿ' => 'y');
                    $string = strtr($string, $unwanted_array);
                    $string = strtoupper($string);

                    if (!isset($headerCount[$string])) {
                        $headerCount[$string] = 1;
                        $header[$k] = $string;
                    } else {
                        $header[$k] = $string . '_' . $headerCount[$string]++;
                    }

                }

                list($SQLFloatConvert, $OriginalFieldTypes) = getFieldTypes($file, $header, $fieldseparator);
                //$SQLFloatConvert = array();
                $attempts = 0;
                $count_field_types = array_count_values($OriginalFieldTypes);
                $total_attempts = isset($count_field_types['float']) ? $count_field_types['float'] + 1 : 1;

                do {
                    //create temp table or real table according to whether SQL checks are to be done eventually.
                    $temp_table = $SQLFloatConvert ? '#TEMP_' . $table . $attempts : $table;

                    //matches labels and field types
                    $SQLFields = array_map('combineHeaderTypeFields', $header, $OriginalFieldTypes);

                    //create table statement with current datatypes
                    $createTableStatement = "CREATE TABLE [dbo].[" . $temp_table . "] (" . implode(", ", $SQLFields) . ")";

                    //runs the query
                    $sql_result = sqlsrv_query($conn, dropSQLTableScript($temp_table) . $createTableStatement . "
						EXEC('BULK INSERT [dbo].[" . $temp_table . "] from ''" . $file . "''
						with (FIRSTROW=2, FIELDTERMINATOR=''" . $fieldseparator . "'', ROWTERMINATOR=''\r\n'', CODEPAGE = ''ACP'')');");
                    echo dropSQLTableScript($temp_table) . $createTableStatement . "
						EXEC('BULK INSERT [dbo].[" . $temp_table . "] from ''" . $file . "''
						with (FIRSTROW=2, FIELDTERMINATOR=''" . $fieldseparator . "'', ROWTERMINATOR=''\r\n'', CODEPAGE = ''ACP'')');" . PHP_EOL;

                    //one more attempt done, check for errors
                    $attempts++;
                    $error = getSQLError();

                    //if errors and attempts available -> check for the wrong field and convert it to varchar(255). Then restart.
                    if ($error !== null && ($attempts < $total_attempts)) {

                        foreach ($header as $k => $h) {
                            //checks for the SQL message relatively to the incriminated field -> switch to varchar and try again.
                            if (preg_match('/\(' . preg_quote($h, '/') . '\)/', $error)) {
                                $OriginalFieldTypes[$k] = 'varchar(255)';
                                break;
                            }
                        }
                    }
                }
                //if error === null query is successful. Never go beyond total attempts.
                while ($error !== null && ($attempts < $total_attempts));

                if ($error !== null) {
                    throw new Exception($error);
                }

                //import is complete. If no error, then check out for internal conversion in case of $SQLFloatConvert fields.
                $sql_convert_result = true;
                $sql_convert_error = '';

                if ($SQLFloatConvert && $error === null) {

                    $fields_do_convert = array();

                    //for each field to be checked against comma, try to select and convert. If no error, then it is float.
                    foreach ($SQLFloatConvert as $k => $field_to_float) {
                        $float_result = sqlsrv_query($conn, "SELECT CONVERT(float, REPLACE([" . $field_to_float . "],',','.')) FROM [" . $temp_table . "]");
                        if ($float_result !== false) {
                            $fields_do_convert[$k] = $field_to_float;
                        }

                    }

                    //if at least one field is available for convertion, prepare SELECT INTO statement from temp table to final table, converting varchar numeric fields to float. Otherwise replicate temp table into final.
                    if ($fields_do_convert) {
                        $select = array();
                        foreach ($header as $k => $h) {
                            if (isset($fields_do_convert[$k])) {
                                $select[] = "CONVERT(float, REPLACE([" . $h . "],',','.')) [" . $h . "]";
                            } else {
                                $select[] = "[" . $h . "]";
                            }

                        }

                        //run SELECT INTO statement
                        $sql_convert_result = sqlsrv_query($conn, /*"drop table #TEMP_ORIGINALE;" . */dropSQLTableScript($table) . "SELECT " . implode(", ", $select) . " INTO [" . $table . "] FROM [" . $temp_table . "]");
                    } else {
                        $sql_convert_result = sqlsrv_query($conn, dropSQLTableScript($table) . "SELECT * INTO [" . $table . "] FROM [" . $temp_table . "]");
                    }

                    $sql_convert_error = getSQLError();

                    if ($sql_convert_error !== null) {
                        throw new Exception($sql_convert_error);
                    }
                    //$sql_convert_result = false;

                }

                writeLog("SUCCESS - FILE $file", getFileType($file));

            } catch (Exception $e) {

                writeLog("ERROR - FILE $file - " . $e->getMessage(), getFileType($file));
            }
        }
        sqlsrv_close($conn);
    } else {
        writeLog("Unable to connect to SQL: " . $conn, getFileType($file));
    }

}

function getFieldTypes($file, $header, $fieldseparator)
{

    global $sql;

    $field_type = array();
    $sql_convert = array();
    $processed = array();
    $row = 0;
    $total_floats = 0;

    if ($sql['create_float_threshold'] == 0) {

        foreach ($header as $k => $h) {
            $field_type[$k] = 'varchar(255)';
        }
    } else {

        if (($f = fopen($file, "r")) !== false) {
            while (($r = fgetcsv($f, 0, $fieldseparator)) !== false) {
                $row++;
                if ($row == 1) {
                    continue;
                } //skip title

                foreach ($header as $k => $h) {
                    //if not varchar yet, then check it out.

                    if (!in_array($k, $processed)) {
                        if (!in_array($k, $sql_convert) && preg_match('/^-?\d+(\.\d+)?$/', $r[$k])) {
                            $field_type[$k] = 'float';
                        } else if (preg_match('/^-?\d+(,\d+)$/', $r[$k])) {
                            $field_type[$k] = 'varchar(255)';
                            $sql_convert[$k] = $h;
                        } else {
                            $field_type[$k] = 'varchar(255)';
                            $processed[] = $k;
                            if (in_array($k, $sql_convert)) {
                                unset($sql_convert[$k]);
                            }

                        }
                    }
                }

                if ($row >= $sql['create_float_threshold']) {
                    break;
                }

            }

            fclose($f);
        }
    }

    return array($sql_convert, $field_type);
}

function combineHeaderTypeFields($label, $fieldType)
{
    return "[" . $label . "] " . $fieldType;
}

function dropSQLTableScript($table)
{
    return "IF (EXISTS (SELECT * FROM INFORMATION_SCHEMA.TABLES
			WHERE TABLE_SCHEMA = 'dbo'
			AND  TABLE_NAME = '" . $table . "'))
			DROP TABLE [dbo].[" . $table . "];";
}

function clearCommas($string)
{
    global $BC;
    global $sql;

    $string = str_replace('""', "$$**$$", $string);

    $string = str_replace($sql['SAPfieldseparator'], '', $string); //gets rid of the sql separator.
    $string = str_replace($BC['fieldseparator'], $sql['SAPfieldseparator'], $string);

    $patternSemiCol = '/"[^"]+"/';

    $revised_string = preg_replace_callback($patternSemiCol, function ($m) {
        global $BC;
        global $sql;

        return str_replace($sql['SAPfieldseparator'], $BC['fieldseparator'], $m[0]);
    }, $string);

    $revised_string = str_replace('"', '', $revised_string);

    $revised_string = str_replace('$$**$$', '""', $revised_string);

    return $revised_string;
}

function getProtocols($file, $field)
{

    global $BC;

    $protocols = array();
    $row = $BC['fromBoard'] ? 1 : 0; //change this in order to get the header or not

    if (($f = fopen($file, "r")) !== false) {
        while (($protocol = fgetcsv($f, 1000, "\t")) !== false) {
            if ($row == 1) {
                $row++;
                continue;
            } //skip title
            $protocols[] = $protocol[$field];
        }
        fclose($f);
    }

    return ($protocols);
}

function getHeader($file, $removeEndingLineFeed = true)
{
    $fn = fopen($file, "r");
    $result = fgets($fn);
    fclose($fn);
    if ($removeEndingLineFeed) {
        $result = str_replace("\r\n", "", $result);
    }

    return $result;
}

function clearFolder($dir)
{
    $files = glob($dir . '*');
    foreach ($files as $file) {
        if (is_file($file)) {
            unlink($file);
        }

    }
}

function isCsv($file)
{

    //check if file folder is csv folder.
    global $path_csv;

    $file_path = dirname($file);

    return strtolower(rtrim($file_path, '/')) == strtolower(rtrim($path_csv, '/'));
}

function getFileType($file)
{

    return isCsv($file) ? CSV : SAP;
}

function checkFile($file)
{

    global $sql;

    $separator = $sql['CSVfieldseparator'] == "\t" ? '\t' : $sql['CSVfieldseparator'];

    $type = getFileType($file);
    $result = true;

    //check if file exists.
    if (!file_exists($file)) {
        writeLog('I can\'t find file ' . $file, $type);
        $result = false;
    }

    return $result;
}

function runSQLStatements($type)
{
    global $sql;

    try {
        $conn = new PDO("sqlsrv:server=" . $sql['sql'] . ";Database = " . $sql['db'], $sql['user'], $sql['psw']);
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    } catch (PDOException $e) {
        die("Error connecting to SQL Server");
    }

    $scripts = glob($sql['sql_folder'] . '/*.sql');

    if (!$scripts) {
        return;
    }

    foreach ($scripts as $s) {

        try {
            $content = file_get_contents($s);
            $sql_result = $conn->query($content);
            $sql_result = null;
            writeLog("SUCCESS - SCRIPT " . basename($s), $type);
        } catch (Exception $e) {
            writeLog("ERROR - SCRIPT " . basename($s) . " - " . $e->getMessage(), $type);
        }
    }

    $conn = null;

}

function SQLconnect()
{
    global $sql;

    //CONNESSIONE DB RELAZIONALE
    $connectionInfo = array(
        "UID" => $sql['user'],
        "PWD" => $sql['psw'],
        "Database" => $sql['db'],
    );

    /* Connect using SQL Server Authentication. */
    $conn = sqlsrv_connect($sql['sql'], $connectionInfo);

    if ($conn === false) {
        return array(false, print_r(sqlsrv_errors(), true));
    } else {
        return array(true, $conn);
    }

}

function getSQLError()
{
    //print_r(sqlsrv_errors());
    if (($errors = sqlsrv_errors()) != null) {
        return $errors[0]['message'];
    } else {
        return null;
    }

}

function writeLog($text, $type)
{

    global $path_logs;

    $now = date("d/m/Y H:i:s");

    if (isset($path_logs[$type]) && file_exists($path_logs[$type])) {

        $fp = fopen($path_logs[$type] . $type . '2SQL.log', 'a');
        fwrite($fp, $now . ' - ' . $text . PHP_EOL);
        fclose($fp);
    } else {
        echo 'Path for logs not found!';
    }

}
