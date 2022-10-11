<?php
include_once 'functions.php';
include_once 'QueryBuilder.php';
include_once 'CSV.php';

class Roger
{
    private $sql;
    private $conn;
    private $type;
    private $fieldseparator;

    public function __construct(array $sql, string $type)
    {
        $this->sql = $sql;
        $this->type = $type;
        $this->fieldseparator = $this->isCsv() ? $sql['CSVfieldseparator'] : $sql['SAPfieldseparator'];
    }

    public function upload2SQL(string $file, string $table)
    {
        global $sql;

        try {
            $table = preg_replace('/\s/', '_', $table);
            if (!file_exists($file)) {
                throw new Exception("File $file does not exist.");
            }

            $header = $this->getAndSanitizeHeader($file);
            list($SQLFloatConvert, $originalFieldTypes) = $this->getFieldDataTypes($file, $header);

            $qb = new QueryBuilder();
            $attempts = 0;
            $count_field_types = array_count_values($originalFieldTypes);
            $total_attempts = isset($count_field_types['float']) ? $count_field_types['float'] + 1 : 1;
            //if error === false query is successful. Never go beyond total attempts.
            do {
                //create temp table or real table according to whether SQL checks are to be done eventually.
                $temp_table = $SQLFloatConvert ? '#TEMP_' . $table . $attempts : $table;

                try {
                    $mustRun = true;
                    $lastError = null;
                    $this->connect();
                    $create_temp_stmt = $qb->createTable($temp_table, $header, $originalFieldTypes, $SQLFloatConvert ? true : false);
                    $bulk_insert_stmt = $qb->bulkInsert($temp_table, $file, $this->fieldseparator);
                    $this->conn->exec($create_temp_stmt);
                    $this->conn->exec($bulk_insert_stmt);
                    $mustRun = false; //to exit loop

                } catch (Exception $e) {
                    $lastError = $e->getMessage();
                    if ($attempts < $total_attempts) {
                        foreach ($header as $k => $h) {
                            //checks for the SQL message relatively to the incriminated field -> switch to varchar and try again.
                            if (preg_match('/\(' . preg_quote($h, '/') . '\)/', $lastError)) {
                                $originalFieldTypes[$k] = 'varchar(255)';
                                break;
                            }
                        }
                    }
                } finally {
                    $attempts++;
                }
            } while ($mustRun && ($attempts < $total_attempts));

            //if after all attempts, we still get error => something is wrong => STOP!
            if ($mustRun) {
                throw new Exception($lastError);
            }

            //continue if there is convertion to finalize temp table
            if ($SQLFloatConvert) {

                $fields_do_convert = [];

                //for each field to be checked against comma, try to select and convert. If no error, then it is float.
                foreach ($SQLFloatConvert as $k => $field_to_float) {
                    $select = $qb->select($temp_table, ['1'], ["TRY_CONVERT(float, REPLACE([$field_to_float],',','.')) IS NULL", "$field_to_float IS NOT NULL"]);
                    $stmt = $this->conn->query($select);
                    if ($stmt->fetch() === false) {
                        $fields_do_convert[] = $field_to_float;
                    }
                }

                //final select into final table
                $selectFields = array_map(fn($h) => in_array($h, $fields_do_convert) ? $qb->convert("REPLACE([$h],',','.')", 'float') . ' as ' . $h : $h, $header);

                $drop_final_stmt = $qb->dropTable($table);
                $selectInto_final_stmt = $qb->selectInto($temp_table, $table, $selectFields);
                $this->conn->query($drop_final_stmt);
                $this->conn->query($selectInto_final_stmt);

            }

            $this->writeLog("FILE - $file", 'SUCCESS');

        } catch (Exception $e) {
            $this->writeLog("FILE - " . $e->getMessage());
        } finally {
            $this->disconnect();
        }
    }

