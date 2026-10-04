<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

class AuthController extends Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->call->database();
        $this->call->library('api');
    }

    // POST /api/auth/register
    public function register()
    {
        $this->api->require_method('POST');
        $data = $this->api->body();

        $username = $data['username'] ?? '';
        $email    = $data['email'] ?? '';
        $password = $data['password'] ?? '';

        $errors = [];
        if (strlen($username) < 3 || strlen($username) > 100) {
            $errors['username'] = 'Username must be 3 to 100 characters.';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'A valid email is required.';
        }
        if (strlen($password) < 8) {
            $errors['password'] = 'Password must be at least 8 characters.';
        }
        if ($errors) {
            $this->api->respond(['error' => 'Validation failed', 'errors' => $errors], 422);
        }

        $exists = $this->db->raw(
            "SELECT id FROM users WHERE username = ? OR email = ? LIMIT 1",
            [$username, $email]
        )->fetch(PDO::FETCH_ASSOC);

        if ($exists) {
            $this->api->respond_error('Username or email already exists', 409);
        }

        $this->db->raw(
            "INSERT INTO users (username, email, password) VALUES (?, ?, ?)",
            [$username, $email, password_hash($password, PASSWORD_DEFAULT)]
        );

        $user = $this->db->raw(
            "SELECT id, username, email, role FROM users WHERE email = ? LIMIT 1",
            [$email]
        )->fetch(PDO::FETCH_ASSOC);

        $this->api->respond(['message' => 'Registered successfully', 'user' => $user], 201);
    }

    // POST /api/auth/login
    public function login()
    {
        $this->api->require_method('POST');
        $data = $this->api->body();

        $login    = $data['login'] ?? '';
        $password = $data['password'] ?? '';

        if ($login === '' || $password === '') {
            $this->api->respond_error('Login and password are required', 422);
        }

        $user = $this->db->raw(
            "SELECT id, username, email, password, role, is_active FROM users WHERE username = ? OR email = ? LIMIT 1",
            [$login, $login]
        )->fetch(PDO::FETCH_ASSOC);

        if (!$user || !password_verify($password, $user['password'])) {
            $this->api->respond_error('Invalid credentials', 401);
        }

        if ((int) $user['is_active'] !== 1) {
            $this->api->respond_error('Account is disabled', 403);
        }

        $tokens = $this->api->issue_tokens([
            'id'   => $user['id'],
            'role' => $user['role'],
        ]);

        $this->api->respond([
            'message' => 'Login successful',
            'tokens'  => $tokens,
            'user'    => [
                'id'       => $user['id'],
                'username' => $user['username'],
                'email'    => $user['email'],
                'role'     => $user['role'],
            ],
        ]);
    }

    // POST /api/auth/refresh
    public function refresh()
    {
        $this->api->require_method('POST');
        $data = $this->api->body();

        $refresh_token = $data['refresh_token'] ?? '';
        if ($refresh_token === '') {
            $this->api->respond_error('refresh_token is required', 422);
        }

        $this->api->refresh_access_token($refresh_token);
    }

    // POST /api/auth/logout
    public function logout()
    {
        $this->api->require_method('POST');
        $this->api->require_jwt();

        $data = $this->api->body();
        $refresh_token = $data['refresh_token'] ?? '';

        if ($refresh_token !== '') {
            $this->api->revoke_refresh_token($refresh_token);
        }

        $this->api->respond(['message' => 'Logged out successfully']);
    }
}