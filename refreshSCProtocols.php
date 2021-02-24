<?php
include('include/config.php');
include('include/functions.php');

$files = glob($BC['extractionsFilesPath'].$BC['protocols_prefix'].'*'.$BC['protocols_suffix']); 

$filenames = array_map("basename", $files);

file_put_contents($BC['protocols_file'], implode("\r\n", $filenames));


?>