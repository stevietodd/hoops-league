<?php

declare(strict_types=1);

final class AuthController
{
    public static function loginForm(): void
    {
        if (Auth::check()) {
            redirect('/');
        }
        render('login', ['title' => 'Log in']);
    }

    public static function login(): void
    {
        verify_csrf();
        $email = trim((string) ($_POST['email'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        if (Auth::attempt($email, $password)) {
            flash('success', 'Welcome back.');
            redirect('/');
        }
        flash('error', 'Invalid email or password.');
        redirect('/login');
    }

    public static function logout(): void
    {
        verify_csrf();
        Auth::logout();
        flash('success', 'Logged out.');
        redirect('/');
    }
}
