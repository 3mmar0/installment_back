<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class DocumentationController extends Controller
{
    /**
     * Display the API documentation page.
     */
    public function index(): View
    {
        return view('documentation.index');
    }

    /**
     * Display the comprehensive API documentation page.
     */
    public function api(): View
    {
        return view('documentation.api');
    }
}
