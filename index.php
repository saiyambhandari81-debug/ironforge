<?php
require_once __DIR__ . '/config/database.php';
require_once ROOT_PATH . '/includes/auth.php';

// Real live data queries
$plans = $pdo->query("
    SELECT plan_name, price, duration_days, features
    FROM plans
    WHERE status = 'active'
    ORDER BY price ASC
")->fetchAll();

$trainers = $pdo->query("
    SELECT full_name, specialization
    FROM trainers
    WHERE status = 'active'
    ORDER BY full_name
    LIMIT 6
")->fetchAll();

// Real statistics from database
$activeMembersCount = (int) $pdo->query("SELECT COUNT(*) FROM members WHERE status = 'active'")->fetchColumn();
$trainersCount      = (int) $pdo->query("SELECT COUNT(*) FROM trainers WHERE status = 'active'")->fetchColumn();
$todayCheckinsCount = (int) $pdo->query("SELECT COUNT(*) FROM attendance WHERE attendance_date = CURDATE()")->fetchColumn();
$activePlansCount   = (int) $pdo->query("SELECT COUNT(*) FROM plans WHERE status = 'active'")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>IronForge Gym - Gym Operations &amp; Member Portal</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="<?= BASE_URL ?>/assets/css/style.css" rel="stylesheet">
    <style>
        .landing-nav {
            background: rgba(20, 24, 31, 0.95);
            backdrop-filter: blur(10px);
            padding: 14px 24px;
            position: sticky;
            top: 0;
            z-index: 1000;
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
        }
        .landing-nav .nav-link {
            color: #b7bccb;
            font-weight: 500;
            font-size: 0.9rem;
            transition: color var(--transition);
        }
        .landing-nav .nav-link:hover {
            color: #ffffff;
        }

        /* Hero Section */
        .hero-saas {
            background: linear-gradient(135deg, var(--ink) 0%, #171d28 100%);
            color: #fff;
            padding: 80px 0 100px;
            position: relative;
            overflow: hidden;
        }
        .hero-saas::before {
            content: '';
            position: absolute;
            top: -150px;
            right: -150px;
            width: 500px;
            height: 500px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(255, 106, 43, 0.18), transparent 70%);
            pointer-events: none;
        }
        .hero-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 6px 16px;
            border-radius: 999px;
            background: rgba(255, 106, 43, 0.12);
            border: 1px solid rgba(255, 106, 43, 0.3);
            color: var(--accent);
            font-size: 0.85rem;
            font-weight: 600;
        }
        .hero-image-card {
            position: relative;
            border-radius: var(--radius-lg);
            overflow: hidden;
            box-shadow: 0 20px 50px rgba(0,0,0,0.4);
            border: 1px solid rgba(255,255,255,0.1);
        }
        .hero-image-card img {
            width: 100%;
            height: 420px;
            object-fit: cover;
            display: block;
        }
        .hero-float-chip {
            position: absolute;
            background: rgba(20, 24, 31, 0.92);
            backdrop-filter: blur(8px);
            border: 1px solid rgba(255, 255, 255, 0.15);
            color: #fff;
            padding: 12px 18px;
            border-radius: var(--radius);
            box-shadow: var(--shadow-md);
        }
        .hero-chip-top { top: 20px; left: 20px; }
        .hero-chip-bottom { bottom: 20px; right: 20px; }

        /* Trust Stats Bar */
        .trust-bar {
            background: var(--surface);
            border-y: 1px solid var(--border);
            padding: 32px 0;
        }
        .stat-number {
            font-size: 2rem;
            font-weight: 800;
            color: var(--ink);
            line-height: 1;
        }

        /* Feature Cards */
        .feature-box {
            border: 1px solid var(--border);
            border-radius: var(--radius);
            background: var(--surface);
            padding: 28px;
            height: 100%;
            transition: transform var(--transition), box-shadow var(--transition);
        }
        .feature-box:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-md);
        }
        .feature-icon-wrap {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            background: var(--accent-soft);
            color: var(--accent-dark);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 1.35rem;
            margin-bottom: 18px;
        }

        /* Facility Showcase Section */
        .facility-section {
            background: var(--ink);
            color: #fff;
            padding: 90px 0;
            position: relative;
        }
        .facility-img-wrap {
            border-radius: var(--radius-lg);
            overflow: hidden;
            border: 1px solid rgba(255,255,255,0.15);
            box-shadow: var(--shadow-lg);
        }
        .facility-img-wrap img {
            width: 100%;
            height: 460px;
            object-fit: cover;
        }
        .facility-spec-item {
            display: flex;
            align-items: flex-start;
            gap: 14px;
            margin-bottom: 20px;
        }
        .facility-spec-icon {
            width: 36px;
            height: 36px;
            border-radius: 10px;
            background: rgba(255,106,43,0.15);
            color: var(--accent);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
            flex-shrink: 0;
        }

        /* Pricing Cards */
        .plan-card-landing {
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            background: var(--surface);
            box-shadow: var(--shadow-sm);
            transition: transform var(--transition), box-shadow var(--transition);
            height: 100%;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }
        .plan-card-landing:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-md);
        }
        .plan-featured-landing {
            background: var(--ink);
            color: #fff;
            border-color: var(--ink);
            position: relative;
        }

        /* FAQ Accordion */
        .accordion-button:not(.collapsed) {
            background-color: var(--accent-soft);
            color: var(--ink);
        }
        .accordion-button:focus {
            box-shadow: 0 0 0 3px var(--accent-soft);
            border-color: var(--accent);
        }

        /* CTA Banner */
        .cta-banner {
            background: linear-gradient(135deg, var(--ink) 0%, var(--ink-2) 100%);
            color: #fff;
            border-radius: var(--radius-lg);
            padding: 60px 40px;
            position: relative;
            overflow: hidden;
        }
        .cta-banner::after {
            content: '';
            position: absolute;
            right: -60px;
            bottom: -60px;
            width: 260px;
            height: 260px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(255,106,43,0.3), transparent 70%);
        }
    </style>
