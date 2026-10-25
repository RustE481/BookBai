<?php
declare(strict_types=1);

require_once __DIR__ . DIRECTORY_SEPARATOR . 'auth.php';

try {
    bookbai_database();
    if (bookbai_current_user()) {
        header('Location: dashboard.php');
        exit;
    }
} catch (Throwable $error) {
    error_log('BookBai login page error: ' . $error->getMessage());
    http_response_code(503);
    $databaseUnavailable = true;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#fffaf1">
    <title>Log in — BookBai</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="styles.css?v=bookbai-auth-1">
    <script src="login.js?v=bookbai-auth-1" defer></script>
</head>
<body class="login-page" data-csrf-token="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
    <main class="login-shell">
        <aside class="login-story" aria-label="BookBai campus book exchange">
            <a class="brand login-brand" href="login.php" aria-label="BookBai login">
                <img src="images/Logo.png" alt="" class="brand-mark">
                <span>Book<span class="brand-accent">Bai</span></span>
            </a>
            <div class="login-story-copy">
                <span class="login-story-kicker"><span></span> YOUR COMMUNITY BOOKSHELF</span>
                <h1>Good books<br>find <em>new</em><br>chapters here.</h1>
                <p>A campus bookshelf made for sharing what you know and discovering what comes next.</p>
            </div>
            <div class="login-book-art" aria-hidden="true">
                <span class="login-orbit login-orbit-one"></span>
                <span class="login-orbit login-orbit-two"></span>
                <span class="login-spark login-spark-one">✳</span>
                <span class="login-spark login-spark-two">✦</span>
                <div class="login-book login-book-back"><span>LEARN SOMETHING<br>NEW EVERY DAY</span><strong>THE<br>OPEN<br>PAGE</strong></div>
                <div class="login-book login-book-front"><span>GOOD THINGS<br>GO AROUND</span><strong>PASS IT<br>ON</strong><i>✳</i></div>
            </div>
            <div class="login-story-footer"><span>READ MORE. WASTE LESS.</span><span>MADE FOR THE SCC COMMUNITY</span></div>
        </aside>

        <section class="login-panel" aria-labelledby="login-title">
            <div class="login-panel-inner">
                <div class="login-mobile-brand">
                    <a class="brand" href="login.php" aria-label="BookBai login">
                        <img src="images/Logo.png" alt="" class="brand-mark">
                        <span>Book<span class="brand-accent">Bai</span></span>
                    </a>
                </div>
                <div class="login-intro">
                    <span class="login-welcome-label">CAMPUS BOOK EXCHANGE</span>
                    <h2 id="login-title">Welcome back</h2>
                    <p class="login-description">Log in or create an account to find your next good read.</p>
                </div>
                <?php if (!empty($databaseUnavailable)): ?>
                    <p class="login-error login-error-visible">The database is unavailable. Ask the administrator to check MySQL and the BookBai database setup.</p>
                <?php endif; ?>
                <div class="auth-tabs" role="tablist" aria-label="Account access">
                    <button class="auth-tab auth-tab-active" id="login-tab" type="button" role="tab" aria-selected="true" aria-controls="login-form">Log in</button>
                    <button class="auth-tab" id="register-tab" type="button" role="tab" aria-selected="false" aria-controls="register-form">Create account</button>
                </div>
                <form id="login-form" class="login-form">
                    <label class="login-field">Email
                        <span class="login-input-wrap">
                            <svg aria-hidden="true" viewBox="0 0 24 24"><rect x="3.5" y="5.5" width="17" height="13" rx="2"></rect><path d="m4.5 7 7.5 6 7.5-6"></path></svg>
                            <input name="email" type="email" required maxlength="120" autocomplete="email" placeholder="you@example.com">
                        </span>
                    </label>
                    <label class="login-field">Password
                        <span class="login-input-wrap">
                            <svg aria-hidden="true" viewBox="0 0 24 24"><rect x="4.5" y="10" width="15" height="10" rx="2"></rect><path d="M8 10V7a4 4 0 0 1 8 0v3M12 14v2"></path></svg>
                            <input name="password" type="password" required maxlength="72" autocomplete="current-password" placeholder="Enter your password">
                        </span>
                    </label>
                    <button class="button button-coral login-submit" type="submit"><span>Log in to BookBai</span><span aria-hidden="true">→</span></button>
                    <p class="login-help">Use the email address and password for your account.</p>
                    <p id="login-error" class="login-error" role="alert" hidden></p>
                </form>
                <form id="register-form" class="login-form" hidden>
                    <label class="login-field">Name
                        <span class="login-input-wrap">
                            <svg aria-hidden="true" viewBox="0 0 24 24"><circle cx="12" cy="8" r="3.5"></circle><path d="M5 20c.8-3.5 3.1-5.2 7-5.2s6.2 1.7 7 5.2"></path></svg>
                            <input name="name" type="text" required maxlength="80" autocomplete="name" placeholder="Your name">
                        </span>
                    </label>
                    <label class="login-field">Email
                        <span class="login-input-wrap">
                            <svg aria-hidden="true" viewBox="0 0 24 24"><rect x="3.5" y="5.5" width="17" height="13" rx="2"></rect><path d="m4.5 7 7.5 6 7.5-6"></path></svg>
                            <input name="email" type="email" required maxlength="120" autocomplete="email" placeholder="you@example.com">
                        </span>
                    </label>
                    <label class="login-field">Course / campus <span class="login-field-optional">(optional)</span>
                        <span class="login-input-wrap">
                            <svg aria-hidden="true" viewBox="0 0 24 24"><path d="M3 9 12 4l9 5-9 5-9-5Z"></path><path d="M7 11.2v5.3c2.8 2.1 7.2 2.1 10 0v-5.3M21 9v6"></path></svg>
                            <input name="course" type="text" maxlength="100" autocomplete="organization-title" placeholder="e.g. Arts & Sciences">
                        </span>
                    </label>
                    <label class="login-field">Password
                        <span class="login-input-wrap">
                            <svg aria-hidden="true" viewBox="0 0 24 24"><rect x="4.5" y="10" width="15" height="10" rx="2"></rect><path d="M8 10V7a4 4 0 0 1 8 0v3M12 14v2"></path></svg>
                            <input name="password" type="password" required minlength="8" maxlength="72" autocomplete="new-password" placeholder="At least 8 characters">
                        </span>
                    </label>
                    <button class="button button-coral login-submit" type="submit"><span>Create account</span><span aria-hidden="true">→</span></button>
                    <p class="login-help">New accounts start with student access. Use at least 8 characters for your password.</p>
                    <p id="register-error" class="login-error" role="alert" hidden></p>
                </form>
                <div class="login-access-note">
                    <span class="login-access-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24"><path d="M12 3.5 19 6v5.2c0 4.2-2.8 7.5-7 9.3-4.2-1.8-7-5.1-7-9.3V6l7-2.5Z"></path><path d="m9 12 2 2 4-4"></path></svg>
                    </span>
                    <span><strong> Welcome to BookBai</strong><br>Create an account to share books with your community.</span>
                </div>
                <p class="login-campus-note">Sibonga Community College <span>·</span> Read more. Waste less.</p>
            </div>
        </section>
    </main>
</body>
</html>
