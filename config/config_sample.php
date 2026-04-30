<?php
$_PATH = "path/to/roger";

//SQL PARAMS
$sql["sql"]                      = "";              //SQL host
$sql["user"]                     = "";              //SQL username
$sql["psw"]                      = "";              //SQL password
$sql["db"]                       = "";              //SQL db - use different DB to run in parallel
$sql["CSVfieldseparator"]        = "\t";            //SQL separator for BULK CSV - TAB by default - do not touch this unless necessary
$sql["CSVrowterminator"]         = "\r\n";          //SQL separator for BULK CSV - \r\n by default - do not touch this unless necessary
$sql["create_float_threshold"]   = 0;               //if =0 Roger skips float data type check and imports all data as varchar(255). If >0 Roger checks float data type values until threshold and tries to create table accordingly.
$sql["do_convert_leading_zeros"] = false;           //if conversion threshold is >0 => if true Roger will attempt conversion to float values like 0100. If false, 0100 will remain varchar.
$sql["batch_folder"]             = "$_PATH/batch/"; //Roger will run each (if any) of the batch files available in the folder in sequential alphabetical order BEFORE any import (regardless import itself).
$sql["sql_folder"]               = "$_PATH/sql/";   //Roger will run each (if any) of the sql scripts available in the folder in sequential alphabetical order AFTER any import (if at least an import is done).
$sql["source_info"]              = false;           //add file info fields to SQL table
$sql['timeout']                  = 300;             //SQL timeout in seconds
//CSV PARAMS
$path_csv            = "$_PATH/csv/";           //csv files - use different folders to run in parallel
$path_csv_processed  = "$_PATH/csv_processed/"; //csv processed files - use different folders to run in parallel
$path_csv_extensions = ['csv'];                 //accepted extensions in csv folder - by default csv only

//only valid if there is xlsx or xls files in csv extensions
$excelTemplates = [
    [
        'template' => 'file_name', //this may even be a substring of the file name
        'sheet'    => '1',         //this may be numerical index or sheet name. Use [name1, name2, index1, ...] for multiple selectors
                                   // 'start_row' => 1,   //optional - automatic if omitted
                                   // 'start_col' => 'A', //optional - automatic if omitted
                                   // 'end_row'   => 1,   //optional - automatic if omitted
                                   // 'end_col'   => 'Z', //optional - automatic if omitted
    ],
];

//SAP CONNECTOR PARAMS
$BC["url"]                  = "http://localhost:8097/";                              //Board connector URL
$BC["fieldseparator"]       = ",";                                                   //Board connector separator - do not touch this unless necessary
$BC["extractionsFilesPath"] = "C:/Program Files/BOARDConnector/config/extractions/"; //path of Board Connector protocols repository
$BC["protocols_file"]       = "$_PATH/Protocols_all.txt";                            //file to be read by board with protocols name
$BC["protocols_prefix"]     = "";                                                    //leave empty if not needed - to collect only protocols starting with...
$BC["protocols_suffix"]     = "";                                                    //leave empty if not needed - to collect only protocols ending with...

$BC["protocols_to_upload"] = "$_PATH/Protocols_to_upload.txt"; //Board extraction of SAP protocols to be loaded.
$BC["fromBoard"]           = false;                            //if true, file "protocols_file" is coming from Board. If false, it is manually updated as a standard list.

$path_SAP = "$_PATH/files/"; //SAP temporary files - use different folders to run in parallel

//LOGS
$path_logs[CSV] = "$_PATH/logs/";
$path_logs[SAP] = "$_PATH/logs/";

$activate_auto_concat = false; //activate autoconcatenation function Y/N
$auto_concat          = [
    [
        'input_table_like' => 'input_%'  //imported table name LIKE condition
        , 'final_table' => 'FINAL_TABLE' //table to append data in
        , 'replace_field' => [           //fields to make comparison against => MAX 5 FIELDS
            'FIELD_1'
            , 'FIELD_2'
            , 'FIELD_3'
            , 'FIELD_4'
            , 'FIELD_5',
        ],
    ],
    [
        'input_table_like' => 'input2_%' //another (optional) condition set
        , 'final_table' => 'FINAL_TABLE2'
        , 'replace_field' => [
            'FIELD_1'
            , 'FIELD_2',
        ],
    ],
];

$activate_unpivot = false; //activate unpivot function Y/N. Unpivot will run AFTER autoconcat if enabled.
$unpivot          = [
    [
        'table_to_unpivot_name' => 'input'    //exact table name to apply unpivot to
        , 'unpivot_field_like' => 'field%'    //field name to unpivot on. Can be LIKE.
        , 'unpivot_field_name' => 'new_field' //exact name of the new field resulting after unpivot. values will be in the autogenerated Amount field.
        , 'consolidate_to_table' => 1         //set it to 0 or 1 in order to consolidate the unpivot to a table or not.
        , 'additionalFields' => [             //optional additional fields to be added to the table/view in sql query language
            'newfield' => 'left(field, 2)'
            , 'newfield2' => 'field_1'
            , 'newfield3' => '...',
        ],
    ],
    [
        '...',
    ],
];
