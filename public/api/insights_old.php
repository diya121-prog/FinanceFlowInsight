<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/helpers/jwt.php';

$database = new Database();
$db = $database->getConnection();

$userData = JWTHandler::getUserFromRequest();
if (!$userData) {
    sendError('Unauthorized', 401);
}

$userId = $userData->id;
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $action = $_GET['action'] ?? 'dashboard';
    
    if ($action === 'dashboard') {
        $currentMonth = date('Y-m-01');
        $currentMonthEnd = date('Y-m-t');
        $lastMonth = date('Y-m-01', strtotime('-1 month'));
        $lastMonthEnd = date('Y-m-t', strtotime('-1 month'));
        
        $totalIncomeQuery = "
            SELECT COALESCE(SUM(amount), 0) as total 
            FROM transactions 
            WHERE user_id = :user_id AND type = 'credit'
        ";
        $stmt = $db->prepare($totalIncomeQuery);
        $stmt->bindParam(':user_id', $userId);
        $stmt->execute();
        $totalIncome = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        $totalExpensesQuery = "
            SELECT COALESCE(SUM(amount), 0) as total 
            FROM transactions 
            WHERE user_id = :user_id AND type = 'debit'
        ";
        $stmt = $db->prepare($totalExpensesQuery);
        $stmt->bindParam(':user_id', $userId);
        $stmt->execute();
        $totalExpenses = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        $currentMonthExpensesQuery = "
            SELECT COALESCE(SUM(amount), 0) as total 
            FROM transactions 
            WHERE user_id = :user_id AND type = 'debit' 
            AND date >= :start_date AND date <= :end_date
        ";
        $stmt = $db->prepare($currentMonthExpensesQuery);
        $stmt->bindParam(':user_id', $userId);
        $stmt->bindParam(':start_date', $currentMonth);
        $stmt->bindParam(':end_date', $currentMonthEnd);
        $stmt->execute();
        $currentMonthExpenses = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        $lastMonthExpensesQuery = "
            SELECT COALESCE(SUM(amount), 0) as total 
            FROM transactions 
            WHERE user_id = :user_id AND type = 'debit' 
            AND date >= :start_date AND date <= :end_date
        ";
        $stmt = $db->prepare($lastMonthExpensesQuery);
        $stmt->bindParam(':user_id', $userId);
        $stmt->bindParam(':start_date', $lastMonth);
        $stmt->bindParam(':end_date', $lastMonthEnd);
        $stmt->execute();
        $lastMonthExpenses = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        $topCategoriesQuery = "
            SELECT c.name, c.color, COALESCE(SUM(t.amount), 0) as total 
            FROM categories c
            LEFT JOIN transactions t ON c.id = t.category_id 
                AND t.user_id = :user_id 
                AND t.type = 'debit'
                AND t.date >= :start_date 
                AND t.date <= :end_date
            WHERE c.name != 'Income'
            GROUP BY c.id, c.name, c.color
            HAVING SUM(t.amount) > 0
            ORDER BY total DESC
            LIMIT 3
        ";
        $stmt = $db->prepare($topCategoriesQuery);
        $stmt->bindParam(':user_id', $userId);
        $stmt->bindParam(':start_date', $currentMonth);
        $stmt->bindParam(':end_date', $currentMonthEnd);
        $stmt->execute();
        $topCategories = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $recurringQuery = "SELECT * FROM recurring_payments WHERE user_id = :user_id ORDER BY amount DESC";
        $stmt = $db->prepare($recurringQuery);
        $stmt->bindParam(':user_id', $userId);
        $stmt->execute();
        $recurringPayments = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        sendResponse([
            'total_income' => floatval($totalIncome),
            'total_expenses' => floatval($totalExpenses),
            'savings' => floatval($totalIncome - $totalExpenses),
            'current_month_expenses' => floatval($currentMonthExpenses),
            'last_month_expenses' => floatval($lastMonthExpenses),
            'top_categories' => $topCategories,
            'recurring_payments' => $recurringPayments
        ]);
    }
    
    elseif ($action === 'category_breakdown') {
        $startDate = $_GET['start_date'] ?? date('Y-m-01');
        $endDate = $_GET['end_date'] ?? date('Y-m-t');
        
        $query = "
            SELECT c.name, c.color, COALESCE(SUM(t.amount), 0) as total, COUNT(t.id) as transaction_count
            FROM categories c
            LEFT JOIN transactions t ON c.id = t.category_id 
                AND t.user_id = :user_id 
                AND t.type = 'debit'
                AND t.date >= :start_date 
                AND t.date <= :end_date
            WHERE c.name != 'Income'
            GROUP BY c.id, c.name, c.color
            HAVING SUM(t.amount) > 0
            ORDER BY total DESC
        ";
        
        $stmt = $db->prepare($query);
        $stmt->bindParam(':user_id', $userId);
        $stmt->bindParam(':start_date', $startDate);
        $stmt->bindParam(':end_date', $endDate);
        $stmt->execute();
        $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        sendResponse(['categories' => $categories]);
    }
    
    elseif ($action === 'monthly_trend') {
        $months = intval($_GET['months'] ?? 6);
        
        $query = "
            SELECT 
                TO_CHAR(date, 'YYYY-MM') as month,
                SUM(CASE WHEN type = 'credit' THEN amount ELSE 0 END) as income,
                SUM(CASE WHEN type = 'debit' THEN amount ELSE 0 END) as expenses
            FROM transactions
            WHERE user_id = :user_id 
            AND date >= DATE_TRUNC('month', CURRENT_DATE - INTERVAL '{$months} months')
            GROUP BY TO_CHAR(date, 'YYYY-MM')
            ORDER BY month ASC
        ";
        
        $stmt = $db->prepare($query);
        $stmt->bindParam(':user_id', $userId);
        $stmt->execute();
        $trend = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        sendResponse(['trend' => $trend]);
    }
    
    elseif ($action === 'weekly_comparison') {
        $query = "
            SELECT 
                EXTRACT(WEEK FROM date) as week,
                TO_CHAR(date, 'YYYY-MM-DD') as week_start,
                SUM(amount) as total
            FROM transactions
            WHERE user_id = :user_id 
            AND type = 'debit'
            AND date >= DATE_TRUNC('week', CURRENT_DATE - INTERVAL '4 weeks')
            GROUP BY EXTRACT(WEEK FROM date), TO_CHAR(date, 'YYYY-MM-DD')
            ORDER BY week_start ASC
        ";
        
        $stmt = $db->prepare($query);
        $stmt->bindParam(':user_id', $userId);
        $stmt->execute();
        $weekly = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        sendResponse(['weekly' => $weekly]);
    }
    
    elseif ($action === 'insights') {
        $currentMonth = date('Y-m-01');
        $currentMonthEnd = date('Y-m-t');
        $lastMonth = date('Y-m-01', strtotime('-1 month'));
        $lastMonthEnd = date('Y-m-t', strtotime('-1 month'));
        
        $insights = [];
        
        $categoryComparisonQuery = "
            SELECT 
                c.name,
                COALESCE(SUM(CASE WHEN t.date >= :current_start AND t.date <= :current_end THEN t.amount ELSE 0 END), 0) as current_month,
                COALESCE(SUM(CASE WHEN t.date >= :last_start AND t.date <= :last_end THEN t.amount ELSE 0 END), 0) as last_month
            FROM categories c
            LEFT JOIN transactions t ON c.id = t.category_id AND t.user_id = :user_id AND t.type = 'debit'
            WHERE c.name != 'Income'
            GROUP BY c.name
            HAVING SUM(CASE WHEN t.date >= :current_start AND t.date <= :current_end THEN t.amount ELSE 0 END) > 0
            OR SUM(CASE WHEN t.date >= :last_start AND t.date <= :last_end THEN t.amount ELSE 0 END) > 0
        ";
        
        $stmt = $db->prepare($categoryComparisonQuery);
        $stmt->bindParam(':user_id', $userId);
        $stmt->bindParam(':current_start', $currentMonth);
        $stmt->bindParam(':current_end', $currentMonthEnd);
        $stmt->bindParam(':last_start', $lastMonth);
        $stmt->bindParam(':last_end', $lastMonthEnd);
        $stmt->execute();
        $categoryComparisons = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($categoryComparisons as $cat) {
            if ($cat['last_month'] > 0) {
                $change = (($cat['current_month'] - $cat['last_month']) / $cat['last_month']) * 100;
                if (abs($change) >= 15) {
                    $direction = $change > 0 ? 'increased' : 'decreased';
                    $insights[] = [
                        'type' => 'category_change',
                        'message' => "Your {$cat['name']} expenses {$direction} by " . abs(round($change)) . "% this month.",
                        'category' => $cat['name'],
                        'change' => round($change, 1)
                    ];
                }
            }
        }
        
        $topSpendingQuery = "
            SELECT c.name, SUM(t.amount) as total
            FROM transactions t
            JOIN categories c ON t.category_id = c.id
            WHERE t.user_id = :user_id AND t.type = 'debit' 
            AND t.date >= :start_date AND t.date <= :end_date
            AND c.name != 'Income'
            GROUP BY c.name
            ORDER BY total DESC
            LIMIT 1
        ";
        
        $stmt = $db->prepare($topSpendingQuery);
        $stmt->bindParam(':user_id', $userId);
        $stmt->bindParam(':start_date', $currentMonth);
        $stmt->bindParam(':end_date', $currentMonthEnd);
        $stmt->execute();
        $topSpending = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($topSpending && $topSpending['total'] > 0) {
            $insights[] = [
                'type' => 'top_spending',
                'message' => "You spent most on {$topSpending['name']} this month.",
                'category' => $topSpending['name'],
                'amount' => floatval($topSpending['total'])
            ];
        }
        
        sendResponse(['insights' => $insights]);
    }
}

else {
    sendError('Method not allowed', 405);
}
