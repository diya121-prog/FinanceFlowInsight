let categories = [];
let transactions = [];
let charts = {};

if (!localStorage.getItem('token')) {
    window.location.href = 'index.html';
}

const user = JSON.parse(localStorage.getItem('user'));
document.getElementById('userName').textContent = user.full_name;

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
        container.innerHTML = '<p class="text-gray-400 text-center py-8">No data available yet</p>';
        return;
    }
    
    container.innerHTML = topCategories.map((cat, index) => `
        <div class="flex items-center justify-between p-4 rounded-xl hover:bg-gray-50 transition mb-3 border border-gray-100">
            <div class="flex items-center flex-1">
                <div class="w-10 h-10 rounded-full flex items-center justify-center mr-3" style="background: ${cat.color}20;">
                    <div class="w-4 h-4 rounded-full" style="background: ${cat.color}"></div>
                </div>
                <div>
                    <span class="font-semibold text-gray-800">${cat.name}</span>
                    <p class="text-xs text-gray-500">Top #${index + 1} Category</p>
                </div>
            </div>
            <span class="text-lg font-bold" style="color: ${cat.color}">${formatCurrency(cat.total)}</span>
        </div>
    `).join('');
}

function displayRecurringPayments(recurring) {
    const container = document.getElementById('recurringPayments');
    if (!recurring || recurring.length === 0) {
        container.innerHTML = `
            <div class="text-center py-12">
                <div class="bg-purple-100 w-20 h-20 rounded-full flex items-center justify-center mx-auto mb-4">
                    <i class="fas fa-repeat text-purple-600 text-3xl"></i>
                </div>
                <p class="text-gray-500">No recurring payments detected yet.</p>
                <p class="text-sm text-gray-400 mt-2">Upload more transactions to detect subscriptions.</p>
            </div>
        `;
        return;
    }
    
    container.innerHTML = `
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            ${recurring.map(payment => `
                <div class="border-2 border-purple-100 rounded-2xl p-5 hover:border-purple-300 transition bg-gradient-to-br from-purple-50 to-white">
                    <div class="flex justify-between items-start mb-3">
                        <div class="flex-1">
                            <h4 class="font-bold text-gray-800 text-lg mb-1">${payment.service_name}</h4>
                            <div class="flex items-center gap-2">
                                <span class="text-xs px-3 py-1 bg-purple-100 text-purple-700 rounded-full font-semibold capitalize">
                                    <i class="fas fa-clock mr-1"></i>${payment.frequency}
                                </span>
                            </div>
                        </div>
                        <div class="text-right">
                            <span class="text-2xl font-bold text-red-600">${formatCurrency(payment.amount)}</span>
                        </div>
                    </div>
                </div>
            `).join('')}
        </div>
    `;
}

function displayInsights(insights) {
    const container = document.getElementById('insightsList');
    if (!insights || insights.length === 0) {
        container.innerHTML = `
            <div>
                <h4 class="font-bold text-gray-800 mb-2 text-lg">Financial Insights</h4>
                <p class="text-gray-700">Upload transactions to get personalized insights about your spending patterns!</p>
            </div>
        `;
        return;
    }
    
    container.innerHTML = `
        <div>
            <h4 class="font-bold text-gray-800 mb-3 text-lg">Your Financial Insights</h4>
            <div class="space-y-2">
                ${insights.map(insight => `
                    <div class="flex items-start">
                        <i class="fas fa-check-circle text-orange-500 mr-2 mt-1"></i>
                        <p class="text-gray-700 font-medium">${insight.message}</p>
                    </div>
                `).join('')}
            </div>
        </div>
    `;
}

function createCategoryPieChart(categoryData) {
    if (charts.pie) charts.pie.destroy();
    
    const ctx = document.getElementById('categoryPieChart').getContext('2d');
    
    if (!categoryData || categoryData.length === 0) {
        ctx.font = '16px Inter';
        ctx.fillStyle = '#9ca3af';
        ctx.textAlign = 'center';
        ctx.fillText('No spending data available', ctx.canvas.width / 2, ctx.canvas.height / 2);
        return;
    }
    
    charts.pie = new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: categoryData.map(c => c.name),
            datasets: [{
                data: categoryData.map(c => parseFloat(c.total)),
                backgroundColor: categoryData.map(c => c.color),
                borderWidth: 0,
                hoverOffset: 15
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        padding: 15,
                        font: {
                            size: 12,
                            family: 'Inter'
                        },
                        usePointStyle: true,
                        pointStyle: 'circle'
                    }
                },
                tooltip: {
                    backgroundColor: 'rgba(0, 0, 0, 0.8)',
                    padding: 12,
                    borderRadius: 8,
                    titleFont: {
                        size: 14,
                        family: 'Inter'
                    },
                    bodyFont: {
                        size: 13,
                        family: 'Inter'
                    },
                    callbacks: {
                        label: function(context) {
                            let label = context.label || '';
                            if (label) {
                                label += ': ';
                            }
                            label += formatCurrency(context.parsed);
                            const total = context.dataset.data.reduce((a, b) => a + b, 0);
                            const percentage = ((context.parsed / total) * 100).toFixed(1);
                            return label + ' (' + percentage + '%)';
                        }
                    }
                }
            },
            cutout: '60%'
        }
    });
}

