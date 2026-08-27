<?php

namespace App\Http\Controllers;

use App\Services\DashboardSummaryService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request, DashboardSummaryService $summary): Response
    {
        return Inertia::render('Dashboard', [
            'summary' => $summary->forUser($request->user()),
        ]);
    }
}
