<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/helpers/jwt.php';
require_once __DIR__ . '/simple_db.php';

$db = new SimpleDatabase();

$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true);

if ($method === 'POST') {
    $action = $_GET['action'] ?? '';
    
    if ($action === 'register') {
        if (!isset($input['email']) || !isset($input['password']) || !isset($input['full_name'])) {
            sendError('Missing required fields', 400);
        }
        
        $email = filter_var($input['email'], FILTER_SANITIZE_EMAIL);
        $password = $input['password'];
        $full_name = htmlspecialchars($input['full_name']);
        
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            sendError('Invalid email format', 400);
        }
        
        if (strlen($password) < 6) {
            sendError('Password must be at least 6 characters', 400);
        }
        
        $existingUser = $db->findOne('users', 'email', $email);
        
        if ($existingUser) {
            sendError('Email already exists', 409);
        }
        
        $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
        
        try {
            $user = $db->insert('users', [
                'email' => $email,
                'password' => $hashedPassword,
                'full_name' => $full_name
            ]);
            
            $token = JWTHandler::encode([
                'id' => $user['id'],
                'email' => $email,
                'full_name' => $full_name
            ]);
            
            sendResponse([
                'message' => 'User registered successfully',
                'token' => $token,
                'user' => [
                    'id' => $user['id'],
                    'email' => $email,
                    'full_name' => $full_name
                ]
            ], 201);
        } catch (Exception $e) {
            sendError('Registration failed: ' . $e->getMessage(), 500);
        }
    }
    
    elseif ($action === 'login') {
        if (!isset($input['email']) || !isset($input['password'])) {
            sendError('Missing email or password', 400);
        }
        
        $email = filter_var($input['email'], FILTER_SANITIZE_EMAIL);
        $password = $input['password'];
        
        $user = $db->findOne('users', 'email', $email);
        
        if (!$user) {
            sendError('Invalid email or password', 401);
        }
        
        if (!password_verify($password, $user['password'])) {
            sendError('Invalid email or password', 401);
        }
        
        $token = JWTHandler::encode([
            'id' => $user['id'],
            'email' => $user['email'],
            'full_name' => $user['full_name']
        ]);
        
        sendResponse([
            'message' => 'Login successful',
            'token' => $token,
            'user' => [
                'id' => $user['id'],
                'email' => $user['email'],
                'full_name' => $user['full_name']
            ]
        ]);
    }
    
    else {
        sendError('Invalid action', 400);
    }
}

elseif ($method === 'GET') {
    $userData = JWTHandler::getUserFromRequest();
    
    if (!$userData) {
        sendError('Unauthorized', 401);
    }
    
    sendResponse([
        'user' => [
            'id' => $userData->id,
            'email' => $userData->email,
            'full_name' => $userData->full_name
        ]
    ]);
}

else {
    sendError('Method not allowed', 405);
}
