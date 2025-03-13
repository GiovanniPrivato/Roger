<?php
include_once 'config.php';
include_once 'functions.php';
include_once 'CSV.php';

class Logger extends CSV
{

    protected $header = ['Error_date', 'Error_time', 'Error_type', 'Error_descripion'];
    private $file     = '';
    private $type;

    public function __construct(string $type)
    {
        global $path_logs;

        $this->type = $type;

        if (! isset($path_logs[$this->type]) || ! file_exists($path_logs[$this->type])) {
            echo 'Path for logs not found!';
            die();
        }

        $this->file = $path_logs[$this->type] . $this->type . '2SQL.log';

        parent::__construct($this->file, "\t");
    }

    public function writeLog($text, $errorType = 'ERROR')
    {

        $date = date("Ymd");
        $time = date("H:i:s");

        if (! isset($path_logs[$this->type]) || ! file_exists($path_logs[$this->type])) {

            $this->write([[$date, $time, $errorType, $text]], $this->header);

        } else {
            echo 'Path for logs not found!';
        }

    }
}
