<?php

namespace Tests\Unit;

use App\Http\Controllers\Controller;

class BaseController extends Controller
{
    public function getbaseRouteVars() {
        return $this->getBaseVariables();
    }
}
