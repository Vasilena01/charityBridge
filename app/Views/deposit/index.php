<div class="deposit-grid">
    <div class="card deposit-card">
        <h1>Deposit funds</h1>
        <p class="deposit-sub">Top up your virtual currency.</p>

        <div class="balance-card">
            <div>
                <div class="balance-label">Current balance</div>
            </div>
            <div class="balance-amount"><?= number_format((float)($user['virtual_balance'] ?? 0), 2) ?></div>
        </div>

        <form method="POST" action="<?= url('deposit') ?>" autocomplete="off">
            <div class="form-group">
                <label for="amount">Amount</label>
                <input type="number" id="amount" name="amount" min="<?= e(env('MIN_DEPOSIT', 1)) ?>" max="<?= e(env('MAX_DEPOSIT', 10000)) ?>" step="0.01" required placeholder="e.g. 50.00">
                <div class="quick-amounts">
                    <button type="button" onclick="document.getElementById('amount').value='25.00'">25</button>
                    <button type="button" onclick="document.getElementById('amount').value='50.00'">50</button>
                    <button type="button" onclick="document.getElementById('amount').value='100.00'">100</button>
                    <button type="button" onclick="document.getElementById('amount').value='250.00'">250</button>
                    <button type="button" onclick="document.getElementById('amount').value='500.00'">500</button>
                </div>
            </div>

            <hr class="form-divider">

            <div class="form-group">
                <label for="card_number">Card number</label>
                <input type="text" id="card_number" name="card_number" required inputmode="numeric" maxlength="23" placeholder="1234 5678 9012 3456" oninput="this.value = this.value.replace(/\D/g,'').slice(0,19).replace(/(\d{4})(?=\d)/g, '$1 ').trim()">
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="expiry">Expiry (MM/YY)</label>
                    <input type="text" id="expiry" name="expiry" required maxlength="5" placeholder="12/29" oninput="var d=this.value.replace(/\D/g,'').slice(0,4); this.value = d.length<=2 ? d : d.slice(0,2)+'/'+d.slice(2)">
                </div>
                <div class="form-group">
                    <label for="cvv">CVV</label>
                    <input type="text" id="cvv" name="cvv" required inputmode="numeric" maxlength="4" placeholder="123" oninput="this.value = this.value.replace(/\D/g, '')">
                </div>
            </div>

            <div class="form-group">
                <label for="card_holder">Cardholder name</label>
                <input type="text" id="card_holder" name="card_holder" required maxlength="100" placeholder="Name on card">
            </div>

            <button type="submit" class="btn btn-primary deposit-submit">Deposit</button>
        </form>
    </div>

    <div class="card deposit-history-card">
        <h2>Recent deposits</h2>
        <?php if (empty($deposits)): ?>
            <div class="purchases-empty">No deposits yet.</div>
        <?php else: ?>
            <table class="purchases-table">
                <thead>
                    <tr><th>Date</th><th>Amount</th><th>Card</th><th>Status</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($deposits as $d): ?>
                        <tr>
                            <td><?= e(date('Y-m-d H:i', strtotime($d['created_at']))) ?></td>
                            <td><strong><?= number_format((float)$d['amount'], 2) ?></strong></td>
                            <td>•••• <?= e($d['card_last4'] ?: '----') ?></td>
                            <td><span class="offer-status-badge offer-status-<?= e($d['status']) ?>"><?= e($d['status']) ?></span></td>
                        </tr>
                    <?php endforeach ?>
                </tbody>
            </table>
        <?php endif ?>
    </div>
</div>