    public function autoConcat()
    {
        global $auto_concat;
        global $activate_auto_concat;
        global $auto_concat_procedure_name;

        if (!$activate_auto_concat) {
            return;
        }

        $this->installTableConsolidation();

        try {
            if (!is_array($auto_concat)) {
                throw new Exception('Auto Concat param must be an array!');
            }

            foreach ($auto_concat as $k => $ac) {
                $check = array_keys($ac);
                if (!in_array('input_table_like', $check)) {
                    throw new Exception(sprintf("Auto Concat entry %d is missing '%s' parameter!", $k + 1, 'input_table_like'));
                }
                if (!in_array('final_table', $check)) {
                    throw new Exception(sprintf("Auto Concat entry %d is missing '%s' parameter!", $k + 1, 'final_table'));
                }
                if (!in_array('replace_field', $check)) {
                    throw new Exception(sprintf("Auto Concat entry %d is missing '%s' parameter!", $k + 1, 'replace_field'));
                }
                if (!is_array($ac['replace_field'])) {
                    throw new Exception(sprintf("Auto Concat entry %d parameter '%s' is not properly specified (must be array)!", $k + 1, 'replace_field'));
                }

                $this->connect();
                $qb = new QueryBuilder();
                $dummy_params = array_fill(0, 5 - sizeof($ac['replace_field']), '1');
                $fieldNames = array_map(fn($f) => $this->cleanFieldName($f), $ac['replace_field']);
                $params = [$ac['input_table_like'], $ac['final_table'], ...array_values($fieldNames)];
                $exec_statement = $qb->execStoredProcedure($auto_concat_procedure_name, array_merge($params, $dummy_params), true);
                $this->conn->exec($exec_statement);
                $this->writeLog('PROCEDURE - Autoconcat successful with params ' . implode(', ', $params), 'SUCCESS');

            }

        } catch (Exception $e) {
            $this->writeLog('PROCEDURE - ' . $e->getMessage());
        } finally {
            $this->disconnect();
        }

    }

    public function runBatchFiles()
    {
        global $sql;

        $scripts = glob($sql['batch_folder'] . '*.bat');

        if (!$scripts) {
            return;
        }

        foreach ($scripts as $s) {

            try {
                $res = shell_exec($s);
                $this->writeLog("BATCH - " . basename($s), 'SUCCESS');
            } catch (Exception $e) {
                $this->writeLog("BATCH - " . basename($s) . " - " . $e->getMessage());
            }

        }

    }

    public function runSQLStatements()
    {
        global $sql;

        $scripts = glob($sql['sql_folder'] . '*.sql');

        if (!$scripts) {
            return;
        }

        $qb = new QueryBuilder();

        foreach ($scripts as $s) {

            try {
                $content = file_get_contents($s);
                $commands = $qb->splitSQLActions($content); //clear multiline comments & split GO's
                $this->connect();
                foreach ($commands as $c) {
                    $sql_result = $this->conn->query($c);
                }
                $this->writeLog("SCRIPT - " . basename($s), 'SUCCESS');
            } catch (Exception $e) {
                $this->writeLog("SCRIPT - " . basename($s) . " - " . $e->getMessage());
            } finally {
                $sql_result = null;
                $this->disconnect();
            }

        }

    }

    private function getAndSanitizeHeader(string $file)
    {
        $fn = fopen($file, "r");
        $headerLine = fgets($fn);
        fclose($fn);

        $headerLine = str_replace("\r\n", "", $headerLine);
        $i = 0;
        $regex = $this->fieldseparator === "\t" ? '\t' : $this->fieldseparator;
        $headerLine = preg_replace_callback('/(^' . $regex . ')|(' . $regex . '(?=' . $regex . '))|(' . $regex . '$)/i', function ($matches) use (&$i) {
            $i++;
            $field = "Field$i";
            if (isset($matches[1]) && $matches[1] == $this->fieldseparator) {
                return "Field$i" . $this->fieldseparator;
            }
            return $this->fieldseparator . "Field$i";
        }, $headerLine);

        $headerLine = ltrim($headerLine, $this->fieldseparator);
        $header = explode($this->fieldseparator, $headerLine);

        $headerCount = array();

        foreach ($header as $k => $h) {
            $string = $this->cleanFieldName($h);

            if (!isset($headerCount[$string])) {
                $headerCount[$string] = 1;
                $header[$k] = $string;
            } else {
                $header[$k] = $string . '_' . $headerCount[$string]++;
            }

        }

        return $header;

    }

