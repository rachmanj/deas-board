<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Label;

class LabelController extends Controller
{
    public function index()
    {
        $labels = Label::query()
            ->orderBy('name')
            ->get(['id', 'name', 'color']);

        return response()->json($labels);
    }
}
