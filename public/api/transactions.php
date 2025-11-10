<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/helpers/jwt.php';
require_once __DIR__ . '/simple_db.php';

$db = new SimpleDatabase();

$userData = JWTHandler::getUserFromRequest();
if (!$userData) {
    sendError('Unauthorized', 401);
}

$userId = $userData->id;
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $action = $_GET['action'] ?? 'list';
    
    if ($action === 'list') {
        $transactions = $db->findAll('transactions', 'user_id', $userId);
        
        $categories = $db->findAll('categories');
        $categoryMap = [];
        foreach ($categories as $cat) {
            $categoryMap[$cat['id']] = $cat;
        }
        
        foreach ($transactions as &$trans) {
            if (isset($trans['category_id']) && isset($categoryMap[$trans['category_id']])) {
                $trans['category_name'] = $categoryMap[$trans['category_id']]['name'];
                $trans['category_color'] = $categoryMap[$trans['category_id']]['color'];
            }
        }
        
        usort($transactions, function($a, $b) {
            return strtotime($b['date']) - strtotime($a['date']);
        });
        
        sendResponse(['transactions' => $transactions]);
    }
    
    elseif ($action === 'categories') {
        $categories = $db->findAll('categories');
        sendResponse(['categories' => $categories]);
    }
}

elseif ($method === 'POST') {
    $action = $_GET['action'] ?? '';
    $input = json_decode(file_get_contents('php://input'), true);
    
    if ($action === 'add') {
        if (!isset($input['date']) || !isset($input['description']) || !isset($input['amount']) || !isset($input['type'])) {
            sendError('Missing required fields', 400);
        }
        
        $transactionData = [
            'user_id' => $userId,
            'date' => $input['date'],
            'description' => htmlspecialchars($input['description']),
            'amount' => (float)$input['amount'],
            'type' => $input['type'],
            'category_id' => $input['category_id'] ?? null,
            'notes' => isset($input['notes']) ? htmlspecialchars($input['notes']) : ''
        ];
        
        $transaction = $db->insert('transactions', $transactionData);
        
        sendResponse([
            'message' => 'Transaction added successfully',
            'transaction' => $transaction
        ], 201);
    }
    
    elseif ($action === 'bulk') {
        if (!isset($input['transactions']) || !is_array($input['transactions'])) {
            sendError('Invalid bulk data', 400);
        }
        
        $added = [];
        foreach ($input['transactions'] as $trans) {
            $transactionData = [
                'user_id' => $userId,
                'date' => $trans['date'],
                'description' => htmlspecialchars($trans['description']),
                'amount' => (float)$trans['amount'],
                'type' => $trans['type'],
                'category_id' => $trans['category_id'] ?? null,
                'notes' => isset($trans['notes']) ? htmlspecialchars($trans['notes']) : ''
            ];
            
            $added[] = $db->insert('transactions', $transactionData);
        }
        
        sendResponse([
            'message' => count($added) . ' transactions added successfully',
            'count' => count($added)
        ], 201);
    }
}

elseif ($method === 'PUT') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $id = (int)($_GET['id'] ?? $input['id'] ?? 0);
    if (!$id) {
        sendError('Transaction ID required', 400);
    }
    
    $transaction = $db->findOne('transactions', 'id', $id);
    if (!$transaction || $transaction['user_id'] !== $userId) {
        sendError('Transaction not found', 404);
    }
    
    $updateData = [];
    if (isset($input['date'])) $updateData['date'] = $input['date'];
    if (isset($input['description'])) $updateData['description'] = htmlspecialchars($input['description']);
    if (isset($input['amount'])) $updateData['amount'] = (float)$input['amount'];
    if (isset($input['type'])) $updateData['type'] = $input['type'];
    if (isset($input['category_id'])) $updateData['category_id'] = $input['category_id'];
    if (isset($input['notes'])) $updateData['notes'] = htmlspecialchars($input['notes']);
    
    $db->update('transactions', $id, $updateData);
    
    sendResponse(['message' => 'Transaction updated successfully']);
}

elseif ($method === 'DELETE') {
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) {
        sendError('Transaction ID required', 400);
    }
    
    $transaction = $db->findOne('transactions', 'id', $id);
    if (!$transaction || $transaction['user_id'] !== $userId) {
        sendError('Transaction not found', 404);
    }
    
    $db->delete('transactions', $id);
    
    sendResponse(['message' => 'Transaction deleted successfully']);
}

else {
    sendError('Method not allowed', 405);
}
