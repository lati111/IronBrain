<?php

namespace App\Http\Controllers\Projects\PKSanc;

use App\Http\Controllers\Controller;
use App\Service\PKSanc\ImportService;

class PKSancController extends Controller
{
    public function overview()
    {
        return view('project.pksanc.home', $this->getBaseVariables());
    }
}
