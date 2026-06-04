<?php
declare(strict_types=1);

session_start();
header('Content-Type: application/json; charset=utf-8');

require __DIR__ . '/config/db.php';
$mailConfig = require __DIR__ . '/config/mail.php';

function ensurePersonalInformationSchema(PDO $pdo): void
{
    $pdo->exec('
        CREATE TABLE IF NOT EXISTS personal_information (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id INT UNSIGNED NOT NULL,
            first_name VARCHAR(100) NOT NULL,
            last_name VARCHAR(100) NOT NULL,
            email VARCHAR(190) NOT NULL,
            phone VARCHAR(50) DEFAULT NULL,
            company VARCHAR(150) DEFAULT NULL,
            website VARCHAR(255) DEFAULT NULL,
            bio TEXT DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            CONSTRAINT fk_personal_information_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            CONSTRAINT uq_personal_information_user UNIQUE (user_id),
            CONSTRAINT uq_personal_information_email UNIQUE (email)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ');

    $pdo->exec('
        INSERT INTO personal_information (user_id, first_name, last_name, email, phone, company, website, bio)
        SELECT u.id, u.first_name, u.last_name, u.email, u.phone, u.company, u.website, u.bio
        FROM users u
        LEFT JOIN personal_information pi ON pi.user_id = u.id
        WHERE pi.user_id IS NULL
    ');
}

ensurePersonalInformationSchema($pdo);

function respond(array $data, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function requestData(): array
{
    $raw = file_get_contents('php://input');
    if (!$raw) {
        return $_POST ?: [];
    }

    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

function requireAuth(): int
{
    if (empty($_SESSION['user_id'])) {
        respond([
            'success' => false,
            'message' => 'Please login first.'
        ], 401);
    }

    return (int) $_SESSION['user_id'];
}

function loadUser(PDO $pdo, int $userId): ?array
{
    $statement = $pdo->prepare('
        SELECT
            u.id,
            COALESCE(pi.first_name, u.first_name) AS first_name,
            COALESCE(pi.last_name, u.last_name) AS last_name,
            COALESCE(pi.email, u.email) AS email,
            COALESCE(pi.phone, u.phone) AS phone,
            COALESCE(pi.company, u.company) AS company,
            COALESCE(pi.website, u.website) AS website,
            COALESCE(pi.bio, u.bio) AS bio,
            u.plan,
            u.avatar_path,
            u.created_at
        FROM users u
        LEFT JOIN personal_information pi ON pi.user_id = u.id
        WHERE u.id = ?
        LIMIT 1
    ');
    $statement->execute([$userId]);
    $user = $statement->fetch();

    return $user ?: null;
}

function loadContacts(PDO $pdo, int $userId): array
{
    $statement = $pdo->prepare('
        SELECT id, email, company, fax, tags, status, created_at
        FROM contacts
        WHERE user_id = ?
        ORDER BY id DESC
    ');
    $statement->execute([$userId]);

    $contacts = [];
    foreach ($statement->fetchAll() as $row) {
        $row['tags'] = $row['tags'] ? json_decode($row['tags'], true) : [];
        $row['tags'] = is_array($row['tags']) ? $row['tags'] : [];
        $row['tags_text'] = implode(', ', $row['tags']);
        $row['name'] = strstr($row['email'], '@', true) ?: $row['email'];
        $contacts[] = $row;
    }

    return $contacts;
}

function loadCampaigns(PDO $pdo, int $userId): array
{
    $statement = $pdo->prepare('
        SELECT id, name, subject, sender_name, sender_email, content, recipients_raw, audience_tags, audience_notes,
               status, recipients_count, opens_count, clicks_count, schedule_at, sent_at, created_at, updated_at
        FROM campaigns
        WHERE user_id = ?
        ORDER BY id DESC
    ');
    $statement->execute([$userId]);
    return $statement->fetchAll();
}

function loadBillingHistory(PDO $pdo, int $userId): array
{
    $statement = $pdo->prepare('
        SELECT id, title, amount_label, icon, created_at
        FROM billing_history
        WHERE user_id = ?
        ORDER BY id DESC
    ');
    $statement->execute([$userId]);
    return $statement->fetchAll();
}

function bootstrapPayload(PDO $pdo, int $userId, ?string $message = null): array
{
    $payload = [
        'success' => true,
        'authenticated' => true,
        'user' => loadUser($pdo, $userId),
        'contacts' => loadContacts($pdo, $userId),
        'campaigns' => loadCampaigns($pdo, $userId),
        'billing_history' => loadBillingHistory($pdo, $userId),
    ];

    if ($message !== null) {
        $payload['message'] = $message;
    }

    return $payload;
}

function validateEmailList(string $raw): array
{
    $emails = preg_split('/[\s,;]+/', trim($raw)) ?: [];
    $valid = [];

    foreach ($emails as $email) {
        $clean = strtolower(trim($email));
        if ($clean !== '' && filter_var($clean, FILTER_VALIDATE_EMAIL)) {
            $valid[$clean] = $clean;
        }
    }

    return array_values($valid);
}

function normalizeContactStatus(string $status): string
{
    $clean = strtolower(trim($status));
    $allowed = ['active', 'inactive', 'unsubscribed', 'bounced'];

    return in_array($clean, $allowed, true) ? $clean : 'active';
}

function normalizeContactTags($raw): array
{
    if (is_array($raw)) {
        $source = $raw;
    } else {
        $source = explode(',', (string) $raw);
    }

    $tags = [];
    foreach ($source as $tag) {
        $clean = trim((string) $tag);
        if ($clean !== '') {
            $tags[$clean] = $clean;
        }
    }

    return array_values($tags);
}

function smtpExpect($socket, array $validCodes): string
{
    $response = '';

    while (($line = fgets($socket, 515)) !== false) {
        $response .= $line;
        if (isset($line[3]) && $line[3] === ' ') {
            break;
        }
    }

    $code = (int) substr($response, 0, 3);
    if (!in_array($code, $validCodes, true)) {
        throw new RuntimeException(trim($response) !== '' ? trim($response) : 'SMTP server returned an unexpected response.');
    }

    return $response;
}

function smtpWrite($socket, string $command): void
{
    fwrite($socket, $command . "\r\n");
}

function buildMimeMessage(string $fromEmail, string $fromName, string $toEmail, string $subject, string $html): string
{
    $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
    $safeFromName = str_replace(['"', "\r", "\n"], '', $fromName);
    $boundary = 'mailflow_' . bin2hex(random_bytes(8));

    $headers = [
        'From: "' . $safeFromName . '" <' . $fromEmail . '>',
        'To: <' . $toEmail . '>',
        'Subject: ' . $encodedSubject,
        'MIME-Version: 1.0',
        'Content-Type: multipart/alternative; boundary="' . $boundary . '"',
    ];

    $plainText = trim(strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $html)));
    if ($plainText === '') {
        $plainText = 'This email contains HTML content.';
    }

    $body = implode("\r\n", $headers) . "\r\n\r\n";
    $body .= '--' . $boundary . "\r\n";
    $body .= "Content-Type: text/plain; charset=UTF-8\r\n";
    $body .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
    $body .= $plainText . "\r\n\r\n";
    $body .= '--' . $boundary . "\r\n";
    $body .= "Content-Type: text/html; charset=UTF-8\r\n";
    $body .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
    $body .= $html . "\r\n\r\n";
    $body .= '--' . $boundary . "--\r\n";

    return $body;
}

function sendEmailViaGmailSmtp(array $mailConfig, string $toEmail, string $subject, string $html, ?string $senderName = null): void
{
    $host = (string) ($mailConfig['host'] ?? '');
    $port = (int) ($mailConfig['port'] ?? 587);
    $username = (string) ($mailConfig['username'] ?? '');
    $password = (string) ($mailConfig['password'] ?? '');
    $fromEmail = (string) ($mailConfig['from_email'] ?? $username);
    $fromName = trim((string) ($senderName !== null && $senderName !== '' ? $senderName : ($mailConfig['from_name'] ?? 'MailFlow')));
    $timeout = (int) ($mailConfig['timeout'] ?? 20);

    if ($host === '' || $username === '' || $password === '' || $fromEmail === '') {
        throw new RuntimeException('Please complete config/mail.php with your Gmail SMTP settings first.');
    }

    $socket = stream_socket_client(
        'tcp://' . $host . ':' . $port,
        $errorNumber,
        $errorMessage,
        $timeout,
        STREAM_CLIENT_CONNECT
    );

    if (!$socket) {
        throw new RuntimeException('SMTP connection failed: ' . $errorMessage);
    }

    stream_set_timeout($socket, $timeout);

    try {
        smtpExpect($socket, [220]);

        smtpWrite($socket, 'EHLO mailflow.local');
        smtpExpect($socket, [250]);

        smtpWrite($socket, 'STARTTLS');
        smtpExpect($socket, [220]);

        if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            throw new RuntimeException('Could not start TLS encryption for Gmail SMTP.');
        }

        smtpWrite($socket, 'EHLO mailflow.local');
        smtpExpect($socket, [250]);

        smtpWrite($socket, 'AUTH LOGIN');
        smtpExpect($socket, [334]);
        smtpWrite($socket, base64_encode($username));
        smtpExpect($socket, [334]);
        smtpWrite($socket, base64_encode($password));
        smtpExpect($socket, [235]);

        smtpWrite($socket, 'MAIL FROM:<' . $fromEmail . '>');
        smtpExpect($socket, [250]);
        smtpWrite($socket, 'RCPT TO:<' . $toEmail . '>');
        smtpExpect($socket, [250, 251]);
        smtpWrite($socket, 'DATA');
        smtpExpect($socket, [354]);

        $message = buildMimeMessage($fromEmail, $fromName, $toEmail, $subject, $html);
        fwrite($socket, str_replace("\n.", "\n..", $message) . "\r\n.\r\n");
        smtpExpect($socket, [250]);

        smtpWrite($socket, 'QUIT');
        smtpExpect($socket, [221]);
    } finally {
        fclose($socket);
    }
}

function campaignSendResult(array $campaign, array $mailConfig): array
{
    $emails = validateEmailList((string) ($campaign['recipients_raw'] ?? ''));
    if (!$emails) {
        return [
            'attempted' => 0,
            'sent' => 0,
            'opens' => 0,
            'clicks' => 0,
            'message' => 'Campaign marked as sent, but no valid recipients were found.'
        ];
    }

    $senderName = trim((string) ($campaign['sender_name'] ?? 'MailFlow'));
    $subject = trim((string) ($campaign['subject'] ?? ''));
    $content = (string) ($campaign['content'] ?? '');

    $sentCount = 0;
    $lastError = '';
    foreach ($emails as $email) {
        try {
            sendEmailViaGmailSmtp($mailConfig, $email, $subject, $content, $senderName);
            $sentCount++;
        } catch (Throwable $exception) {
            $lastError = $exception->getMessage();
        }
    }

    return [
        'attempted' => count($emails),
        'sent' => $sentCount,
        'opens' => (int) floor(count($emails) * 0.42),
        'clicks' => (int) floor(count($emails) * 0.12),
        'message' => $sentCount > 0
            ? "Campaign sent to {$sentCount} recipient(s)."
            : 'Campaign was not sent. ' . ($lastError !== '' ? $lastError : 'Please check config/mail.php and your Gmail App Password.')
    ];
}

$action = $_GET['action'] ?? '';
$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
$data = requestData();

try {
    if ($action === 'session' && $method === 'GET') {
        if (empty($_SESSION['user_id'])) {
            respond([
                'success' => true,
                'authenticated' => false
            ]);
        }

        respond(bootstrapPayload($pdo, (int) $_SESSION['user_id']));
    }

    if ($action === 'register' && $method === 'POST') {
        $firstName = trim((string) ($data['first_name'] ?? ''));
        $lastName = trim((string) ($data['last_name'] ?? ''));
        $email = strtolower(trim((string) ($data['email'] ?? '')));
        $password = (string) ($data['password'] ?? '');

        if ($firstName === '' || $lastName === '') {
            respond(['success' => false, 'message' => 'First name and last name are required.'], 422);
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            respond(['success' => false, 'message' => 'Please enter a valid email address.'], 422);
        }

        if (strlen($password) < 6) {
            respond(['success' => false, 'message' => 'Password must be at least 6 characters.'], 422);
        }

        $check = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
        $check->execute([$email]);
        if ($check->fetch()) {
            respond(['success' => false, 'message' => 'This email is already registered.'], 409);
        }

        $insert = $pdo->prepare('
            INSERT INTO users (first_name, last_name, email, password, plan)
            VALUES (?, ?, ?, ?, ?)
        ');
        $pdo->beginTransaction();
        $insert->execute([$firstName, $lastName, $email, password_hash($password, PASSWORD_DEFAULT), 'Free']);

        $newUserId = (int) $pdo->lastInsertId();
        $profileInsert = $pdo->prepare('
            INSERT INTO personal_information (user_id, first_name, last_name, email, phone, company, website, bio)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ');
        $profileInsert->execute([$newUserId, $firstName, $lastName, $email, null, null, null, null]);
        $pdo->commit();

        $_SESSION['user_id'] = $newUserId;
        session_regenerate_id(true);
        respond(bootstrapPayload($pdo, (int) $_SESSION['user_id'], 'Account created successfully.'));
    }

    if ($action === 'login' && $method === 'POST') {
        $email = strtolower(trim((string) ($data['email'] ?? '')));
        $password = (string) ($data['password'] ?? '');

        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') {
            respond(['success' => false, 'message' => 'Email and password are required.'], 422);
        }

        $statement = $pdo->prepare('SELECT id, password FROM users WHERE email = ? LIMIT 1');
        $statement->execute([$email]);
        $user = $statement->fetch();

        if (!$user || !password_verify($password, $user['password'])) {
            respond(['success' => false, 'message' => 'Invalid email or password.'], 401);
        }

        $_SESSION['user_id'] = (int) $user['id'];
        session_regenerate_id(true);
        respond(bootstrapPayload($pdo, (int) $_SESSION['user_id'], 'Login successful.'));
    }

    if ($action === 'logout' && $method === 'POST') {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }
        session_destroy();

        respond([
            'success' => true,
            'authenticated' => false,
            'message' => 'Logged out successfully.'
        ]);
    }

    if ($action === 'contacts' && $method === 'GET') {
        $userId = requireAuth();
        respond([
            'success' => true,
            'contacts' => loadContacts($pdo, $userId)
        ]);
    }

    if ($action === 'contacts' && $method === 'POST') {
        $userId = requireAuth();
        $contactId = isset($data['id']) && $data['id'] !== '' ? (int) $data['id'] : 0;
        $importContacts = $data['contacts'] ?? [];
        $emails = $data['emails'] ?? [];
        $emails = is_array($emails) ? $emails : [];
        $emails = array_values(array_unique(array_filter(array_map(static function ($email) {
            $clean = strtolower(trim((string) $email));
            return filter_var($clean, FILTER_VALIDATE_EMAIL) ? $clean : null;
        }, $emails))));

        if (!is_array($importContacts)) {
            $importContacts = [];
        }

        if (!$emails && !$importContacts) {
            respond(['success' => false, 'message' => 'Please provide at least one valid email address.'], 422);
        }

        $company = trim((string) ($data['company'] ?? ''));
        $fax = trim((string) ($data['fax'] ?? ''));
        $status = normalizeContactStatus((string) ($data['status'] ?? 'active'));
        $tagsText = trim((string) ($data['tags'] ?? ''));
        $tags = normalizeContactTags($tagsText);
        $tagsJson = json_encode($tags, JSON_UNESCAPED_UNICODE);

        if ($contactId > 0) {
            $check = $pdo->prepare('SELECT id FROM contacts WHERE id = ? AND user_id = ? LIMIT 1');
            $check->execute([$contactId, $userId]);
            if (!$check->fetch()) {
                respond(['success' => false, 'message' => 'Contact not found.'], 404);
            }

            $duplicate = $pdo->prepare('SELECT id FROM contacts WHERE user_id = ? AND email = ? AND id <> ? LIMIT 1');
            $duplicate->execute([$userId, $emails[0], $contactId]);
            if ($duplicate->fetch()) {
                respond(['success' => false, 'message' => 'You already have a contact with this email.'], 409);
            }

            $update = $pdo->prepare('
                UPDATE contacts
                SET email = ?, company = ?, fax = ?, tags = ?, status = ?, updated_at = NOW()
                WHERE id = ? AND user_id = ?
            ');
            $update->execute([$emails[0], $company, $fax, $tagsJson, $status, $contactId, $userId]);

            respond([
                'success' => true,
                'message' => 'Contact updated successfully.',
                'contacts' => loadContacts($pdo, $userId)
            ]);
        }

        $insert = $pdo->prepare('
            INSERT INTO contacts (user_id, email, company, fax, tags, status)
            VALUES (?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                company = VALUES(company),
                fax = VALUES(fax),
                tags = VALUES(tags),
                status = VALUES(status),
                updated_at = NOW()
        ');

        $savedCount = 0;

        if ($importContacts) {
            foreach ($importContacts as $contact) {
                if (!is_array($contact)) {
                    continue;
                }

                $email = strtolower(trim((string) ($contact['email'] ?? '')));
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    continue;
                }

                $rowCompany = trim((string) ($contact['company'] ?? ''));
                $rowFax = trim((string) ($contact['fax'] ?? ''));
                $rowStatus = normalizeContactStatus((string) ($contact['status'] ?? 'active'));
                $rowTags = normalizeContactTags($contact['tags'] ?? []);
                $rowTagsJson = json_encode($rowTags, JSON_UNESCAPED_UNICODE);

                $insert->execute([$userId, $email, $rowCompany, $rowFax, $rowTagsJson, $rowStatus]);
                $savedCount++;
            }
        } else {
            foreach ($emails as $email) {
                $insert->execute([$userId, $email, $company, $fax, $tagsJson, $status]);
                $savedCount++;
            }
        }

        if ($savedCount === 0) {
            respond(['success' => false, 'message' => 'No valid contacts were found in the imported file.'], 422);
        }

        respond([
            'success' => true,
            'message' => $savedCount === 1 ? '1 contact saved successfully.' : $savedCount . ' contacts saved successfully.',
            'contacts' => loadContacts($pdo, $userId)
        ]);
    }

    if ($action === 'contact_delete' && $method === 'POST') {
        $userId = requireAuth();
        $ids = $data['ids'] ?? [];
        $ids = is_array($ids) ? array_values(array_filter(array_map('intval', $ids))) : [];

        if (!$ids) {
            respond(['success' => false, 'message' => 'No contacts selected.'], 422);
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $statement = $pdo->prepare("DELETE FROM contacts WHERE user_id = ? AND id IN ({$placeholders})");
        $statement->execute(array_merge([$userId], $ids));

        respond([
            'success' => true,
            'message' => 'Contact(s) deleted successfully.',
            'contacts' => loadContacts($pdo, $userId)
        ]);
    }

    if ($action === 'campaigns' && $method === 'GET') {
        $userId = requireAuth();
        respond([
            'success' => true,
            'campaigns' => loadCampaigns($pdo, $userId)
        ]);
    }

    if ($action === 'campaigns' && $method === 'POST') {
        $userId = requireAuth();
        $campaignId = isset($data['id']) && $data['id'] !== '' ? (int) $data['id'] : 0;

        $name = trim((string) ($data['name'] ?? ''));
        $subject = trim((string) ($data['subject'] ?? ''));
        $senderName = trim((string) ($data['sender_name'] ?? ''));
        $senderEmail = trim((string) ($data['sender_email'] ?? ''));
        $content = trim((string) ($data['content'] ?? ''));
        $recipientsRaw = trim((string) ($data['recipients_raw'] ?? ''));
        $audienceTags = trim((string) ($data['audience_tags'] ?? ''));
        $audienceNotes = trim((string) ($data['audience_notes'] ?? ''));
        $status = trim((string) ($data['status'] ?? 'draft'));
        $scheduleAt = trim((string) ($data['schedule_at'] ?? ''));
        $scheduleAt = $scheduleAt !== '' ? str_replace('T', ' ', $scheduleAt) . ':00' : null;

        if ($name === '' || $subject === '') {
            respond(['success' => false, 'message' => 'Campaign name and subject are required.'], 422);
        }

        if ($senderEmail !== '' && !filter_var($senderEmail, FILTER_VALIDATE_EMAIL)) {
            respond(['success' => false, 'message' => 'Please enter a valid sender email.'], 422);
        }

        $payload = [
            'name' => $name,
            'subject' => $subject,
            'sender_name' => $senderName,
            'sender_email' => $senderEmail,
            'content' => $content,
            'recipients_raw' => $recipientsRaw,
            'audience_tags' => $audienceTags,
            'audience_notes' => $audienceNotes,
            'status' => $status,
            'schedule_at' => $scheduleAt,
            'recipients_count' => 0,
            'opens_count' => 0,
            'clicks_count' => 0,
            'sent_at' => null,
        ];

        if ($status === 'sent') {
            $send = campaignSendResult($payload, $mailConfig);
            $payload['recipients_count'] = $send['attempted'];
            $payload['opens_count'] = $send['opens'];
            $payload['clicks_count'] = $send['clicks'];
            $payload['sent_at'] = date('Y-m-d H:i:s');
            $message = $send['message'];
        } else {
            $message = $campaignId > 0 ? 'Campaign updated successfully.' : 'Campaign created successfully.';
        }

        if ($campaignId > 0) {
            $check = $pdo->prepare('SELECT id FROM campaigns WHERE id = ? AND user_id = ? LIMIT 1');
            $check->execute([$campaignId, $userId]);
            if (!$check->fetch()) {
                respond(['success' => false, 'message' => 'Campaign not found.'], 404);
            }

            $update = $pdo->prepare('
                UPDATE campaigns
                SET name = ?, subject = ?, sender_name = ?, sender_email = ?, content = ?, recipients_raw = ?,
                    audience_tags = ?, audience_notes = ?, status = ?, recipients_count = ?, opens_count = ?, clicks_count = ?,
                    schedule_at = ?, sent_at = ?, updated_at = NOW()
                WHERE id = ? AND user_id = ?
            ');
            $update->execute([
                $payload['name'],
                $payload['subject'],
                $payload['sender_name'],
                $payload['sender_email'],
                $payload['content'],
                $payload['recipients_raw'],
                $payload['audience_tags'],
                $payload['audience_notes'],
                $payload['status'],
                $payload['recipients_count'],
                $payload['opens_count'],
                $payload['clicks_count'],
                $payload['schedule_at'],
                $payload['sent_at'],
                $campaignId,
                $userId
            ]);
        } else {
            $insert = $pdo->prepare('
                INSERT INTO campaigns (
                    user_id, name, subject, sender_name, sender_email, content, recipients_raw,
                    audience_tags, audience_notes, status, recipients_count, opens_count, clicks_count, schedule_at, sent_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ');
            $insert->execute([
                $userId,
                $payload['name'],
                $payload['subject'],
                $payload['sender_name'],
                $payload['sender_email'],
                $payload['content'],
                $payload['recipients_raw'],
                $payload['audience_tags'],
                $payload['audience_notes'],
                $payload['status'],
                $payload['recipients_count'],
                $payload['opens_count'],
                $payload['clicks_count'],
                $payload['schedule_at'],
                $payload['sent_at']
            ]);
        }

        respond([
            'success' => true,
            'message' => $message,
            'campaigns' => loadCampaigns($pdo, $userId)
        ]);
    }

    if ($action === 'campaign_view' && $method === 'GET') {
        $userId = requireAuth();
        $campaignId = (int) ($_GET['id'] ?? 0);
        if ($campaignId <= 0) {
            respond(['success' => false, 'message' => 'Campaign id is required.'], 422);
        }

        $statement = $pdo->prepare('
            SELECT id, name, subject, sender_name, sender_email, content, recipients_raw, audience_tags, audience_notes,
                   status, recipients_count, opens_count, clicks_count, schedule_at, sent_at, created_at, updated_at
            FROM campaigns
            WHERE id = ? AND user_id = ?
            LIMIT 1
        ');
        $statement->execute([$campaignId, $userId]);
        $campaign = $statement->fetch();

        if (!$campaign) {
            respond(['success' => false, 'message' => 'Campaign not found.'], 404);
        }

        respond([
            'success' => true,
            'campaign' => $campaign
        ]);
    }

    if ($action === 'campaign_delete' && $method === 'POST') {
        $userId = requireAuth();
        $campaignId = (int) ($data['id'] ?? 0);
        if ($campaignId <= 0) {
            respond(['success' => false, 'message' => 'Campaign id is required.'], 422);
        }

        $delete = $pdo->prepare('DELETE FROM campaigns WHERE id = ? AND user_id = ?');
        $delete->execute([$campaignId, $userId]);

        respond([
            'success' => true,
            'message' => 'Campaign deleted successfully.',
            'campaigns' => loadCampaigns($pdo, $userId)
        ]);
    }

    if ($action === 'profile_update' && $method === 'POST') {
        $userId = requireAuth();
        $firstName = trim((string) ($data['first_name'] ?? ''));
        $lastName = trim((string) ($data['last_name'] ?? ''));
        $email = strtolower(trim((string) ($data['email'] ?? '')));
        $phone = trim((string) ($data['phone'] ?? ''));
        $company = trim((string) ($data['company'] ?? ''));
        $website = trim((string) ($data['website'] ?? ''));
        $bio = trim((string) ($data['bio'] ?? ''));

        if ($firstName === '' || $lastName === '') {
            respond(['success' => false, 'message' => 'First name and last name are required.'], 422);
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            respond(['success' => false, 'message' => 'Please enter a valid email address.'], 422);
        }

        $check = $pdo->prepare('
            SELECT u.id
            FROM users u
            LEFT JOIN personal_information pi ON pi.user_id = u.id
            WHERE COALESCE(pi.email, u.email) = ? AND u.id <> ?
            LIMIT 1
        ');
        $check->execute([$email, $userId]);
        if ($check->fetch()) {
            respond(['success' => false, 'message' => 'That email is already used by another account.'], 409);
        }

        $pdo->beginTransaction();

        $updateUser = $pdo->prepare('
            UPDATE users
            SET first_name = ?, last_name = ?, email = ?, updated_at = NOW()
            WHERE id = ?
        ');
        $updateUser->execute([$firstName, $lastName, $email, $userId]);

        $updateProfile = $pdo->prepare('
            INSERT INTO personal_information (user_id, first_name, last_name, email, phone, company, website, bio)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                first_name = VALUES(first_name),
                last_name = VALUES(last_name),
                email = VALUES(email),
                phone = VALUES(phone),
                company = VALUES(company),
                website = VALUES(website),
                bio = VALUES(bio),
                updated_at = NOW()
        ');
        $updateProfile->execute([$userId, $firstName, $lastName, $email, $phone, $company, $website, $bio]);

        $pdo->commit();

        respond([
            'success' => true,
            'message' => 'Profile updated successfully.',
            'user' => loadUser($pdo, $userId)
        ]);
    }

    if ($action === 'upload_avatar' && $method === 'POST') {
        $userId = requireAuth();

        if (!isset($_FILES['avatar']) || !is_array($_FILES['avatar'])) {
            respond(['success' => false, 'message' => 'Please choose a profile photo.'], 422);
        }

        $file = $_FILES['avatar'];
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            respond(['success' => false, 'message' => 'Upload failed. Please try again.'], 422);
        }

        if (($file['size'] ?? 0) > 2 * 1024 * 1024) {
            respond(['success' => false, 'message' => 'Profile photo must be smaller than 2MB.'], 422);
        }

        $mimeType = mime_content_type($file['tmp_name']);
        $allowed = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
        ];

        if (!isset($allowed[$mimeType])) {
            respond(['success' => false, 'message' => 'Only JPG, PNG, and WEBP are allowed.'], 422);
        }

        $uploadDir = __DIR__ . '/uploads/avatars';
        if (!is_dir($uploadDir) && !mkdir($uploadDir, 0777, true) && !is_dir($uploadDir)) {
            respond(['success' => false, 'message' => 'Could not create avatar upload directory.'], 500);
        }

        $currentUser = loadUser($pdo, $userId);
        if (!$currentUser) {
            respond(['success' => false, 'message' => 'User not found.'], 404);
        }

        if (!empty($currentUser['avatar_path'])) {
            $oldPath = __DIR__ . '/' . ltrim((string) $currentUser['avatar_path'], '/');
            if (is_file($oldPath)) {
                @unlink($oldPath);
            }
        }

        $filename = 'avatar_' . $userId . '_' . bin2hex(random_bytes(8)) . '.' . $allowed[$mimeType];
        $destination = $uploadDir . '/' . $filename;

        if (!move_uploaded_file($file['tmp_name'], $destination)) {
            respond(['success' => false, 'message' => 'Could not save the uploaded photo.'], 500);
        }

        $avatarPath = 'uploads/avatars/' . $filename;
        $statement = $pdo->prepare('UPDATE users SET avatar_path = ?, updated_at = NOW() WHERE id = ?');
        $statement->execute([$avatarPath, $userId]);

        respond([
            'success' => true,
            'message' => 'Profile photo updated successfully.',
            'user' => loadUser($pdo, $userId)
        ]);
    }

    if ($action === 'change_plan' && $method === 'POST') {
        $userId = requireAuth();
        $plan = trim((string) ($data['plan'] ?? ''));
        $allowedPlans = ['Free', 'Pro', 'Business', 'Enterprise'];

        if (!in_array($plan, $allowedPlans, true)) {
            respond(['success' => false, 'message' => 'Invalid plan selected.'], 422);
        }

        $pdo->beginTransaction();

        $update = $pdo->prepare('UPDATE users SET plan = ?, updated_at = NOW() WHERE id = ?');
        $update->execute([$plan, $userId]);

        $history = $pdo->prepare('INSERT INTO billing_history (user_id, title, amount_label, icon) VALUES (?, ?, ?, ?)');
        $history->execute([
            $userId,
            $plan . ' Plan selected',
            $plan === 'Free' ? '$0.00' : 'Pending',
            $plan === 'Free' ? '🎁' : '💳'
        ]);

        $pdo->commit();

        respond([
            'success' => true,
            'message' => 'Plan updated successfully.',
            'user' => loadUser($pdo, $userId),
            'billing_history' => loadBillingHistory($pdo, $userId)
        ]);
    }

    if ($action === 'checkout_plan' && $method === 'POST') {
        $userId = requireAuth();
        $plan = trim((string) ($data['plan'] ?? ''));
        $payment = is_array($data['payment'] ?? null) ? $data['payment'] : [];
        $prices = [
            'Pro' => '$29.00',
            'Business' => '$79.00',
            'Enterprise' => 'Custom'
        ];

        if (!isset($prices[$plan])) {
            respond(['success' => false, 'message' => 'Invalid paid plan selected.'], 422);
        }

        $billingEmail = trim((string) ($payment['billing_email'] ?? ''));
        $cardholderName = trim((string) ($payment['cardholder_name'] ?? ''));
        $cardNumber = preg_replace('/\D+/', '', (string) ($payment['card_number'] ?? ''));
        $cardExpiry = trim((string) ($payment['card_expiry'] ?? ''));
        $cardCvc = trim((string) ($payment['card_cvc'] ?? ''));

        if (!filter_var($billingEmail, FILTER_VALIDATE_EMAIL)) {
            respond(['success' => false, 'message' => 'Please enter a valid billing email.'], 422);
        }

        if ($cardholderName === '' || strlen($cardNumber) < 12 || $cardExpiry === '' || strlen($cardCvc) < 3) {
            respond(['success' => false, 'message' => 'Please complete the payment form correctly.'], 422);
        }

        $pdo->beginTransaction();

        $update = $pdo->prepare('UPDATE users SET plan = ?, updated_at = NOW() WHERE id = ?');
        $update->execute([$plan, $userId]);

        $history = $pdo->prepare('INSERT INTO billing_history (user_id, title, amount_label, icon) VALUES (?, ?, ?, ?)');
        $history->execute([
            $userId,
            $plan . ' Plan - Monthly',
            $prices[$plan],
            '💳'
        ]);

        $pdo->commit();

        respond([
            'success' => true,
            'message' => $plan . ' plan activated successfully.',
            'user' => loadUser($pdo, $userId),
            'billing_history' => loadBillingHistory($pdo, $userId)
        ]);
    }

    if ($action === 'password_change' && $method === 'POST') {
        $userId = requireAuth();
        $currentPassword = (string) ($data['current_password'] ?? '');
        $newPassword = (string) ($data['new_password'] ?? '');

        if (strlen($newPassword) < 6) {
            respond(['success' => false, 'message' => 'New password must be at least 6 characters.'], 422);
        }

        $statement = $pdo->prepare('SELECT password FROM users WHERE id = ? LIMIT 1');
        $statement->execute([$userId]);
        $user = $statement->fetch();

        if (!$user || !password_verify($currentPassword, $user['password'])) {
            respond(['success' => false, 'message' => 'Current password is incorrect.'], 401);
        }

        $update = $pdo->prepare('UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?');
        $update->execute([password_hash($newPassword, PASSWORD_DEFAULT), $userId]);

        respond([
            'success' => true,
            'message' => 'Password updated successfully.'
        ]);
    }

    if ($action === 'delete_account' && $method === 'POST') {
        $userId = requireAuth();
        $delete = $pdo->prepare('DELETE FROM users WHERE id = ?');
        $delete->execute([$userId]);

        $_SESSION = [];
        session_destroy();

        respond([
            'success' => true,
            'message' => 'Account deleted successfully.'
        ]);
    }

    respond([
        'success' => false,
        'message' => 'Invalid API route.'
    ], 404);
} catch (Throwable $exception) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    respond([
        'success' => false,
        'message' => 'Server error: ' . $exception->getMessage()
    ], 500);
}
