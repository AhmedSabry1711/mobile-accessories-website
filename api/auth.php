<?php
/**
 * Mobi Boost — Admin authentication API.
 *
 * POST /api/auth.php  { username, password }  -> { token, name }
 * GET  /api/auth.php  (Bearer token)          -> { name, username }
 */
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $user = require_admin();
    ok(['name' => $user['username'], 'username' => $user['username']]);
}

if ($method === 'POST') {
    $data = json_input();
    $username = trim((string)($data['username'] ?? ''));
    $password = (string)($data['password'] ?? '');

    if ($username === '' || $password === '') {
        fail('Username and password are required', 400);
    }

$user = mongo_find_one('users', ['username' => $username]);
    if (!$user || !password_verify($password, (string)$user['passwordHash'])) {
        fail('Invalid credentials', 401);
}

    ok([
        'token' => create_token($user),
        'name' => $user['username'],
    ]);
}

fail('Method not allowed', 405);
