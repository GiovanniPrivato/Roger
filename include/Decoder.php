<?php
include_once 'config.php';
include_once 'functions.php';

class Decoder
{
    private $path;
    private $delim;
    private $decoder = [];
    private $codeField = 0;
    private $descField = 1;

    const DESC = 1;
    const CODE = 2;

    public function __construct(string $path, string $delim)
    {
        $this->path = $path;
        $this->delim = $delim;
        $this->decoder = $this->checkAndLoadFile();
    }

    public function decode(string $string)
    {

        // if(!isset($this->decoder[strtolower($string)])) echo preg_replace('/[\r\n]/', '', $string);

        // $string = preg_replace('/[\r\n]/', '', $string);

        // return isset($this->decoder[strtolower($string)]) ? $this->decoder[strtolower($string)] : false;

        $string = preg_replace('/[\s]/', '', $string);

        $keys = array_map(fn($v) => strtolower(preg_replace('/[\s]/', '', $v)), $this->list(self::DESC));

        $index = array_search(strtolower($string), $keys);

        return $index !== false ? array_values($this->decoder)[$index] : false;

    }

    public function setCodeField(int $codeField)
    {
        $this->codeField = $codeField;
        $this->decoder = $this->checkAndLoadFile();
    }
    public function setDescField(int $descField)
    {
        $this->descField = $descField;
        $this->decoder = $this->checkAndLoadFile();
    }

    public function list(int $type)
    {
        return $type === self::DESC ? array_keys($this->decoder) : array_values($this->decoder);
    }

    private function checkAndLoadFile()
    {

        if (!file_exists($this->path)) {
            throw new Exception("I can't find decode file '$this->path'");
        }

        $array = [];
        $row = 1;

        if (($f = fopen($this->path, "r")) !== false) {
            while (($r = fgetcsv($f, 1000, $this->delim)) !== false) {
                if ($row == 1) {
                    $row++;
                    continue;
                } //skip title
                if (isset($r[$this->descField])) {
                    $array[strtolower($r[$this->descField])] = $r[$this->codeField];
                }

            }
            fclose($f);
        }

        return $array;
    }
}
