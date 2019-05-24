<?php
namespace {{NSPrefix}}Model{{FolderName}};

use Swork\Db\MySqlModel;

class {{ClassName}} extends MySqlModel
{
    public function __construct()
    {
        $tbl = '{{TableName}}';
        $key = ['{{IdName}}', MySqlModel::{{IdType}}];
        $cols = [
            {{Columns}}
        ];
        $inc = '{{Instance}}';
        parent::__construct($tbl, $key, $cols, $inc);
    }
}
