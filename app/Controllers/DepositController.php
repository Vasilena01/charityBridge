<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\Db;
use App\Core\Flash;
use App\Core\Request;
use App\Core\Response;
use App\Core\Url;
use App\Core\View;
use App\Models\Deposit;
use App\Models\User;

final class DepositController
{
    public function index(Request $request): void
    {
        $user = Auth::require();
        if (!in_array($user['role'], ['volunteer', 'company'], true)) {
            Response::forbidden('Only volunteers and companies can deposit funds.');
        }
        $deposits = Deposit::listForUser((int)$user['id']);

        Response::html(View::render('deposit/index', [
            'title'    => 'Deposit Funds - CharityBridge',
            'css'      => ['profile.css', 'deposit.css'],
            'user'     => $user,
            'deposits' => $deposits,
            'errors'   => [],
            'old'      => [],
        ]));
    }

    public function store(Request $request): void
    {
        $user = Auth::require();
        if (!in_array($user['role'], ['volunteer', 'company'], true)) {
            Response::forbidden('Only volunteers and companies can deposit funds.');
        }

        $min = (float)env('MIN_DEPOSIT', 1.00);
        $max = (float)env('MAX_DEPOSIT', 10000.00);

        $errors = [];
        $amount = $request->input('amount');
        if (!is_numeric($amount)) {
            $errors[] = 'Amount must be numeric.';
        } else {
            $amount = round((float)$amount, 2);
            if ($amount < $min) $errors[] = 'Minimum deposit is ' . number_format($min, 2);
            if ($amount > $max) $errors[] = 'Maximum deposit is ' . number_format($max, 2);
        }

        $cardRaw = preg_replace('/\s+/', '', (string)$request->input('card_number', ''));
        if (!preg_match('/^\d{13,19}$/', $cardRaw)) {
            $errors[] = 'Card number must be 13–19 digits.';
        }
        $last4 = substr($cardRaw, -4);

        $cardHolder = trim((string)$request->input('card_holder', ''));
        if ($cardHolder === '' || strlen($cardHolder) > 100) {
            $errors[] = 'Card holder name is required (max 100 chars).';
        }

        $expiry = trim((string)$request->input('expiry', ''));
        if (!preg_match('/^(0[1-9]|1[0-2])\s*\/\s*\d{2}$/', $expiry)) {
            $errors[] = 'Expiry must be in MM/YY format.';
        }

        $cvv = trim((string)$request->input('cvv', ''));
        if (!preg_match('/^\d{3,4}$/', $cvv)) {
            $errors[] = 'CVV must be 3 or 4 digits.';
        }

        if ($errors) {
            Flash::set('error', implode(' ', $errors));
            Response::redirect(Url::to('deposit'));
        }

        $pdo = Db::pdo();
        $pdo->beginTransaction();
        try {
            $balanceBefore = User::getBalance((int)$user['id']);
            $balanceAfter  = round($balanceBefore + (float)$amount, 2);
            User::setBalance((int)$user['id'], $balanceAfter);
            Deposit::create([
                'user_id'        => (int)$user['id'],
                'amount'         => $amount,
                'balance_before' => $balanceBefore,
                'balance_after'  => $balanceAfter,
                'card_last4'     => $last4,
                'card_holder'    => $cardHolder,
            ]);
            $pdo->commit();
            Auth::refresh();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }

        Flash::set('success', sprintf('Deposited %.2f from card •••• %s. New balance: %.2f', $amount, $last4, $balanceAfter));
        Response::redirect(Url::to('deposit'));
    }
}
