<?php
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../config.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class JWTHandler {
    public static function encode($payload) {
        $issuedAt = time();
        $expire = $issuedAt + (60 * 60 * 24 * 7);
        
        $token = [
            'iat' => $issuedAt,
            'exp' => $expire,
            'data' => $payload
        ];
        
        return JWT::encode($token, Config::$JWT_SECRET, 'HS256');
    }
    
    public static function decode($token) {
        try {
            $decoded = JWT::decode($token, new Key(Config::$JWT_SECRET, 'HS256'));
            return $decoded->data;
        } catch (Exception $e) {
            return null;
        }
    }
    
    public static function getUserFromRequest() {
        $headers = function_exists('getallheaders') ? getallheaders() : [];
        
        if (empty($headers) && isset($_SERVER['HTTP_AUTHORIZATION'])) {
            $headers['Authorization'] = $_SERVER['HTTP_AUTHORIZATION'];
        }
        
        $token = null;
        
        if (isset($headers['Authorization'])) {
            $authHeader = $headers['Authorization'];
            $arr = explode(' ', $authHeader);
            if (count($arr) === 2 && $arr[0] === 'Bearer') {
                $token = $arr[1];
            }
        }
        
        if (!$token) {
            return null;
        }
        
        $userData = self::decode($token);
        return $userData;
    }
}
