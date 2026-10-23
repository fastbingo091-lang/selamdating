<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/security.php';

start_app_session();

$databaseConnected = false;

try {
    $statement = database()->prepare('SELECT 1');
    $statement->execute();
    $databaseConnected = true;
} catch (PDOException $exception) {
    error_log(sprintf(
        'Selam homepage database check failed [%s]: %s',
        $exception->getCode(),
        $exception->getMessage()
    ));
}

$appRoot = '../';
$pageTitle = 'Meet Selam';
$pageDescription = 'A warmer place to begin a new connection.';
$activeNav = 'home';
$authenticated = isset($_SESSION['user_id']);

require __DIR__ . '/../includes/app_header.php';
?>

<section class="selam-hero">
    <div class="selam-hero-copy">
        <span class="selam-kicker"><i class="bi bi-heart-fill" aria-hidden="true"></i> YOUR NEXT CHAPTER STARTS HERE</span>
        <h2>Make room for a little more <span>connection.</span></h2>
        <p>Selam means hello. Let it be the beginning of something meaningful.</p>
        <div class="selam-hero-actions">
            <?php if ($authenticated): ?>
                <a class="selam-primary-link" href="../dashboard/index.php">Go to your space <i class="bi bi-arrow-right" aria-hidden="true"></i></a>
                <a class="selam-secondary-link" href="../profile.php">View your profile</a>
            <?php else: ?>
                <a class="selam-primary-link" href="../register.php">Join Selam <i class="bi bi-arrow-right" aria-hidden="true"></i></a>
                <a class="selam-secondary-link" href="../login.php">I already have an account</a>
            <?php endif; ?>
        </div>
        <div class="selam-hero-note">
            <i class="bi bi-stars" aria-hidden="true"></i>
            A thoughtful, welcoming place to start.
        </div>
    </div>
    <div class="selam-hero-art" aria-hidden="true">
        <div class="selam-sun"></div>
        <div class="selam-heart-orbit orbit-one"><i class="bi bi-heart-fill"></i></div>
        <div class="selam-heart-orbit orbit-two"><i class="bi bi-heart-fill"></i></div>
        <div class="selam-heart-main"><i class="bi bi-heart-fill"></i></div>
        <span class="selam-spark spark-one">✦</span>
        <span class="selam-spark spark-two">✧</span>
        <span class="selam-spark spark-three">✦</span>
    </div>
</section>

<section class="selam-welcome-panel">
    <div class="selam-section-icon"><i class="bi bi-chat-heart" aria-hidden="true"></i></div>
    <div>
        <span class="eyebrow">A NOTE FROM SELAM</span>
        <h2>Good connections begin with being yourself.</h2>
        <p>Find mutual matches, share a little love with likes and comments, and start a private chat.</p>
    </div>
    <span class="status-badge warning">Early access</span>
</section>

<section class="selam-steps" aria-labelledby="selam-steps-title">
    <div class="selam-section-heading">
        <div>
            <span class="eyebrow">A SIMPLE BEGINNING</span>
            <h2 id="selam-steps-title">Your Selam, your way</h2>
        </div>
        <span class="selam-heading-aside">Start at your own pace</span>
    </div>
    <div class="selam-step-grid">
        <a class="selam-step-card" href="<?= $authenticated ? '../profile.php' : '../register.php' ?>">
            <span class="selam-step-number">01</span>
            <span class="selam-step-icon"><i class="bi bi-person-heart" aria-hidden="true"></i></span>
            <h3>Start with you</h3>
            <p>Set up your Selam account and visit your profile.</p>
            <span class="selam-step-link"><?= $authenticated ? 'View profile' : 'Create account' ?> <i class="bi bi-arrow-right" aria-hidden="true"></i></span>
        </a>
        <a class="selam-step-card" href="<?= $authenticated ? '../dashboard/index.php' : '../login.php' ?>">
            <span class="selam-step-number">02</span>
            <span class="selam-step-icon"><i class="bi bi-sparkles" aria-hidden="true"></i></span>
            <h3>Make yourself at home</h3>
            <p>Come back to your personal space whenever you like.</p>
            <span class="selam-step-link"><?= $authenticated ? 'Open dashboard' : 'Sign in' ?> <i class="bi bi-arrow-right" aria-hidden="true"></i></span>
        </a>
        <div class="selam-step-card selam-coming-card">
            <span class="selam-step-number">03</span>
            <span class="selam-step-icon"><i class="bi bi-chat-dots" aria-hidden="true"></i></span>
            <h3>More ways to connect</h3>
            <p>Meet profiles whose interests match yours, and start a private chat.</p>
            <span class="selam-coming-label"><i class="bi bi-heart-fill" aria-hidden="true"></i> Discover on Selam</span>
        </div>
    </div>
