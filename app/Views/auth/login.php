<div class="card login-card">
    <h1>Welcome Back!</h1>
    <p>Log in to continue making a difference</p>

    <?php if (!empty($errors)): ?>
        <div class="errors">
            <ul>
                <?php foreach ($errors as $err): ?>
                    <li><?= e($err) ?></li>
                <?php endforeach ?>
            </ul>
        </div>
    <?php endif ?>

    <form method="POST" action="<?= url('login') ?>">
        <div class="form-group">
            <label for="email">Email:</label>
            <input type="email" id="email" name="email" required placeholder="your@email.com" value="<?= e(old('email', $old)) ?>">
        </div>

        <div class="form-group">
            <label for="password">Password:</label>
            <input type="password" id="password" name="password" required placeholder="Enter your password">
        </div>

        <button type="submit" class="btn btn-primary">Log In</button>
    </form>

    <p class="text-center">
        Don't have an account? <a href="<?= url('signup') ?>">Sign up</a>
    </p>
</div>
