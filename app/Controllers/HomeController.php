<?php
namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\View;

final class HomeController
{
    public function index(Request $request): void
    {
        Response::html(View::render('home/index', [
            'title' => env('APP_NAME', 'CharityBridge') . ' - Support Charitable Campaigns',
            'css'   => ['index.css'],
        ]));
    }
}
