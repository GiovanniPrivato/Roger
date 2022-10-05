<?php
include 'include/config.php';
include 'include/functions.php';
include 'include/Roger.php';

$files = glob($path_csv . '*.csv');

$roger = new Roger($sql, CSV);

//optionally runs batches before.
$roger->runBatchFiles();

if ($files) {

    foreach ($files as $file) {

        if (file_exists($file)) {

            echo 'Updating ' . basename($file) . '...' . PHP_EOL;
            $roger->upload2SQL($file, strtoupper(pathinfo($file)['filename']));
            rename($file, $path_csv_processed . basename($file));

        }

    }

    if (isset($auto_concat) && $auto_concat) {
        $roger->autoConcat();
    }
    //optionally runs statements after.
    $roger->runSQLStatements();

}
