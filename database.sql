-- Personal Finance & Expense Analyzer Database Schema

CREATE TABLE IF NOT EXISTS users (
    id SERIAL PRIMARY KEY,
    email VARCHAR(255) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS categories (
    id SERIAL PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    keywords TEXT,
    color VARCHAR(7) DEFAULT '#6366f1',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS transactions (
    id SERIAL PRIMARY KEY,
    user_id INTEGER REFERENCES users(id) ON DELETE CASCADE,
    date DATE NOT NULL,
    description VARCHAR(500) NOT NULL,
    amount DECIMAL(12, 2) NOT NULL,
    type VARCHAR(10) NOT NULL CHECK (type IN ('credit', 'debit')),
    category_id INTEGER REFERENCES categories(id),
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS recurring_payments (
    id SERIAL PRIMARY KEY,
    user_id INTEGER REFERENCES users(id) ON DELETE CASCADE,
    service_name VARCHAR(255) NOT NULL,
    amount DECIMAL(12, 2) NOT NULL,
    frequency VARCHAR(20) DEFAULT 'monthly',
    last_detected DATE,
    category_id INTEGER REFERENCES categories(id),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_transactions_user_date ON transactions(user_id, date DESC);
CREATE INDEX idx_transactions_category ON transactions(category_id);
CREATE INDEX idx_recurring_user ON recurring_payments(user_id);

-- Insert default categories with keywords
INSERT INTO categories (name, keywords, color) VALUES
('Food & Dining', 'swiggy,zomato,restaurant,cafe,food,dining,pizza,burger,dominos,mcdonalds,kfc,subway', '#ef4444'),
('Transport', 'uber,ola,lyft,taxi,metro,bus,fuel,petrol,gas,parking', '#f59e0b'),
('Entertainment', 'netflix,spotify,amazon prime,hotstar,disney,youtube,movie,cinema,pvr,inox', '#8b5cf6'),
('Shopping', 'amazon,flipkart,myntra,ajio,meesho,shopping,mall,store,retail', '#ec4899'),
('Bills & Utilities', 'electricity,water,gas,internet,broadband,wifi,telephone,mobile,recharge', '#14b8a6'),
('Healthcare', 'hospital,clinic,pharmacy,doctor,medical,health,medicine,apollo,fortis', '#06b6d4'),
('Education', 'school,college,university,course,udemy,coursera,book,education,tuition', '#3b82f6'),
('Groceries', 'grocery,supermarket,bigbasket,grofers,blinkit,instamart,zepto,vegetable', '#10b981'),
('Travel', 'flight,hotel,booking,airbnb,makemytrip,goibibo,travel,vacation,trip', '#f97316'),
('Subscriptions', 'subscription,membership,premium,renewal', '#6366f1'),
('Income', 'salary,income,payment,refund,cashback,credit,deposit', '#22c55e'),
('Other', 'miscellaneous,other,general', '#64748b')
ON CONFLICT DO NOTHING;
