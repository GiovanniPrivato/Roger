<?php
function getProtocols($file, $field)
{

    global $BC;

    $protocols = array();
    $row = $BC['fromBoard'] ? 1 : 0; //change this in order to get the header or not

    if (($f = fopen($file, "r")) !== false) {
        while (($protocol = fgetcsv($f, 1000, "\t")) !== false) {
            if ($row == 1) {
                $row++;
                continue;
            } //skip title
            $protocols[] = $protocol[$field];
        }
        fclose($f);
    }

    return ($protocols);
}

function clearFolder($dir)
{
    $files = glob($dir . '*');
    foreach ($files as $file) {
        if (is_file($file)) {
            unlink($file);
        }

    }
}
