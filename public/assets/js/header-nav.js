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
    let leftLinks = `
        <a href="index.html" class="nav-link">Home</a>
        <a href="campaigns.html" class="nav-link">Browse Campaigns</a>
    `;

    if (user.role === 'organizer') {
        leftLinks += `<a href="my-campaigns.html" class="nav-link">My Campaigns</a>`;
        leftLinks += `<a href="create-campaign.html" class="nav-link">Create Campaign</a>`;
    }

    let balancePill = '';
    if ((user.role === 'volunteer' || user.role === 'company') && user.virtual_balance !== undefined && user.virtual_balance !== null) {
        const balance = parseFloat(user.virtual_balance).toFixed(2);
        balancePill = `<a href="profile.html" class="nav-link" title="Virtual currency balance" style="background:#fff9f5;border:1px solid #ffd7ba;border-radius:14px;padding:4px 12px;color:#ff6b6b;font-weight:600;">${balance}</a>`;
    }

    headerNav.innerHTML = `
        <div class="nav-left">
            ${leftLinks}
        </div>
        <div class="nav-right">
            ${balancePill}
            <span class="welcome-text">Welcome, ${user.first_name}</span>
            <a href="profile.html" class="nav-link">Profile</a>
            <a href="#" onclick="handleLogout(); return false;" class="nav-link">Logout</a>
        </div>
    `;
}

function renderHeaderNav() { initHeaderNav(); }

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

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initHeaderNav);
} else {
    initHeaderNav();
}
