<?php

/**
 * src : source folder
 * encrypted : Output folder
 */

$costantName = 'PHP_BOLT_KEY';
$php_blot_key = "K.Group!";
$src = dirname(__DIR__);
$output = 'release';
$output_full = $src . '\\' . $output;
rrmdir($output_full);
mkdir($output_full, 0777, true);
/**
 * No need to edit following code
 */

$excludes = file_get_contents($src . '\\encrypt\\exclude.txt');
$excludes = explode(PHP_EOL, $excludes);
$excludes_but_copy = file_get_contents($src . '\\encrypt\\exclude_but_copy.txt');
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

    //php
    $contents = file_get_contents($filePath);

    $cipher = bolt_encrypt("?>" . $contents, $php_blot_key);
    $preppand = "<?php if (!defined('$costantName')) define('$costantName', '$php_blot_key'); bolt_decrypt( __FILE__ , $costantName); return 0;
    ##!!!##";
    // $newFile = str_replace('input', 'encrypted', $filePath);

    $fp = fopen($newFile, 'w');
    fwrite($fp, $preppand . $cipher);
    fclose($fp);

    // $filePathProcessed = str_replace('input', 'processed', $filePathOLD);

    // if (!file_exists(dirname($filePathProcessed))) {
    //     mkdir(dirname($filePathProcessed), 0777, true);
    // }

    // rename($filePathOLD, $filePathProcessed);

    // $job = pathinfo($newFile)['filename'] . '.bat';
    // $phpfile = dirname(__FILE__, 2) . "\\release\\" . pathinfo($newFile)['filename'] . '.php';
    //echo dirname(__FILE__,2).'php\php.exe '.pathinfo($newFile)['filename'].'.bat';
    // file_put_contents(dirname(__FILE__,2).'/jobs/'.$job, dirname(__FILE__,2).'\php\php.exe '.$phpfile);

    unset($cipher);
    unset($contents);
}

// $out_str = substr_replace($src, '', 0, 4);
// $file_location = __DIR__ . "/encrypted/" . $out_str;

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
