<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TemplateBuilderController extends Controller
{
    public function __invoke(Request $request): Response
    {
        abort_if($request->user() === null, 403);

        return Inertia::render('template-builder/index');
    }
}
