let categories = [];
let transactions = [];
let charts = {};

if (!localStorage.getItem('token')) {
    window.location.href = 'index.html';
}

const user = JSON.parse(localStorage.getItem('user'));
document.getElementById('userName').textContent = `Welcome, ${user.full_name}`;

document.getElementById('logoutBtn').addEventListener('click', () => {
    localStorage.clear();
    window.location.href = 'index.html';
});

async function loadDashboard() {
    try {
        const [dashboardData, insightsData, categoryData] = await Promise.all([
            API.insights.getDashboard(),
            API.insights.getInsights(),
            API.insights.getCategoryBreakdown(
                new Date(new Date().getFullYear(), new Date().getMonth(), 1).toISOString().split('T')[0],
                new Date(new Date().getFullYear(), new Date().getMonth() + 1, 0).toISOString().split('T')[0]
            )
        ]);
        
        document.getElementById('totalIncome').textContent = formatCurrency(dashboardData.total_income);
        document.getElementById('totalExpenses').textContent = formatCurrency(dashboardData.total_expenses);
        document.getElementById('savings').textContent = formatCurrency(dashboardData.savings);
        document.getElementById('monthExpenses').textContent = formatCurrency(dashboardData.current_month_expenses);
        
        displayTopCategories(dashboardData.top_categories);
        displayRecurringPayments(dashboardData.recurring_payments);
        displayInsights(insightsData.insights);
        
        createCategoryPieChart(categoryData.categories);
        await createMonthlyTrendChart();
        
    } catch (error) {
        console.error('Error loading dashboard:', error);
        showMessage('message', 'Failed to load dashboard', 'error');
    }
}

function displayTopCategories(topCategories) {
    const container = document.getElementById('topCategories');
    if (!topCategories || topCategories.length === 0) {
        container.innerHTML = '<p class="text-gray-500">No data available</p>';
        return;
    }
    
    container.innerHTML = topCategories.map(cat => `
        <div class="flex items-center justify-between mb-3">
            <div class="flex items-center">
                <div class="w-3 h-3 rounded-full mr-2" style="background-color: ${cat.color}"></div>
                <span class="text-sm font-medium">${cat.name}</span>
            </div>
            <span class="text-sm font-bold">${formatCurrency(cat.total)}</span>
        </div>
    `).join('');
}

function displayRecurringPayments(recurring) {
    const container = document.getElementById('recurringPayments');
    if (!recurring || recurring.length === 0) {
        container.innerHTML = '<p class="text-gray-500">No recurring payments detected yet. Upload more transactions to detect subscriptions.</p>';
        return;
    }
    
    container.innerHTML = `
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            ${recurring.map(payment => `
                <div class="border border-gray-200 rounded-lg p-4">
                    <div class="flex justify-between items-start">
                        <div>
                            <h4 class="font-semibold text-gray-800">${payment.service_name}</h4>
                            <p class="text-sm text-gray-500 capitalize">${payment.frequency}</p>
                        </div>
                        <span class="text-lg font-bold text-red-600">${formatCurrency(payment.amount)}</span>
                    </div>
                </div>
            `).join('')}
        </div>
    `;
}

function displayInsights(insights) {
    const container = document.getElementById('insightsList');
    if (!insights || insights.length === 0) {
        container.innerHTML = '<p class="text-gray-700">Upload transactions to get personalized insights!</p>';
        return;
    }
    
    container.innerHTML = `
        <div>
            <h4 class="font-semibold text-gray-800 mb-2">Insights</h4>
            <ul class="list-disc list-inside space-y-1">
                ${insights.map(insight => `<li class="text-sm text-gray-700">${insight.message}</li>`).join('')}
            </ul>
        </div>
    `;
}

