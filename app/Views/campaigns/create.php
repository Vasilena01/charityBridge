<div class="card create-campaign-form">
    <h1>Create New Campaign</h1>
    <p>Start making a difference today</p>

    <?php if (!empty($errors)): ?>
        <div class="errors">
            <ul><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach ?></ul>
        </div>
    <?php endif ?>

    <form method="POST" action="<?= url('campaigns') ?>">
        <div class="form-group">
            <label for="title">Campaign Title</label>
            <input type="text" id="title" name="title" required maxlength="255" placeholder="Enter a compelling title" value="<?= e(old('title', $old)) ?>">
        </div>

        <div class="form-group">
            <label for="description">Description</label>
            <textarea id="description" name="description" required rows="6" placeholder="Describe your campaign, its goals, and how contributions will be used"><?= e(old('description', $old)) ?></textarea>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label for="campaign_type">Campaign Type</label>
                <?php $sel = old('campaign_type', $old); ?>
                <select id="campaign_type" name="campaign_type" required>
                    <option value="">-- Select Type --</option>
                    <?php foreach ([
                        'bazaar' => 'Christmas Bazaar', 'online_game' => 'Online Game',
                        'fundraiser' => 'General Fundraiser', 'volunteer' => 'Volunteer Drive',
                        'goods' => 'Goods Collection', 'other' => 'Other',
                    ] as $k => $label): ?>
                        <option value="<?= e($k) ?>" <?= $sel === $k ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach ?>
                </select>
            </div>

            <div class="form-group">
                <label for="goal_amount">Goal Amount</label>
                <input type="text" id="goal_amount" name="goal_amount" placeholder="e.g. 10000" value="<?= e(old('goal_amount', $old)) ?>">
                <small style="color:#7f8c8d;">Leave blank for Volunteer / Goods campaigns.</small>
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label for="deadline">Campaign Deadline</label>
                <input type="date" id="deadline" name="deadline" required min="<?= e(date('Y-m-d', strtotime('+1 day'))) ?>" value="<?= e(old('deadline', $old)) ?>">
            </div>
        </div>

        <p style="color:#7f8c8d;font-size:13px;">You can add items &amp; services after creating the campaign.</p>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Publish Campaign</button>
        </div>

        <div class="form-cancel">
            <a href="<?= url('my-campaigns') ?>">Cancel and return to my campaigns</a>
        </div>
    </form>
</div>
