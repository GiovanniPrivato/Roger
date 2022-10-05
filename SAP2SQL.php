<?php
include 'include/config.php';
include 'include/functions.php';
include 'include/SapReader.php';
include 'include/Roger.php';

$sap = new SapReader($BC);
$roger = new Roger($sql, SAP);

//optionally runs batches before.
$roger->runBatchFiles();

if ($protocols = getProtocols($BC['protocols_to_upload'], 0)) {

    clearFolder($path_SAP);

    foreach ($protocols as $p) {

        $file = $path_SAP . $p . '.txt';

        echo 'Updating ' . $p . '...' . PHP_EOL;

        $sap->downloadData($p, $file);

        $roger->upload2SQL($file, $p);

        unlink($file);

    }

    if (isset($auto_concat) && $auto_concat) {
        $roger->autoConcat();
    }

    $roger->runSQLStatements(); //optionally runs statements after.

}
