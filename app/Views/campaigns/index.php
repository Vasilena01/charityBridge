<?php
$user = current_user();
$typeNames = [
    'bazaar' => 'Christmas Bazaar', 'online_game' => 'Online Game',
    'fundraiser' => 'Fundraiser', 'volunteer' => 'Volunteer Drive',
    'goods' => 'Goods Collection', 'other' => 'Other',
];
?>
<div class="page-header">
    <div>
        <h1>Browse Campaigns</h1>
        <p>Discover and support charitable causes</p>
    </div>
    <?php if ($user && $user['role'] === 'organizer'): ?>
        <div id="organizer-actions">
            <a href="<?= url('campaigns/create') ?>" class="btn btn-primary">+ Create New Campaign</a>
        </div>
    <?php endif ?>
</div>

<div class="filters">
    <form method="GET" action="<?= url('campaigns') ?>" class="filters-grid">
        <div class="filter-group">
            <label for="filter-type">Campaign Type:</label>
            <select id="filter-type" name="type">
                <option value="">All Types</option>
                <?php foreach ($typeNames as $k => $label): ?>
                    <option value="<?= e($k) ?>" <?= ($filters['type'] ?? '') === $k ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach ?>
            </select>
        </div>
        <div class="filter-group">
            <label for="filter-search">Search:</label>
            <input type="text" id="filter-search" name="search" value="<?= e($filters['search'] ?? '') ?>" placeholder="Search campaigns...">
        </div>
        <div class="filter-group">
            <button type="submit" class="btn btn-primary">Apply</button>
            <a href="<?= url('campaigns') ?>" class="btn btn-secondary">Clear</a>
        </div>
    </form>
</div>

<?php if (empty($campaigns)): ?>
    <div class="empty-state">
        <h2>No campaigns found</h2>
        <p>There are no active campaigns matching your filters.</p>
        <?php if ($user && $user['role'] === 'organizer'): ?>
            <a href="<?= url('campaigns/create') ?>" class="btn btn-primary">Create the First Campaign</a>
        <?php else: ?>
            <p style="color: #95a5a6;">Check back soon for new campaigns!</p>
        <?php endif ?>
    </div>
<?php else: ?>
    <div class="campaigns-grid">
        <?php foreach ($campaigns as $c):
            $progress = $c['goal_amount'] > 0 ? ($c['current_amount'] / $c['goal_amount']) * 100 : 0;
            $deadline = strtotime($c['deadline']);
            $daysLeft = (int)ceil(($deadline - time()) / 86400);
            $isExpired = $daysLeft < 0;
        ?>
            <div class="campaign-card">
                <span class="campaign-type-badge"><?= e($typeNames[$c['campaign_type']] ?? $c['campaign_type']) ?></span>
                <h3><?= e($c['title']) ?></h3>
                <div class="campaign-meta">
                    <span>By: <?= e($c['first_name']) ?> <?= e($c['last_name']) ?></span>
                    <span>Deadline: <?= e(date('Y-m-d', $deadline)) ?></span>
                    <span><?= $isExpired ? 'Expired' : ($daysLeft . ' days left') ?></span>
                </div>
                <p class="campaign-description">
                    <?= e(mb_strimwidth($c['description'], 0, 120, '…')) ?>
                </p>
                <div class="progress-section">
                    <div class="progress-text">
                        <span class="progress-funded"><?= number_format($progress, 0) ?>% funded</span>
                        <span><?= e($c['current_amount']) ?> / <?= e($c['goal_amount']) ?></span>
                    </div>
                    <div class="progress-bar">
                        <div class="progress-fill" style="width: <?= min($progress, 100) ?>%"></div>
                    </div>
                </div>
                <div class="campaign-actions">
                    <?php if (!$user): ?>
                        <a href="<?= url('signup') ?>" class="btn btn-primary btn-small">Sign Up to Support</a>
                    <?php elseif ($user['role'] === 'organizer' && (int)$c['organizer_id'] === (int)$user['id']): ?>
                        <a href="<?= url('my-campaigns') ?>" class="btn btn-secondary btn-small">Manage</a>
                        <a href="<?= url('campaigns/' . $c['id']) ?>" class="btn btn-primary btn-small">View Details</a>
                    <?php elseif ($user['role'] === 'organizer'): ?>
                        <a href="<?= url('campaigns/' . $c['id']) ?>" class="btn btn-secondary btn-small">View Details</a>
                    <?php else: ?>
                        <a href="<?= url('campaigns/' . $c['id']) ?>" class="btn btn-primary btn-small">Support Campaign</a>
                    <?php endif ?>
                </div>
            </div>
        <?php endforeach ?>
    </div>
<?php endif ?>
