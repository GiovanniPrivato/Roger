<?php
include_once 'CSV.php';
include_once 'Logger.php';
include_once "libraries/vendor/autoload.php";

class ExcelFile
{

    private $file;
    private $fileName;
    private $data = [];
    private Logger $logger;

    public function __construct($file)
    {
        $this->file     = $file;
        $this->fileName = pathinfo($file)['filename'];
        $this->logger   = new Logger(CSV);
        if ($this->isExcelFile()) {
            $this->loadData();
        }

    }

    public function toCSV(string $separator = ';', string $rowTerminator = "\r\n")
    {

        global $path_csv;

        $files = [];

        if (! $this->data) {
            return [];
        }

        foreach ($this->data as $d) {

            $csvFile = $path_csv . $this->fileName . '_' . $d['sheetName'] . '.' . ($path_csv_extensions[0] ?? 'csv');

            if (file_exists($csvFile)) {
                unlink($csvFile);
            }

            $csv = new CSV($csvFile, $separator);
            $csv->write($d['data'], []);
            $this->logger->writelog(sprintf('File %s created from template %s and sheet %s', basename($csvFile), $d['template']['template'], $d['sheetName']), 'SUCCESS');
            $csv = null;

            $files[] = $csvFile;
        }

        return $files;

    }

    public function isExcelFile()
    {
        return in_array(pathinfo($this->file)['extension'], ['xlsx', 'xls']);
    }

    public function loadData()
    {

        global $excelTemplates;

        foreach ($excelTemplates as $index => $t) {

            if (! preg_match('/' . $t['template'] . '/i', $this->fileName)) {
                continue;
            }

            try {
                $sheetsData = $this->collectSheetsData($t, $index + 1);
                if (! $sheetsData) {
                    throw new Exception(sprintf('No data available in file %s from template %s and sheet %s', basename($this->fileName), $t['template'], $t['sheet']));
                }
                $this->data = array_merge($this->data, $sheetsData);
            } catch (Exception $e) {
                $this->logger->writelog($e->getMessage(), 'ERROR');
                continue;
            }

        }

    }

    private function collectSheetsData($template, $templateIndex)
    {

        $spreadsheet = PhpOffice\PhpSpreadsheet\IOFactory::load($this->file);
        $data        = [];
        $index       = [];

        if (! isset($template['sheet'])) {
            return [];
        }

        //transforming indexes into array
        if (! is_array($template['sheet'])) {
            $index = [$template['sheet']];
        }

        $sheetIndexes = [];

        foreach ($index as $i) {
            if (is_numeric($i)) {

                if ($i <= 0) {
                    continue;
                    $this->logger->writelog('Cannot load sheet in position 0 for file ' . $this->fileName, 'WARNING');
                }

                if ($spreadsheet->getSheetCount() < $i) {
                    $this->logger->writelog('Cannot load sheet in position ' . $i . ' for file ' . $this->fileName, 'WARNING');
                    continue;
                }
                $sheetIndexes[] = ['type' => 'numeric', 'index' => $i - 1];
            } else {
                $sheets = array_filter($spreadsheet->getSheetNames(), fn($s) => preg_match('/' . $i . '/i', $s));

                $sheetIndexes = array_merge($sheetIndexes, array_map(fn($a) => ['type' => 'name', 'index' => $a], array_keys($sheets)));
            }
        }

        if (sizeof($sheetIndexes) == 0) {
            $this->logger->writelog('No sheets to be loaded for file ' . $this->fileName . ' and template ' . $templateIndex, 'WARNING');
            return [];
        }

        foreach ($sheetIndexes as $i) {
            $names       = $spreadsheet->getSheetNames();
            $name        = $i['type'] == 'numeric' ? $i : $names[$i['index']];
            $data[$name] = ['sheetName' => $name, 'data' => $this->loadSheetData($spreadsheet, $template, $i['index']), 'template' => $template];
        }

        return $data;

    }

    private function loadSheetData($spreadsheet, $template, $index): array
    {

        //first sheet considered with data
        $sheet = $spreadsheet->getSheet($index);

        $lowestColumn  = $template['start_col'] ?? 'A';                      //last available col
        $lowestRow     = $template['start_row'] ?? 1;                        //last available row
        $highestColumn = $template['end_col'] ?? $sheet->getHighestColumn(); //last available col
        $highestRow    = $template['end_row'] ?? $sheet->getHighestRow();    //last available row
        $rows          = $sheet->rangeToArray(
            $lowestColumn . $lowestRow . ':' . $highestColumn . $highestRow, // The worksheet range that we want to retrieve
            null,                                                            // Value that should be returned for empty cells
            true,                                                            // Should formulas be calculated (the equivalent of getCalculatedValue() for each cell)
            false                                                            // Should values be formatted (the equivalent of getFormattedValue() for each cell)
        );

        //get rid of entirely empty lines
        $rows = array_filter($rows, fn($a) => ltrim(implode("", $a)));

        //sanitize line feeds in fields.
        $rows = array_map(fn($a) => str_replace(["\r\n", "\r", "\n"], '', $a), $rows);

        return $rows;
    }

    private function number2Letter(int $number = 0)
    {
        $arr = range('A', 'Z');

        if ($number < sizeof($arr)) {
            return $arr[$number];
        }

        $frontLetter = floor($number / (sizeof($arr) - 1));

        return $arr[$frontLetter - 1] . $arr[$number - ($frontLetter * sizeof($arr))];

    }
}
