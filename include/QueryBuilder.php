<?php

class QueryBuilder
{

    public $schema = 'dbo';

    public function __construct()
    {

    }

    public function select(string $table, array $fields = ['*'], array $where = [])
    {
        $whereCondition = $where ? 'WHERE ' . implode('AND', $where) : '';
        return sprintf("SELECT %s FROM [%s].[%s] %s", implode(", ", $fields), $this->schema, $table, $whereCondition);

    }

    public function selectInto(string $tableFrom, string $tableTo, array $fields = ['*'])
    {
        return sprintf("SELECT %s INTO [%s].[%s] FROM [%s].[%s]", implode(", ", $fields), $this->schema, $tableTo, $this->schema, $tableFrom);

    }

    public function convert(string $field, string $datatype, string $flag = '')
    {
        if ($flag) {
            $flag = ',' . $flag;
        }

        return sprintf("CONVERT(%s, %s %s)", $datatype, $field, $flag);
    }

    public function createTable(string $table, array $fields, array $datatypes, bool $isTemp = false)
    {
        $table_fields = array_map(fn($f, $d) => "[" . $f . "] " . $d, $fields, $datatypes);

        $drop = $isTemp ? '' : $this->dropTable($table);
        return sprintf("%s CREATE TABLE [%s].[%s] (%s);", $drop, $this->schema, $table, implode(", ", $table_fields));

    }

    public function dropTable(string $table)
    {
        return sprintf("IF (EXISTS (SELECT * FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = '%s' AND  TABLE_NAME = '%s'))	DROP TABLE [%s].[%s];", $this->schema, $table, $this->schema, $table);
    }

    public function bulkInsert(string $table, string $file, string $fieldseparator, string $rowterminator = "\r\n", string $codepage = 'ACP')
    {
        return sprintf("EXEC('BULK INSERT [%s].[%s] from ''%s''
						with (FIRSTROW=2, FIELDTERMINATOR=''%s'', ROWTERMINATOR=''%s'', CODEPAGE = ''%s'')');", $this->schema, $table, $file, $fieldseparator, $rowterminator, $codepage);
    }

    public function createStoredProcedure(string $procedure_name)
    {
        return sprintf("IF NOT EXISTS (SELECT * FROM sys.objects WHERE type = 'P' AND OBJECT_ID = OBJECT_ID('[%s].[%s]')) EXEC('CREATE PROCEDURE [%s].[%s] AS BEGIN SET NOCOUNT ON; END')", $this->schema, $procedure_name, $this->schema, $procedure_name);
    }

    public function execStoredProcedure(string $procedure_name, array $options = [], bool $quoteOptions = false)
    {
        $optionClause = $options && $quoteOptions ? sprintf("'%s'", implode("','", $options)) : implode(',', $options);
        return sprintf("EXEC [%s].[%s] %s", $this->schema, $procedure_name, $optionClause);
    }

    public function splitSQLActions(string $sqlCommand)
    {
        $command = preg_replace('/\/\*(?:[\w\W])*?\*\//im', '', $sqlCommand); //delete multiline comments
        $commands = preg_split('/(^(go)[\s,;])|(^(go)$)|(;go)|(;)\s*(go)/im', $command);
        return $commands;
    }

}
