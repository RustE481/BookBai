<?php
declare(strict_types=1);

require_once __DIR__ . DIRECTORY_SEPARATOR . 'auth.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function respond(array $data, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function request_data(): array
{
    $input = file_get_contents('php://input');
    $data = json_decode($input ?: '', true);
    return is_array($data) ? $data : $_POST;
}

function require_user(): int
{
    $user = bookbai_current_user();
    if (!$user) {
        respond(['error' => 'Please log in to continue.'], 401);
    }
    return (int) $user['id'];
}

function require_admin(PDO $pdo): void
{
    require_user();
    $statement = $pdo->prepare('SELECT role FROM users WHERE id = ?');
    $statement->execute([$_SESSION['user_id']]);
    if ($statement->fetchColumn() !== 'admin') {
        respond(['error' => 'Administrator access is required.'], 403);
    }
}

function require_csrf(): void
{
    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        respond(['error' => 'Your session has expired. Refresh the page and try again.'], 403);
    }
}

function required_text(array $data, string $key, int $maxLength): string
{
    $value = trim((string) ($data[$key] ?? ''));
    if ($value === '' || mb_strlen($value) > $maxLength) {
        respond(['error' => ucfirst($key) . ' is required and must be at most ' . $maxLength . ' characters.'], 422);
    }
    return $value;
}

