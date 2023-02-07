<?php

namespace App\Exports;

use App\Models\Assets\Asset;
use Maatwebsite\Excel\Concerns\FromCollection;
use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;


class ExportExcel implements FromView
{
    protected $data;

    function __construct($data) {
            $this->data = $data;
    }
    public function view(): View
    {
        return view('autoReport', [
            'assets' => $this->data,
        ]);
    }
}


