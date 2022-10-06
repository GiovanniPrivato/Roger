<?php

class SapReader
{
    private $BC;
    private $header;
    private $lastErrorCode;
    private $header_downloaded;

    public function __construct(array $BC)
    {
        $this->BC = $BC;
    }

    public function downloadData($protocol, $file)
    {
        $this->header_downloaded = false;
        $buffer = '';
        $count = 0;

        $fp = fopen($file, 'w');

        // init cURL
        $ch = curl_init();

        // open SAP Connector protocol
        curl_setopt($ch, CURLOPT_URL, sprintf('%s?name=%s', $this->BC['url'], $protocol));

        // no headers
        curl_setopt($ch, CURLOPT_HEADER, 1);

        // remote content not passed to print
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $callback = function ($ch, $str) use ($fp, &$buffer, &$count) {

            // echo curl_getinfo($ch, CURLINFO_HEADER_SIZE) . PHP_EOL;

            $stringLen = strlen($str);

            if (!$this->header_downloaded) {
                $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
                $buffer_header = substr($this->header, $header_size, strlen($this->header) - $header_size - 1);
                $this->extractHeader($str, $header_size);
                if ($this->header_downloaded) {
                    $str = $buffer_header . $str;
                } else {
                    return $stringLen;
                }

            }

            $lines = explode("\r\n", $str);

            $lines_num = sizeof($lines);
            $lines[0] = $buffer . $lines[0]; //prefix the partial last line from last time.
            $buffer = $lines[$lines_num - 1]; //last partial line.
            $lines[$lines_num - 1] = ''; //empty string so to keep glue after.

            $strings = implode("\r\n", $lines);

            $strings = $this->clearCommas($strings);

            $strings = mb_convert_encoding($strings, 'ISO-8859-1', 'UTF-8');

            fwrite($fp, $strings); //adding separator at begin and end.

            return $stringLen; //return the exact length
        };

        curl_setopt($ch, CURLOPT_WRITEFUNCTION, $callback);
        curl_exec($ch);

        // close cURL
        curl_close($ch);
        fclose($fp);

        return [$this->lastErrorCode == "200", $this->lastErrorCode];

    }

    private function extractHeader(string $content, int $header_size)
    {

        if (strlen($this->header) > $header_size) {
            $this->header = substr($this->header, 0, $header_size);
            $this->header_downloaded = true;
            $this->lastErrorCode = preg_match('/\d{3}/', $this->header);
            return;
        }

        $this->header = $this->header . $content;

    }

    private function clearCommas(string $string)
    {
        global $sql;
        global $BC;

        // $string = str_replace('""', "$$**$$", $string);
        //"(?:(?:(?:"")|[^"]).*?)*" => regex SAP quoted strings
        //(?:("?)(?:(?:(?:"")|[^"])*?.*?)*\1)\K, => regex unquoted commas

        $string = str_replace($sql['SAPfieldseparator'], '', $string); //gets rid of the TAB.
        $string = preg_replace('/(?:("?)((?:(?:(?:"")|[^"])*?.*?)*)(?:\1))(' . $BC['fieldseparator'] . ')/i', "$2" . $sql['SAPfieldseparator'], $string);
        // $string = str_replace($BC['fieldseparator'], $sql['SAPfieldseparator'], $string);

        $string = str_replace('""', '"', $string); //double quotes replacement

        // $revised_string = str_replace('$$**$$', '""', $revised_string);

        return $string;
    }

    private function isDownloadSuccessful()
    {
        return $this->lastErrorCode == "200";
    }
}
