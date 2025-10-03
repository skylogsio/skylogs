<?php

namespace App\Helpers;

use Maatwebsite\Excel\HeadingRowImport;

class ExcelHeadingRow extends HeadingRowImport {

    public function map($row): array {
        return $row;
    }
}
