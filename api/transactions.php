<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/helpers/jwt.php';
require_once __DIR__ . '/helpers/categorizer.php';

$database = new Database();
$db = $database->getConnection();

$userData = JWTHandler::getUserFromRequest();
if (!$userData) {
    sendError('Unauthorized', 401);
}

$userId = $userData->id;
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $action = $_GET['action'] ?? 'list';
    
    if ($action === 'list') {
        $category = $_GET['category'] ?? null;
        $startDate = $_GET['start_date'] ?? null;
        $endDate = $_GET['end_date'] ?? null;
        $search = $_GET['search'] ?? null;
        
        $query = "
            SELECT t.*, c.name as category_name, c.color as category_color 
            FROM transactions t
            LEFT JOIN categories c ON t.category_id = c.id
            WHERE t.user_id = :user_id
        ";
        
        $params = [':user_id' => $userId];
        
        if ($category) {
            $query .= " AND t.category_id = :category";
            $params[':category'] = $category;
        }
        
        if ($startDate) {
            $query .= " AND t.date >= :start_date";
            $params[':start_date'] = $startDate;
        }
        
        if ($endDate) {
            $query .= " AND t.date <= :end_date";
            $params[':end_date'] = $endDate;
        }
        
        if ($search) {
            $query .= " AND (t.description ILIKE :search OR t.notes ILIKE :search)";
            $params[':search'] = '%' . $search . '%';
        }
        
        $query .= " ORDER BY t.date DESC, t.id DESC";
        
        $stmt = $db->prepare($query);
        $stmt->execute($params);
        $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        sendResponse(['transactions' => $transactions]);
    }
    
    elseif ($action === 'categories') {
        $query = "SELECT * FROM categories ORDER BY name";
        $stmt = $db->prepare($query);
        $stmt->execute();
        $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        sendResponse(['categories' => $categories]);
    }
}

elseif ($method === 'POST') {
    $action = $_GET['action'] ?? '';
    $input = json_decode(file_get_contents('php://input'), true);
    
    if ($action === 'upload_csv') {
        if (!isset($_FILES['file'])) {
            sendError('No file uploaded', 400);
        }
        
        $file = $_FILES['file'];
        
        if ($file['error'] !== UPLOAD_ERR_OK) {
            sendError('Upload failed', 400);
        }
        
        $fileExt = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if ($fileExt !== 'csv') {
            sendError('Only CSV files are allowed', 400);
        }
        
        $handle = fopen($file['tmp_name'], 'r');
        if (!$handle) {
            sendError('Failed to read file', 500);
        }
        
        $headers = fgetcsv($handle);
        if (!$headers) {
            fclose($handle);
            sendError('Invalid CSV format', 400);
        }
        
        $headers = array_map('strtolower', array_map('trim', $headers));
        $dateIdx = array_search('date', $headers);
        $descIdx = array_search('description', $headers);
        $amountIdx = array_search('amount', $headers);
        
        if ($dateIdx === false || $descIdx === false || $amountIdx === false) {
            fclose($handle);
            sendError('CSV must contain date, description, and amount columns', 400);
        }
        
        $categorizer = new Categorizer($db);
        $importedCount = 0;
        
        $db->beginTransaction();
        
        try {
            while (($row = fgetcsv($handle)) !== false) {
                if (count($row) < max($dateIdx, $descIdx, $amountIdx) + 1) {
                    continue;
                }
                
                $date = $row[$dateIdx];
                $description = $row[$descIdx];
                $amount = floatval(str_replace(',', '', $row[$amountIdx]));
                
                $dateObj = date_create_from_format('Y-m-d', $date);
                if (!$dateObj) {
                    $dateObj = date_create_from_format('d/m/Y', $date);
                }
                if (!$dateObj) {
                    $dateObj = date_create_from_format('m/d/Y', $date);
                }
                if (!$dateObj) {
                    continue;
                }
                $formattedDate = $dateObj->format('Y-m-d');
                
                $type = $amount >= 0 ? 'credit' : 'debit';
                $absAmount = abs($amount);
                
                $categoryId = $categorizer->autoCategorize($description, $amount);
                
                $query = "
                    INSERT INTO transactions (user_id, date, description, amount, type, category_id)
                    VALUES (:user_id, :date, :description, :amount, :type, :category_id)
                ";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':user_id', $userId);
                $stmt->bindParam(':date', $formattedDate);
                $stmt->bindParam(':description', $description);
                $stmt->bindParam(':amount', $absAmount);
                $stmt->bindParam(':type', $type);
                $stmt->bindParam(':category_id', $categoryId);
                $stmt->execute();
                
                $importedCount++;
            }
            
            $categorizer->detectRecurringPayments($userId);
            
            $db->commit();
            fclose($handle);
            
            sendResponse([
                'message' => 'CSV uploaded successfully',
                'imported_count' => $importedCount
            ]);
        } catch (Exception $e) {
            $db->rollBack();
            fclose($handle);
            sendError('Import failed: ' . $e->getMessage(), 500);
        }
    }
    
    elseif ($action === 'add') {
        if (!isset($input['date']) || !isset($input['description']) || !isset($input['amount']) || !isset($input['type'])) {
            sendError('Missing required fields', 400);
        }
        
        $date = $input['date'];
        $description = htmlspecialchars($input['description']);
        $amount = abs(floatval($input['amount']));
        $type = $input['type'];
        $notes = htmlspecialchars($input['notes'] ?? '');
        
        if (!in_array($type, ['credit', 'debit'])) {
            sendError('Invalid transaction type', 400);
        }
        
        $categorizer = new Categorizer($db);
        $categoryId = $input['category_id'] ?? $categorizer->autoCategorize($description, $type === 'credit' ? $amount : -$amount);
        
        $query = "
            INSERT INTO transactions (user_id, date, description, amount, type, category_id, notes)
            VALUES (:user_id, :date, :description, :amount, :type, :category_id, :notes)
            RETURNING id
        ";
        
        try {
            $stmt = $db->prepare($query);
            $stmt->bindParam(':user_id', $userId);
            $stmt->bindParam(':date', $date);
            $stmt->bindParam(':description', $description);
            $stmt->bindParam(':amount', $amount);
            $stmt->bindParam(':type', $type);
            $stmt->bindParam(':category_id', $categoryId);
            $stmt->bindParam(':notes', $notes);
            $stmt->execute();
            
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            sendResponse([
                'message' => 'Transaction added successfully',
                'transaction_id' => $result['id']
            ], 201);
        } catch (PDOException $e) {
            sendError('Failed to add transaction: ' . $e->getMessage(), 500);
        }
    }
}

