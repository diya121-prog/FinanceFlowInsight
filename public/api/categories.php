<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/simple_db.php';

$db = new SimpleDatabase();

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $categories = $db->findAll('categories');
    sendResponse(['categories' => $categories]);
} else {
    sendError('Method not allowed', 405);
}