</head>
<body>

<!-- Navigation Bar -->
<nav class="landing-nav d-flex justify-content-between align-items-center">
    <a href="<?= BASE_URL ?>/" class="d-flex align-items-center gap-2 text-decoration-none">
        <div class="sidebar-brand p-0" style="height:auto;border:none;">
            <div class="mark" style="width:34px;height:34px;font-size:0.95rem;">IF</div>
        </div>
        <span class="fw-bold text-white fs-5">IronForge</span>
    </a>
    
    <div class="d-none d-lg-flex align-items-center gap-4">
        <a href="#features" class="nav-link">Features</a>
        <a href="#facility" class="nav-link">Facility</a>
        <a href="#plans" class="nav-link">Pricing</a>
        <a href="#trainers" class="nav-link">Coaches</a>
        <a href="#faq" class="nav-link">FAQ</a>
    </div>

    <div class="d-flex align-items-center gap-2">
        <a href="<?= BASE_URL ?>/login.php" class="btn btn-sm btn-outline-light me-1">Log in</a>
        <a href="<?= BASE_URL ?>/register.php" class="btn btn-sm btn-accent fw-semibold">
            <i class="bi bi-person-plus me-1"></i> Join Now
        </a>
    </div>
</nav>

<!-- Hero Section -->
<section class="hero-saas">
    <div class="container">
        <div class="row align-items-center g-5">
            <div class="col-lg-6">
                <div class="hero-badge mb-3">
                    <i class="bi bi-shield-check"></i> IronForge Gym Operations Platform
                </div>
                <h1 class="display-4 fw-extrabold text-white mb-3" style="letter-spacing: -0.02em; line-height: 1.15;">
                    Train hard.<br>Manage with clarity.
                </h1>
                <p class="lead text-white-50 mb-4" style="font-size: 1.15rem;">
                    The complete management platform for gym operations and member self-service. Handle memberships, trainer slot bookings, attendance check-ins, and financial reports.
                </p>
                
                <div class="d-flex align-items-center gap-3 flex-wrap mb-4">
                    <a href="<?= BASE_URL ?>/register.php" class="btn btn-accent btn-lg fw-bold px-4 py-3">
                        <i class="bi bi-person-plus-fill me-1"></i> Create Account
                    </a>
                    <a href="#plans" class="btn btn-outline-light btn-lg fw-semibold px-4 py-3">
                        Explore Plans <i class="bi bi-arrow-down ms-1"></i>
                    </a>
                </div>

                <div class="d-flex align-items-center gap-4 text-white-50 small pt-2 border-top border-secondary-subtle">
                    <div class="d-flex align-items-center gap-2">
                        <i class="bi bi-check-circle-fill text-success fs-6"></i>
                        <span>Instant Registration</span>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <i class="bi bi-check-circle-fill text-success fs-6"></i>
                        <span>eSewa / Khalti / Cash</span>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <i class="bi bi-check-circle-fill text-success fs-6"></i>
                        <span>Role-Based Access</span>
                    </div>
                </div>
            </div>

            <!-- Uploaded Gym Image Card Showcase -->
            <div class="col-lg-6">
                <div class="hero-image-card">
                    <img src="<?= BASE_URL ?>/assets/images/gym-hero.jpg" alt="IronForge Gym Facility Floor">
                    
                    <div class="hero-float-chip hero-chip-top">
                        <div class="d-flex align-items-center gap-2">
                            <span class="badge bg-success rounded-circle p-1"><i class="bi bi-check"></i></span>
                            <div>
                                <div class="fw-bold fs-7 lh-1">IronForge Gym</div>
                                <div class="small text-white-50" style="font-size:0.75rem;">Verified Gym Facility</div>
                            </div>
                        </div>
                    </div>

                    <div class="hero-float-chip hero-chip-bottom">
                        <div class="d-flex align-items-center gap-3">
                            <div class="icon-btn bg-accent text-white border-0" style="width:36px;height:36px;">
                                <i class="bi bi-clock-history"></i>
                            </div>
                            <div>
                                <div class="fw-bold tnum fs-6"><?= number_format($todayCheckinsCount) ?> Check-in<?= $todayCheckinsCount === 1 ? '' : 's' ?> Today</div>
                                <div class="small text-success" style="font-size:0.75rem;"><i class="bi bi-check-circle me-1"></i>Live Attendance</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Real Database Metrics Bar -->
