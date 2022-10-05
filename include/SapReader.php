<?php

class SapReader
{
    private $BC;

    public function __construct(array $BC)
    {
        $this->BC = $BC;
    }

    public function downloadData($protocol, $file)
    {

        $buffer = '';
        $count = 0;

        $fp = fopen($file, 'w');

        // init cURL
        $ch = curl_init();

        // open SAP Connector protocol
        curl_setopt($ch, CURLOPT_URL, $this->BC['url'] . '?name=' . $protocol);

        // no headers
        curl_setopt($ch, CURLOPT_HEADER, 0);

        // remote content not passed to print
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $callback = function ($ch, $str) use ($fp, &$buffer, &$count) {

            $lines = explode("\r\n", $str);

            $lines_num = sizeof($lines);
            $lines[0] = $buffer . $lines[0]; //prefix the partial last line from last time.
            $buffer = $lines[$lines_num - 1]; //last partial line.
            $lines[$lines_num - 1] = ''; //empty string so to keep glue after.

            $strings = implode("\r\n", $lines);

            $strings = clearCommas($strings);

            $strings = mb_convert_encoding($strings, 'ISO-8859-1', 'UTF-8');

            fwrite($fp, $strings); //adding separator at begin and end.

            return strlen($str); //return the exact length
        };

        curl_setopt($ch, CURLOPT_WRITEFUNCTION, $callback);
        curl_exec($ch);
        //$header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);

        // close cURL
        curl_close($ch);
        fclose($fp);
    }
}
