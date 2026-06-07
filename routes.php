<?php

use App\Controllers\AuthController;
use App\Controllers\CampaignItemsController;
use App\Controllers\CampaignsController;
use App\Controllers\ContributionsController;
use App\Controllers\DepositController;
use App\Controllers\HomeController;
use App\Controllers\ProductionOffersController;
use App\Controllers\ProfileController;
use App\Controllers\PurchasesController;

return [
    ['GET',  '/',                              [HomeController::class,             'index']],

    ['GET',  '/login',                         [AuthController::class,             'showLogin']],
    ['POST', '/login',                         [AuthController::class,             'login']],
    ['GET',  '/signup',                        [AuthController::class,             'showSignup']],
    ['POST', '/signup',                        [AuthController::class,             'signup']],
    ['POST', '/logout',                        [AuthController::class,             'logout']],

    ['GET',  '/campaigns',                     [CampaignsController::class,        'index']],
    ['GET',  '/campaigns/create',              [CampaignsController::class,        'create']],
    ['POST', '/campaigns',                     [CampaignsController::class,        'store']],
    ['GET',  '/campaigns/{id}',                [CampaignsController::class,        'show']],
    ['GET',  '/campaigns/{id}/edit',           [CampaignsController::class,        'edit']],
    ['POST', '/campaigns/{id}',                [CampaignsController::class,        'update']],
    ['POST', '/campaigns/{id}/delete',         [CampaignsController::class,        'destroy']],
    ['GET',  '/my-campaigns',                  [CampaignsController::class,        'my']],

    ['POST', '/campaigns/{id}/items',          [CampaignItemsController::class,    'store']],
    ['POST', '/campaign-items/{id}',           [CampaignItemsController::class,    'update']],
    ['POST', '/campaign-items/{id}/delete',    [CampaignItemsController::class,    'destroy']],

    ['POST', '/campaigns/{id}/contributions',  [ContributionsController::class,    'store']],
    ['POST', '/contributions/{id}/cancel',     [ContributionsController::class,    'cancel']],

    ['POST', '/campaigns/{id}/offers',         [ProductionOffersController::class, 'store']],
    ['POST', '/offers/{id}/decide',            [ProductionOffersController::class, 'decide']],

    ['POST', '/items/{id}/purchase',           [PurchasesController::class,        'store']],

    ['GET',  '/deposit',                       [DepositController::class,          'index']],
    ['POST', '/deposit',                       [DepositController::class,          'store']],

    ['GET',  '/profile',                       [ProfileController::class,          'index']],
];
