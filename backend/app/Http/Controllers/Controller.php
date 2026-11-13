<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;

class Controller extends BaseController
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;

    /** Plafond de ?limit= : sans borne, une seule requete peut tout charger. */
    protected const MAX_PER_PAGE = 100;

    protected function perPage(\Illuminate\Http\Request $request, int $default): int
    {
        return max(1, min((int) $request->get('limit', $default), self::MAX_PER_PAGE));
    }
}
