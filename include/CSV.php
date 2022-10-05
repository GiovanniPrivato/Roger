<?php
include_once 'config.php';
include_once 'functions.php';

class CSV
{

    private $path;
    private $delim;

    public function __construct(string $path, string $delim)
    {
        $this->path = $path;
        $this->delim = $delim;
    }

    public function write(array $data, array $labels = [])
    {

        $withLabels = !file_exists($this->path) || !$this->getHeader();

        $fp = fopen($this->path, 'a');

        if ($withLabels && $labels) {
            fwrite($fp, implode($this->delim, $labels) . "\r\n");
        }
        //add headers

        foreach ($data as $k => $fields) {
            // if(!$withLabels && $k === 0) continue;
            fwrite($fp, implode($this->delim, $fields) . "\r\n"); //add data
        }

        fclose($fp);
    }

    private function getHeader()
    {
        $fn = fopen($this->path, "r");

        $headerLine = fgets($fn);

        fclose($fn);

        $headerLine = str_replace("\r\n", "", $headerLine);

        return $headerLine;
    }

}
