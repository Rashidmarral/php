<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;

abstract class ApiController extends Controller
{
    protected function companyId(): int
    {
        return Auth::user()->company_id;
    }
}