elseif ($method === 'PUT') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['id'])) {
        sendError('Transaction ID required', 400);
    }
    
    $transactionId = $input['id'];
    
    $checkQuery = "SELECT id FROM transactions WHERE id = :id AND user_id = :user_id";
    $checkStmt = $db->prepare($checkQuery);
    $checkStmt->bindParam(':id', $transactionId);
    $checkStmt->bindParam(':user_id', $userId);
    $checkStmt->execute();
    
    if ($checkStmt->rowCount() === 0) {
        sendError('Transaction not found', 404);
    }
    
    $updates = [];
    $params = [':id' => $transactionId, ':user_id' => $userId];
    
    if (isset($input['date'])) {
        $updates[] = "date = :date";
        $params[':date'] = $input['date'];
    }
    
    if (isset($input['description'])) {
        $updates[] = "description = :description";
        $params[':description'] = htmlspecialchars($input['description']);
    }
    
    if (isset($input['amount'])) {
        $updates[] = "amount = :amount";
        $params[':amount'] = abs(floatval($input['amount']));
    }
    
    if (isset($input['type'])) {
        if (!in_array($input['type'], ['credit', 'debit'])) {
            sendError('Invalid transaction type', 400);
        }
        $updates[] = "type = :type";
        $params[':type'] = $input['type'];
    }
    
    if (isset($input['category_id'])) {
        $updates[] = "category_id = :category_id";
        $params[':category_id'] = $input['category_id'];
    }
    
    if (isset($input['notes'])) {
        $updates[] = "notes = :notes";
        $params[':notes'] = htmlspecialchars($input['notes']);
    }
    
    if (empty($updates)) {
        sendError('No fields to update', 400);
    }
    
    $updates[] = "updated_at = CURRENT_TIMESTAMP";
    
    $query = "UPDATE transactions SET " . implode(', ', $updates) . " WHERE id = :id AND user_id = :user_id";
    
    try {
        $stmt = $db->prepare($query);
        $stmt->execute($params);
        
        sendResponse(['message' => 'Transaction updated successfully']);
    } catch (PDOException $e) {
        sendError('Failed to update transaction: ' . $e->getMessage(), 500);
    }
}

elseif ($method === 'DELETE') {
    $transactionId = $_GET['id'] ?? null;
    
    if (!$transactionId) {
        sendError('Transaction ID required', 400);
    }
    
    $query = "DELETE FROM transactions WHERE id = :id AND user_id = :user_id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':id', $transactionId);
    $stmt->bindParam(':user_id', $userId);
    $stmt->execute();
    
    if ($stmt->rowCount() === 0) {
        sendError('Transaction not found', 404);
    }
    
    sendResponse(['message' => 'Transaction deleted successfully']);
}

else {
    sendError('Method not allowed', 405);
}
