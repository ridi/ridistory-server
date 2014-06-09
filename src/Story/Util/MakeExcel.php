<?php
namespace Story\Util;

class MakeExcel
{
    public static function setExcelHeader($file_name)
    {
        header('Content-type: application/vnd.ms-excel;charset=utf-8' );
        header('Content-Disposition: attachment; filename=' . $file_name . '.xls');
        header('Cache-Control: max-age=0');
    }
} 