<section class="trust-bar">
    <div class="container">
        <div class="row text-center g-4">
            <div class="col-6 col-md-3">
                <div class="stat-number tnum"><?= number_format($activeMembersCount) ?></div>
                <div class="text-muted small mt-1">Active Gym Members</div>
            </div>
            <div class="col-6 col-md-3">
                <div class="stat-number tnum text-success"><?= number_format($todayCheckinsCount) ?></div>
                <div class="text-muted small mt-1">Check-ins Today</div>
            </div>
            <div class="col-6 col-md-3">
                <div class="stat-number tnum"><?= number_format($trainersCount) ?></div>
                <div class="text-muted small mt-1">Available Coaches</div>
            </div>
            <div class="col-6 col-md-3">
                <div class="stat-number tnum text-warning"><?= number_format($activePlansCount) ?></div>
                <div class="text-muted small mt-1">Active Membership Options</div>
            </div>
        </div>
    </div>
</section>

<!-- SaaS Features Section -->
<section id="features" class="py-5 my-4">
    <div class="container">
        <div class="text-center mb-5" style="max-width: 680px; margin: 0 auto;">
            <span class="badge bg-accent-soft text-accent mb-2 px-3 py-2 fw-semibold fs-7">SYSTEM CAPABILITIES</span>
            <h2 class="h2 fw-bold text-dark mb-3">Everything You Need to Run Your Gym</h2>
            <p class="text-muted fs-6">A complete software system built specifically for gym owners and active members.</p>
        </div>

        <div class="row g-4">
            <div class="col-md-6 col-lg-4">
                <div class="feature-box">
                    <div class="feature-icon-wrap">
                        <i class="bi bi-person-workspace"></i>
                    </div>
                    <h3 class="h5 fw-bold mb-2">Member Self-Service Portal</h3>
                    <p class="text-muted small mb-0">Members log in to view active plan details, renewal expiration dates, personal attendance logs, and payment receipts.</p>
                </div>
            </div>

            <div class="col-md-6 col-lg-4">
                <div class="feature-box">
                    <div class="feature-icon-wrap tone-success">
                        <i class="bi bi-clock-history"></i>
                    </div>
                    <h3 class="h5 fw-bold mb-2">Attendance Verification</h3>
                    <p class="text-muted small mb-0">Real-time daily check-in and check-out tracking with instant member status verification at the front desk.</p>
                </div>
            </div>

            <div class="col-md-6 col-lg-4">
                <div class="feature-box">
                    <div class="feature-icon-wrap tone-info">
                        <i class="bi bi-calendar-check"></i>
                    </div>
                    <h3 class="h5 fw-bold mb-2">Trainer Slot Bookings</h3>
                    <p class="text-muted small mb-0">Manage coach availability schedules and let members book dedicated personal training sessions with one click.</p>
                </div>
            </div>

            <div class="col-md-6 col-lg-4">
                <div class="feature-box">
                    <div class="feature-icon-wrap tone-warning">
                        <i class="bi bi-pie-chart-fill"></i>
                    </div>
                    <h3 class="h5 fw-bold mb-2">Financial P&amp;L Analytics</h3>
                    <p class="text-muted small mb-0">Track revenue by payment method (eSewa, Khalti, Cash, Card), categorize operational expenses, and calculate net profit.</p>
                </div>
            </div>

            <div class="col-md-6 col-lg-4">
                <div class="feature-box">
                    <div class="feature-icon-wrap tone-danger">
                        <i class="bi bi-arrow-left-right"></i>
                    </div>
                    <h3 class="h5 fw-bold mb-2">Transfer &amp; Refund Approval Queues</h3>
                    <p class="text-muted small mb-0">Process membership transfer and refund requests safely with database row locks preventing double approvals.</p>
                </div>
            </div>

            <div class="col-md-6 col-lg-4">
                <div class="feature-box">
                    <div class="feature-icon-wrap">
                        <i class="bi bi-shield-lock-fill"></i>
                    </div>
                    <h3 class="h5 fw-bold mb-2">Enterprise Security</h3>
                    <p class="text-muted small mb-0">Custom session protection with HttpOnly + SameSite=Lax cookies, CSRF token verification, and strict role guards.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Facility & Equipment Showcase Section -->
