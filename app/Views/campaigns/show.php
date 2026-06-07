<?php
$user = current_user();
$typeNames = [
    'bazaar' => 'Christmas Bazaar', 'online_game' => 'Online Game',
    'fundraiser' => 'Fundraiser', 'volunteer' => 'Volunteer Drive',
    'goods' => 'Goods Collection', 'other' => 'Other',
];
$progress = $campaign['goal_amount'] > 0 ? ($campaign['current_amount'] / $campaign['goal_amount']) * 100 : 0;
$deadline = strtotime($campaign['deadline']);
$daysLeft = (int)ceil(($deadline - time()) / 86400);
$isExpired = $daysLeft < 0;
$isOrganizer = $user && $user['role'] === 'organizer';
$canBuy = $user && in_array($user['role'], ['volunteer', 'company'], true) && !$is_owner;
$canContribute = $canBuy && $campaign['status'] === 'published';
?>
<div class="campaign-detail-header">
    <a href="<?= url('campaigns') ?>" class="back-link">← Back to Campaigns</a>
</div>

<div class="campaign-detail-card">
    <div class="campaign-detail-header-section">
        <div>
            <span class="campaign-type-badge"><?= e($typeNames[$campaign['campaign_type']] ?? $campaign['campaign_type']) ?></span>
            <h1><?= e($campaign['title']) ?></h1>
            <div class="campaign-organizer">
                <span>Organized by: <?= e($campaign['first_name']) ?> <?= e($campaign['last_name']) ?></span>
                <span class="status-badge status-<?= e($campaign['status']) ?>"><?= e($campaign['status']) ?></span>
            </div>
        </div>
    </div>

    <div class="campaign-detail-body">
        <div class="campaign-detail-main">
            <div class="campaign-section">
                <h2>About This Campaign</h2>
                <p class="campaign-description"><?= nl2br(e($campaign['description'])) ?></p>
            </div>

            <div class="campaign-section">
                <h3>Campaign Details</h3>
                <dl class="campaign-info-list">
                    <dt>Campaign Type:</dt>
                    <dd><?= e($typeNames[$campaign['campaign_type']] ?? $campaign['campaign_type']) ?></dd>
                    <dt>Deadline:</dt>
                    <dd>
                        <?= e(date('Y-m-d', $deadline)) ?>
                        <?php if ($isExpired): ?>
                            <span style="color: #e74c3c;">(Expired)</span>
                        <?php else: ?>
                            (<?= $daysLeft ?> days left)
                        <?php endif ?>
                    </dd>
                    <dt>Created:</dt>
                    <dd><?= e(date('Y-m-d', strtotime($campaign['created_at']))) ?></dd>
                </dl>
            </div>

            <div class="campaign-section">
                <div class="items-section-header">
                    <h2>Items &amp; Services</h2>
                    <?php if ($canContribute): ?>
                        <details>
                            <summary class="offer-produce-btn" style="cursor:pointer;list-style:none;">+ Offer to produce</summary>
                            <form method="POST" action="<?= url('campaigns/' . $campaign['id'] . '/offers') ?>" style="margin-top:14px;padding:14px;border:1px solid #eee;border-radius:8px;">
                                <p style="color:#666;font-size:13px;">Describe the good or service you'll make for this campaign. If the organizer accepts, it will be added to the catalog at the price you propose.</p>
                                <div class="form-group">
                                    <label>Item name</label>
                                    <input type="text" name="name" maxlength="255" required placeholder="e.g. Hand-knit scarf">
                                </div>
                                <div class="form-group">
                                    <label>Description (optional)</label>
                                    <textarea name="description" maxlength="1000" placeholder="Material, size, anything the organizer should know"></textarea>
                                </div>
                                <div class="form-row">
                                    <div class="form-group">
                                        <label>Type</label>
                                        <select name="item_type">
                                            <option value="good">Good</option>
                                            <option value="service">Service</option>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label>Production cost</label>
                                        <input type="number" name="proposed_production_cost" min="0" step="0.01" value="0.00" required>
                                    </div>
                                    <div class="form-group">
                                        <label>Donation</label>
                                        <input type="number" name="proposed_donation_amount" min="0" step="0.01" value="0.00" required>
                                    </div>
                                    <div class="form-group">
                                        <label>Quantity</label>
                                        <input type="number" name="quantity_offered" min="1" max="1000" value="1" required>
                                    </div>
                                </div>
                                <button type="submit" class="btn btn-primary">Submit offer</button>
                            </form>
                        </details>
                    <?php endif ?>
                </div>

                <?php if (empty($items)): ?>
                    <div class="items-empty-state">This campaign has no items yet.</div>
                <?php else: ?>
                    <div class="items-catalog">
                        <?php foreach ($items as $item):
                            $cost     = (float)$item['production_cost'];
                            $donation = (float)$item['donation_amount'];
                            $total    = $cost + $donation;
                            $qty      = (int)$item['quantity_available'];
                            $sold     = (int)($item['quantity_sold'] ?? 0);
                            $soldOut  = $qty !== -1 && $sold >= $qty;
                            $availability = $qty === -1 ? 'Unlimited availability' : (max($qty - $sold, 0) . ' of ' . $qty . ' available');
                        ?>
                            <div class="item-card">
                                <div class="item-card-header">
                                    <div class="item-name"><?= e($item['name']) ?></div>
                                    <span class="item-type-badge"><?= e($item['item_type'] ?? 'good') ?></span>
                                </div>
                                <?php if (!empty($item['producer_id'])): ?>
                                    <div class="item-producer">Produced by a contributor</div>
                                <?php endif ?>
                                <?php if (!empty($item['description'])): ?>
                                    <div class="item-desc"><?= e($item['description']) ?></div>
                                <?php endif ?>
                                <div class="item-pricing">
                                    <div class="label">Production cost</div><div class="value"><?= number_format($cost, 2) ?></div>
                                    <div class="label">Donation</div><div class="value"><?= number_format($donation, 2) ?></div>
                                    <div class="item-total-row" style="grid-column: 1 / -1;">
                                        <span>You pay</span><span><?= number_format($total, 2) ?></span>
                                    </div>
                                </div>
                                <div class="item-availability"><?= e($availability) ?><?= $soldOut ? ' — Sold out' : '' ?></div>
                                <?php if ($soldOut): ?>
                                    <button class="item-buy-btn" disabled>Sold out</button>
                                <?php elseif (!$user): ?>
                                    <a href="<?= url('login') ?>" class="item-buy-btn">Sign in to purchase</a>
                                <?php elseif ($is_owner): ?>
                                    <button class="item-buy-btn" disabled title="Organizers cannot buy from their own campaign">Your campaign</button>
                                <?php elseif ($user['role'] === 'organizer'): ?>
                                    <button class="item-buy-btn" disabled>Organizers cannot purchase</button>
                                <?php else: ?>
                                    <?php $maxQ = $qty === -1 ? 100 : min(max($qty - $sold, 0), 100); ?>
                                    <form method="POST" action="<?= url('items/' . $item['id'] . '/purchase') ?>" style="display:flex;gap:8px;align-items:center;margin-top:8px;">
                                        <input type="number" name="quantity" min="1" max="<?= $maxQ ?>" value="1" required style="width:80px;">
                                        <button type="submit" class="item-buy-btn" style="flex:1;">Purchase</button>
                                    </form>
                                <?php endif ?>
                            </div>
                        <?php endforeach ?>
                    </div>
                <?php endif ?>
            </div>
        </div>

        <div class="campaign-detail-sidebar">
            <div class="campaign-progress-card">
                <h3>Campaign Progress</h3>
                <div class="progress-stats">
                    <div class="stat">
                        <div class="stat-value"><?= e($campaign['current_amount']) ?></div>
                        <div class="stat-label">Raised</div>
                    </div>
                    <div class="stat">
                        <div class="stat-value"><?= e($campaign['goal_amount']) ?></div>
                        <div class="stat-label">Goal</div>
                    </div>
                </div>
                <div class="progress-section">
                    <div class="progress-text">
                        <span class="progress-funded"><?= number_format($progress, 0) ?>% funded</span>
                    </div>
                    <div class="progress-bar">
                        <div class="progress-fill" style="width: <?= min($progress, 100) ?>%"></div>
                    </div>
                </div>

                <div class="campaign-actions">
                    <?php if (!$user): ?>
                        <a href="<?= url('signup') ?>" class="btn btn-primary">Sign Up to Support</a>
                    <?php elseif ($is_owner): ?>
                        <a href="<?= url('campaigns/' . $campaign['id'] . '/edit') ?>" class="btn btn-secondary">Manage Campaign</a>
                    <?php elseif ($user['role'] === 'organizer'): ?>
                        <p style="color: #95a5a6;">Organizers cannot donate to campaigns</p>
                    <?php endif ?>
                </div>

                <?php if ($canContribute): ?>
                    <details style="margin-top:14px;">
                        <summary class="btn btn-primary" style="cursor:pointer;list-style:none;text-align:center;">
                            <?= $user['role'] === 'company' ? 'Support Campaign' : 'Donate / Volunteer' ?>
                        </summary>
                        <form method="POST" action="<?= url('campaigns/' . $campaign['id'] . '/contributions') ?>" style="margin-top:14px;padding:14px;border:1px solid #eee;border-radius:8px;">
                            <div class="form-group">
                                <label>Type</label>
                                <select name="type" required>
                                    <option value="monetary">Donate money</option>
                                    <option value="hours">Volunteer hours</option>
                                    <option value="goods">Pledge goods</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Amount (for money)</label>
                                <input type="number" name="amount" min="0.01" step="0.01" placeholder="0.00">
                                <small style="color:#7f8c8d;">Your balance: <?= number_format((float)($user['virtual_balance'] ?? 0), 2) ?></small>
                            </div>
                            <div class="form-group">
                                <label>Hours (for volunteering)</label>
                                <input type="number" name="hours_count" min="0.5" step="0.5" placeholder="e.g. 4">
                            </div>
                            <div class="form-group">
                                <label>Goods description (for goods)</label>
                                <textarea name="goods_description" maxlength="1000" placeholder="e.g. 10 boxes of canned food"></textarea>
                            </div>
                            <div class="form-group">
                                <label>Estimated value of goods (optional)</label>
                                <input type="number" name="goods_estimated_value" min="0" step="0.01" placeholder="0.00">
                            </div>
                            <div class="form-group">
                                <label>Note for the organizer (optional)</label>
                                <textarea name="note" maxlength="500" placeholder="Anything they should know"></textarea>
                            </div>
                            <button type="submit" class="btn btn-primary">Submit</button>
                        </form>
                    </details>
                <?php endif ?>

                <?php $totalContribs = $summary['monetary_count'] + $summary['hours_count'] + $summary['goods_count']; ?>
                <?php if ($totalContribs > 0): ?>
                    <div class="contrib-summary" style="display:grid;">
                        <div class="label">Monetary donations</div>
                        <div class="value"><?= number_format($summary['monetary_total'], 2) ?> (<?= $summary['monetary_count'] ?>)</div>
                        <div class="label">Volunteer hours pledged</div>
                        <div class="value"><?= $summary['hours_count'] > 0 ? number_format($summary['hours_total'], 1) . ' h (' . $summary['hours_count'] . ')' : '0' ?></div>
                        <div class="label">Goods pledges</div>
                        <div class="value">
                            <?php if ($summary['goods_count'] > 0): ?>
                                <?= $summary['goods_count'] ?><?= $summary['goods_value'] > 0 ? ' (~' . number_format($summary['goods_value'], 2) . ')' : '' ?>
                            <?php else: ?>0<?php endif ?>
                        </div>
                    </div>
                <?php endif ?>
            </div>
        </div>
    </div>
</div>
