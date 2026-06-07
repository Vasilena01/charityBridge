<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\Flash;
use App\Core\Request;
use App\Core\Response;
use App\Core\Url;
use App\Core\View;
use App\Models\User;

final class AuthController
{
    public function showLogin(Request $request): void
    {
        if (Auth::check()) {
            Response::redirect(Url::to('profile'));
        }
        Response::html(View::render('auth/login', [
            'title'  => 'Log In - CharityBridge',
            'css'    => ['login.css'],
            'errors' => [],
            'old'    => [],
        ], 'auth'));
    }

    public function login(Request $request): void
    {
        $email    = trim((string)$request->input('email', ''));
        $password = (string)$request->input('password', '');
        $errors   = [];

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Valid email is required.';
        }
        if ($password === '') {
            $errors[] = 'Password is required.';
        }

        if (!$errors) {
            $user = User::findByEmail($email);
            $hash = $user ? $user['password_hash'] : password_hash('dummy', PASSWORD_DEFAULT);
            if ($user && password_verify($password, $hash)) {
                Auth::login((int)$user['id']);
                Flash::set('success', 'Welcome back, ' . $user['first_name'] . '.');
                Response::redirect(Url::to('profile'));
            }
            $errors[] = 'Invalid email or password.';
        }

        Response::html(View::render('auth/login', [
            'title'  => 'Log In - CharityBridge',
            'css'    => ['login.css'],
            'errors' => $errors,
            'old'    => ['email' => $email],
        ], 'auth'));
    }

    public function showSignup(Request $request): void
    {
        if (Auth::check()) {
            Response::redirect(Url::to('profile'));
        }
        Response::html(View::render('auth/signup', [
            'title'  => 'Sign Up - CharityBridge',
            'css'    => ['signup.css'],
            'errors' => [],
            'old'    => [],
        ], 'auth'));
    }

    public function signup(Request $request): void
    {
        $email           = trim((string)$request->input('email', ''));
        $password        = (string)$request->input('password', '');
        $passwordConfirm = (string)$request->input('password_confirm', '');
        $firstName       = trim((string)$request->input('first_name', ''));
        $lastName        = trim((string)$request->input('last_name', ''));
        $role            = (string)$request->input('role', '');

        $minLen = (int)env('PASSWORD_MIN_LENGTH', 8);
        $errors = [];

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Valid email is required.';
        }
        if (strlen($password) < $minLen) {
            $errors[] = 'Password must be at least ' . $minLen . ' characters.';
        }
        if ($password !== $passwordConfirm) {
            $errors[] = 'Passwords do not match.';
        }
        if ($firstName === '' || strlen($firstName) > 100) {
            $errors[] = 'First name is required (max 100 characters).';
        }
        if ($lastName === '' || strlen($lastName) > 100) {
            $errors[] = 'Last name is required (max 100 characters).';
        }
        if (!in_array($role, ['volunteer', 'organizer', 'company'], true)) {
            $errors[] = 'Please select a valid role.';
        }
        if (!$errors && User::emailExists($email)) {
            $errors[] = 'Email address is already registered.';
        }

        if ($errors) {
            Response::html(View::render('auth/signup', [
                'title'  => 'Sign Up - CharityBridge',
                'css'    => ['signup.css'],
                'errors' => $errors,
                'old'    => [
                    'email'      => $email,
                    'first_name' => $firstName,
                    'last_name'  => $lastName,
                    'role'       => $role,
                ],
            ], 'auth'));
            return;
        }

        $userId = User::create([
            'email'         => $email,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'first_name'    => $firstName,
            'last_name'     => $lastName,
            'role'          => $role,
        ]);

        Auth::login($userId);
        Flash::set('success', 'Welcome to CharityBridge, ' . $firstName . '!');
        Response::redirect(Url::to('profile'));
    }

    public function logout(Request $request): void
    {
        Auth::logout();
        Flash::set('success', 'You have been logged out.');
        Response::redirect(Url::to(''));
    }
}
