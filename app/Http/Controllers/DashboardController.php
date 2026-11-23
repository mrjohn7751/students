<?php

namespace App\Http\Controllers;

use App\Services\Stats\DashboardStats;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request, DashboardStats $stats): View
    {
        $filters = $request->validate([
            'department_id' => ['nullable', 'integer'],
            'education_type' => ['nullable', 'string', 'max:8'],
            'education_form' => ['nullable', 'string', 'max:8'],
        ]);

        return view('dashboard', [
            'stats' => $stats->build($filters),
            'options' => $stats->filterOptions(),
            'filters' => $filters,
        ]);
    }
}