</section>

<section class="selam-utility-panel">
    <div>
        <span class="eyebrow">YOUR SELAM ACCOUNT</span>
        <h2>Everything in one place</h2>
        <p>Manage your account and explore the tools currently available.</p>
    </div>
    <div class="selam-utility-links">
        <a href="../profile.php"><i class="bi bi-person-circle" aria-hidden="true"></i> Profile <i class="bi bi-arrow-up-right" aria-hidden="true"></i></a>
        <a href="../wallet/index.php"><i class="bi bi-wallet2" aria-hidden="true"></i> Wallet <i class="bi bi-arrow-up-right" aria-hidden="true"></i></a>
    </div>
</section>

<div class="selam-system-note" role="status">
    <span><i class="bi bi-info-circle" aria-hidden="true"></i> Wallet deposits and payouts are agent processed. Confirm the recipient ID before sending an internal transfer.</span>
    <span class="status-badge <?= $databaseConnected ? 'success' : 'warning' ?>">
        <?= $databaseConnected ? 'Service ready' : 'Service setup needed' ?>
    </span>
</div>

<style>
.selam-hero {
    position: relative;
    display: grid;
    min-height: 360px;
    grid-template-columns: minmax(0, 1.2fr) minmax(240px, .8fr);
    align-items: center;
    gap: 18px;
    overflow: hidden;
    margin-bottom: 24px;
    padding: clamp(26px, 5vw, 52px);
    border: 1px solid #f0dce4;
    border-radius: 28px;
    background:
        radial-gradient(ellipse at 88% 10%, rgba(255, 211, 218, .76), transparent 38%),
        linear-gradient(118deg, #fff 5%, #fff6f3 55%, #fbe9ed 100%);
    box-shadow: 0 22px 60px rgba(126, 57, 81, .09);
}

.selam-hero-copy {
    position: relative;
    z-index: 1;
    max-width: 600px;
}

.selam-kicker {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    color: #aa506c;
    font-size: 10px;
    font-weight: 800;
    letter-spacing: .14em;
}

.selam-kicker i {
    font-size: 12px;
}

.selam-hero h2 {
    max-width: 560px;
    margin: 16px 0 12px;
    color: #372832;
    font-size: clamp(36px, 6vw, 62px);
    font-weight: 750;
    letter-spacing: -.055em;
    line-height: 1.04;
}

.selam-hero h2 span {
    color: #bd5574;
}

.selam-hero-copy > p {
    max-width: 440px;
    margin: 0;
    color: #776873;
    font-size: 16px;
    line-height: 1.7;
}

.selam-hero-actions {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 12px 18px;
    margin-top: 25px;
}

.selam-primary-link {
    display: inline-flex;
    min-height: 48px;
    align-items: center;
    justify-content: center;
    gap: 10px;
    padding: 0 19px;
    border-radius: 14px;
    background: linear-gradient(135deg, #c85a79, #a84263);
    box-shadow: 0 8px 18px rgba(170, 60, 93, .2);
    color: #fff;
    font-size: 14px;
    font-weight: 700;
    text-decoration: none;
    transition: transform .16s ease, box-shadow .16s ease;
}

.selam-primary-link:hover {
    transform: translateY(-2px);
    box-shadow: 0 12px 22px rgba(170, 60, 93, .25);
    color: #fff;
}

.selam-secondary-link {
    color: #795b6b;
    font-size: 13px;
    font-weight: 700;
    text-decoration: none;
}

.selam-secondary-link:hover {
    color: #a84263;
}

.selam-hero-note {
    display: flex;
    align-items: center;
    gap: 7px;
    margin-top: 24px;
    color: #8a7781;
    font-size: 11px;
}

.selam-hero-note i {
    color: #b95270;
}

.selam-hero-art {
    position: relative;
    width: min(100%, 320px);
    aspect-ratio: 1;
    justify-self: center;
}

.selam-sun {
    position: absolute;
    inset: 11%;
    border: 1px solid rgba(190, 89, 119, .13);
    border-radius: 50%;
    background: radial-gradient(circle, rgba(255, 255, 255, .8), rgba(244, 191, 203, .25) 65%, transparent 67%);
    box-shadow: 0 0 0 22px rgba(255, 255, 255, .24), 0 0 0 44px rgba(196, 81, 112, .035);
}

.selam-heart-main {
    position: absolute;
    top: 50%;
    left: 50%;
    display: grid;
    width: 132px;
    height: 132px;
    place-items: center;
    transform: translate(-50%, -50%) rotate(-8deg);
    border: 1px solid rgba(255, 255, 255, .75);
    border-radius: 42px;
    background: linear-gradient(145deg, #e78098, #b44767);
    box-shadow: 0 23px 50px rgba(153, 50, 79, .24), inset 0 2px 2px rgba(255, 255, 255, .4);
    color: #fff;
    font-size: 58px;
}

.selam-heart-orbit {
    position: absolute;
    z-index: 1;
    display: grid;
    width: 46px;
    height: 46px;
    place-items: center;
    border: 1px solid rgba(255, 255, 255, .85);
    border-radius: 16px;
    background: rgba(255, 255, 255, .83);
    box-shadow: 0 10px 25px rgba(120, 54, 76, .12);
    color: #c65d7b;
    font-size: 18px;
}

.orbit-one {
    top: 22%;
    right: 12%;
    transform: rotate(12deg);
}

.orbit-two {
    bottom: 20%;
    left: 12%;
    transform: rotate(-13deg);
    color: #d68b77;
}

.selam-spark {
    position: absolute;
    color: #d9949f;
    font-size: 22px;
}

.spark-one { top: 14%; left: 28%; }
.spark-two { right: 14%; bottom: 25%; font-size: 28px; }
.spark-three { bottom: 15%; left: 40%; font-size: 15px; }

.selam-welcome-panel {
    display: flex;
    align-items: center;
    gap: 16px;
    margin-bottom: 32px;
    padding: 20px 22px;
    border: 1px solid #f0dce4;
    border-radius: 19px;
    background: #fff;
}

.selam-section-icon,
.selam-step-icon {
    display: grid;
    flex: 0 0 auto;
    place-items: center;
    border-radius: 15px;
    background: #fff0f2;
    color: #ba5574;
}

.selam-section-icon {
    width: 48px;
    height: 48px;
    font-size: 21px;
}

.selam-welcome-panel > div:nth-child(2) {
    min-width: 0;
    flex: 1;
}

.selam-welcome-panel h2 {
    margin: 3px 0 0;
    color: #382a32;
    font-size: 17px;
    font-weight: 700;
}

.selam-welcome-panel p {
    margin: 4px 0 0;
    color: #7d7078;
    font-size: 12px;
}

.selam-section-heading {
    display: flex;
    align-items: flex-end;
    justify-content: space-between;
    gap: 12px;
    margin-bottom: 15px;
}

.selam-section-heading h2,
.selam-utility-panel h2 {
    margin: 5px 0 0;
    color: #382a32;
    font-size: 22px;
    font-weight: 750;
    letter-spacing: -.025em;
}

.selam-heading-aside {
    color: #8b7c84;
    font-size: 12px;
}

.selam-step-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 14px;
    margin-bottom: 30px;
}

.selam-step-card {
    position: relative;
    display: flex;
    min-height: 198px;
    flex-direction: column;
    align-items: flex-start;
    padding: 19px;
    border: 1px solid #f0e2e6;
    border-radius: 19px;
    background: #fff;
    color: #392b33;
    text-decoration: none;
    transition: transform .16s ease, box-shadow .16s ease, border-color .16s ease;
}

a.selam-step-card:hover {
    transform: translateY(-3px);
    border-color: #e8c5d0;
    box-shadow: 0 14px 32px rgba(111, 55, 74, .09);
    color: #392b33;
}

.selam-step-number {
    position: absolute;
    top: 18px;
    right: 18px;
    color: #b89ba5;
    font-size: 11px;
    font-weight: 800;
    letter-spacing: .08em;
}

.selam-step-icon {
    width: 42px;
    height: 42px;
    margin-bottom: 15px;
    font-size: 19px;
}

.selam-step-card h3 {
    margin: 0;
    font-size: 15px;
    font-weight: 700;
}

.selam-step-card p {
    margin: 6px 0 17px;
    color: #80737a;
    font-size: 12px;
    line-height: 1.55;
}

.selam-step-link,
.selam-coming-label {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    margin-top: auto;
    color: #b64e6d;
    font-size: 11px;
    font-weight: 700;
}

.selam-coming-card {
    background: linear-gradient(145deg, #fff, #fff7f8);
}

.selam-coming-label {
    color: #85727b;
}

.selam-utility-panel {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 22px;
    margin-bottom: 20px;
    padding: 22px;
    border: 1px solid #f0dce4;
    border-radius: 19px;
    background: linear-gradient(110deg, #fff4f4, #fff 70%);
}

.selam-utility-panel h2 {
    font-size: 18px;
}

.selam-utility-panel p {
    margin: 5px 0 0;
    color: #7d7078;
    font-size: 12px;
}

.selam-utility-links {
    display: flex;
    flex-wrap: wrap;
    justify-content: flex-end;
    gap: 8px;
}

.selam-utility-links a {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 10px 12px;
    border: 1px solid #f0dce4;
    border-radius: 12px;
    background: #fff;
    color: #604b56;
    font-size: 11px;
    font-weight: 700;
    text-decoration: none;
}

.selam-utility-links a > i:first-child {
    color: #b84f6f;
    font-size: 14px;
}

.selam-utility-links a:hover {
    border-color: #ddaeba;
    color: #a84263;
}

.selam-system-note {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding: 12px 14px;
    border: 1px solid #f0e2e6;
    border-radius: 13px;
    color: #7d7078;
    font-size: 11px;
}

.selam-system-note > span:first-child {
    display: inline-flex;
    align-items: center;
    gap: 7px;
}

.selam-system-note > span:first-child i {
    color: #b84f6f;
}

@media (max-width: 760px) {
    .selam-hero {
        min-height: 0;
        grid-template-columns: minmax(0, 1fr) 210px;
        padding: 28px;
    }

    .selam-hero-art {
        width: 210px;
    }

    .selam-heart-main {
        width: 100px;
        height: 100px;
        border-radius: 32px;
        font-size: 44px;
    }

    .selam-step-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .selam-step-card:last-child {
        grid-column: 1 / -1;
        min-height: 150px;
    }

    .selam-utility-panel {
        align-items: flex-start;
        flex-direction: column;
    }

    .selam-utility-links {
        justify-content: flex-start;
    }
}

@media (max-width: 520px) {
    .selam-hero {
        grid-template-columns: 1fr;
        padding: 25px 21px 19px;
        border-radius: 22px;
    }

    .selam-hero h2 {
        margin-top: 13px;
        font-size: clamp(34px, 10vw, 48px);
    }

    .selam-hero-copy > p {
        font-size: 14px;
    }

    .selam-hero-art {
        width: 190px;
        margin-top: 4px;
    }

    .selam-welcome-panel {
        align-items: flex-start;
        flex-wrap: wrap;
        padding: 16px;
    }

    .selam-welcome-panel > .status-badge {
        margin-left: 64px;
    }

    .selam-section-heading {
        align-items: flex-start;
        flex-direction: column;
    }

    .selam-step-grid {
        grid-template-columns: 1fr;
    }

    .selam-step-card,
    .selam-step-card:last-child {
        min-height: 0;
        grid-column: auto;
    }

    .selam-system-note {
        align-items: flex-start;
        flex-direction: column;
    }
}
</style>

<?php require __DIR__ . '/../includes/app_footer.php'; ?>
