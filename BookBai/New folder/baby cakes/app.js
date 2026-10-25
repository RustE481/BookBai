(() => {
    "use strict";

    const grid = document.getElementById("book-grid");
    const searchInput = document.getElementById("book-search");
    const categoryFilter = document.getElementById("category-filter");
    const conditionFilter = document.getElementById("condition-filter");
    const priceFilter = document.getElementById("price-filter");
    const sortSelect = document.getElementById("sort-books");
    const toast = document.getElementById("toast-message");
    const views = Array.from(document.querySelectorAll("[data-view]"));
    const dialogs = Array.from(document.querySelectorAll("dialog"));
    let books = [];
    let saved = [];
    let inbox = [];
    let profile = null;
    let csrfToken = "";
    let isAdmin = false;
    let adminStats = null;
    let adminUsers = [];
    let adminReports = [];
    let adminFilter = "all";
    let reportFilter = "open";
    let activeReportMessageId = null;
    let currentView = "browse";
    let activeBookId = null;
    let activeConversation = null;
    let toastTimer;

    function escapeHtml(value) {
        return String(value == null ? "" : value).replace(/[&<>"']/g, character => ({
            "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;"
        })[character]);
    }

    function showToast(message) {
        toast.textContent = message;
        toast.classList.add("toast-visible");
        window.clearTimeout(toastTimer);
        toastTimer = window.setTimeout(() => toast.classList.remove("toast-visible"), 3800);
    }

    async function api(action, values, fileForm) {
        const options = { method: "POST", headers: { "X-CSRF-Token": csrfToken } };
        if (fileForm) {
            options.body = fileForm;
        } else {
            options.headers["Content-Type"] = "application/json";
            options.body = JSON.stringify(values || {});
        }
        const response = await fetch(`api.php?action=${encodeURIComponent(action)}`, options);
        let result;
        try {
            result = await response.json();
        } catch (error) {
            throw new Error("The server returned an invalid response. Check the PHP error log.");
        }
        if (!response.ok) throw new Error(result.error || "The request could not be completed.");
        return result;
    }

    async function refreshData() {
        const response = await fetch("api.php?action=bootstrap", { cache: "no-store" });
        const data = await response.json();
        if (!response.ok) throw new Error(data.error || "Could not load BookBai.");
        books = data.books;
        saved = data.saved.map(String);
        inbox = data.inbox;
        profile = data.user;
        isAdmin = data.isAdmin;
        adminStats = data.adminStats || null;
        adminUsers = data.adminUsers || [];
        adminReports = data.adminReports || [];
        csrfToken = data.csrfToken;
        document.getElementById("admin-nav").hidden = !isAdmin;
        document.getElementById("inbox-count").textContent = String(getConversations().length);
        document.getElementById("profile-button").textContent = profile ? initials(profile.name)[0] : "S";
        document.querySelector('#account-form [name="role"]').value = profile.role === "teacher"
            ? "Teacher"
            : profile.role === "admin" ? "Administrator" : "Student";
        document.getElementById("account-form").elements.password.value = "";
        document.getElementById("backend-notice").innerHTML = profile
            ? `<span aria-hidden="true">✳</span> Signed in as ${escapeHtml(profile.name)}. New book listings are reviewed before appearing publicly.`
            : '<span aria-hidden="true">✳</span> Create an account to list books, save favourites, and contact other students.';
        if (currentView === "admin" && !isAdmin) currentView = "browse";
        if (currentView === "admin") renderAdmin();
        updateBooks();
    }

    function initials(name) {
        return name.trim().split(/\s+/).slice(0, 2).map(part => part[0]).join("").toUpperCase() || "S";
    }

    function bookCard(book) {
        const savedBook = saved.includes(String(book.id));
        const cover = book.image
            ? `<div class="book-cover book-cover-photo" style="background-image:url('${escapeHtml(book.image)}')">`
            : `<div class="book-cover cover-atomic">`;
        const amount = book.type === "Donation" ? "Free" : `$${Number(book.price).toFixed(Number(book.price) % 1 ? 2 : 0)}`;
        const ownerControls = book.owner
            ? `<label class="owner-status">Listing status
                <select class="status-select" data-status-id="${escapeHtml(book.id)}" aria-label="Update status for ${escapeHtml(book.title)}">
                    ${["Available", "Sold", "Donated", "Exchanged", "Unavailable"].map(status => `<option${book.status === status ? " selected" : ""}>${status}</option>`).join("")}
                </select>
            </label>`
            : `<button class="contact-button" type="button" data-contact="${escapeHtml(book.id)}">${book.type === "Exchange" ? "Request swap" : book.type === "Donation" ? "Request book" : "Contact"} <span aria-hidden="true">↗</span></button>`;
        return `<article class="book-card${savedBook ? " book-card-saved" : ""}" data-id="${escapeHtml(book.id)}">
            ${cover}
                <span class="cover-edition">${escapeHtml(book.category)}</span>
                <span class="cover-title">${escapeHtml(book.title)}</span>
                <span class="cover-author">${escapeHtml(book.author)}</span>
                <span class="cover-art" aria-hidden="true"></span>
                <span class="listing-type">${escapeHtml(book.type)}</span>
                <button class="favorite-button" type="button" aria-label="${savedBook ? "Remove" : "Save"} ${escapeHtml(book.title)} ${savedBook ? "from" : "to"} saved books" aria-pressed="${savedBook}">${savedBook ? "♥" : "♡"}</button>
            </div>
            <div class="book-card-info">
                <div class="book-title-row"><div><h3>${escapeHtml(book.title)}</h3><p>${escapeHtml(book.author)}</p></div><strong class="book-price">${amount}</strong></div>
                <div class="book-meta"><span>${escapeHtml(book.subject)}</span><span>${escapeHtml(book.condition)}</span><span>${escapeHtml(book.category)}</span></div>
                ${!book.approved ? '<p class="moderation-status moderation-pending">Pending review</p>' : ""}
                <div class="seller-row"><span class="seller-avatar">${escapeHtml(initials(book.seller))}</span><span>${escapeHtml(book.seller)}</span>${ownerControls}</div>
            </div>
        </article>`;
    }

    function matchesCurrentView(book) {
        if (currentView === "saved") return saved.includes(String(book.id));
        if (currentView === "mine") return book.owner;
        return true;
    }

    function updateBooks() {
        const query = searchInput.value.trim().toLowerCase();
        const selectedTypes = Array.from(document.querySelectorAll('input[name="type"]:checked')).map(input => input.value);
        const category = categoryFilter.value;
        const condition = conditionFilter.value;
        const maximumPrice = priceFilter.value === "" ? Infinity : Number(priceFilter.value);
        const visibleBooks = books.filter(book => {
            if ((book.status !== "Available" && currentView !== "mine") || book.removed || !matchesCurrentView(book)) return false;
            if (!book.approved && !book.owner && !isAdmin) return false;
            const haystack = [book.title, book.author, book.subject, book.category, book.seller].join(" ").toLowerCase();
            return haystack.includes(query)
                && (!selectedTypes.length || selectedTypes.includes(book.type))
                && (!category || book.category === category)
                && (!condition || book.condition === condition)
                && Number(book.price) <= maximumPrice;
        });
        if (sortSelect.value === "price-low") visibleBooks.sort((a, b) => Number(a.price) - Number(b.price));
        if (sortSelect.value === "price-high") visibleBooks.sort((a, b) => Number(b.price) - Number(a.price));
        if (sortSelect.value === "title") visibleBooks.sort((a, b) => a.title.localeCompare(b.title));
        grid.innerHTML = visibleBooks.map(bookCard).join("");

        const activeFilters = [];
        if (query) activeFilters.push(`“${query}”`);
        if (selectedTypes.length) activeFilters.push(...selectedTypes);
        if (category) activeFilters.push(category);
        if (condition) activeFilters.push(condition);
        if (Number.isFinite(maximumPrice)) activeFilters.push(`Up to $${maximumPrice}`);
        document.getElementById("active-filters").textContent = activeFilters.length ? `Showing results for ${activeFilters.join(" · ")}` : "";
        document.getElementById("book-count").textContent = `(${visibleBooks.length})`;
        document.getElementById("empty-state").hidden = visibleBooks.length !== 0;
        document.getElementById("empty-heading").textContent = currentView === "mine" ? "You haven’t listed a book yet" : "No books found just yet";
        document.getElementById("empty-copy").textContent = currentView === "mine"
            ? "List a book to help another student find their next good read."
            : "Try another search or clear a filter. Your next read is out there.";
        document.getElementById("empty-clear").hidden = currentView === "mine";
        document.getElementById("saved-count").textContent = String(saved.length);
        document.getElementById("inbox-count").textContent = String(getConversations().length);
    }

    function clearFilters() {
        searchInput.value = "";
        categoryFilter.value = "";
        conditionFilter.value = "";
        priceFilter.value = "";
        document.querySelectorAll('input[name="type"]').forEach(input => { input.checked = false; });
        updateBooks();
    }

    function setView(view) {
        if (view === "admin" && !isAdmin) {
            showToast("Administrator access is required.");
            return;
        }
        currentView = view;
        views.forEach(button => button.classList.toggle("dashboard-nav-active", button.dataset.view === view));
        const isAdminView = view === "admin";
        const isBrowse = view !== "admin" && view !== "inbox";
        document.getElementById("browse-layout").hidden = !isBrowse;
        document.getElementById("sort-wrap").hidden = !isBrowse;
        document.getElementById("moderation-panel").hidden = !isAdminView;
        document.getElementById("inbox-panel").hidden = view !== "inbox";
        document.getElementById("view-kicker").textContent = ({
            browse: "PASS IT ON, FIND IT HERE", saved: "YOUR LITTLE READING LIST",
            mine: "YOUR BOOKS, YOUR WAY", admin: "COMMUNITY CARE", inbox: "STUDENT CONNECTIONS"
        })[view];
        document.getElementById("browse-heading").innerHTML = ({
            browse: 'Books for you <span class="book-count" id="book-count"></span>',
            saved: 'Saved books <span class="book-count" id="book-count"></span>',
            mine: 'My listings <span class="book-count" id="book-count"></span>',
            admin: 'Moderate listings <span class="book-count" id="book-count"></span>',
            inbox: 'Your messages <span class="book-count" id="book-count"></span>'
        })[view];
        if (isAdminView) renderAdmin();
        if (view === "inbox") renderInbox();
        updateBooks();
        document.getElementById("browse-section").scrollIntoView({ behavior: "smooth", block: "start" });
    }

    function renderAdmin() {
        const stats = adminStats || { total: 0, pending: 0, published: 0, removed: 0 };
        document.getElementById("admin-stat-total").textContent = String(stats.total);
        document.getElementById("admin-stat-pending").textContent = String(stats.pending);
        document.getElementById("admin-stat-published").textContent = String(stats.published);
        document.getElementById("admin-stat-users").textContent = String(adminUsers.length);
        document.getElementById("admin-stat-reports").textContent = String(stats.open_reports || 0);
        document.getElementById("admin-filter-all").textContent = String(stats.total);
        document.getElementById("admin-filter-pending").textContent = String(stats.pending);
        document.getElementById("admin-filter-published").textContent = String(stats.published);
        document.getElementById("admin-filter-removed").textContent = String(stats.removed);
        renderModeration();
        renderAdminReports();
        renderAdminUsers();
    }

    function renderModeration() {
        const panel = document.getElementById("moderation-list");
        const query = document.getElementById("admin-book-search").value.trim().toLowerCase();
        const filteredBooks = books.filter(book => {
            if (adminFilter === "pending" && (book.approved || book.removed)) return false;
            if (adminFilter === "published" && (!book.approved || book.removed)) return false;
            if (adminFilter === "removed" && !book.removed) return false;
            return [book.title, book.author, book.subject, book.seller].join(" ").toLowerCase().includes(query);
        });
        if (!filteredBooks.length) {
            panel.innerHTML = '<p class="moderation-empty">No database listings match this filter.</p>';
            return;
        }
        panel.innerHTML = filteredBooks.map(book => {
            const status = book.removed ? "Removed" : book.approved ? "Published" : "Pending review";
            const statusClass = book.removed ? " moderation-removed" : book.approved ? "" : " moderation-pending";
            const amount = book.type === "Donation" ? "Free" : `$${Number(book.price).toFixed(2)}`;
            return `<article class="moderation-item admin-moderation-item">
            <div class="admin-book-summary">
                <strong>${escapeHtml(book.title)}</strong>
                <span>${escapeHtml(book.author)} · ${escapeHtml(book.subject)} · ${escapeHtml(book.seller)}</span>
                <small>${escapeHtml(book.category)} · ${escapeHtml(book.condition)} · ${escapeHtml(book.type)} · ${amount}</small>
                ${book.description ? `<p>${escapeHtml(book.description)}</p>` : ""}
            </div>
            <span class="moderation-status${statusClass}">${status}</span>
            <div class="moderation-actions">
                ${!book.approved && !book.removed ? `<button type="button" data-moderate="approve" data-id="${escapeHtml(book.id)}">Approve</button>` : ""}
                ${book.removed ? `<button type="button" data-moderate="restore" data-id="${escapeHtml(book.id)}">Restore</button>` : ""}
                ${!book.removed ? `<button class="moderation-remove" type="button" data-moderate="remove" data-id="${escapeHtml(book.id)}">Remove</button>` : ""}
            </div>
        </article>`;
        }).join("");
    }

    function renderAdminUsers() {
        const panel = document.getElementById("admin-user-list");
        const query = document.getElementById("admin-user-search").value.trim().toLowerCase();
        const users = adminUsers.filter(user => [
            user.name, user.email, user.course, user.role
        ].join(" ").toLowerCase().includes(query));
        if (!users.length) {
            panel.innerHTML = '<p class="moderation-empty">No community accounts match this search.</p>';
            return;
        }
        panel.innerHTML = users.map(user => `<article class="admin-user-row">
            <span class="seller-avatar">${escapeHtml(initials(user.name))}</span>
            <div class="admin-user-details"><strong>${escapeHtml(user.name)}</strong><span>${escapeHtml(user.email)}</span><small>${escapeHtml(user.course || "No course listed")}</small></div>
            <span class="admin-role-badge">${escapeHtml(user.role)}</span>
            <span class="admin-user-listings">${escapeHtml(user.listing_count)} listings</span>
        </article>`).join("");
    }

    function renderAdminReports() {
        const panel = document.getElementById("admin-report-list");
        const counts = {
            open: adminReports.filter(report => report.status === "open").length,
            reviewed: adminReports.filter(report => report.status === "reviewed").length,
            dismissed: adminReports.filter(report => report.status === "dismissed").length
        };
        document.getElementById("admin-report-open-count").textContent = String(counts.open);
        document.getElementById("admin-report-reviewed-count").textContent = String(counts.reviewed);
        document.getElementById("admin-report-dismissed-count").textContent = String(counts.dismissed);
        const query = document.getElementById("admin-report-search").value.trim().toLowerCase();
        const reports = adminReports.filter(report => {
            if (report.status !== reportFilter) return false;
            return [
                report.book_title, report.reporter, report.reported_user,
                report.reported_message, report.details, report.reason
            ].join(" ").toLowerCase().includes(query);
        });
        if (!reports.length) {
            panel.innerHTML = `<p class="moderation-empty">No ${reportFilter} reports match this search.</p>`;
            return;
        }
        panel.innerHTML = reports.map(report => `<article class="admin-report-item">
            <div class="admin-report-topline">
                <span class="admin-report-reason">${escapeHtml(report.reason.replaceAll("_", " "))}</span>
                <span class="moderation-status${report.status === "open" ? " moderation-pending" : ""}">${escapeHtml(report.status)}</span>
                <time>${escapeHtml(new Date(report.created_at.replace(" ", "T")).toLocaleString())}</time>
            </div>
            <div class="admin-report-context">
                <strong>${escapeHtml(report.book_title || "Book listing unavailable")}</strong>
                <span>Reported by ${escapeHtml(report.reporter)}${report.reported_user ? ` · Message from ${escapeHtml(report.reported_user)}` : ""}</span>
                ${report.reported_message ? `<blockquote>${escapeHtml(report.reported_message)}</blockquote>` : ""}
                ${report.details ? `<p>${escapeHtml(report.details)}</p>` : ""}
            </div>
            ${report.status === "open" ? `<div class="moderation-actions">
                <button type="button" data-report-action="reviewed" data-id="${escapeHtml(report.id)}">Mark reviewed</button>
                <button type="button" data-report-action="dismissed" data-id="${escapeHtml(report.id)}">Dismiss</button>
                ${report.book_id ? `<button class="moderation-remove" type="button" data-report-action="remove_listing" data-id="${escapeHtml(report.id)}">Remove listing</button>` : ""}
            </div>` : ""}
        </article>`).join("");
    }

    function renderInbox() {
        const panel = document.getElementById("inbox-list");
        const conversations = getConversations().sort((first, second) =>
            new Date(second.latest.created_at.replace(" ", "T")) - new Date(first.latest.created_at.replace(" ", "T"))
        );
        if (!conversations.length) {
            panel.innerHTML = '<p class="moderation-empty">No conversations yet. Contact a book owner to start one.</p>';
            return;
        }
        panel.innerHTML = conversations.map(conversation => `<article class="moderation-item conversation-item">
            <div><strong>${escapeHtml(conversation.bookTitle)} · ${escapeHtml(conversation.otherName)}</strong>
                <span>${conversation.messages.length} message${conversation.messages.length === 1 ? "" : "s"}</span>
                <p>${escapeHtml(conversation.latest.message)}</p>
                <span>${escapeHtml(new Date(conversation.latest.created_at.replace(" ", "T")).toLocaleString())}</span>
            </div>
            <div class="moderation-actions"><button type="button" data-conversation-book="${escapeHtml(conversation.bookId)}" data-conversation-user="${escapeHtml(conversation.otherId)}">Open conversation</button></div>
        </article>`).join("");
    }

    function getConversations() {
        const grouped = new Map();
        inbox.forEach(message => {
            const sentByCurrentUser = String(message.sender_id) === String(profile.id);
            const otherId = sentByCurrentUser ? message.recipient_id : message.sender_id;
            const otherName = sentByCurrentUser ? message.recipient : message.sender;
            const key = `${message.book_id}:${otherId}`;
            if (!grouped.has(key)) {
                grouped.set(key, {
                    bookId: message.book_id,
                    bookTitle: message.book_title,
                    otherId,
                    otherName,
                    messages: []
                });
            }
            grouped.get(key).messages.push(message);
        });
        return Array.from(grouped.values()).map(conversation => ({
            ...conversation,
            latest: conversation.messages[conversation.messages.length - 1]
        }));
    }

    function openConversation(bookId, otherId) {
        const conversation = getConversations().find(item =>
            String(item.bookId) === String(bookId) && String(item.otherId) === String(otherId)
        );
        if (!conversation) return;
        activeBookId = conversation.bookId;
        activeConversation = { bookId: conversation.bookId, recipientId: conversation.otherId };
        document.getElementById("contact-kind").textContent = "BOOKBAI CONVERSATION";
        document.getElementById("contact-dialog-title").textContent = conversation.otherName;
        document.getElementById("contact-book-info").textContent =
            conversation.bookTitle;
        document.getElementById("conversation-history").innerHTML = conversation.messages.map(message => {
            const sentByCurrentUser = String(message.sender_id) === String(profile.id);
            return `<article class="conversation-message${sentByCurrentUser ? " conversation-message-own" : ""}">
                <div><strong>${sentByCurrentUser ? "You" : escapeHtml(conversation.otherName)}</strong>
                    <time>${escapeHtml(new Date(message.created_at.replace(" ", "T")).toLocaleString())}</time></div>
                <p>${escapeHtml(message.message)}</p>
                ${message.offered_book ? `<span>Swap offer: ${escapeHtml(message.offered_book)}</span>` : ""}
                ${!sentByCurrentUser ? `<button type="button" data-report-message="${escapeHtml(message.id)}">Report message</button>` : ""}
            </article>`;
        }).join("");
        document.getElementById("conversation-history").hidden = false;
        document.getElementById("contact-message").value = "";
        document.getElementById("exchange-book-field").hidden = true;
        document.getElementById("contact-submit").textContent = "Send reply";
        openDialog("contact-dialog");
        document.getElementById("conversation-history").scrollTop =
            document.getElementById("conversation-history").scrollHeight;
    }

    function openDialog(id) {
        if (id === "account-dialog" && profile) {
            const form = document.getElementById("account-form");
            form.elements.name.value = profile.name || "";
            form.elements.email.value = profile.email || "";
            form.elements.course.value = profile.course || "";
            form.elements.password.value = "";
        }
        document.getElementById(id).showModal();
    }

    document.querySelectorAll("[data-open]").forEach(button => button.addEventListener("click", () => {
        if (button.dataset.open === "listing-dialog" && !profile) {
            openDialog("account-dialog");
            showToast("Log in or create an account before listing a book.");
            return;
        }
        openDialog(button.dataset.open);
    }));
    document.querySelectorAll("[data-close]").forEach(button => button.addEventListener("click", () => button.closest("dialog").close()));
    dialogs.forEach(dialog => dialog.addEventListener("click", event => {
        if (event.target === dialog) dialog.close();
    }));
    document.getElementById("profile-button").addEventListener("click", () => openDialog("account-dialog"));
    views.forEach(button => button.addEventListener("click", () => setView(button.dataset.view)));
    [searchInput, categoryFilter, conditionFilter, priceFilter].forEach(control => control.addEventListener("input", updateBooks));
    document.querySelectorAll('input[name="type"]').forEach(input => input.addEventListener("change", updateBooks));
    sortSelect.addEventListener("change", updateBooks);
    document.getElementById("clear-filters").addEventListener("click", clearFilters);
    document.getElementById("empty-clear").addEventListener("click", clearFilters);
    document.getElementById("admin-book-search").addEventListener("input", renderModeration);
    document.getElementById("admin-user-search").addEventListener("input", renderAdminUsers);
    document.getElementById("admin-report-search").addEventListener("input", renderAdminReports);
    document.getElementById("admin-book-filters").addEventListener("click", event => {
        const button = event.target.closest("[data-admin-filter]");
        if (!button) return;
        adminFilter = button.dataset.adminFilter;
        document.querySelectorAll("[data-admin-filter]").forEach(filterButton => {
            filterButton.classList.toggle("admin-filter-active", filterButton === button);
        });
        renderModeration();
    });
    document.getElementById("admin-report-filters").addEventListener("click", event => {
        const button = event.target.closest("[data-report-filter]");
        if (!button) return;
        reportFilter = button.dataset.reportFilter;
        document.querySelectorAll("[data-report-filter]").forEach(filterButton => {
            filterButton.classList.toggle("admin-filter-active", filterButton === button);
        });
        renderAdminReports();
    });

    grid.addEventListener("click", async event => {
        const favorite = event.target.closest(".favorite-button");
        const contact = event.target.closest("[data-contact]");
        if (favorite) {
            if (!profile) {
                openDialog("account-dialog");
                showToast("Log in to save books.");
                return;
            }
            try {
                await api("save", { bookId: favorite.closest(".book-card").dataset.id });
                await refreshData();
            } catch (error) {
                showToast(error.message);
            }
        }
        if (contact) {
            if (!profile) {
                openDialog("account-dialog");
                showToast("Log in to contact a book owner.");
                return;
            }
            const book = books.find(item => String(item.id) === contact.dataset.contact);
            if (!book) return;
            activeBookId = book.id;
            activeConversation = null;
            document.getElementById("conversation-history").hidden = true;
            const exchange = book.type === "Exchange";
            document.getElementById("contact-kind").textContent = exchange ? "MAKE A GOOD SWAP" : "CONNECT WITH A STUDENT";
            document.getElementById("contact-dialog-title").textContent = exchange ? "Request an exchange" : book.type === "Donation" ? "Request this donated book" : "Ask about this book";
            document.getElementById("contact-book-info").textContent = `${book.title} by ${book.author} · listed by ${book.seller}`;
            document.getElementById("contact-message").value = exchange ? "Hi! I’m interested in swapping books with you." : "Hi! Is this book still available?";
            document.getElementById("exchange-book-field").hidden = !exchange;
            document.getElementById("contact-submit").textContent = exchange ? "Send exchange request" : "Send message";
            openDialog("contact-dialog");
        }
    });

    grid.addEventListener("change", async event => {
        const select = event.target.closest("[data-status-id]");
        if (!select) return;
        try {
            await api("status", { bookId: select.dataset.statusId, status: select.value });
            await refreshData();
            showToast("Listing status updated.");
        } catch (error) {
            showToast(error.message);
            await refreshData();
        }
    });

    document.getElementById("moderation-list").addEventListener("click", async event => {
        const action = event.target.closest("[data-moderate]");
        if (!action) return;
        try {
            await api("moderate", { bookId: action.dataset.id, decision: action.dataset.moderate });
            await refreshData();
            showToast(action.dataset.moderate === "approve"
                ? "Listing approved."
                : action.dataset.moderate === "restore" ? "Listing restored." : "Listing removed from the marketplace.");
        } catch (error) {
            showToast(error.message);
        }
    });

    document.getElementById("inbox-list").addEventListener("click", event => {
        const conversation = event.target.closest("[data-conversation-book]");
        if (conversation) {
            openConversation(conversation.dataset.conversationBook, conversation.dataset.conversationUser);
        }
    });

    document.getElementById("conversation-history").addEventListener("click", event => {
        const button = event.target.closest("[data-report-message]");
        if (!button) return;
        activeReportMessageId = button.dataset.reportMessage;
        document.getElementById("report-form").reset();
        openDialog("report-dialog");
    });

    document.getElementById("report-form").addEventListener("submit", async event => {
        event.preventDefault();
        const form = event.currentTarget;
        try {
            const result = await api("report-message", {
                messageId: activeReportMessageId,
                reason: form.elements.reason.value,
                details: form.elements.details.value.trim()
            });
            form.closest("dialog").close();
            showToast(result.message);
        } catch (error) {
            showToast(error.message);
        }
    });

    document.getElementById("admin-report-list").addEventListener("click", async event => {
        const button = event.target.closest("[data-report-action]");
        if (!button) return;
        try {
            const result = await api("report-action", {
                reportId: button.dataset.id,
                decision: button.dataset.reportAction
            });
            await refreshData();
            showToast(result.message);
        } catch (error) {
            showToast(error.message);
        }
    });

    document.getElementById("account-form").addEventListener("submit", async event => {
        event.preventDefault();
        const form = event.currentTarget;
        const values = {
            name: form.elements.name.value.trim(),
            email: form.elements.email.value.trim(),
            course: form.elements.course.value.trim(),
            password: form.elements.password.value
        };
        try {
            await api("profile", values);
            await refreshData();
            form.closest("dialog").close();
            showToast("Your profile has been updated.");
        } catch (error) {
            showToast(error.message);
        }
    });

    document.getElementById("logout-button").addEventListener("click", async () => {
        try {
            await api("logout", {});
            window.location.replace("login.php");
        } catch (error) {
            showToast(error.message);
        }
    });

    document.getElementById("listing-type").addEventListener("change", event => {
        const donation = event.target.value === "Donation";
        document.getElementById("price-field").hidden = donation;
        document.getElementById("listing-form").elements.price.required = !donation;
    });

    document.getElementById("listing-image").addEventListener("change", event => {
        const file = event.target.files[0];
        const preview = document.getElementById("image-preview");
        if (!file) {
            preview.hidden = true;
            preview.removeAttribute("src");
            return;
        }
        if (!file.type.startsWith("image/") || file.size > 1024 * 1024) {
            event.target.value = "";
            preview.hidden = true;
            showToast("Choose an image smaller than 1 MB.");
            return;
        }
        const reader = new FileReader();
        reader.addEventListener("load", () => {
            preview.src = reader.result;
            preview.hidden = false;
        });
        reader.addEventListener("error", () => showToast("The selected image could not be read."));
        reader.readAsDataURL(file);
    });

    document.getElementById("listing-form").addEventListener("submit", async event => {
        event.preventDefault();
        const form = event.currentTarget;
        const values = new FormData(form);
        try {
            await api("listing", null, values);
            form.reset();
            form.elements.price.value = 10;
            document.getElementById("price-field").hidden = false;
            form.elements.price.required = true;
            document.getElementById("image-preview").hidden = true;
            document.getElementById("listing-dialog").close();
            await refreshData();
            setView("mine");
            showToast("Your book listing was submitted for moderator review.");
        } catch (error) {
            showToast(error.message);
        }
    });

    document.getElementById("contact-form").addEventListener("submit", async event => {
        event.preventDefault();
        const form = event.currentTarget;
        try {
            if (activeConversation) {
                await api("reply", {
                    bookId: activeConversation.bookId,
                    recipientId: activeConversation.recipientId,
                    message: form.elements.message.value.trim()
                });
                await refreshData();
                openConversation(activeConversation.bookId, activeConversation.recipientId);
                showToast("Your reply has been sent.");
            } else {
                await api("message", {
                    bookId: activeBookId,
                    message: form.elements.message.value.trim(),
                    offer: form.elements.offer.value.trim()
                });
                form.closest("dialog").close();
                form.reset();
                showToast("Your message has been sent to the book owner.");
                await refreshData();
            }
        } catch (error) {
            showToast(error.message);
        }
    });

    refreshData().catch(error => {
        console.error("Unable to load BookBai data.", error);
        showToast(error.message);
        document.getElementById("empty-heading").textContent = "Could not load the marketplace";
        document.getElementById("empty-copy").textContent = "Check that MySQL is running and database.sql has been imported in phpMyAdmin.";
        document.getElementById("empty-state").hidden = false;
    });
})();
