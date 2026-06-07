<?php $user = current_user(); ?>
<header>
    <div class="header-content">
        <nav>
            <a href="<?= url('') ?>" class="logo">CharityBridge</a>
            <div class="header-nav-wrapper">
                <?php if ($user): ?>
                    <div class="nav-left">
                        <a href="<?= url('') ?>" class="nav-link">Home</a>
                        <a href="<?= url('campaigns') ?>" class="nav-link">Browse Campaigns</a>
                        <?php if ($user['role'] === 'organizer'): ?>
                            <a href="<?= url('my-campaigns') ?>" class="nav-link">My Campaigns</a>
                            <a href="<?= url('campaigns/create') ?>" class="nav-link">Create Campaign</a>
                        <?php endif ?>
                    </div>
                    <div class="nav-right">
                        <?php if (in_array($user['role'], ['volunteer', 'company'], true) && isset($user['virtual_balance'])): ?>
                            <a href="<?= url('profile') ?>" class="nav-link" title="Virtual currency balance"
                               style="background:#fff9f5;border:1px solid #ffd7ba;border-radius:14px;padding:4px 12px;color:#ff6b6b;font-weight:600;">
                                <?= number_format((float)$user['virtual_balance'], 2) ?>
                            </a>
                        <?php endif ?>
                        <span class="welcome-text">Welcome, <?= e($user['first_name']) ?></span>
                        <a href="<?= url('profile') ?>" class="nav-link">Profile</a>
                        <form method="POST" action="<?= url('logout') ?>" style="display:inline;">
                            <button type="submit" class="nav-link" style="background:none;border:none;cursor:pointer;color:inherit;font:inherit;padding:0;">Logout</button>
                        </form>
                    </div>
                <?php else: ?>
                    <div class="nav-left">
                        <a href="<?= url('') ?>" class="nav-link">Home</a>
                        <a href="<?= url('campaigns') ?>" class="nav-link">Browse Campaigns</a>
                    </div>
                    <div class="nav-right">
                        <a href="<?= url('login') ?>" class="nav-link">Log In</a>
                        <a href="<?= url('signup') ?>" class="btn btn-primary" style="padding: 8px 16px;">Sign Up</a>
                    </div>
                <?php endif ?>
            </div>
        </nav>
    </div>
</header>