<section id="facility" class="facility-section">
    <div class="container">
        <div class="row align-items-center g-5">
            <div class="col-lg-6">
                <div class="facility-img-wrap">
                    <img src="<?= BASE_URL ?>/assets/images/gym-hero.jpg" alt="IronForge Gym Equipment and Facility">
                </div>
            </div>

            <div class="col-lg-6">
                <span class="badge bg-accent text-white mb-2 px-3 py-2 fw-semibold fs-7">GYM FACILITY</span>
                <h2 class="h1 fw-bold text-white mb-3">Modern Training Environment</h2>
                <p class="text-white-50 mb-4 fs-6">
                    IronForge Gym features commercial grade resistance stations, free weight dumbbell racks, squat platforms, and climate control.
                </p>

                <div class="facility-spec-item">
                    <div class="facility-spec-icon">
                        <i class="bi bi-award"></i>
                    </div>
                    <div>
                        <h4 class="h6 fw-bold text-white mb-1">Dumbbell &amp; Free Weight Zone</h4>
                        <p class="text-white-50 small mb-0">Complete dumbbell pairs with heavy rack space and incline workout benches.</p>
                    </div>
                </div>

                <div class="facility-spec-item">
                    <div class="facility-spec-icon">
                        <i class="bi bi-lightning-charge"></i>
                    </div>
                    <div>
                        <h4 class="h6 fw-bold text-white mb-1">Cable &amp; Resistance Racks</h4>
                        <p class="text-white-50 small mb-0">Smooth cable towers for isolations and full-body conditioning exercises.</p>
                    </div>
                </div>

                <div class="facility-spec-item">
                    <div class="facility-spec-icon">
                        <i class="bi bi-wind"></i>
                    </div>
                    <div>
                        <h4 class="h6 fw-bold text-white mb-1">Climate-Controlled Ventilation</h4>
                        <p class="text-white-50 small mb-0">Ducted airflow ensuring fresh air during peak workout hours.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Membership Plans Section -->
<section id="plans" class="container py-5 my-4">
    <div class="text-center mb-5" style="max-width: 600px; margin: 0 auto;">
        <span class="badge bg-accent-soft text-accent mb-2 px-3 py-2 fw-semibold fs-7">TRANSPARENT PRICING</span>
        <h2 class="h2 fw-bold text-dark mb-2">Membership Plans</h2>
        <p class="text-muted fs-6">Select the ideal plan for your fitness journey. Transparent pricing with instant activation.</p>
    </div>

    <div class="row g-4 justify-content-center">
        <?php if (!$plans): ?>
            <div class="col-12 text-center text-muted py-5">
                <i class="bi bi-box-seam fs-1 text-secondary mb-2 d-block"></i>
                Plans will appear here once configured by gym administration.
            </div>
        <?php else: ?>
            <?php foreach ($plans as $i => $p): ?>
                <div class="col-md-6 col-lg-4">
                    <div class="plan-card-landing p-4 <?= $i === 1 ? 'plan-featured-landing' : '' ?>">
                        <div>
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <span class="badge <?= $i === 1 ? 'bg-warning text-dark' : 'bg-light text-dark border' ?>">
                                    <?= (int)$p['duration_days'] ?> Days
                                </span>
                                <?php if ($i === 1): ?>
                                    <span class="badge bg-danger">Featured Tier</span>
                                <?php endif; ?>
                            </div>
                            <h3 class="h4 fw-bold mb-2"><?= htmlspecialchars($p['plan_name']) ?></h3>
                            <div class="fs-2 fw-bold mb-3 tnum price-tag">
                                <?= formatMoney($p['price']) ?>
                            </div>
                            <?php if (!empty($p['features'])): ?>
                                <p class="small mb-4 <?= $i === 1 ? 'text-white-50' : 'text-muted' ?>">
                                    <?= nl2br(htmlspecialchars($p['features'])) ?>
                                </p>
                            <?php endif; ?>
                        </div>
                        <div>
                            <a href="<?= BASE_URL ?>/register.php"
                               class="btn <?= $i === 1 ? 'btn-accent' : 'btn-dark' ?> w-100 py-2 fw-semibold">
                                Get Started <i class="bi bi-chevron-right ms-1"></i>
                            </a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</section>

