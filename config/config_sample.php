<?php
$_PATH = "path/to/config/dir";

//SQL PARAMS
$sql["sql"] = ""; //SQL host
$sql["user"] = ""; //SQL username
$sql["psw"] = ""; //SQL password
$sql["db"] = ""; //SQL db - use different DB to run in parallel
$sql["SAPfieldseparator"] = ";"; //SQL separator for BULK SAP - do not touch this unless necessary
$sql["CSVfieldseparator"] = "\t"; //SQL separator for BULK CSV - TAB by default - do not touch this unless necessary
$sql["create_float_threshold"] = 0; //if =0 Roger skips float data type check and imports all data as varchar(255). If >0 Roger checks float data type values until threshold and tries to create table accordingly.

//CSV PARAMS
$path_csv = "$_PATH/csv/"; //csv files - use different folders to run in parallel
$path_csv_processed = "$_PATH/csv_processed/"; //csv processed files - use different folders to run in parallel


//SAP CONNECTOR PARAMS
$BC["url"] = "http://localhost:8097/"; //Board connector URL
$BC["fieldseparator"] = ","; //Board connector separator - do not touch this unless necessary
$BC["extractionsFilesPath"] = "C:/Program Files/BOARDConnector/config/extractions/"; //path of Board Connector protocols repository
$BC["protocols_file"] = "$_PATH/Protocols_all.txt"; //file to be read by board with protocols name
$BC["protocols_prefix"] = ""; //leave empty if not needed - to collect only protocols starting with...
$BC["protocols_suffix"] = ""; //leave empty if not needed - to collect only protocols ending with...

$BC["protocols_to_upload"] = "$_PATH/Protocols_to_upload.txt"; //Board extraction of SAP protocols to be loaded.
$BC["fromBoard"] = false; //if true, file "protocols_file" is coming from Board. If false, it is manually updated as a standard list.

$path_SAP = "$_PATH/files/"; //SAP temporary files - use different folders to run in parallel

//LOGS
$path_logs[CSV] = "$_PATH/logs/";
$path_logs[SAP] = "$_PATH/logs/";
