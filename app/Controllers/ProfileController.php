<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Models\Contribution;
use App\Models\ProductionOffer;
use App\Models\Purchase;

final class ProfileController
{
    public function index(Request $request): void
    {
        $user = Auth::require();

        $purchases     = [];
        $offers        = [];
        $contributions = [];

        if (in_array($user['role'], ['volunteer', 'company'], true)) {
            $purchases     = Purchase::listForUser((int)$user['id']);
            $offers        = ProductionOffer::listForUser((int)$user['id']);
            $contributions = Contribution::listForUser((int)$user['id']);
        }

        Response::html(View::render('profile/index', [
            'title'         => 'My Profile - CharityBridge',
            'css'           => ['profile.css'],
            'user'          => $user,
            'purchases'     => $purchases,
            'offers'        => $offers,
            'contributions' => $contributions,
        ]));
    }
}
