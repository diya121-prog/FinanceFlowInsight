<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/helpers/jwt.php';

$database = new Database();
$db = $database->getConnection();

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
        
        $checkQuery = "SELECT id FROM users WHERE email = :email";
        $checkStmt = $db->prepare($checkQuery);
        $checkStmt->bindParam(':email', $email);
        $checkStmt->execute();
        
        if ($checkStmt->rowCount() > 0) {
            sendError('Email already exists', 409);
        }
        
        $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
        
        $query = "INSERT INTO users (email, password, full_name) VALUES (:email, :password, :full_name) RETURNING id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':email', $email);
        $stmt->bindParam(':password', $hashedPassword);
        $stmt->bindParam(':full_name', $full_name);
        
        try {
            $stmt->execute();
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
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
        } catch (PDOException $e) {
            sendError('Registration failed: ' . $e->getMessage(), 500);
        }
    }
    
    elseif ($action === 'login') {
        if (!isset($input['email']) || !isset($input['password'])) {
            sendError('Missing email or password', 400);
        }
        
        $email = filter_var($input['email'], FILTER_SANITIZE_EMAIL);
        $password = $input['password'];
        
        $query = "SELECT id, email, password, full_name FROM users WHERE email = :email";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':email', $email);
        $stmt->execute();
        
        if ($stmt->rowCount() === 0) {
            sendError('Invalid email or password', 401);
        }
        
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
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
