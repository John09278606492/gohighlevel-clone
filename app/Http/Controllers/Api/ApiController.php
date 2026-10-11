<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SubAccount;
use Illuminate\Http\Request;

abstract class ApiController extends Controller
{
    protected function account(Request $request): SubAccount
    {
        return $request->attributes->get('subAccount');
    }

    protected function perPage(Request $request): int
    {
        return min(100, max(1, (int) $request->query('per_page', 25)));
    }
}
