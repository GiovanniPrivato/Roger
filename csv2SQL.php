<?php
include 'include/config.php';
include 'include/functions.php';
include 'include/Logger.php';
include 'include/ExcelFile.php';
include 'include/Roger.php';

$roger = new Roger($sql, CSV);
//optionally runs batch files before.
$roger->runBatchFiles();

$files = glob(sprintf('%s*.{%s}', $path_csv, implode(",", $path_csv_extensions)), GLOB_BRACE);

if (! $files) {
    return;
}

foreach ($files as $file) {

    if (! file_exists($file)) {
        continue;
    }

    echo 'Updating ' . basename($file) . '...' . PHP_EOL;

    $excel = new ExcelFile($file);

    //excel files
    if ($excel->isExcelFile()) {
        $excelFiles = $excel->toCSV($sql['CSVfieldseparator'], $sql['CSVrowterminator']);

        foreach ($excelFiles as $excelFile) {
            $roger->upload2SQL($excelFile, strtoupper(pathinfo($excelFile)['filename']));
            rename($excelFile, $path_csv_processed . basename($excelFile));
        }

    } else {
        $roger->upload2SQL($file, strtoupper(pathinfo($file)['filename']));
    }

    //move original file in processed folder
    rename($file, $path_csv_processed . basename($file));

}

if (isset($auto_concat) && $auto_concat) {
    $roger->autoConcat();
}
//optionally runs statements after.
$roger->runSQLStatements();
