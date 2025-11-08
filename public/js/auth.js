const loginTab = document.getElementById('loginTab');
const registerTab = document.getElementById('registerTab');
const loginForm = document.getElementById('loginForm');
const registerForm = document.getElementById('registerForm');
const loginFormElement = document.getElementById('loginFormElement');
const registerFormElement = document.getElementById('registerFormElement');

loginTab.addEventListener('click', () => {
    loginTab.classList.add('border-indigo-600', 'text-indigo-600');
    loginTab.classList.remove('text-gray-500');
    registerTab.classList.remove('border-indigo-600', 'text-indigo-600');
    registerTab.classList.add('text-gray-500');
    
    loginForm.classList.remove('hidden');
    registerForm.classList.add('hidden');
});

registerTab.addEventListener('click', () => {
    registerTab.classList.add('border-indigo-600', 'text-indigo-600');
    registerTab.classList.remove('text-gray-500');
    loginTab.classList.remove('border-indigo-600', 'text-indigo-600');
    loginTab.classList.add('text-gray-500');
    
    registerForm.classList.remove('hidden');
    loginForm.classList.add('hidden');
});

loginFormElement.addEventListener('submit', async (e) => {
    e.preventDefault();
    
    const email = document.getElementById('loginEmail').value;
    const password = document.getElementById('loginPassword').value;
    
    try {
        const response = await API.auth.login(email, password);
        localStorage.setItem('token', response.token);
        localStorage.setItem('user', JSON.stringify(response.user));
        
        showMessage('message', 'Login successful! Redirecting...', 'success');
        setTimeout(() => {
            window.location.href = 'dashboard.html';
        }, 1000);
    } catch (error) {
        showMessage('message', error.message || 'Login failed', 'error');
    }
});

registerFormElement.addEventListener('submit', async (e) => {
    e.preventDefault();
    
    const full_name = document.getElementById('registerName').value;
    const email = document.getElementById('registerEmail').value;
    const password = document.getElementById('registerPassword').value;
    
    try {
        const response = await API.auth.register(email, password, full_name);
        localStorage.setItem('token', response.token);
        localStorage.setItem('user', JSON.stringify(response.user));
        
        showMessage('message', 'Registration successful! Redirecting...', 'success');
        setTimeout(() => {
            window.location.href = 'dashboard.html';
        }, 1000);
    } catch (error) {
        showMessage('message', error.message || 'Registration failed', 'error');
    }
});

if (localStorage.getItem('token')) {
    window.location.href = 'dashboard.html';
}
