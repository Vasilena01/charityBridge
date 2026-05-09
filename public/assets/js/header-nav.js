// Unified header navigation for all pages
function initHeaderNav() {
    const token = localStorage.getItem('auth_token');
    const userJson = localStorage.getItem('user');
    const headerNav = document.getElementById('header-nav');

    if (!headerNav) return;

    if (token && userJson) {
        const user = JSON.parse(userJson);
        renderAuthenticatedNav(user, headerNav);
    } else {
        renderPublicNav(headerNav);
    }
}

function renderAuthenticatedNav(user, headerNav) {
    let leftLinks = '';

    // All authenticated users see these links
    leftLinks = `
        <a href="index.html" class="nav-link">Home</a>
        <a href="campaigns.html" class="nav-link">Browse Campaigns</a>
    `;

    // Organizers get additional link
    if (user.role === 'organizer') {
        leftLinks += `<a href="my-campaigns.html" class="nav-link">My Campaigns</a>`;
        leftLinks += `<a href="create-campaign.html" class="nav-link">Create Campaign</a>`;
    }

    headerNav.innerHTML = `
        <div class="nav-left">
            ${leftLinks}
        </div>
        <div class="nav-right">
            <span class="welcome-text">Welcome, ${user.first_name}</span>
            <a href="profile.html" class="nav-link">Profile</a>
            <a href="#" onclick="handleLogout(); return false;" class="nav-link">Logout</a>
        </div>
    `;
}

function renderPublicNav(headerNav) {
    headerNav.innerHTML = `
        <div class="nav-left">
            <a href="index.html" class="nav-link">Home</a>
            <a href="campaigns.html" class="nav-link">Browse Campaigns</a>
        </div>
        <div class="nav-right">
            <a href="login.html" class="nav-link">Log In</a>
            <a href="signup.html" class="btn btn-primary" style="padding: 8px 16px;">Sign Up</a>
        </div>
    `;
}

function handleLogout() {
    localStorage.removeItem('auth_token');
    localStorage.removeItem('user');
    window.location.href = 'index.html';
}

// Initialize on page load
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initHeaderNav);
} else {
    initHeaderNav();
}
