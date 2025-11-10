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
    $action = $_GET['action'] ?? 'dashboard';
    
    if ($action === 'dashboard') {
        $transactions = $db->findAll('transactions', 'user_id', $userId);
        $categories = $db->findAll('categories');
        
        $totalIncome = 0;
        $totalExpenses = 0;
        $currentMonthExpenses = 0;
        $lastMonthExpenses = 0;
        
        $currentMonth = date('Y-m');
        $lastMonth = date('Y-m', strtotime('-1 month'));
        
        $categoryTotals = [];
        foreach ($categories as $cat) {
            $categoryTotals[$cat['id']] = [
                'name' => $cat['name'],
                'color' => $cat['color'],
                'total' => 0
            ];
        }
        
        foreach ($transactions as $trans) {
            if ($trans['type'] === 'credit') {
                $totalIncome += $trans['amount'];
            } elseif ($trans['type'] === 'debit') {
                $totalExpenses += $trans['amount'];
                
                if (strpos($trans['date'], $currentMonth) === 0) {
                    $currentMonthExpenses += $trans['amount'];
                    
                    if (isset($trans['category_id']) && isset($categoryTotals[$trans['category_id']])) {
                        $categoryTotals[$trans['category_id']]['total'] += $trans['amount'];
                    }
                }
                
                if (strpos($trans['date'], $lastMonth) === 0) {
                    $lastMonthExpenses += $trans['amount'];
                }
            }
        }
        
        $topCategories = array_filter($categoryTotals, function($cat) {
            return $cat['total'] > 0 && $cat['name'] !== 'Income';
        });
        
        usort($topCategories, function($a, $b) {
            return $b['total'] - $a['total'];
        });
        
        $topCategories = array_slice($topCategories, 0, 3);
        
        $recurringPayments = [];
        $transactionsByDesc = [];
        foreach ($transactions as $trans) {
            if ($trans['type'] === 'debit') {
                $desc = strtolower(trim($trans['description']));
                if (!isset($transactionsByDesc[$desc])) {
                    $transactionsByDesc[$desc] = [];
                }
                $transactionsByDesc[$desc][] = $trans;
            }
        }
        
        foreach ($transactionsByDesc as $desc => $transList) {
            if (count($transList) >= 2) {
                $amounts = array_map(function($t) { return $t['amount']; }, $transList);
                $avgAmount = array_sum($amounts) / count($amounts);
                $variance = 0;
                foreach ($amounts as $amt) {
                    $variance += pow($amt - $avgAmount, 2);
                }
                $variance = $variance / count($amounts);
                
                if ($variance < ($avgAmount * 0.1)) {
                    $recurringPayments[] = [
                        'service_name' => ucfirst($desc),
                        'amount' => round($avgAmount, 2),
                        'frequency' => count($transList) >= 3 ? 'monthly' : 'recurring'
                    ];
                }
            }
        }
        
        usort($recurringPayments, function($a, $b) {
            return $b['amount'] - $a['amount'];
        });
        
        sendResponse([
            'total_income' => $totalIncome,
            'total_expenses' => $totalExpenses,
            'savings' => $totalIncome - $totalExpenses,
            'current_month_expenses' => $currentMonthExpenses,
            'last_month_expenses' => $lastMonthExpenses,
            'top_categories' => array_values($topCategories),
            'recurring_payments' => array_slice($recurringPayments, 0, 6)
        ]);
    }
    
    elseif ($action === 'category_breakdown') {
        $startDate = $_GET['start_date'] ?? date('Y-m-01');
        $endDate = $_GET['end_date'] ?? date('Y-m-t');
        
        $transactions = $db->findAll('transactions', 'user_id', $userId);
        $categories = $db->findAll('categories');
        
        $categoryMap = [];
        foreach ($categories as $cat) {
            $categoryMap[$cat['id']] = [
                'name' => $cat['name'],
                'color' => $cat['color'],
                'total' => 0
            ];
        }
        
        foreach ($transactions as $trans) {
            if ($trans['type'] === 'debit' && 
                $trans['date'] >= $startDate && 
                $trans['date'] <= $endDate &&
                isset($trans['category_id']) && 
                isset($categoryMap[$trans['category_id']])) {
                $categoryMap[$trans['category_id']]['total'] += $trans['amount'];
            }
        }
        
        $breakdown = array_filter($categoryMap, function($cat) {
            return $cat['total'] > 0 && $cat['name'] !== 'Income';
        });
        
        sendResponse(['categories' => array_values($breakdown)]);
    }
    
    elseif ($action === 'monthly_trend') {
        $transactions = $db->findAll('transactions', 'user_id', $userId);
        $months = [];
        
        foreach ($transactions as $trans) {
            $month = substr($trans['date'], 0, 7);
            if (!isset($months[$month])) {
                $months[$month] = ['income' => 0, 'expenses' => 0];
            }
            
            if ($trans['type'] === 'credit') {
                $months[$month]['income'] += $trans['amount'];
            } else {
                $months[$month]['expenses'] += $trans['amount'];
            }
        }
        
        ksort($months);
        $trend = [];
        foreach ($months as $month => $data) {
            $trend[] = [
                'month' => $month,
                'income' => $data['income'],
                'expenses' => $data['expenses']
            ];
        }
        
        sendResponse(['trend' => array_slice($trend, -6)]);
    }
    
    elseif ($action === 'insights') {
        $currentMonth = date('Y-m');
        $lastMonth = date('Y-m', strtotime('-1 month'));
        
        $transactions = $db->findAll('transactions', 'user_id', $userId);
        $categories = $db->findAll('categories');
        
        $categoryMap = [];
        foreach ($categories as $cat) {
            $categoryMap[$cat['id']] = $cat['name'];
        }
        
        $categoryCurrentMonth = [];
        $categoryLastMonth = [];
        
        foreach ($transactions as $trans) {
            if ($trans['type'] === 'debit' && isset($trans['category_id'])) {
                $catName = $categoryMap[$trans['category_id']] ?? 'Other';
                
                if (strpos($trans['date'], $currentMonth) === 0) {
                    if (!isset($categoryCurrentMonth[$catName])) {
                        $categoryCurrentMonth[$catName] = 0;
                    }
                    $categoryCurrentMonth[$catName] += $trans['amount'];
                }
                
                if (strpos($trans['date'], $lastMonth) === 0) {
                    if (!isset($categoryLastMonth[$catName])) {
                        $categoryLastMonth[$catName] = 0;
                    }
                    $categoryLastMonth[$catName] += $trans['amount'];
                }
            }
        }
        
        $insights = [];
        
        foreach ($categoryCurrentMonth as $catName => $currentAmount) {
            $lastAmount = $categoryLastMonth[$catName] ?? 0;
            if ($lastAmount > 0) {
                $change = (($currentAmount - $lastAmount) / $lastAmount) * 100;
                if (abs($change) >= 15) {
                    $direction = $change > 0 ? 'increased' : 'decreased';
                    $insights[] = [
                        'type' => 'category_change',
                        'message' => "Your {$catName} expenses {$direction} by " . abs(round($change)) . "% this month."
                    ];
                }
            } elseif ($currentAmount > 500) {
                $insights[] = [
                    'type' => 'new_category',
                    'message' => "You spent ₹" . round($currentAmount) . " on {$catName} this month."
                ];
            }
        }
        
        if (empty($insights)) {
            $insights[] = [
                'type' => 'general',
                'message' => "Keep tracking your expenses to get personalized insights!"
            ];
        }
        
        sendResponse(['insights' => array_slice($insights, 0, 5)]);
    }
    
    else {
        sendResponse([
            'total_income' => 0,
            'total_expenses' => 0,
            'savings' => 0,
            'current_month_expenses' => 0
        ]);
    }
}

else {
    sendError('Method not allowed', 405);
}
