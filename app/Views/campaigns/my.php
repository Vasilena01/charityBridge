<div class="campaigns-header">
    <div>
        <h1>My Campaigns</h1>
        <p>Manage and track your charitable campaigns</p>
    </div>
    <a href="<?= url('campaigns/create') ?>" class="btn btn-primary">+ Create Campaign</a>
</div>

<?php
$total     = count($campaigns);
$active    = count(array_filter($campaigns, fn($c) => $c['status'] === 'published'));
$completed = count(array_filter($campaigns, fn($c) => in_array($c['status'], ['successful', 'ended'], true)));
?>
<div class="stats-grid">
    <div class="stat-card"><h3><?= $total ?></h3><p>Total Campaigns</p></div>
    <div class="stat-card"><h3><?= $active ?></h3><p>Active</p></div>
    <div class="stat-card"><h3><?= $completed ?></h3><p>Completed</p></div>
</div>

<?php if (empty($campaigns)): ?>
    <div class="empty-state">
        <h2>No campaigns yet</h2>
        <p>Create your first campaign to start making a difference!</p>
        <a href="<?= url('campaigns/create') ?>" class="btn btn-primary">Create Your First Campaign</a>
    </div>
<?php else: ?>
    <?php foreach ($campaigns as $c):
        $progress  = $c['goal_amount'] > 0 ? ($c['current_amount'] / $c['goal_amount']) * 100 : 0;
        $deadline  = strtotime($c['deadline']);
        $daysLeft  = (int)ceil(($deadline - time()) / 86400);
        $isExpired = $daysLeft < 0;
    ?>
        <div class="campaign-card">
            <div class="campaign-header">
                <div class="campaign-title">
                    <h3><?= e($c['title']) ?></h3>
                    <span class="status-badge status-<?= e($c['status']) ?>"><?= e($c['status']) ?></span>
                </div>
            </div>
            <div class="campaign-meta">
                <span>Deadline: <?= e(date('Y-m-d', $deadline)) ?></span>
                <span><?= $isExpired ? 'Expired' : ($daysLeft . ' days left') ?></span>
                <span>Type: <?= e($c['campaign_type']) ?></span>
            </div>
            <p><?= e(mb_strimwidth($c['description'], 0, 150, '…')) ?></p>
            <div>
                <div class="progress-header">
                    <span class="progress-label">Progress</span>
                    <span class="progress-amount"><?= e($c['current_amount']) ?> / <?= e($c['goal_amount']) ?></span>
                </div>
                <div class="progress-bar">
                    <div class="progress-fill" style="width: <?= min($progress, 100) ?>%"></div>
                </div>
                <span class="progress-percentage"><?= number_format($progress, 1) ?>% of goal</span>
            </div>
            <div class="campaign-actions" style="display:flex;gap:8px;margin-top:10px;">
                <a href="<?= url('campaigns/' . $c['id']) ?>" class="btn btn-primary btn-small">View Details</a>
                <a href="<?= url('campaigns/' . $c['id'] . '/edit') ?>" class="btn btn-secondary btn-small">Edit</a>
                <form method="POST" action="<?= url('campaigns/' . $c['id'] . '/delete') ?>" onsubmit="return confirm('Are you sure you want to delete &quot;<?= e(addslashes($c['title'])) ?>&quot;? This action cannot be undone.')" style="display:inline;">
                    <button type="submit" class="btn btn-secondary btn-small btn-delete">Delete</button>
                </form>
            </div>
        </div>
    <?php endforeach ?>
<?php endif ?>
