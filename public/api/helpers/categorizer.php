<?php

class Categorizer {
    private $db;
    
    public function __construct($database) {
        $this->db = $database;
    }
    
    public function autoCategorize($description, $amount) {
        $description = strtolower($description);
        
        if ($amount > 0) {
            $query = "SELECT id FROM categories WHERE name = 'Income' LIMIT 1";
            $stmt = $this->db->prepare($query);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($result) {
                return $result['id'];
            }
        }
        
        $query = "SELECT id, name, keywords FROM categories WHERE name != 'Income'";
        $stmt = $this->db->prepare($query);
        $stmt->execute();
        $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $maxScore = 0;
        $bestCategoryId = null;
        
        foreach ($categories as $category) {
            if (empty($category['keywords'])) continue;
            
            $keywords = explode(',', strtolower($category['keywords']));
            $score = 0;
            
            foreach ($keywords as $keyword) {
                $keyword = trim($keyword);
                if (strpos($description, $keyword) !== false) {
                    $score += strlen($keyword);
                }
            }
            
            if ($score > $maxScore) {
                $maxScore = $score;
                $bestCategoryId = $category['id'];
            }
        }
        
        if ($bestCategoryId) {
            return $bestCategoryId;
        }
        
        $query = "SELECT id FROM categories WHERE name = 'Other' LIMIT 1";
        $stmt = $this->db->prepare($query);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? $result['id'] : null;
    }
    
    public function detectRecurringPayments($userId) {
        $query = "
            SELECT 
                description,
                amount,
                category_id,
                COUNT(*) as occurrence_count,
                MAX(date) as last_date,
                ROUND(AVG(EXTRACT(DAY FROM date - LAG(date) OVER (PARTITION BY description, amount ORDER BY date)))) as avg_days_between
            FROM transactions
            WHERE user_id = :user_id 
            AND type = 'debit'
            AND amount > 0
            GROUP BY description, amount, category_id
            HAVING COUNT(*) >= 2
            ORDER BY occurrence_count DESC, amount DESC
        ";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':user_id', $userId);
        $stmt->execute();
        $potentialRecurring = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $recurring = [];
        foreach ($potentialRecurring as $item) {
            if ($item['occurrence_count'] >= 3 || 
                ($item['occurrence_count'] >= 2 && $item['avg_days_between'] >= 25 && $item['avg_days_between'] <= 35)) {
                
                $deleteQuery = "DELETE FROM recurring_payments WHERE user_id = :user_id AND service_name = :service_name";
                $deleteStmt = $this->db->prepare($deleteQuery);
                $deleteStmt->bindParam(':user_id', $userId);
                $deleteStmt->bindParam(':service_name', $item['description']);
                $deleteStmt->execute();
                
                $insertQuery = "
                    INSERT INTO recurring_payments (user_id, service_name, amount, frequency, last_detected, category_id)
                    VALUES (:user_id, :service_name, :amount, :frequency, :last_detected, :category_id)
                ";
                $insertStmt = $this->db->prepare($insertQuery);
                $insertStmt->bindParam(':user_id', $userId);
                $insertStmt->bindParam(':service_name', $item['description']);
                $insertStmt->bindParam(':amount', $item['amount']);
                
                $frequency = 'monthly';
                if ($item['avg_days_between']) {
                    if ($item['avg_days_between'] >= 85 && $item['avg_days_between'] <= 95) {
                        $frequency = 'quarterly';
                    } elseif ($item['avg_days_between'] >= 350 && $item['avg_days_between'] <= 370) {
                        $frequency = 'yearly';
                    }
                }
                
                $insertStmt->bindParam(':frequency', $frequency);
                $insertStmt->bindParam(':last_detected', $item['last_date']);
                $insertStmt->bindParam(':category_id', $item['category_id']);
                $insertStmt->execute();
                
                $recurring[] = [
                    'service_name' => $item['description'],
                    'amount' => $item['amount'],
                    'frequency' => $frequency,
                    'last_detected' => $item['last_date']
                ];
            }
        }
        
        return $recurring;
    }
}
