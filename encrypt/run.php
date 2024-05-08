<?php

require_once 'Obfuscator.php';

$src = dirname(__DIR__);
$output = "release";
$output_full = dirname(__DIR__) . '\\' . $output;
rrmdir($output_full);
mkdir($output_full, 0777, true);

$excludes = file_get_contents(__DIR__ . '/exclude.txt');
$excludes = explode(PHP_EOL, $excludes);
$excludes_but_copy = file_get_contents(__DIR__ . '/exclude_but_copy.txt');
$excludes_but_copy = explode(PHP_EOL, $excludes_but_copy);

foreach ($excludes as $key => $folder) {
    $excludes[$key] = $src . '\\' . $folder;
}

foreach ($excludes_but_copy as $key => $folder) {
    $excludes_but_copy[$key] = $src . '\\' . $folder;
}

$rec = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($src));
foreach ($rec as $file) {

    $filePath = $file->getPathname();
    if (array_filter($excludes, fn($e) => preg_match('/' . preg_quote($e) . '/', $filePath))) {
        continue;
    }

    if ($file->isDir()) {
        $newDir = str_replace($src, $output_full, $file->getPath());
        if (!is_dir($newDir)) {
            mkdir($newDir, 0777, true);
        }

        continue;
    }

    $contents = "";
    $newFile = str_replace($src, $output_full, $filePath);

    //not php
    if (pathinfo($filePath, PATHINFO_EXTENSION) != 'php' || array_filter($excludes_but_copy, fn($e) => preg_match('/' . preg_quote($e) . '/', $filePath))) {
        copy($filePath, $newFile);
        continue;
    }

    Obfuscator::obfuscateFile($filePath, $newFile);

}

echo "Successfully Encrypted...";

function rrmdir($dir)
{
    if (is_dir($dir)) {
        $objects = scandir($dir);
        foreach ($objects as $object) {
            if ($object != "." && $object != "..") {
                if (filetype($dir . "/" . $object) == "dir") {
                    rrmdir($dir . "/" . $object);
                } else {
                    unlink($dir . "/" . $object);
                }

            }
        }
        reset($objects);
        rmdir($dir);
    }
}
