<div class="card profile-card">
    <h1>My Profile</h1>
    <div class="profile-info">
        <dl>
            <dt>Email:</dt><dd><?= e($user['email']) ?></dd>
            <dt>First Name:</dt><dd><?= e($user['first_name']) ?></dd>
            <dt>Last Name:</dt><dd><?= e($user['last_name']) ?></dd>
            <dt>Role:</dt><dd><?= e(ucfirst($user['role'])) ?></dd>
            <dt>Bio:</dt><dd><?= !empty($user['bio']) ? e($user['bio']) : 'No bio added yet' ?></dd>
        </dl>
    </div>

    <?php if (in_array($user['role'], ['volunteer', 'company'], true)): ?>
        <div class="balance-card">
            <div>
                <div class="balance-label">Virtual currency balance</div>
                <small style="color:#7f8c8d;">Used to purchase items from campaigns</small>
            </div>
            <div class="balance-right">
                <div class="balance-amount"><?= number_format((float)($user['virtual_balance'] ?? 0), 2) ?></div>
                <a href="<?= url('deposit') ?>" class="btn btn-primary balance-deposit-btn">+ Deposit</a>
            </div>
        </div>
    <?php endif ?>

    <div class="profile-actions">
        <a href="<?= url('') ?>" class="btn btn-secondary">Back to Home</a>
    </div>
</div>

<?php if (in_array($user['role'], ['volunteer', 'company'], true)): ?>
    <div class="card purchases-card">
        <h2>Recent purchases</h2>
        <?php if (empty($purchases)): ?>
            <div class="purchases-empty">You have not made any purchases yet.</div>
        <?php else: ?>
            <table class="purchases-table">
                <thead>
                    <tr><th>Date</th><th>Campaign</th><th>Item</th><th class="num">Qty</th><th class="num">Paid</th><th class="num">Donated</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($purchases as $p): ?>
                        <tr>
                            <td><?= e(date('Y-m-d', strtotime($p['created_at']))) ?></td>
                            <td><?= e($p['campaign_title']) ?></td>
                            <td><?= e($p['item_name']) ?></td>
                            <td class="num"><?= e($p['quantity']) ?></td>
                            <td class="num"><?= number_format((float)$p['total_paid'], 2) ?></td>
                            <td class="num"><?= number_format((float)$p['total_donation'], 2) ?></td>
                        </tr>
                    <?php endforeach ?>
                </tbody>
            </table>
        <?php endif ?>
    </div>

    <div class="card offers-card">
        <h2>My production offers</h2>
        <?php if (empty($offers)): ?>
            <div class="purchases-empty">You have not made any production offers yet.</div>
        <?php else: ?>
            <table class="purchases-table">
                <thead>
                    <tr><th>Date</th><th>Campaign</th><th>Item</th><th class="num">Qty</th><th class="num">Total value</th><th>Status</th><th></th></tr>
                </thead>
                <tbody>
                    <?php foreach ($offers as $o):
                        $totalAll = ((float)$o['proposed_production_cost'] + (float)$o['proposed_donation_amount']) * (int)$o['quantity_offered'];
                    ?>
                        <tr>
                            <td><?= e(date('Y-m-d', strtotime($o['created_at']))) ?></td>
                            <td><?= e($o['campaign_title']) ?></td>
                            <td>
                                <?= e($o['name']) ?>
                                <?php if ($o['status'] === 'rejected' && !empty($o['organizer_note'])): ?>
                                    <div class="offer-note">"<?= e($o['organizer_note']) ?>"</div>
                                <?php endif ?>
                            </td>
                            <td class="num"><?= e($o['quantity_offered']) ?></td>
                            <td class="num"><?= number_format($totalAll, 2) ?></td>
                            <td><span class="offer-status-badge offer-status-<?= e($o['status']) ?>"><?= e($o['status']) ?></span></td>
                            <td class="offer-row-actions">
                                <?php if ($o['status'] === 'pending'): ?>
                                    <form method="POST" action="<?= url('offers/' . $o['id'] . '/decide') ?>" onsubmit="return confirm('Cancel this offer?')" style="display:inline;">
                                        <input type="hidden" name="action" value="cancel">
                                        <button type="submit" class="btn-link" style="background:none;border:none;color:#e74c3c;cursor:pointer;">Cancel</button>
                                    </form>
                                <?php endif ?>
                            </td>
                        </tr>
                    <?php endforeach ?>
                </tbody>
            </table>
        <?php endif ?>
    </div>

    <div class="card offers-card">
        <h2>My contributions</h2>
        <?php if (empty($contributions)): ?>
            <div class="purchases-empty">You have not made any direct contributions yet.</div>
        <?php else: ?>
            <table class="purchases-table">
                <thead>
                    <tr><th>Date</th><th>Campaign</th><th>Type</th><th>Detail</th><th>Status</th><th></th></tr>
                </thead>
                <tbody>
                    <?php foreach ($contributions as $c):
                        $detail = $c['type'] === 'monetary'
                            ? number_format((float)$c['amount'], 2)
                            : ($c['type'] === 'hours'
                                ? number_format((float)$c['hours_count'], 1) . ' h'
                                : e($c['goods_description']) . (!empty($c['goods_estimated_value']) ? ' (~' . number_format((float)$c['goods_estimated_value'], 2) . ')' : ''));
                    ?>
                        <tr>
                            <td><?= e(date('Y-m-d', strtotime($c['created_at']))) ?></td>
                            <td><?= e($c['campaign_title']) ?></td>
                            <td><?= e($c['type']) ?></td>
                            <td><?= $detail ?></td>
                            <td><span class="offer-status-badge offer-status-<?= e($c['status']) ?>"><?= e($c['status']) ?></span></td>
                            <td class="offer-row-actions">
                                <?php if ($c['type'] !== 'monetary' && $c['status'] === 'pending'): ?>
                                    <form method="POST" action="<?= url('contributions/' . $c['id'] . '/cancel') ?>" onsubmit="return confirm('Cancel this contribution?')" style="display:inline;">
                                        <button type="submit" class="btn-link" style="background:none;border:none;color:#e74c3c;cursor:pointer;">Cancel</button>
                                    </form>
                                <?php endif ?>
                            </td>
                        </tr>
                    <?php endforeach ?>
                </tbody>
            </table>
        <?php endif ?>
    </div>
<?php endif ?>

<div class="features profile-features">
    <div class="feature-card">
        <h3>Browse Campaigns</h3>
        <p>Discover and support charitable campaigns in your community.</p>
        <a href="<?= url('campaigns') ?>" class="btn btn-secondary">View Campaigns</a>
    </div>

    <?php if ($user['role'] === 'organizer'): ?>
        <div class="feature-card">
            <h3>My Campaigns</h3>
            <p>Manage your charitable campaigns and track progress.</p>
            <a href="<?= url('my-campaigns') ?>" class="btn btn-secondary">Manage Campaigns</a>
        </div>
    <?php endif ?>
</div>
