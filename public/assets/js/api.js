/**
 * API utility for making requests to the backend
 */
const API = {
    baseURL: '/api',

    /**
     * Make an API request
     * @param {string} endpoint - API endpoint (e.g., '/auth/login')
     * @param {object} options - Fetch options
     * @returns {Promise<object>} - JSON response
     */
    async request(endpoint, options = {}) {
        const url = this.baseURL + endpoint;

        const defaultOptions = {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json',
            },
            credentials: 'include' // Include cookies for session
        };

        const config = { ...defaultOptions, ...options };

        // If body is an object, stringify it
        if (config.body && typeof config.body === 'object' && !(config.body instanceof FormData)) {
            config.body = JSON.stringify(config.body);
        }

        try {
            const response = await fetch(url, config);
            const data = await response.json();
            return data;
        } catch (error) {
            console.error('API request failed:', error);
            return { success: false, error: 'Network error. Please try again.' };
        }
    },

    /**
     * GET request
     */
    async get(endpoint) {
        return this.request(endpoint, { method: 'GET' });
    },

    /**
     * POST request
     */
    async post(endpoint, body) {
        return this.request(endpoint, {
            method: 'POST',
            body
        });
    },

    /**
     * Get CSRF token
     */
    async getCsrfToken() {
        const response = await this.get('/auth/csrf-token');
        return response.csrf_token || '';
    },

    /**
     * Check if user is authenticated
     */
    async checkSession() {
        return this.get('/auth/session');
    },

    /**
     * User signup
     */
    async signup(formData) {
        const csrfToken = await this.getCsrfToken();
        return this.post('/auth/signup', { ...formData, csrf_token: csrfToken });
    },

    /**
     * User login
     */
    async login(email, password) {
        const csrfToken = await this.getCsrfToken();
        return this.post('/auth/login', { email, password, csrf_token: csrfToken });
    },

    /**
     * User logout
     */
    async logout() {
        return this.post('/auth/logout', {});
    }
};

// Export for use in other files
if (typeof module !== 'undefined' && module.exports) {
    module.exports = API;
}
