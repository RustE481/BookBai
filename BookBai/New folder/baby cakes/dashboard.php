<?php
declare(strict_types=1);

require_once __DIR__ . DIRECTORY_SEPARATOR . 'auth.php';

if (!bookbai_current_user()) {
    header('Location: login.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#fffaf1">
    <meta name="description" content="Find, share, and exchange second-hand books with students on your campus.">
    <title>Browse books — BookBai</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="styles.css?v=bookbai-auth-1">
    <script src="app.js?v=bookbai-auth-1" defer></script>
</head>
<body class="dashboard-page">
    <header class="dashboard-header">
        <a class="brand" href="index.php" aria-label="BookBai home">
            <img src="images/Logo.png" alt="" class="brand-mark">
            <span>Book<span class="brand-accent">Bai</span></span>
        </a>
        <nav class="dashboard-nav app-navigation" aria-label="Dashboard navigation">
            <button class="dashboard-nav-active" type="button" data-view="browse">Explore books</button>
            <button type="button" data-view="saved">Saved <span class="saved-nav-count" id="saved-count">0</span></button>
            <button type="button" data-view="inbox">Messages <span class="saved-nav-count" id="inbox-count">0</span></button>
            <button type="button" data-view="mine">My listings</button>
            <button type="button" data-view="admin" id="admin-nav" hidden>Moderate</button>
        </nav>
        <div class="dashboard-account">
            <button class="button button-coral button-small" type="button" data-open="listing-dialog">＋ List a book</button>
            <button class="profile-button" id="profile-button" type="button" aria-label="Open your profile">S</button>
        </div>
    </header>

    <main class="dashboard-main">
        <div class="demo-notice" id="backend-notice"><span aria-hidden="true">✳</span> Create an account to list books, save favourites, and contact other students.</div>
        <section class="dashboard-welcome">
            <div>
                <span class="section-kicker">THE CAMPUS BOOKSHELF</span>
                <h1>Find your next <span>good thing.</span></h1>
                <p>Books looking for a new shelf, a new reader, or a new story to be part of.</p>
            </div>
            <div class="welcome-note"><span aria-hidden="true">✳</span><span>Every book has<br>a next chapter.</span></div>
        </section>

        <section class="browse-section" id="browse-section" aria-labelledby="browse-heading">
            <div class="browse-topline">
                <div>
                    <span class="section-kicker" id="view-kicker">PASS IT ON, FIND IT HERE</span>
                    <h2 id="browse-heading">Books for you <span class="book-count" id="book-count"></span></h2>
                </div>
                <label class="sort-control" id="sort-wrap">Sort by
                    <select id="sort-books" aria-label="Sort books">
                        <option value="featured">Featured</option>
                        <option value="price-low">Price: low to high</option>
                        <option value="price-high">Price: high to low</option>
                        <option value="title">Title: A to Z</option>
                    </select>
                </label>
            </div>

            <div class="book-browser" id="browse-layout">
                <aside class="filter-panel" aria-label="Book filters">
                    <div class="filter-heading"><h3>Find a book</h3><button type="button" id="clear-filters" class="clear-filters">Clear all</button></div>
                    <label class="search-field">
                        <span aria-hidden="true">⌕</span>
                        <input id="book-search" type="search" placeholder="Title, author, course, subject..." autocomplete="off">
                    </label>
                    <fieldset class="filter-group">
                        <legend>Looking for</legend>
                        <label class="filter-option"><input type="checkbox" name="type" value="For sale"><span>For sale</span></label>
                        <label class="filter-option"><input type="checkbox" name="type" value="Donation"><span>Donation</span></label>
                        <label class="filter-option"><input type="checkbox" name="type" value="Exchange"><span>Exchange</span></label>
                    </fieldset>
                    <label class="select-filter">Category
                        <select id="category-filter">
                            <option value="">All categories</option>
                            <option value="Textbook">Textbooks</option>
                            <option value="Fiction">Fiction</option>
                            <option value="Nonfiction">Nonfiction</option>
                            <option value="Reference">Reference</option>
                        </select>
                    </label>
                    <label class="select-filter">Condition
                        <select id="condition-filter">
                            <option value="">Any condition</option>
                            <option value="Like new">Like new</option>
                            <option value="Very good">Very good</option>
                            <option value="Good">Good</option>
                            <option value="Fair">Fair</option>
                        </select>
                    </label>
                    <label class="select-filter">Maximum price
                        <select id="price-filter">
                            <option value="">Any price</option>
                            <option value="10">Up to $10</option>
                            <option value="15">Up to $15</option>
                            <option value="25">Up to $25</option>
                            <option value="50">Up to $50</option>
                        </select>
                    </label>
                    <div class="filter-footnote"><span aria-hidden="true">♡</span> Every book deserves another reader.</div>
                </aside>

                <div class="books-area">
                    <div class="active-filters" id="active-filters" aria-live="polite"></div>
                    <div class="book-grid" id="book-grid"></div>
                    <div class="empty-state" id="empty-state" hidden>
                        <span aria-hidden="true">⌕</span>
                        <h3 id="empty-heading">No books found just yet</h3>
                        <p id="empty-copy">Try another search or clear a filter. Your next read is out there.</p>
                        <button type="button" class="button button-dark" id="empty-clear">Clear filters</button>
                    </div>
                </div>
            </div>
            <section class="moderation-panel" id="moderation-panel" hidden aria-labelledby="moderation-heading">
                <div class="moderation-heading">
                    <div><span class="section-kicker">COMMUNITY CARE</span><h2 id="moderation-heading">Listing moderation</h2></div>
                    <p>Oversee the campus book exchange, review submissions, and manage community accounts.</p>
                </div>
                <div class="admin-stat-grid" id="admin-stat-grid">
                    <article class="admin-stat-card"><span>Total listings</span><strong id="admin-stat-total">0</strong><small>Books in the database</small></article>
                    <article class="admin-stat-card admin-stat-pending"><span>Needs review</span><strong id="admin-stat-pending">0</strong><small>Awaiting approval</small></article>
                    <article class="admin-stat-card"><span>Published</span><strong id="admin-stat-published">0</strong><small>Visible to campus</small></article>
                    <article class="admin-stat-card"><span>Community</span><strong id="admin-stat-users">0</strong><small>Registered accounts</small></article>
                    <article class="admin-stat-card admin-stat-reports"><span>Open reports</span><strong id="admin-stat-reports">0</strong><small>Safety concerns</small></article>
                </div>
                <div class="admin-section-heading">
                    <div><span class="section-kicker">LISTING QUEUE</span><h3>Review books</h3></div>
                    <label class="admin-search">Search listings<input type="search" id="admin-book-search" placeholder="Title, author, or seller"></label>
                </div>
                <div class="admin-filter-tabs" id="admin-book-filters" role="group" aria-label="Filter listings">
                    <button class="admin-filter-active" type="button" data-admin-filter="all">All <span id="admin-filter-all">0</span></button>
                    <button type="button" data-admin-filter="pending">Pending <span id="admin-filter-pending">0</span></button>
                    <button type="button" data-admin-filter="published">Published <span id="admin-filter-published">0</span></button>
                    <button type="button" data-admin-filter="removed">Removed <span id="admin-filter-removed">0</span></button>
                </div>
                <div class="moderation-list" id="moderation-list"></div>
                <div class="admin-section-heading admin-users-heading">
                    <div><span class="section-kicker">SAFETY & TRUST</span><h3>Member reports</h3></div>
                    <label class="admin-search">Search reports<input type="search" id="admin-report-search" placeholder="Book, member, or message"></label>
                </div>
                <div class="admin-filter-tabs" id="admin-report-filters" role="group" aria-label="Filter member reports">
                    <button class="admin-filter-active" type="button" data-report-filter="open">Open <span id="admin-report-open-count">0</span></button>
                    <button type="button" data-report-filter="reviewed">Reviewed <span id="admin-report-reviewed-count">0</span></button>
                    <button type="button" data-report-filter="dismissed">Dismissed <span id="admin-report-dismissed-count">0</span></button>
                </div>
                <div class="admin-report-list" id="admin-report-list"></div>
                <div class="admin-section-heading admin-users-heading">
                    <div><span class="section-kicker">SCC COMMUNITY</span><h3>Accounts</h3></div>
                    <label class="admin-search">Search accounts<input type="search" id="admin-user-search" placeholder="Name, email, or role"></label>
                </div>
                <div class="admin-user-list" id="admin-user-list"></div>
            </section>
            <section class="moderation-panel" id="inbox-panel" hidden aria-labelledby="inbox-heading">
                <div class="moderation-heading">
                    <div><span class="section-kicker">STUDENT CONNECTIONS</span><h2 id="inbox-heading">Your messages</h2></div>
                    <p>Continue conversations with students about book listings.</p>
                </div>
                <div class="moderation-list" id="inbox-list"></div>
            </section>
        </section>

        <section class="list-book-callout" id="list-a-book">
            <div class="callout-flower" aria-hidden="true">✳</div>
            <div><span class="section-kicker">A book ready for its next reader?</span><h2>Make a little more shelf space.</h2><p>Sell it, donate it, or find a good swap.</p></div>
            <button class="button button-dark" type="button" data-open="listing-dialog">List a book <span aria-hidden="true">→</span></button>
        </section>
    </main>

    <footer class="site-footer dashboard-footer">
        <a class="brand brand-footer" href="index.php">
            <img src="images/Logo.png" alt="" class="brand-mark">
            <span>Book<span class="brand-accent">Bai</span></span>
        </a>
        <p>Pass a good thing on.</p>
        <a href="index.php">Back to home ↑</a>
    </footer>

    <dialog class="app-dialog" id="listing-dialog" aria-labelledby="listing-dialog-title">
        <form id="listing-form" class="dialog-form">
            <div class="dialog-heading"><div><span class="section-kicker">PASS IT ON</span><h2 id="listing-dialog-title">List a book</h2></div><button class="dialog-close" type="button" data-close aria-label="Close">×</button></div>
            <p class="dialog-intro">Add the details so another student can find their next good read.</p>
            <div class="form-grid">
                <label class="form-field form-span-two">Book title<input name="title" required maxlength="100" placeholder="e.g. Introduction to Biology"></label>
                <label class="form-field">Author<input name="author" required maxlength="80" placeholder="Author name"></label>
                <label class="form-field">Course / subject<input name="subject" required maxlength="80" placeholder="e.g. Biology 110"></label>
                <label class="form-field">Category<select name="category" required><option value="Textbook">Textbook</option><option value="Fiction">Fiction</option><option value="Nonfiction">Nonfiction</option><option value="Reference">Reference</option></select></label>
                <label class="form-field">Condition<select name="condition" required><option value="Like new">Like new</option><option value="Very good">Very good</option><option value="Good">Good</option><option value="Fair">Fair</option></select></label>
                <label class="form-field">Listing type<select name="type" id="listing-type" required><option value="For sale">For sale</option><option value="Donation">Donation</option><option value="Exchange">Exchange</option></select></label>
                <label class="form-field" id="price-field">Price ($)<input name="price" type="number" min="0" max="9999" step="0.01" value="10"></label>
                <label class="form-field form-span-two">Description<textarea name="description" required maxlength="500" rows="3" placeholder="Edition, notes, or anything a reader should know..."></textarea></label>
                <label class="form-field form-span-two">Book photo <span class="field-optional">(optional, max 1 MB)</span><input name="image" type="file" accept="image/*" id="listing-image"></label>
                <img class="image-preview" id="image-preview" alt="Selected book photo preview" hidden>
            </div>
            <div class="dialog-actions"><button class="button button-quiet" type="button" data-close>Cancel</button><button class="button button-coral" type="submit">Publish listing <span aria-hidden="true">→</span></button></div>
        </form>
    </dialog>

    <dialog class="app-dialog" id="account-dialog" aria-labelledby="account-dialog-title">
        <form id="account-form" class="dialog-form">
            <div class="dialog-heading"><div><span class="section-kicker">YOUR BOOKBAI PROFILE</span><h2 id="account-dialog-title">Account profile</h2></div><button class="dialog-close" type="button" data-close aria-label="Close">×</button></div>
            <p class="dialog-intro" id="account-intro">Update your account details and password here.</p>
            <div class="form-grid">
                <label class="form-field">Name<input name="name" required maxlength="80" placeholder="Your name"></label>
                <label class="form-field">Account email<input name="email" type="email" required maxlength="120" placeholder="you@example.com"></label>
                <label class="form-field">Course / campus<input name="course" maxlength="100" placeholder="e.g. Arts & Sciences"></label>
                <label class="form-field">Role<input name="role" type="text" value="" readonly aria-readonly="true"></label>
                <label class="form-field form-span-two">Change password <span class="field-optional">(leave blank to keep your current password)</span><input name="password" type="password" minlength="8" maxlength="72" autocomplete="new-password" placeholder="At least 8 characters"></label>
            </div>
            <div class="dialog-actions" id="profile-actions">
                <button class="button button-quiet" type="button" id="logout-button">Log out</button>
                <button class="button button-coral" type="submit">Save profile</button>
            </div>
        </form>
    </dialog>

    <dialog class="app-dialog" id="contact-dialog" aria-labelledby="contact-dialog-title">
        <form id="contact-form" class="dialog-form">
            <div class="dialog-heading"><div><span class="section-kicker" id="contact-kind">CONNECT WITH A STUDENT</span><h2 id="contact-dialog-title">Ask about this book</h2></div><button class="dialog-close" type="button" data-close aria-label="Close">×</button></div>
            <p class="dialog-intro" id="contact-book-info"></p>
            <div class="conversation-history" id="conversation-history" hidden aria-live="polite"></div>
            <label class="form-field">Your message<textarea name="message" id="contact-message" required maxlength="500" rows="4" placeholder="Hi! Is this book still available?"></textarea></label>
            <label class="form-field" id="exchange-book-field" hidden>Book you’d like to offer<input name="offer" maxlength="100" placeholder="Title of the book you can swap"></label>
            <div class="dialog-actions"><button class="button button-quiet" type="button" data-close>Cancel</button><button class="button button-coral" type="submit" id="contact-submit">Send message</button></div>
        </form>
    </dialog>

    <dialog class="app-dialog" id="report-dialog" aria-labelledby="report-dialog-title">
        <form id="report-form" class="dialog-form">
            <div class="dialog-heading"><div><span class="section-kicker">COMMUNITY SAFETY</span><h2 id="report-dialog-title">Report a message</h2></div><button class="dialog-close" type="button" data-close aria-label="Close">×</button></div>
            <p class="dialog-intro">Tell the SCC moderators what concerns you. Your report is private and only visible to administrators.</p>
            <label class="form-field">What is the concern?
                <select name="reason" required>
                    <option value="">Choose a reason</option>
                    <option value="scam">Possible scam or fraud</option>
                    <option value="offensive">Offensive or abusive content</option>
                    <option value="harassment">Harassment or unwanted contact</option>
                    <option value="inappropriate">Inappropriate content</option>
                    <option value="other">Other safety concern</option>
                </select>
            </label>
            <label class="form-field">Additional details <span class="field-optional">(optional)</span>
                <textarea name="details" maxlength="500" rows="4" placeholder="Share any context that may help the moderators review this report."></textarea>
            </label>
            <div class="dialog-actions"><button class="button button-quiet" type="button" data-close>Cancel</button><button class="button button-coral" type="submit">Send private report</button></div>
        </form>
    </dialog>

    <div class="toast-message" id="toast-message" role="status" aria-live="polite"></div>
</body>
</html>