async function createMonthlyTrendChart() {
    if (charts.trend) charts.trend.destroy();
    
    try {
        const data = await API.insights.getMonthlyTrend(6);
        const ctx = document.getElementById('monthlyTrendChart').getContext('2d');
        
        if (!data.trend || data.trend.length === 0) {
            ctx.font = '16px Inter';
            ctx.fillStyle = '#9ca3af';
            ctx.textAlign = 'center';
            ctx.fillText('No trend data available', ctx.canvas.width / 2, ctx.canvas.height / 2);
            return;
        }
        
        charts.trend = new Chart(ctx, {
            type: 'line',
            data: {
                labels: data.trend.map(t => {
                    const [year, month] = t.month.split('-');
                    return new Date(year, month - 1).toLocaleDateString('en-US', { month: 'short', year: 'numeric' });
                }),
                datasets: [
                    {
                        label: 'Income',
                        data: data.trend.map(t => parseFloat(t.income)),
                        borderColor: '#10b981',
                        backgroundColor: 'rgba(16, 185, 129, 0.1)',
                        tension: 0.4,
                        fill: true,
                        borderWidth: 3,
                        pointRadius: 5,
                        pointHoverRadius: 7,
                        pointBackgroundColor: '#10b981',
                        pointBorderColor: '#fff',
                        pointBorderWidth: 2
                    },
                    {
                        label: 'Expenses',
                        data: data.trend.map(t => parseFloat(t.expenses)),
                        borderColor: '#ef4444',
                        backgroundColor: 'rgba(239, 68, 68, 0.1)',
                        tension: 0.4,
                        fill: true,
                        borderWidth: 3,
                        pointRadius: 5,
                        pointHoverRadius: 7,
                        pointBackgroundColor: '#ef4444',
                        pointBorderColor: '#fff',
                        pointBorderWidth: 2
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                interaction: {
                    intersect: false,
                    mode: 'index'
                },
                plugins: {
                    legend: {
                        position: 'top',
                        labels: {
                            padding: 15,
                            font: {
                                size: 13,
                                family: 'Inter',
                                weight: '600'
                            },
                            usePointStyle: true,
                            pointStyle: 'circle'
                        }
                    },
                    tooltip: {
                        backgroundColor: 'rgba(0, 0, 0, 0.8)',
                        padding: 12,
                        borderRadius: 8,
                        titleFont: {
                            size: 14,
                            family: 'Inter'
                        },
                        bodyFont: {
                            size: 13,
                            family: 'Inter'
                        },
                        callbacks: {
                            label: function(context) {
                                let label = context.dataset.label || '';
                                if (label) {
                                    label += ': ';
                                }
                                label += formatCurrency(context.parsed.y);
                                return label;
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(0, 0, 0, 0.05)'
                        },
                        ticks: {
                            font: {
                                family: 'Inter'
                            },
                            callback: function(value) {
                                return '₹' + value.toLocaleString();
                            }
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        },
                        ticks: {
                            font: {
                                family: 'Inter'
                            }
                        }
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
        
        const transactionCategory = document.getElementById('transactionCategory');
        transactionCategory.innerHTML = '<option value="">Select Category</option>' +
            categories.map(cat => `<option value="${cat.id}">${cat.name}</option>`).join('');
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
        tbody.innerHTML = '<tr><td colspan="6" class="text-center py-12 text-gray-400">No transactions found. Add your first transaction!</td></tr>';
        return;
    }
    
    tbody.innerHTML = transactionList.map(t => `
        <tr>
            <td class="px-4 py-4 text-sm font-medium text-gray-700">${formatDate(t.date)}</td>
            <td class="px-4 py-4 text-sm font-semibold text-gray-800">${t.description}</td>
            <td class="px-4 py-4 text-sm">
                <span class="category-badge" style="background-color: ${t.category_color}20; color: ${t.category_color}">
                    ${t.category_name || 'Uncategorized'}
                </span>
            </td>
            <td class="px-4 py-4 text-sm font-bold ${t.type === 'credit' ? 'text-green-600' : 'text-red-600'}">
                ${t.type === 'credit' ? '+' : '-'}${formatCurrency(t.amount)}
            </td>
            <td class="px-4 py-4 text-sm">
                <span class="category-badge ${t.type === 'credit' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'}">
                    <i class="fas fa-${t.type === 'credit' ? 'arrow-up' : 'arrow-down'} mr-1"></i>
                    ${t.type === 'credit' ? 'Income' : 'Expense'}
                </span>
            </td>
            <td class="px-4 py-4 text-sm text-center">
                <button onclick="editTransaction(${t.id})" class="text-blue-600 hover:text-blue-800 mr-3 transition">
                    <i class="fas fa-edit text-lg"></i>
                </button>
                <button onclick="deleteTransaction(${t.id})" class="text-red-600 hover:text-red-800 transition">
                    <i class="fas fa-trash text-lg"></i>
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
        type: document.getElementById('transactionType').value,
        category_id: document.getElementById('transactionCategory').value
    };
    
    try {
        await API.transactions.add(transaction);
        showMessage('message', 'Transaction added successfully!', 'success');
        e.target.reset();
        document.getElementById('transactionDate').valueAsDate = new Date();
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
