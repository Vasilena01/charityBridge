<?php
$typeOptions = [
    'bazaar' => 'Christmas Bazaar', 'online_game' => 'Online Game',
    'fundraiser' => 'General Fundraiser', 'volunteer' => 'Volunteer Drive',
    'goods' => 'Goods Collection', 'other' => 'Other',
];
?>
<div class="card create-campaign-form">
    <h1>Edit Campaign</h1>
    <p>Update your campaign details</p>

    <form method="POST" action="<?= url('campaigns/' . $campaign['id']) ?>">
        <div class="form-group">
            <label for="title">Campaign Title</label>
            <input type="text" id="title" name="title" required maxlength="255" value="<?= e($campaign['title']) ?>">
        </div>

        <div class="form-group">
            <label for="description">Description</label>
            <textarea id="description" name="description" required rows="6"><?= e($campaign['description']) ?></textarea>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label for="campaign_type">Campaign Type</label>
                <select id="campaign_type" name="campaign_type" required>
                    <option value="">-- Select Type --</option>
                    <?php foreach ($typeOptions as $k => $label): ?>
                        <option value="<?= e($k) ?>" <?= $campaign['campaign_type'] === $k ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach ?>
                </select>
            </div>
            <div class="form-group">
                <label for="goal_amount">Goal Amount</label>
                <input type="text" id="goal_amount" name="goal_amount" value="<?= e($campaign['goal_amount']) ?>">
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label for="deadline">Campaign Deadline</label>
                <input type="date" id="deadline" name="deadline" required value="<?= e(date('Y-m-d', strtotime($campaign['deadline']))) ?>">
            </div>
        </div>

        <div class="form-actions">
            <button type="submit" name="action" value="save" class="btn btn-primary">Save Changes</button>
        </div>
    </form>

    <div class="items-section" style="margin-top:30px;">
        <h2>Items &amp; Services</h2>
        <p class="items-help">
            Goods or services this campaign offers. Each item has a
            <strong>production cost</strong> and a <strong>donation amount</strong>.
            Use quantity <code>-1</code> for unlimited.
        </p>

        <?php if (empty($items)): ?>
            <div class="items-empty">No items yet — add one below.</div>
        <?php else: ?>
            <div class="items-list">
                <?php foreach ($items as $item):
                    $cost = (float)$item['production_cost'];
                    $don  = (float)$item['donation_amount'];
                ?>
                    <div class="item-row-wrap" style="border:1px solid #eee;padding:18px;border-radius:8px;margin-bottom:12px;">
                        <form method="POST" action="<?= url('campaign-items/' . $item['id']) ?>" id="item-form-<?= (int)$item['id'] ?>" style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px 16px;">
                            <div class="form-group" style="grid-column:1 / -1;margin:0;">
                                <label>Name</label>
                                <input type="text" name="name" maxlength="255" required value="<?= e($item['name']) ?>">
                            </div>
                            <div class="form-group" style="margin:0;">
                                <label>Type</label>
                                <select name="item_type">
                                    <option value="good"    <?= $item['item_type'] === 'good'    ? 'selected' : '' ?>>Good</option>
                                    <option value="service" <?= $item['item_type'] === 'service' ? 'selected' : '' ?>>Service</option>
                                </select>
                            </div>
                            <div class="form-group" style="margin:0;">
                                <label>Quantity</label>
                                <input type="number" name="quantity_available" min="-1" step="1" value="<?= e($item['quantity_available']) ?>" title="-1 for unlimited">
                                <small style="color:#7f8c8d;">Use -1 for unlimited</small>
                            </div>
                            <div class="form-group" style="margin:0;">
                                <label>Production cost</label>
                                <input type="number" name="production_cost" min="0" step="0.01" value="<?= e(number_format($cost, 2, '.', '')) ?>" required>
                            </div>
                            <div class="form-group" style="margin:0;">
                                <label>Donation</label>
                                <input type="number" name="donation_amount" min="0" step="0.01" value="<?= e(number_format($don, 2, '.', '')) ?>" required>
                            </div>
                        </form>
                        <div style="display:flex;justify-content:flex-end;gap:10px;align-items:center;margin-top:14px;">
                            <form method="POST" action="<?= url('campaign-items/' . $item['id'] . '/delete') ?>" onsubmit="return confirm('Delete this item?')" style="margin:0;">
                                <button type="submit" class="item-delete-btn" style="background:none;border:1px solid #e74c3c;color:#e74c3c;cursor:pointer;padding:9px 18px;border-radius:999px;font-weight:600;font-size:14px;">Delete</button>
                            </form>
                            <button type="submit" form="item-form-<?= (int)$item['id'] ?>" class="btn btn-primary">Save</button>
                        </div>
                    </div>
                <?php endforeach ?>
            </div>
        <?php endif ?>

        <details>
            <summary class="add-item-btn" style="cursor:pointer;list-style:none;display:inline-block;padding:10px 16px;border:1px dashed #ff6b6b;color:#ff6b6b;border-radius:8px;margin-top:10px;">+ Add Item</summary>
            <form method="POST" action="<?= url('campaigns/' . $campaign['id'] . '/items') ?>" style="margin-top:14px;display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px 16px;border:1px solid #eee;padding:18px;border-radius:8px;">
                <div class="form-group" style="grid-column:1 / -1;margin:0;">
                    <label>Name</label>
                    <input type="text" name="name" maxlength="255" required placeholder="e.g. Hand-knit scarf">
                </div>
                <div class="form-group" style="margin:0;">
                    <label>Type</label>
                    <select name="item_type">
                        <option value="good">Good</option>
                        <option value="service">Service</option>
                    </select>
                </div>
                <div class="form-group" style="margin:0;">
                    <label>Quantity</label>
                    <input type="number" name="quantity_available" min="-1" step="1" value="1">
                    <small style="color:#7f8c8d;">Use -1 for unlimited</small>
                </div>
                <div class="form-group" style="margin:0;">
                    <label>Production cost</label>
                    <input type="number" name="production_cost" min="0" step="0.01" value="0.00" required>
                </div>
                <div class="form-group" style="margin:0;">
                    <label>Donation</label>
                    <input type="number" name="donation_amount" min="0" step="0.01" value="0.00" required>
                </div>
                <div style="grid-column:1 / -1;display:flex;justify-content:flex-end;">
                    <button type="submit" class="btn btn-primary">Add item</button>
                </div>
            </form>
        </details>
    </div>

    <?php if (!empty($offers)): ?>
        <div class="offers-section" style="margin-top:30px;">
            <h2>Production Offers</h2>
            <?php foreach ($offers as $o):
                $totalAll = ((float)$o['proposed_production_cost'] + (float)$o['proposed_donation_amount']) * (int)$o['quantity_offered'];
            ?>
                <div class="offer-row<?= $o['status'] === 'pending' ? ' is-pending' : '' ?>" style="border:1px solid #eee;padding:12px;border-radius:8px;margin-bottom:10px;">
                    <div class="offer-meta">
                        From <?= e($o['first_name']) ?> <?= e($o['last_name']) ?>
                        (<?= e($o['producer_role']) ?>) · <?= e(date('Y-m-d', strtotime($o['created_at']))) ?>
                    </div>
                    <div class="offer-name"><?= e($o['name']) ?>
                        <span style="font-weight: 400; color:#7f8c8d; font-size:13px;">— <?= e($o['item_type']) ?></span>
                    </div>
                    <?php if (!empty($o['description'])): ?>
                        <div class="offer-desc"><?= e($o['description']) ?></div>
                    <?php endif ?>
                    <div class="offer-pricing">
                        <?= (int)$o['quantity_offered'] ?> × (cost <?= number_format((float)$o['proposed_production_cost'], 2) ?>
                        + donation <?= number_format((float)$o['proposed_donation_amount'], 2) ?>)
                        = <strong><?= number_format($totalAll, 2) ?></strong>
                    </div>
                    <?php if (!empty($o['organizer_note'])): ?>
                        <div class="offer-note">Your note: "<?= e($o['organizer_note']) ?>"</div>
                    <?php endif ?>
                    <?php if ($o['status'] === 'pending'): ?>
                        <div class="offer-actions" style="display:flex;gap:8px;margin-top:8px;">
                            <form method="POST" action="<?= url('offers/' . $o['id'] . '/decide') ?>" style="display:inline;">
                                <input type="hidden" name="action" value="accept">
                                <button type="submit" class="offer-accept-btn">Accept</button>
                            </form>
                            <form method="POST" action="<?= url('offers/' . $o['id'] . '/decide') ?>" style="display:inline;">
                                <input type="hidden" name="action" value="reject">
                                <input type="text" name="organizer_note" placeholder="Optional note (why)" style="width:200px;">
                                <button type="submit" class="offer-reject-btn">Reject</button>
                            </form>
                        </div>
                    <?php else: ?>
                        <div class="offer-status-line"><?= e($o['status']) ?><?= !empty($o['decided_at']) ? ' on ' . e(date('Y-m-d', strtotime($o['decided_at']))) : '' ?></div>
                    <?php endif ?>
                </div>
            <?php endforeach ?>
        </div>
    <?php endif ?>

    <?php if (!empty($contributions)): ?>
        <div class="offers-section" style="margin-top:30px;">
            <h2>Contributions Received</h2>
            <?php
            $monTotal = 0; $hrTotal = 0; $goodsCount = 0;
            foreach ($contributions as $c) {
                if ($c['type'] === 'monetary' && $c['status'] === 'completed') $monTotal += (float)$c['amount'];
                if ($c['type'] === 'hours'    && $c['status'] !== 'cancelled') $hrTotal  += (float)$c['hours_count'];
                if ($c['type'] === 'goods'    && $c['status'] !== 'cancelled') $goodsCount++;
            }
            ?>
            <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; margin-bottom: 14px;">
                <div style="background:#fff9f5;padding:12px;border-radius:8px;text-align:center;">
                    <div style="font-size:20px;font-weight:700;color:#ff6b6b;"><?= number_format($monTotal, 2) ?></div>
                    <div style="font-size:12px;color:#7f8c8d;text-transform:uppercase;">Donated</div>
                </div>
                <div style="background:#fff9f5;padding:12px;border-radius:8px;text-align:center;">
                    <div style="font-size:20px;font-weight:700;color:#ff6b6b;"><?= number_format($hrTotal, 1) ?> h</div>
                    <div style="font-size:12px;color:#7f8c8d;text-transform:uppercase;">Volunteer hours</div>
                </div>
                <div style="background:#fff9f5;padding:12px;border-radius:8px;text-align:center;">
                    <div style="font-size:20px;font-weight:700;color:#ff6b6b;"><?= $goodsCount ?></div>
                    <div style="font-size:12px;color:#7f8c8d;text-transform:uppercase;">Goods pledges</div>
                </div>
            </div>
            <?php foreach ($contributions as $c):
                $detail = $c['type'] === 'monetary'
                    ? number_format((float)$c['amount'], 2)
                    : ($c['type'] === 'hours'
                        ? number_format((float)$c['hours_count'], 1) . ' h'
                        : e($c['goods_description']) . (!empty($c['goods_estimated_value']) ? ' (~' . number_format((float)$c['goods_estimated_value'], 2) . ')' : ''));
            ?>
                <div class="offer-row" style="grid-template-columns: 1fr;border:1px solid #eee;padding:10px;border-radius:6px;margin-bottom:6px;">
                    <div class="offer-meta">
                        <?= e(date('Y-m-d', strtotime($c['created_at']))) ?> ·
                        From <?= e($c['first_name']) ?> <?= e($c['last_name']) ?> (<?= e($c['contributor_role']) ?>)
                        · <span class="offer-status-line"><?= e($c['status']) ?></span>
                    </div>
                    <div class="offer-name"><?= e($c['type']) ?>: <?= $detail ?></div>
                    <?php if (!empty($c['note'])): ?>
                        <div class="offer-note">"<?= e($c['note']) ?>"</div>
                    <?php endif ?>
                </div>
            <?php endforeach ?>
        </div>
    <?php endif ?>

    <div class="form-cancel" style="margin-top:30px;">
        <a href="<?= url('my-campaigns') ?>">← Back to my campaigns</a>
    </div>
</div>
