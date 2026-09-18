<?php

namespace App\Http\Controllers;

use App\Services\Reporting\DashboardService;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request, DashboardService $service)
    {
        return view('dashboard', $service->forCompany($request->user()->activeCompany()));
    }
}
