<?php
// ----------------------------------- VERSION 4.0 - 04/10/2022 -------------------------------------------- //
define("CSV", "csv");
define("SAP", "SAP");
define("JSON", "JSON");

$options = getopt("c:");
if (!isset($options['c'])) {
    die('Config file not defined');
}

$config_file = $options['c'];
if (substr($config_file, -4) == '.php') {
    $config_file = rtrim($config_file, '.php');
}

include dirname(__DIR__) . '/config/' . $config_file . '.php';

$auto_concat_procedure_name = 'roger_auto_consolidate_table';
$sql["SAPfieldseparator"] = "\t";

$path_csv = adjustPath($path_csv);
$path_csv_processed = adjustPath($path_csv_processed);
$path_SAP = adjustPath($path_SAP);
$path_logs[CSV] = adjustPath($path_logs[CSV]);
$path_logs[SAP] = adjustPath($path_logs[SAP]);
$sql['sql_folder'] = adjustPath($sql['sql_folder']);
$sql['batch_folder'] = adjustPath($sql['batch_folder']);
$BC['protocols_to_upload'] = adjustPath($BC['protocols_to_upload']);
$BC['protocols_file'] = adjustPath($BC['protocols_file']);
$BC['extractionsFilesPath'] = adjustPath($BC['extractionsFilesPath']);

function adjustPath($path)
{
    $path = str_replace("\\", '/', $path);
    $path = rtrim($path, '/');
    $file_parts = pathinfo($path);

    if (!isset($file_parts['extension']) || (isset($file_parts['extension']) && $file_parts['extension'] == null)) {
        $path .= '/';
    }

    return $path;
}
