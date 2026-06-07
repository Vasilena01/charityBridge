<?php $user = current_user(); ?>
<div class="hero">
    <h1>CharityBridge</h1>
    <p>Support charitable campaigns through donations, volunteering, and community action</p>

    <div class="carousel">
        <div class="carousel-inner" id="carouselInner">
            <?php
            $slides = [
                ['carousel-1.jpg', 'Volunteers helping together',  'Join Our Community',     'Together we make a bigger impact'],
                ['carousel-2.jpg', 'Volunteers helping community', 'Make an Impact',         'Your time and effort create real change'],
                ['carousel-3.jpg', 'Helping hands reaching out',   'Reach Out and Help',     "Your kindness can change someone's world"],
                ['carousel-4.jpg', 'Community helping each other', 'Community Together',     'Supporting each other makes us stronger'],
                ['carousel-5.jpg', 'Children receiving help',      'Support Those in Need',  'Give children the chance they deserve'],
            ];
            foreach ($slides as $i => $s):
            ?>
                <div class="carousel-item">
                    <img src="<?= asset('images/' . $s[0]) ?>" alt="<?= e($s[1]) ?>">
                    <div class="carousel-caption">
                        <h3><?= e($s[2]) ?></h3>
                        <p><?= e($s[3]) ?></p>
                    </div>
                </div>
            <?php endforeach ?>
        </div>
        <button class="carousel-control prev" onclick="moveCarousel(-1)">‹</button>
        <button class="carousel-control next" onclick="moveCarousel(1)">›</button>
        <div class="carousel-indicators">
            <?php foreach ($slides as $i => $_): ?>
                <button class="carousel-indicator<?= $i === 0 ? ' active' : '' ?>" onclick="showSlide(<?= $i ?>)"></button>
            <?php endforeach ?>
        </div>
    </div>

    <?php if (!$user): ?>
        <div id="hero-buttons">
            <a href="<?= url('signup') ?>" class="btn btn-primary">Get Started</a>
            <a href="<?= url('login') ?>" class="btn btn-secondary">Log In</a>
        </div>
    <?php endif ?>
</div>

<div class="features">
    <div class="feature-card">
        <h3>For Volunteers</h3>
        <p>Browse campaigns, donate virtual currency, sign up for volunteer opportunities, and track your impact across multiple causes.</p>
    </div>
    <div class="feature-card">
        <h3>For Organizers</h3>
        <p>Create and manage charitable campaigns with goal tracking, volunteer coordination, and goods/services offerings.</p>
    </div>
    <div class="feature-card">
        <h3>For Companies</h3>
        <p>Register your campaigns, make large contributions, and amplify your company's charitable impact.</p>
    </div>
</div>

<div class="card how-it-works">
    <h2>How It Works</h2>
    <p>CharityBridge connects people who want to help with campaigns that need support. Whether you're organizing a Christmas bazaar, running an online fundraiser, or coordinating volunteer efforts, we make it easy to track contributions of all types.</p>
    <div id="cta-button">
        <?php if (!$user): ?>
            <a href="<?= url('signup') ?>" class="btn btn-primary">Join Now</a>
        <?php elseif (in_array($user['role'], ['organizer', 'company'], true)): ?>
            <a href="<?= url('campaigns/create') ?>" class="btn btn-primary">Create Campaign</a>
            <a href="<?= url('my-campaigns') ?>" class="btn btn-secondary">My Campaigns</a>
        <?php else: ?>
            <a href="<?= url('campaigns') ?>" class="btn btn-primary">Explore Campaigns</a>
            <a href="<?= url('profile') ?>" class="btn btn-secondary">My Profile</a>
        <?php endif ?>
    </div>
</div>

<script>
let currentSlide = 0;
const slides = document.querySelectorAll('.carousel-item');
const indicators = document.querySelectorAll('.carousel-indicator');
const carouselInner = document.getElementById('carouselInner');

function showSlide(index) {
    if (index >= slides.length) currentSlide = 0;
    else if (index < 0) currentSlide = slides.length - 1;
    else currentSlide = index;
    carouselInner.style.transform = `translateX(-${currentSlide * 100}%)`;
    indicators.forEach((indicator, i) => indicator.classList.toggle('active', i === currentSlide));
}
function moveCarousel(direction) { showSlide(currentSlide + direction); }
setInterval(() => moveCarousel(1), 5000);
</script>
