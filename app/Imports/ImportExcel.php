<?php

namespace App\Imports;

use App\Models\Assets\Asset;
use Maatwebsite\Excel\Concerns\ToModel;

class ImportExcel implements ToModel
{
    /**
    * @param array $row
    *
    * @return \Illuminate\Database\Eloquent\Model|null
    */
    public function model(array $row)
    {
        return new Asset([
            'asset_title' => $row[0],
            'mn_number' => $row[1],
        ]);
    }
}
