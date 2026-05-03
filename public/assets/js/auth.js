/**
 * Authentication page handlers
 */

/**
 * Handle signup form submission
 */
async function handleSignup(event) {
    event.preventDefault();

    const form = event.target;
    const formData = {
        email: form.email.value,
        password: form.password.value,
        password_confirm: form.password_confirm.value,
        first_name: form.first_name.value,
        last_name: form.last_name.value,
        role: form.role.value
    };

    // Clear previous messages
    clearMessages();
    showLoading('Signing up...');

    const response = await API.signup(formData);

    hideLoading();

    if (response.success) {
        showSuccess(response.message || 'Registration successful! Redirecting to login...');
        setTimeout(() => {
            window.location.href = 'login.html';
        }, 1500);
    } else {
        showError(response.error || 'Registration failed. Please try again.');
    }
}

/**
 * Handle login form submission
 */
async function handleLogin(event) {
    event.preventDefault();

    const form = event.target;
    const email = form.email.value;
    const password = form.password.value;

    // Clear previous messages
    clearMessages();
    showLoading('Logging in...');

    const response = await API.login(email, password);

    hideLoading();

    if (response.success) {
        showSuccess('Login successful! Redirecting...');
        // Store user info in sessionStorage
        sessionStorage.setItem('user', JSON.stringify(response.user));
        setTimeout(() => {
            window.location.href = 'dashboard.html';
        }, 1000);
    } else {
        showError(response.error || 'Login failed. Please try again.');
    }
}

/**
 * Handle logout
 */
async function handleLogout() {
    const response = await API.logout();

    if (response.success) {
        sessionStorage.removeItem('user');
        window.location.href = 'index.html';
    }
}

/**
 * Check if user is logged in, redirect if needed
 */
async function requireAuth() {
    const session = await API.checkSession();

    if (!session.authenticated) {
        window.location.href = 'login.html';
        return null;
    }

    return session.user;
}

/**
 * Check if user is logged in, redirect to dashboard if yes
 */
async function redirectIfAuthenticated() {
    const session = await API.checkSession();

    if (session.authenticated) {
        window.location.href = 'dashboard.html';
    }
}

/**
 * Show error message
 */
function showError(message) {
    const messageDiv = document.getElementById('message');
    if (messageDiv) {
        messageDiv.innerHTML = `
            <div class="errors">
                <ul><li>${message}</li></ul>
            </div>
        `;
    }
}

/**
 * Show success message
 */
function showSuccess(message) {
    const messageDiv = document.getElementById('message');
    if (messageDiv) {
        messageDiv.innerHTML = `
            <div class="success">
                <p>${message}</p>
            </div>
        `;
    }
}

/**
 * Show loading message
 */
function showLoading(message) {
    const messageDiv = document.getElementById('message');
    if (messageDiv) {
        messageDiv.innerHTML = `<p>${message}</p>`;
    }
}

/**
 * Hide loading message
 */
function hideLoading() {
    // Loading is cleared by showError/showSuccess or clearMessages
}

/**
 * Clear all messages
 */
function clearMessages() {
    const messageDiv = document.getElementById('message');
    if (messageDiv) {
        messageDiv.innerHTML = '';
    }
}
