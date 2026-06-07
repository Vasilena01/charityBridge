<div class="card signup-card">
    <h1>Join CharityBridge</h1>
    <p>Start making a difference today</p>

    <?php if (!empty($errors)): ?>
        <div class="errors">
            <ul>
                <?php foreach ($errors as $err): ?>
                    <li><?= e($err) ?></li>
                <?php endforeach ?>
            </ul>
        </div>
    <?php endif ?>

    <form method="POST" action="<?= url('signup') ?>">
        <div class="form-group">
            <label for="email">Email:</label>
            <input type="email" id="email" name="email" required placeholder="your@email.com" value="<?= e(old('email', $old)) ?>">
        </div>

        <div class="form-row">
            <div class="form-group">
                <label for="first_name">First Name:</label>
                <input type="text" id="first_name" name="first_name" required maxlength="100" placeholder="John" value="<?= e(old('first_name', $old)) ?>">
            </div>
            <div class="form-group">
                <label for="last_name">Last Name:</label>
                <input type="text" id="last_name" name="last_name" required maxlength="100" placeholder="Doe" value="<?= e(old('last_name', $old)) ?>">
            </div>
        </div>

        <div class="form-group">
            <label for="password">Password:</label>
            <input type="password" id="password" name="password" required minlength="<?= (int)env('PASSWORD_MIN_LENGTH', 8) ?>" placeholder="Minimum <?= (int)env('PASSWORD_MIN_LENGTH', 8) ?> characters">
            <small>Minimum <?= (int)env('PASSWORD_MIN_LENGTH', 8) ?> characters</small>
        </div>

        <div class="form-group">
            <label for="password_confirm">Confirm Password:</label>
            <input type="password" id="password_confirm" name="password_confirm" required placeholder="Re-enter your password">
        </div>

        <div class="form-group">
            <label for="role">I am a:</label>
            <?php $selectedRole = old('role', $old); ?>
            <select id="role" name="role" required>
                <option value="">-- Select Role --</option>
                <option value="volunteer" <?= $selectedRole === 'volunteer' ? 'selected' : '' ?>>Volunteer/Donor</option>
                <option value="organizer" <?= $selectedRole === 'organizer' ? 'selected' : '' ?>>Campaign Organizer</option>
                <option value="company"   <?= $selectedRole === 'company'   ? 'selected' : '' ?>>Company Representative</option>
            </select>
        </div>

        <button type="submit" class="btn btn-primary">Sign Up</button>
    </form>

    <p class="text-center">
        Already have an account? <a href="<?= url('login') ?>">Log in</a>
    </p>
</div>