try {
    $pdo = bookbai_database();
    $action = $_GET['action'] ?? '';
    $data = request_data();

    if ($action === 'csrf' && $_SERVER['REQUEST_METHOD'] === 'GET') {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        respond(['csrfToken' => $_SESSION['csrf_token']]);
    }

    if ($action === 'bootstrap' && $_SERVER['REQUEST_METHOD'] === 'GET') {
        $user = bookbai_current_user();
        if (!$user) {
            respond(['error' => 'Please log in to continue.'], 401);
        }
        $userId = (int) $user['id'];
        $isAdmin = $user['role'] === 'admin';

        $sql = 'SELECT b.id, b.title, b.author, b.subject, b.category, b.`condition`, b.price,
                       b.type, b.description, b.image, b.status, b.approved, b.removed,
                       u.name AS seller, b.user_id
                FROM books b JOIN users u ON u.id = b.user_id';
        if (!$isAdmin) {
            $sql .= ' WHERE b.removed = 0 AND (b.approved = 1 OR b.user_id = :user_id)';
        }
        $sql .= ' ORDER BY b.created_at DESC';
        $statement = $pdo->prepare($sql);
        if (!$isAdmin) {
            $statement->execute(['user_id' => $userId]);
        } else {
            $statement->execute();
        }
        $books = array_map(static function (array $book) use ($userId): array {
            $book['price'] = (float) $book['price'];
            $book['approved'] = (bool) $book['approved'];
            $book['removed'] = (bool) $book['removed'];
            $book['owner'] = $userId > 0 && (int) $book['user_id'] === $userId;
            unset($book['user_id']);
            return $book;
        }, $statement->fetchAll());

        $statement = $pdo->prepare('SELECT book_id FROM saved_books WHERE user_id = ?');
        $statement->execute([$userId]);
        $saved = array_map('intval', $statement->fetchAll(PDO::FETCH_COLUMN));

        $statement = $pdo->prepare(
            'SELECT m.id, m.book_id, m.sender_id, m.recipient_id, m.message, m.offered_book, m.created_at,
                    sender.name AS sender,
                    recipient.name AS recipient,
                    b.title AS book_title
             FROM messages m
             JOIN users sender ON sender.id = m.sender_id
             JOIN users recipient ON recipient.id = m.recipient_id
             JOIN books b ON b.id = m.book_id
             WHERE m.recipient_id = ? OR m.sender_id = ?
             ORDER BY m.created_at ASC, m.id ASC'
        );
        $statement->execute([$userId, $userId]);
        $inbox = $statement->fetchAll();
        $response = [
            'books' => $books,
            'saved' => $saved,
            'inbox' => $inbox,
            'user' => [
                'id' => $user['id'],
                'name' => $user['name'],
                'email' => $user['email'],
                'course' => $user['course'],
                'role' => $user['role'],
            ],
            'isAdmin' => $isAdmin,
            'csrfToken' => $_SESSION['csrf_token'],
        ];
        if ($isAdmin) {
            $statement = $pdo->query(
                'SELECT COUNT(*) AS total,
                        COALESCE(SUM(approved = 0 AND removed = 0), 0) AS pending,
                        COALESCE(SUM(approved = 1 AND removed = 0), 0) AS published,
                        COALESCE(SUM(removed = 1), 0) AS removed
                 FROM books'
            );
            $response['adminStats'] = $statement->fetch();
            $statement = $pdo->query('SELECT COUNT(*) FROM reports WHERE status = "open"');
            $response['adminStats']['open_reports'] = (int) $statement->fetchColumn();

            $statement = $pdo->query(
                'SELECT u.id, u.name, u.email, u.course, u.role, u.created_at,
                        COUNT(b.id) AS listing_count
                 FROM users u
                 LEFT JOIN books b ON b.user_id = u.id
                 GROUP BY u.id, u.name, u.email, u.course, u.role, u.created_at
                 ORDER BY u.created_at DESC'
            );
            $response['adminUsers'] = $statement->fetchAll();

            $statement = $pdo->query(
                'SELECT r.id, r.message_id, r.book_id, r.reason, r.details, r.status, r.created_at,
                        reporter.name AS reporter,
                        reported_user.name AS reported_user, b.title AS book_title,
                        m.message AS reported_message
                 FROM reports r
                 JOIN users reporter ON reporter.id = r.reporter_id
                 LEFT JOIN users reported_user ON reported_user.id = r.reported_user_id
                 LEFT JOIN books b ON b.id = r.book_id
                 LEFT JOIN messages m ON m.id = r.message_id
                 ORDER BY FIELD(r.status, "open", "reviewed", "dismissed"), r.created_at DESC
                 LIMIT 200'
            );
            $response['adminReports'] = $statement->fetchAll();
        }
        respond($response);
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        respond(['error' => 'Unsupported request method.'], 405);
    }
    require_csrf();

    if ($action === 'login') {
        $email = strtolower(trim((string) ($data['email'] ?? '')));
        $password = (string) ($data['password'] ?? '');
        if (!$email || strlen($email) > 120 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            respond(['error' => 'Enter a valid email address.'], 422);
        }
        $statement = $pdo->prepare('SELECT id, password_hash FROM users WHERE email = ?');
        $statement->execute([$email]);
        $user = $statement->fetch();
        $passwordMatches = $user && password_verify($password, $user['password_hash']);
        if (
            $user
            && !$passwordMatches
            && $password !== ''
            && password_get_info($user['password_hash'])['algoName'] === 'unknown'
            && hash_equals($user['password_hash'], $password)
        ) {
            $passwordMatches = true;
            $statement = $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
            $statement->execute([password_hash($password, PASSWORD_DEFAULT), $user['id']]);
        }
        if (!$passwordMatches) {
            respond(['error' => 'Email or password is incorrect.'], 401);
        }
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int) $user['id'];
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        respond(['message' => 'You are now logged in.']);
    }

    if ($action === 'register') {
        $name = required_text($data, 'name', 80);
        $email = strtolower(trim((string) ($data['email'] ?? '')));
        $course = trim((string) ($data['course'] ?? ''));
        $password = (string) ($data['password'] ?? '');
        if (!$email || strlen($email) > 120 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            respond(['error' => 'Enter a valid email address.'], 422);
        }
        if (mb_strlen($course) > 100) {
            respond(['error' => 'Course / campus must be at most 100 characters.'], 422);
        }
        if (strlen($password) < 8 || strlen($password) > 72) {
            respond(['error' => 'Your password must be between 8 and 72 characters.'], 422);
        }
        $statement = $pdo->prepare('SELECT id FROM users WHERE email = ?');
        $statement->execute([$email]);
        if ($statement->fetchColumn()) {
            respond(['error' => 'An account with that email already exists. Log in or use another email.'], 409);
        }
        $statement = $pdo->prepare(
            'INSERT INTO users (name, email, password_hash, course) VALUES (?, ?, ?, ?)'
        );
        try {
            $statement->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT), $course]);
        } catch (PDOException $error) {
            if ($error->getCode() === '23000') {
                respond(['error' => 'An account with that email already exists. Log in or use another email.'], 409);
            }
            throw $error;
        }
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int) $pdo->lastInsertId();
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        respond(['message' => 'Your account has been created.']);
    }

    if ($action === 'logout') {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }
        session_destroy();
        respond(['message' => 'You have been logged out.']);
    }

    if ($action === 'profile') {
        $userId = require_user();
        $name = required_text($data, 'name', 80);
        $email = strtolower(trim((string) ($data['email'] ?? '')));
        $course = trim((string) ($data['course'] ?? ''));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 120 || mb_strlen($course) > 100) {
            respond(['error' => 'Enter a valid email and a course / campus under 100 characters.'], 422);
        }
        $statement = $pdo->prepare('SELECT id FROM users WHERE email = ? AND id <> ?');
        $statement->execute([$email, $userId]);
        if ($statement->fetchColumn()) {
            respond(['error' => 'That email address is already in use.'], 409);
        }
        $password = (string) ($data['password'] ?? '');
        if ($password !== '' && (strlen($password) < 8 || strlen($password) > 72)) {
            respond(['error' => 'A new password must be between 8 and 72 characters.'], 422);
        }
        try {
            if ($password !== '') {
                $statement = $pdo->prepare('UPDATE users SET name = ?, email = ?, course = ?, password_hash = ? WHERE id = ?');
                $statement->execute([$name, $email, $course, password_hash($password, PASSWORD_DEFAULT), $userId]);
            } else {
                $statement = $pdo->prepare('UPDATE users SET name = ?, email = ?, course = ? WHERE id = ?');
                $statement->execute([$name, $email, $course, $userId]);
            }
        } catch (PDOException $error) {
            if ($error->getCode() === '23000') {
                respond(['error' => 'That email address is already in use.'], 409);
            }
            throw $error;
        }
        respond(['message' => 'Your profile has been updated.']);
    }

    if ($action === 'save') {
        $userId = require_user();
        $bookId = filter_var($data['bookId'] ?? null, FILTER_VALIDATE_INT);
        if (!$bookId) {
            respond(['error' => 'Invalid book.'], 422);
        }
        $statement = $pdo->prepare('SELECT id FROM books WHERE id = ? AND removed = 0');
        $statement->execute([$bookId]);
        if (!$statement->fetchColumn()) {
            respond(['error' => 'That book is no longer available.'], 404);
        }
        $statement = $pdo->prepare('SELECT id FROM saved_books WHERE user_id = ? AND book_id = ?');
        $statement->execute([$userId, $bookId]);
        if ($statement->fetchColumn()) {
            $statement = $pdo->prepare('DELETE FROM saved_books WHERE user_id = ? AND book_id = ?');
            $statement->execute([$userId, $bookId]);
            respond(['saved' => false]);
        }
        $statement = $pdo->prepare('INSERT INTO saved_books (user_id, book_id) VALUES (?, ?)');
        $statement->execute([$userId, $bookId]);
        respond(['saved' => true]);
    }

    if ($action === 'listing') {
        $userId = require_user();
        $title = required_text($data, 'title', 100);
        $author = required_text($data, 'author', 80);
        $subject = required_text($data, 'subject', 80);
        $description = required_text($data, 'description', 500);
        $category = (string) ($data['category'] ?? '');
        $condition = (string) ($data['condition'] ?? '');
        $type = (string) ($data['type'] ?? '');
        if (!in_array($category, ['Textbook', 'Fiction', 'Nonfiction', 'Reference'], true)
            || !in_array($condition, ['Like new', 'Very good', 'Good', 'Fair'], true)
            || !in_array($type, ['For sale', 'Donation', 'Exchange'], true)) {
            respond(['error' => 'Choose a valid category, condition, and listing type.'], 422);
        }
        $price = $type === 'Donation' ? 0 : filter_var($data['price'] ?? null, FILTER_VALIDATE_FLOAT);
        if ($price === false || $price < 0 || $price > 9999) {
            respond(['error' => 'Enter a price between 0 and 9999.'], 422);
        }
        $imagePath = null;
        if (isset($_FILES['image']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) {
            $image = $_FILES['image'];
            if ($image['error'] !== UPLOAD_ERR_OK || $image['size'] > 1048576) {
                respond(['error' => 'The photo could not be uploaded or is larger than 1 MB.'], 422);
            }
            $imageInfo = @getimagesize($image['tmp_name']);
            $mime = $imageInfo['mime'] ?? '';
            $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp'];
            if (!isset($extensions[$mime])) {
                respond(['error' => 'Upload a valid JPG, PNG, GIF, or WebP image.'], 422);
            }
            $directory = __DIR__ . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'books';
            if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
                throw new RuntimeException('Unable to create the book image upload directory.');
            }
            $filename = bin2hex(random_bytes(16)) . '.' . $extensions[$mime];
            if (!move_uploaded_file($image['tmp_name'], $directory . DIRECTORY_SEPARATOR . $filename)) {
                throw new RuntimeException('Unable to store the uploaded book image.');
            }
            $imagePath = 'uploads/books/' . $filename;
        }
        $statement = $pdo->prepare(
            'INSERT INTO books (user_id, title, author, subject, category, `condition`, price, type, description, image, approved)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0)'
        );
        $statement->execute([$userId, $title, $author, $subject, $category, $condition, $price, $type, $description, $imagePath]);
        respond(['message' => 'Your listing was submitted for moderator review.']);
    }

    if ($action === 'status') {
        $userId = require_user();
        $bookId = filter_var($data['bookId'] ?? null, FILTER_VALIDATE_INT);
        $status = (string) ($data['status'] ?? '');
        if (!$bookId || !in_array($status, ['Available', 'Sold', 'Donated', 'Exchanged', 'Unavailable'], true)) {
            respond(['error' => 'Choose a valid listing status.'], 422);
        }
        $statement = $pdo->prepare('UPDATE books SET status = ? WHERE id = ? AND user_id = ? AND removed = 0');
        $statement->execute([$status, $bookId, $userId]);
        if (!$statement->rowCount()) {
            respond(['error' => 'That listing could not be updated.'], 404);
        }
        respond(['message' => 'Listing status updated.']);
    }

    if ($action === 'message') {
        $senderId = require_user();
        $bookId = filter_var($data['bookId'] ?? null, FILTER_VALIDATE_INT);
        $message = required_text($data, 'message', 500);
        $offer = trim((string) ($data['offer'] ?? ''));
        if (!$bookId || mb_strlen($offer) > 100) {
            respond(['error' => 'Invalid book or exchange offer.'], 422);
        }
        $statement = $pdo->prepare('SELECT user_id FROM books WHERE id = ? AND approved = 1 AND removed = 0 AND status = "Available"');
        $statement->execute([$bookId]);
        $sellerId = (int) $statement->fetchColumn();
        if (!$sellerId || $sellerId === $senderId) {
            respond(['error' => 'You cannot contact the owner of this listing.'], 422);
        }
        $statement = $pdo->prepare('INSERT INTO messages (book_id, sender_id, recipient_id, message, offered_book) VALUES (?, ?, ?, ?, ?)');
        $statement->execute([$bookId, $senderId, $sellerId, $message, $offer]);
        respond(['message' => 'Your message has been sent to the book owner.']);
    }

    if ($action === 'reply') {
        $senderId = require_user();
        $bookId = filter_var($data['bookId'] ?? null, FILTER_VALIDATE_INT);
        $recipientId = filter_var($data['recipientId'] ?? null, FILTER_VALIDATE_INT);
        $message = required_text($data, 'message', 500);
        if (!$bookId || !$recipientId || $recipientId === $senderId) {
            respond(['error' => 'Invalid conversation.'], 422);
        }
        $statement = $pdo->prepare(
            'SELECT id
             FROM messages
             WHERE book_id = ?
               AND ((sender_id = ? AND recipient_id = ?) OR (sender_id = ? AND recipient_id = ?))
             LIMIT 1'
        );
        $statement->execute([$bookId, $senderId, $recipientId, $recipientId, $senderId]);
        if (!$statement->fetchColumn()) {
            respond(['error' => 'You can only reply to an existing conversation.'], 404);
        }
        $statement = $pdo->prepare(
            'INSERT INTO messages (book_id, sender_id, recipient_id, message, offered_book) VALUES (?, ?, ?, ?, "")'
        );
        $statement->execute([$bookId, $senderId, $recipientId, $message]);
        respond(['message' => 'Your reply has been sent.']);
    }

    if ($action === 'report-message') {
        $reporterId = require_user();
        $messageId = filter_var($data['messageId'] ?? null, FILTER_VALIDATE_INT);
        $reason = (string) ($data['reason'] ?? '');
        $details = trim((string) ($data['details'] ?? ''));
        if (!$messageId || !in_array($reason, ['scam', 'offensive', 'harassment', 'inappropriate', 'other'], true)) {
            respond(['error' => 'Choose a message and a valid report reason.'], 422);
        }
        if (mb_strlen($details) > 500) {
            respond(['error' => 'Report details must be 500 characters or fewer.'], 422);
        }
        $statement = $pdo->prepare(
            'SELECT m.id, m.book_id, m.sender_id, m.recipient_id
             FROM messages m
             WHERE m.id = ? AND (m.sender_id = ? OR m.recipient_id = ?)'
        );
        $statement->execute([$messageId, $reporterId, $reporterId]);
        $message = $statement->fetch();
        if (!$message || (int) $message['sender_id'] === $reporterId) {
            respond(['error' => 'You can report messages you received in your inbox.'], 404);
        }
        try {
            $statement = $pdo->prepare(
                'INSERT INTO reports (reporter_id, reported_user_id, book_id, message_id, reason, details)
                 VALUES (?, ?, ?, ?, ?, ?)'
            );
            $statement->execute([
                $reporterId,
                $message['sender_id'],
                $message['book_id'],
                $messageId,
                $reason,
                $details,
            ]);
        } catch (PDOException $error) {
            if ($error->getCode() === '23000') {
                respond(['error' => 'You have already reported this message.'], 409);
            }
            throw $error;
        }
        respond(['message' => 'Your report was sent to the SCC administrators for review.']);
    }

    if ($action === 'report-action') {
        require_admin($pdo);
        $reportId = filter_var($data['reportId'] ?? null, FILTER_VALIDATE_INT);
        $decision = (string) ($data['decision'] ?? '');
        if (!$reportId || !in_array($decision, ['reviewed', 'dismissed', 'remove_listing'], true)) {
            respond(['error' => 'Choose a valid report action.'], 422);
        }
        if ($decision === 'remove_listing') {
            $pdo->beginTransaction();
            try {
                $statement = $pdo->prepare('SELECT book_id FROM reports WHERE id = ? FOR UPDATE');
                $statement->execute([$reportId]);
                $bookId = $statement->fetchColumn();
                if (!$bookId) {
                    $pdo->rollBack();
                    respond(['error' => 'This report has no listing to remove.'], 422);
                }
                $statement = $pdo->prepare('UPDATE books SET removed = 1 WHERE id = ?');
                $statement->execute([$bookId]);
                $statement = $pdo->prepare('UPDATE reports SET status = "reviewed", reviewed_at = CURRENT_TIMESTAMP WHERE id = ?');
                $statement->execute([$reportId]);
                $pdo->commit();
                respond(['message' => 'The listing was removed and the report marked reviewed.']);
            } catch (Throwable $error) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                throw $error;
            }
        }
        $statement = $pdo->prepare(
            'UPDATE reports SET status = ?, reviewed_at = CURRENT_TIMESTAMP WHERE id = ?'
        );
        $statement->execute([$decision, $reportId]);
        if (!$statement->rowCount()) {
            respond(['error' => 'Report not found or already in that state.'], 404);
        }
        respond(['message' => $decision === 'dismissed' ? 'Report dismissed.' : 'Report marked reviewed.']);
    }

    if ($action === 'moderate') {
        require_admin($pdo);
        $bookId = filter_var($data['bookId'] ?? null, FILTER_VALIDATE_INT);
        $decision = (string) ($data['decision'] ?? '');
        if (!$bookId || !in_array($decision, ['approve', 'remove', 'duplicate', 'restore'], true)) {
            respond(['error' => 'Invalid moderation action.'], 422);
        }
        if ($decision === 'approve') {
            $statement = $pdo->prepare('UPDATE books SET approved = 1, removed = 0 WHERE id = ?');
        } elseif ($decision === 'restore') {
            $statement = $pdo->prepare('UPDATE books SET removed = 0 WHERE id = ?');
        } else {
            $statement = $pdo->prepare('UPDATE books SET removed = 1 WHERE id = ?');
        }
        $statement->execute([$bookId]);
        if (!$statement->rowCount()) {
            respond(['error' => 'That listing could not be updated.'], 404);
        }
        $message = match ($decision) {
            'approve' => 'Listing approved.',
            'restore' => 'Listing restored.',
            default => 'Listing removed from the marketplace.',
        };
        respond(['message' => $message]);
    }

    respond(['error' => 'Unknown action.'], 404);
} catch (Throwable $error) {
    error_log('BookBai API error: ' . $error->getMessage());
    respond(['error' => 'The server could not complete your request. Check the database configuration and try again.'], 500);
}