    private function cleanFieldName(string $field)
    {
        $string = preg_replace('/[^\p{L}\p{N}_]/u', '', utf8_encode($field)); //remove special chars.
        $unwanted_array = array('Š' => 'S', 'š' => 's', 'Ž' => 'Z', 'ž' => 'z', 'À' => 'A', 'Á' => 'A', 'Â' => 'A', 'Ã' => 'A', 'Ä' => 'A', 'Å' => 'A', 'Æ' => 'A', 'Ç' => 'C', 'È' => 'E', 'É' => 'E',
            'Ê' => 'E', 'Ë' => 'E', 'Ì' => 'I', 'Í' => 'I', 'Î' => 'I', 'Ï' => 'I', 'Ñ' => 'N', 'Ò' => 'O', 'Ó' => 'O', 'Ô' => 'O', 'Õ' => 'O', 'Ö' => 'O', 'Ø' => 'O', 'Ù' => 'U',
            'Ú' => 'U', 'Û' => 'U', 'Ü' => 'U', 'Ý' => 'Y', 'Þ' => 'B', 'ß' => 'Ss', 'à' => 'a', 'á' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a', 'å' => 'a', 'æ' => 'a', 'ç' => 'c',
            'è' => 'e', 'é' => 'e', 'ê' => 'e', 'ë' => 'e', 'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i', 'ð' => 'o', 'ñ' => 'n', 'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'õ' => 'o',
            'ö' => 'o', 'ø' => 'o', 'ù' => 'u', 'ú' => 'u', 'û' => 'u', 'ý' => 'y', 'þ' => 'b', 'ÿ' => 'y');
        $string = strtr($string, $unwanted_array);
        $string = strtoupper($string);
        return $string;
    }

    private function getFieldDataTypes(string $file, array $header)
    {
        //init all as varchar
        $field_type = array_map(fn($f) => 'varchar(255)', $header);

        if ($this->sql['create_float_threshold'] == 0) {
            return [[], $field_type];
        }

        $f = fopen($file, "r");
        $row = 0;
        $sql_convert = [];
        $processed = [];

        while (($r = fgetcsv($f, 0, $this->fieldseparator)) !== false) {
            $row++;
            if ($row === 1) {
                continue;
            } //skip title

            foreach (array_filter($header, fn($h) => !in_array($h, $processed)) as $k => $h) {

                if (!isset($r[$k]) || !$r[$k]) { //ignore nulls
                    continue;
                }

                //numeric with dot as decimal separator or empty cell
                $regex = $this->sql['do_convert_leading_zeros'] ? '/^-?\d+(\.\d+)?$/' : '/^-?(?(?=0)0(\.\d+)|\d+(\.\d+)?)$/'; // do not convert with leading zeros
                if (!in_array($k, $sql_convert) && (preg_match($regex, $r[$k]))) {
                    $field_type[$k] = 'float';
                    continue;
                }
                $regex = $this->sql['do_convert_leading_zeros'] ? '/^-?\d+(,\d+)$/' : '/^-?(?(?=0)0(\,\d+)|\d+(\,\d+))$/'; // do not convert with leading zeros
                //numeric with comma as decimal separator --> need to convert to dot
                if (preg_match($regex, $r[$k])) {
                    $field_type[$k] = 'varchar(255)';
                    $sql_convert[$k] = $h;
                    continue;
                }

                //else varchar
                $field_type[$k] = 'varchar(255)';
                $processed[] = $h;
                if (in_array($h, $sql_convert)) {
                    unset($sql_convert[$k]);
                }

            }

            if ($row >= $this->sql['create_float_threshold']) {
                break;
            }

        }

        fclose($f);

        return [$sql_convert, $field_type];
    }

    private function installTableConsolidation()
    {
        global $auto_concat_procedure_name;
        $procedure = file_get_contents(__DIR__ . '/sql/consolidate_table.sql');
        try {
            $this->connect();
            $qb = new QueryBuilder();
            $statement = sprintf($procedure, $qb->schema, $auto_concat_procedure_name, $qb->schema, $auto_concat_procedure_name, $qb->schema, $auto_concat_procedure_name);
            $commands = $qb->splitSQLActions($statement); //clear multiline comments & split GO's
            foreach ($commands as $c) {
                $this->conn->exec($c);
            }
        } catch (Exception $e) {
            $this->writeLog('PROCEDURE - ' . $e->getMessage(), );
        } finally {
            $this->disconnect();
        }
    }

    private function isCsv()
    {
        return $this->type === CSV;
    }

    private function connect()
    {
        $this->conn = new PDO("sqlsrv:server=" . $this->sql['sql'] . ";Database = " . $this->sql['db'], $this->sql['user'], $this->sql['psw']);
        $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    private function disconnect()
    {
        $this->conn = null;
    }

    public function writeLog($text, $errorType = 'ERROR')
    {

        global $path_logs;

        $date = date("Ymd");
        $time = date("H:i:s");

        if (isset($path_logs[$this->type]) && file_exists($path_logs[$this->type])) {

            $csv = new CSV($path_logs[$this->type] . $this->type . '2SQL.log', "\t");
            $csv->write([[$date, $time, $errorType, $text]], ['Error_date', 'Error_time', 'Error_type', 'Error_descripion']);

        } else {
            echo 'Path for logs not found!';
        }

    }

}
