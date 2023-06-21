<?php
include 'include/config.php';
include 'include/functions.php';
include 'include/SapReader.php';
include 'include/Roger.php';

$roger = new Roger($sql, SAP);
//optionally runs batch files before.
$roger->runBatchFiles();

$sap = new SapReader($BC);

if ($protocols = getProtocols($BC['protocols_to_upload'], 0)) {

    clearFolder($path_SAP);

    foreach ($protocols as $p) {

        $file = $path_SAP . $p . '.txt';

        list($success, $code) = $sap->downloadData($p, $file, $theo_options);

        if ($success) {
            $roger->upload2SQL($file, $p);
        } else {
            $roger->writeLog("SAP CONNECTOR - Extraction of protocol $p failed with code $code", 'ERROR');
        }

        unlink($file);

    }

    if (isset($auto_concat) && $auto_concat) {
        $roger->autoConcat();
    }

    $roger->runSQLStatements(); //optionally runs statements after.

}