function createCategoryPieChart(categoryData) {
    if (charts.pie) charts.pie.destroy();
    
    const ctx = document.getElementById('categoryPieChart').getContext('2d');
    
    if (!categoryData || categoryData.length === 0) {
        ctx.font = '14px Arial';
        ctx.fillStyle = '#999';
        ctx.textAlign = 'center';
        ctx.fillText('No data available', ctx.canvas.width / 2, ctx.canvas.height / 2);
        return;
    }
    
    charts.pie = new Chart(ctx, {
        type: 'pie',
        data: {
            labels: categoryData.map(c => c.name),
            datasets: [{
                data: categoryData.map(c => parseFloat(c.total)),
                backgroundColor: categoryData.map(c => c.color)
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            plugins: {
                legend: {
                    position: 'bottom'
                }
            }
        }
    });
}

async function createMonthlyTrendChart() {
    if (charts.trend) charts.trend.destroy();
    
    try {
        const data = await API.insights.getMonthlyTrend(6);
        const ctx = document.getElementById('monthlyTrendChart').getContext('2d');
        
        if (!data.trend || data.trend.length === 0) {
            ctx.font = '14px Arial';
            ctx.fillStyle = '#999';
            ctx.textAlign = 'center';
            ctx.fillText('No data available', ctx.canvas.width / 2, ctx.canvas.height / 2);
            return;
        }
        
        charts.trend = new Chart(ctx, {
            type: 'line',
            data: {
                labels: data.trend.map(t => t.month),
                datasets: [
                    {
                        label: 'Income',
                        data: data.trend.map(t => parseFloat(t.income)),
                        borderColor: '#22c55e',
                        backgroundColor: 'rgba(34, 197, 94, 0.1)',
                        tension: 0.3
                    },
                    {
                        label: 'Expenses',
                        data: data.trend.map(t => parseFloat(t.expenses)),
                        borderColor: '#ef4444',
                        backgroundColor: 'rgba(239, 68, 68, 0.1)',
                        tension: 0.3
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        position: 'top'
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
    } catch (error) {
        console.error('Error creating monthly trend chart:', error);
    }
}

async function loadCategories() {
    try {
        const data = await API.transactions.getCategories();
        categories = data.categories;
        
        const categoryFilter = document.getElementById('categoryFilter');
        categoryFilter.innerHTML = '<option value="">All Categories</option>' +
            categories.map(cat => `<option value="${cat.id}">${cat.name}</option>`).join('');
        
        const editCategory = document.getElementById('editCategory');
        editCategory.innerHTML = categories.map(cat => `<option value="${cat.id}">${cat.name}</option>`).join('');
    } catch (error) {
        console.error('Error loading categories:', error);
    }
}

async function loadTransactions(filters = {}) {
    try {
        const data = await API.transactions.list(filters);
        transactions = data.transactions;
        displayTransactions(transactions);
    } catch (error) {
        console.error('Error loading transactions:', error);
        showMessage('message', 'Failed to load transactions', 'error');
    }
}

function displayTransactions(transactionList) {
    const tbody = document.getElementById('transactionsTable');
    
    if (!transactionList || transactionList.length === 0) {
        tbody.innerHTML = '<tr><td colspan="6" class="text-center py-4 text-gray-500">No transactions found</td></tr>';
        return;
    }
    
    tbody.innerHTML = transactionList.map(t => `
        <tr class="border-b hover:bg-gray-50">
            <td class="px-4 py-3 text-sm">${formatDate(t.date)}</td>
            <td class="px-4 py-3 text-sm">${t.description}</td>
            <td class="px-4 py-3 text-sm">
                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs" style="background-color: ${t.category_color}20; color: ${t.category_color}">
                    ${t.category_name || 'Uncategorized'}
                </span>
            </td>
            <td class="px-4 py-3 text-sm font-semibold ${t.type === 'credit' ? 'text-green-600' : 'text-red-600'}">
                ${t.type === 'credit' ? '+' : '-'}${formatCurrency(t.amount)}
            </td>
            <td class="px-4 py-3 text-sm">
                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs ${t.type === 'credit' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'}">
                    ${t.type === 'credit' ? 'Income' : 'Expense'}
                </span>
            </td>
            <td class="px-4 py-3 text-sm">
                <button onclick="editTransaction(${t.id})" class="text-blue-600 hover:text-blue-800 mr-2">
                    <i class="fas fa-edit"></i>
                </button>
                <button onclick="deleteTransaction(${t.id})" class="text-red-600 hover:text-red-800">
                    <i class="fas fa-trash"></i>
                </button>
            </td>
        </tr>
    `).join('');
}

document.getElementById('uploadBtn').addEventListener('click', async () => {
    const fileInput = document.getElementById('csvFile');
    const file = fileInput.files[0];
    
    if (!file) {
        showMessage('message', 'Please select a CSV file', 'error');
        return;
    }
    
    try {
        const response = await API.transactions.uploadCSV(file);
        showMessage('message', `Successfully imported ${response.imported_count} transactions!`, 'success');
        fileInput.value = '';
        await loadDashboard();
        await loadTransactions();
    } catch (error) {
        showMessage('message', error.message || 'CSV upload failed', 'error');
    }
});

document.getElementById('addTransactionForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    
    const transaction = {
        date: document.getElementById('transactionDate').value,
        description: document.getElementById('transactionDesc').value,
        amount: document.getElementById('transactionAmount').value,
        type: document.getElementById('transactionType').value
    };
    
    try {
        await API.transactions.add(transaction);
        showMessage('message', 'Transaction added successfully!', 'success');
        e.target.reset();
        await loadDashboard();
        await loadTransactions();
    } catch (error) {
        showMessage('message', error.message || 'Failed to add transaction', 'error');
    }
});

async function editTransaction(id) {
    const transaction = transactions.find(t => t.id == id);
    if (!transaction) return;
    
    document.getElementById('editId').value = transaction.id;
    document.getElementById('editDate').value = transaction.date;
    document.getElementById('editDesc').value = transaction.description;
    document.getElementById('editAmount').value = transaction.amount;
    document.getElementById('editCategory').value = transaction.category_id;
    
    document.getElementById('editModal').classList.remove('hidden');
}

async function deleteTransaction(id) {
    if (!confirm('Are you sure you want to delete this transaction?')) return;
    
    try {
        await API.transactions.delete(id);
        showMessage('message', 'Transaction deleted successfully!', 'success');
        await loadDashboard();
        await loadTransactions();
    } catch (error) {
        showMessage('message', error.message || 'Failed to delete transaction', 'error');
    }
}

document.getElementById('editTransactionForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    
    const transaction = {
        id: document.getElementById('editId').value,
        date: document.getElementById('editDate').value,
        description: document.getElementById('editDesc').value,
        amount: document.getElementById('editAmount').value,
        category_id: document.getElementById('editCategory').value
    };
    
    try {
        await API.transactions.update(transaction);
        showMessage('message', 'Transaction updated successfully!', 'success');
        document.getElementById('editModal').classList.add('hidden');
        await loadDashboard();
        await loadTransactions();
    } catch (error) {
        showMessage('message', error.message || 'Failed to update transaction', 'error');
    }
});

document.getElementById('cancelEdit').addEventListener('click', () => {
    document.getElementById('editModal').classList.add('hidden');
});

document.getElementById('searchBox').addEventListener('input', (e) => {
    const search = e.target.value;
    loadTransactions({ search });
});

document.getElementById('categoryFilter').addEventListener('change', (e) => {
    const category = e.target.value;
    loadTransactions(category ? { category } : {});
});

document.getElementById('transactionDate').valueAsDate = new Date();

window.editTransaction = editTransaction;
window.deleteTransaction = deleteTransaction;

loadCategories();
loadDashboard();
loadTransactions();
