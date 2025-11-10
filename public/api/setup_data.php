<?php
require_once __DIR__ . '/simple_db.php';

$db = new SimpleDatabase();

$defaultCategories = [
    ['name' => 'Food & Dining', 'keywords' => 'swiggy,zomato,restaurant,cafe,food,dining,pizza,burger', 'color' => '#ef4444'],
    ['name' => 'Transport', 'keywords' => 'uber,ola,taxi,metro,bus,fuel,petrol,gas,parking', 'color' => '#f59e0b'],
    ['name' => 'Entertainment', 'keywords' => 'netflix,spotify,prime,hotstar,disney,youtube,movie,cinema', 'color' => '#8b5cf6'],
    ['name' => 'Shopping', 'keywords' => 'amazon,flipkart,myntra,shopping,mall,store,retail', 'color' => '#ec4899'],
    ['name' => 'Bills & Utilities', 'keywords' => 'electricity,water,gas,internet,broadband,wifi,telephone,mobile', 'color' => '#14b8a6'],
    ['name' => 'Healthcare', 'keywords' => 'hospital,clinic,pharmacy,doctor,medical,health,medicine', 'color' => '#06b6d4'],
    ['name' => 'Education', 'keywords' => 'school,college,university,course,udemy,coursera,book,education', 'color' => '#3b82f6'],
    ['name' => 'Groceries', 'keywords' => 'grocery,supermarket,bigbasket,grofers,blinkit,vegetable', 'color' => '#10b981'],
    ['name' => 'Travel', 'keywords' => 'flight,hotel,booking,airbnb,makemytrip,travel,vacation,trip', 'color' => '#f97316'],
    ['name' => 'Income', 'keywords' => 'salary,income,payment,refund,cashback,credit,deposit', 'color' => '#22c55e'],
    ['name' => 'Other', 'keywords' => 'miscellaneous,other,general', 'color' => '#64748b']
];

$existingCategories = $db->findAll('categories');
if (empty($existingCategories)) {
    foreach ($defaultCategories as $category) {
        $db->insert('categories', $category);
    }
    echo "Categories initialized!\n";
} else {
    echo "Categories already exist.\n";
}