<!-- Coaches / Trainers Showcase Section -->
<?php if ($trainers): ?>
<section id="trainers" class="py-5" style="background: var(--surface); border-top: 1px solid var(--border);">
    <div class="container">
        <div class="text-center mb-5" style="max-width: 600px; margin: 0 auto;">
            <span class="badge bg-accent-soft text-accent mb-2 px-3 py-2 fw-semibold fs-7">EXPERT COACHING</span>
            <h2 class="h2 fw-bold text-dark mb-2">Train with Certified Coaches</h2>
            <p class="text-muted fs-6">Personalized guidance to accelerate your fitness gains.</p>
        </div>
        <div class="row g-3 justify-content-center">
            <?php foreach ($trainers as $t): ?>
                <div class="col-6 col-md-4 col-lg-3">
                    <div class="card p-4 text-center h-100">
                        <div class="avatar-chip mx-auto mb-3" style="width:54px;height:54px;font-size:1.25rem;">
                            <?= strtoupper(substr($t['full_name'] ?? 'T', 0, 1)) ?>
                        </div>
                        <div class="fw-bold text-dark fs-6 mb-1"><?= htmlspecialchars($t['full_name'] ?? '') ?></div>
                        <div class="badge bg-light text-dark border small fw-normal"><?= htmlspecialchars($t['specialization'] ?? 'Fitness Coach') ?></div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- FAQ Section -->
<section id="faq" class="py-5 bg-light border-top">
    <div class="container" style="max-width: 800px;">
        <div class="text-center mb-5">
            <span class="badge bg-accent-soft text-accent mb-2 px-3 py-2 fw-semibold fs-7">QUESTIONS &amp; ANSWERS</span>
            <h2 class="h2 fw-bold text-dark mb-2">Frequently Asked Questions</h2>
            <p class="text-muted fs-6">Find answers to common questions about memberships and gym access.</p>
        </div>

        <div class="accordion" id="faqAccordion">
            <div class="accordion-item mb-2 border rounded">
                <h2 class="accordion-header">
                    <button class="accordion-button fw-semibold" type="button" data-bs-toggle="collapse" data-bs-target="#faq1">
                        How do I register for a new gym membership?
                    </button>
                </h2>
                <div id="faq1" class="accordion-collapse collapse show" data-bs-parent="#faqAccordion">
                    <div class="accordion-body text-muted small">
                        Simply click on "Create Account" or "Join Now", fill out your basic contact details, and choose your preferred membership plan. You can pay via Cash, Card, Bank Transfer, eSewa, or Khalti.
                    </div>
                </div>
            </div>

            <div class="accordion-item mb-2 border rounded">
                <h2 class="accordion-header">
                    <button class="accordion-button collapsed fw-semibold" type="button" data-bs-toggle="collapse" data-bs-target="#faq2">
                        What payment methods are supported?
                    </button>
                </h2>
                <div id="faq2" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                    <div class="accordion-body text-muted small">
                        IronForge supports eSewa, Khalti, Bank Transfers, Credit/Debit Cards, and direct Cash payments at the reception desk.
                    </div>
                </div>
            </div>

            <div class="accordion-item mb-2 border rounded">
                <h2 class="accordion-header">
                    <button class="accordion-button collapsed fw-semibold" type="button" data-bs-toggle="collapse" data-bs-target="#faq3">
                        How do trainer slot bookings work?
                    </button>
                </h2>
                <div id="faq3" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                    <div class="accordion-body text-muted small">
                        Premium members (Gym + Cardio + Personal trainer) with an active, unexpired membership can log into their portal, open Bookings, and reserve trainer slots. Basic and Standard plans do not include personal training.
                    </div>
                </div>
            </div>

            <div class="accordion-item border rounded">
                <h2 class="accordion-header">
                    <button class="accordion-button collapsed fw-semibold" type="button" data-bs-toggle="collapse" data-bs-target="#faq4">
                        Can I transfer my membership or request a refund?
                    </button>
                </h2>
                <div id="faq4" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                    <div class="accordion-body text-muted small">
                        Yes. Transfer and refund requests can be submitted through the admin desk or user requests queue, where they are securely reviewed by gym administrators.
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Final Call-To-Action Banner -->
<section class="container my-5">
    <div class="cta-banner text-center">
        <h2 class="display-6 fw-bold mb-3">Ready to Start Your Transformation?</h2>
        <p class="lead text-white-50 mb-4" style="max-width: 600px; margin: 0 auto;">
            Join IronForge Gym today. Consistent workouts, modern facilities, and expert personal trainers await you.
        </p>
        <a href="<?= BASE_URL ?>/register.php" class="btn btn-accent btn-lg fw-bold px-5 py-3">
            <i class="bi bi-person-plus-fill me-1"></i> Get Started Now
        </a>
    </div>
