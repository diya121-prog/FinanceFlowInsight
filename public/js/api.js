const API_URL = '/api';

const API = {
    async request(endpoint, options = {}) {
        const token = localStorage.getItem('token');
        const headers = {
            'Content-Type': 'application/json',
            ...options.headers
        };
        
        if (token && !options.skipAuth) {
            headers['Authorization'] = `Bearer ${token}`;
        }
        
        try {
            const response = await fetch(`${API_URL}${endpoint}`, {
                ...options,
                headers
            });
            
            const data = await response.json();
            
            if (!response.ok) {
                throw new Error(data.error || 'Request failed');
            }
            
            return data;
        } catch (error) {
            throw error;
        }
    },
    
    async uploadFile(endpoint, formData) {
        const token = localStorage.getItem('token');
        const headers = {};
        
        if (token) {
            headers['Authorization'] = `Bearer ${token}`;
        }
        
        try {
            const response = await fetch(`${API_URL}${endpoint}`, {
                method: 'POST',
                headers,
                body: formData
            });
            
            const data = await response.json();
            
            if (!response.ok) {
                throw new Error(data.error || 'Upload failed');
            }
            
            return data;
        } catch (error) {
            throw error;
        }
    },
    
    auth: {
        login: (email, password) => API.request('/auth.php?action=login', {
            method: 'POST',
            body: JSON.stringify({ email, password }),
            skipAuth: true
        }),
        
        register: (email, password, full_name) => API.request('/auth.php?action=register', {
            method: 'POST',
            body: JSON.stringify({ email, password, full_name }),
            skipAuth: true
        }),
        
        getUser: () => API.request('/auth.php')
    },
    
    transactions: {
        list: (params = {}) => {
            const query = new URLSearchParams(params).toString();
            return API.request(`/transactions.php?action=list&${query}`);
        },
        
        add: (transaction) => API.request('/transactions.php?action=add', {
            method: 'POST',
            body: JSON.stringify(transaction)
        }),
        
        update: (transaction) => API.request('/transactions.php', {
            method: 'PUT',
            body: JSON.stringify(transaction)
        }),
        
        delete: (id) => API.request(`/transactions.php?id=${id}`, {
            method: 'DELETE'
        }),
        
        uploadCSV: (file) => {
            const formData = new FormData();
            formData.append('file', file);
            return API.uploadFile('/transactions.php?action=upload_csv', formData);
        },
        
        getCategories: () => API.request('/transactions.php?action=categories')
    },
    
    insights: {
        getDashboard: () => API.request('/insights.php?action=dashboard'),
        getCategoryBreakdown: (start_date, end_date) => API.request(`/insights.php?action=category_breakdown&start_date=${start_date}&end_date=${end_date}`),
        getMonthlyTrend: (months = 6) => API.request(`/insights.php?action=monthly_trend&months=${months}`),
        getWeeklyComparison: () => API.request('/insights.php?action=weekly_comparison'),
        getInsights: () => API.request('/insights.php?action=insights')
    }
};

function showMessage(elementId, message, type = 'success') {
    const msgElement = document.getElementById(elementId);
    if (!msgElement) return;
    
    msgElement.className = `p-4 rounded-lg ${type === 'success' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'}`;
    msgElement.textContent = message;
    msgElement.classList.remove('hidden');
    
    setTimeout(() => {
        msgElement.classList.add('hidden');
    }, 5000);
}

function formatCurrency(amount) {
    return new Intl.NumberFormat('en-IN', {
        style: 'currency',
        currency: 'INR',
        minimumFractionDigits: 0,
        maximumFractionDigits: 0
    }).format(amount);
}

function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString('en-IN', {
        year: 'numeric',
        month: 'short',
        day: 'numeric'
    });
}
