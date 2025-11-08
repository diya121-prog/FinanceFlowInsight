# Personal Finance & Expense Analyzer

## Overview
A comprehensive personal finance application with expense tracking, CSV upload, automatic categorization, recurring payment detection, and visual analytics. Built with PHP backend and HTML/CSS/JavaScript/AJAX frontend.

## Tech Stack
- **Backend**: PHP 8.2
- **Database**: PostgreSQL
- **Frontend**: HTML5, CSS3, JavaScript (ES6+), AJAX
- **Styling**: Tailwind CSS
- **Charts**: Chart.js
- **Authentication**: JWT (JSON Web Tokens)

## Project Structure
```
/
├── api/                    # PHP backend API
│   ├── config.php         # Database and app configuration
│   ├── auth.php           # Authentication endpoints
│   ├── transactions.php   # Transaction management
│   ├── insights.php       # Analytics and insights
│   └── helpers/           # Utility functions
├── public/                # Frontend files
│   ├── index.html         # Landing page
│   ├── dashboard.html     # Main dashboard
│   ├── css/              # Stylesheets
│   └── js/               # JavaScript files
├── uploads/              # CSV file uploads
└── database.sql          # Database schema
```

## Core Features
1. User Authentication (JWT-based)
2. CSV Upload and Transaction Parsing
3. Automatic Expense Categorization
4. Recurring Payment Detection
5. Expense Insights and Analytics
6. Visual Data Dashboard (Charts)
7. Manual Transaction Management
8. Monthly/Weekly Reports

## Recent Changes
- Initial project setup (Nov 8, 2025)
- PHP 8.2 environment configured
- PostgreSQL database initialized
- Complete application structure created

## Database Schema
- users: User accounts
- transactions: Financial transactions
- categories: Expense categories
- recurring_payments: Detected subscriptions

## Usage
The application runs on PHP's built-in server on port 5000. Access the dashboard at the root URL after authentication.