</section>

<!-- SaaS Footer -->
<footer class="footer">
    <div class="container py-4">
        <div class="row g-4 mb-4">
            <div class="col-lg-4">
                <div class="d-flex align-items-center gap-2 mb-3">
                    <div class="sidebar-brand p-0" style="height:auto;border:none;">
                        <div class="mark" style="width:32px;height:32px;font-size:0.9rem;">IF</div>
                    </div>
                    <span class="text-white fw-bold fs-5">IronForge Gym</span>
                </div>
                <p class="text-white-50 small mb-3">
                    Train hard. Stay consistent. Comprehensive gym operations and member self-service software built for performance.
                </p>
                <div class="text-white-50 small">
                    <i class="bi bi-geo-alt me-1 text-accent"></i> Kathmandu, Nepal
                </div>
            </div>

            <div class="col-6 col-lg-2">
                <h5 class="text-white fs-6 fw-bold mb-3">Quick Links</h5>
                <ul class="list-unstyled small mb-0">
                    <li class="mb-2"><a href="<?= BASE_URL ?>/" class="text-white-50 text-decoration-none">Home</a></li>
                    <li class="mb-2"><a href="#features" class="text-white-50 text-decoration-none">Features</a></li>
                    <li class="mb-2"><a href="#plans" class="text-white-50 text-decoration-none">Pricing Plans</a></li>
                    <li class="mb-2"><a href="#trainers" class="text-white-50 text-decoration-none">Coaches</a></li>
                </ul>
            </div>

            <div class="col-6 col-lg-2">
                <h5 class="text-white fs-6 fw-bold mb-3">Portals</h5>
                <ul class="list-unstyled small mb-0">
                    <li class="mb-2"><a href="<?= BASE_URL ?>/login.php" class="text-white-50 text-decoration-none">Member Login</a></li>
                    <li class="mb-2"><a href="<?= BASE_URL ?>/register.php" class="text-white-50 text-decoration-none">Member Register</a></li>
                    <li class="mb-2"><a href="<?= BASE_URL ?>/login.php" class="text-white-50 text-decoration-none">Admin Login</a></li>
                </ul>
            </div>

            <div class="col-lg-4">
                <h5 class="text-white fs-6 fw-bold mb-3">Supported Payments</h5>
                <div class="d-flex flex-wrap gap-2 mb-3">
                    <span class="badge bg-secondary">eSewa</span>
                    <span class="badge bg-secondary">Khalti</span>
                    <span class="badge bg-secondary">Bank Transfer</span>
                    <span class="badge bg-secondary">Card</span>
                    <span class="badge bg-secondary">Cash</span>
                </div>
                <div class="text-white-50 small">
                    System Timezone: Asia/Kathmandu (NPR / Rs.)
                </div>
            </div>
        </div>

        <div class="border-top border-secondary pt-3 d-flex flex-wrap justify-content-between align-items-center gap-2 small text-white-50">
            <div>&copy; <?= date('Y') ?> IronForge Gym Management System. All rights reserved.</div>
            <div>Designed for gym performance.</div>
        </div>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>