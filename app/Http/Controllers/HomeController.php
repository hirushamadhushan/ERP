<?php

namespace App\Http\Controllers;

use App\Models\User;

class HomeController extends Controller
{
    /**
     * Display the ERP Home Dashboard with live DB statistics.
     */
    public function index()
    {
        $totalUsers  = User::count();
        $activeUsers = User::where('status', 'active')->count();
        $adminUsers  = User::where('role', 'administrator')->count();
        $recentUsers = User::latest()->take(5)->get();

        return view('home.index', compact(
            'totalUsers',
            'activeUsers',
            'adminUsers',
            'recentUsers'
        ));
    }
}
