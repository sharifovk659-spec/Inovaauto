<?php

declare(strict_types=1);

function ia_google_oauth_enabled(): bool
{
    $id = trim((string) (ia_env('IA_GOOGLE_CLIENT_ID') ?? ''));
    $secret = trim((string) (ia_env('IA_GOOGLE_CLIENT_SECRET') ?? ''));

    return $id !== '' && $secret !== '';
}

function ia_google_oauth_client_id(): string
{
    return trim((string) (ia_env('IA_GOOGLE_CLIENT_ID') ?? ''));
}

function ia_google_oauth_client_secret(): string
{
    return trim((string) (ia_env('IA_GOOGLE_CLIENT_SECRET') ?? ''));
}

function ia_google_oauth_redirect_uri(): string
{
    return ia_public_url('google-auth-callback.php');
}

function ia_google_ensure_schema(): void
{
    $pdo = ia_db();
    if (ia_db_column_exists($pdo, 'platform_users', 'google_id')) {
        return;
    }

    if (ia_db_is_pgsql($pdo)) {
        $pdo->exec('ALTER TABLE platform_users ADD COLUMN google_id VARCHAR(64) NULL');
        try {
            $pdo->exec(
                'CREATE UNIQUE INDEX IF NOT EXISTS uq_platform_users_google_id ON platform_users (google_id) WHERE google_id IS NOT NULL'
            );
        } catch (\Throwable) {
        }

        return;
    }

    $pdo->exec('ALTER TABLE platform_users ADD COLUMN google_id VARCHAR(64) NULL AFTER email');
    try {
        $pdo->exec('CREATE UNIQUE INDEX uq_platform_users_google_id ON platform_users (google_id)');
    } catch (\Throwable) {
    }
}

/**
 * @return array<string, mixed>|null
 */
function ia_platform_find_by_google_id(string $googleId): ?array
{
    $googleId = trim($googleId);
    if ($googleId === '') {
        return null;
    }
    $st = ia_db()->prepare('SELECT * FROM platform_users WHERE google_id = ? LIMIT 1');
    $st->execute([$googleId]);
    $row = $st->fetch();

    return $row ?: null;
}

/**
 * @param array<string, mixed> $profile ответ Google userinfo
 * @return array{ok: bool, user?: array<string, mixed>, error?: string}
 */
function ia_platform_login_or_register_google(array $profile): array
{
    $googleId = trim((string) ($profile['sub'] ?? ''));
    $email = strtolower(trim((string) ($profile['email'] ?? '')));
    $name = trim((string) ($profile['name'] ?? ''));
    $emailVerified = filter_var($profile['email_verified'] ?? false, FILTER_VALIDATE_BOOLEAN);

    if ($googleId === '') {
        return ['ok' => false, 'error' => 'Не удалось получить ID Google.'];
    }
    if ($email === '' || !$emailVerified) {
        return ['ok' => false, 'error' => 'Google не подтвердил ваш email.'];
    }
    if ($name === '') {
        $name = strstr($email, '@', true) ?: 'Google User';
    }

    $user = ia_platform_find_by_google_id($googleId);
    if ($user === null) {
        $user = ia_platform_find_by_email($email);
    }

    if ($user !== null) {
        if ((string) ($user['status'] ?? '') !== 'active') {
            return ['ok' => false, 'error' => 'Учётная запись недоступна.'];
        }

        $existingGoogleId = trim((string) ($user['google_id'] ?? ''));
        if ($existingGoogleId !== '' && $existingGoogleId !== $googleId) {
            return ['ok' => false, 'error' => 'Этот email уже привязан к другому аккаунту Google.'];
        }

        if ($existingGoogleId === '') {
            $pdo = ia_db();
            $pdo->prepare('UPDATE platform_users SET google_id = ? WHERE id = ?')->execute([$googleId, (int) $user['id']]);
            $user = ia_platform_find_by_google_id($googleId) ?? $user;
            $user['google_id'] = $googleId;
        }

        return ['ok' => true, 'user' => $user];
    }

    $st = ia_db()->prepare(
        'INSERT INTO platform_users (name, phone, email, password_hash, google_id, account_type, status) VALUES (?, ?, ?, NULL, ?, ?, \'active\')'
    );

    try {
        $st->execute([$name, '', $email, $googleId, 'private']);
    } catch (\PDOException $e) {
        error_log('google_register: ' . $e->getMessage());

        return ['ok' => false, 'error' => 'Не удалось создать учётную запись.'];
    }

    $user = ia_platform_find_by_google_id($googleId);
    if ($user === null) {
        return ['ok' => false, 'error' => 'Учётная запись создана, но вход не выполнен. Попробуйте снова.'];
    }

    return ['ok' => true, 'user' => $user];
}

/**
 * @param array<string, string> $fields
 * @return array<string, mixed>|null
 */
function ia_google_http_post(string $url, array $fields): ?array
{
    $body = http_build_query($fields);
    $ctx = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/x-www-form-urlencoded\r\nAccept: application/json\r\n",
            'content' => $body,
            'timeout' => 20,
            'ignore_errors' => true,
        ],
        'ssl' => [
            'verify_peer' => true,
            'verify_peer_name' => true,
        ],
    ]);
    $raw = @file_get_contents($url, false, $ctx);
    if ($raw === false || $raw === '') {
        return null;
    }
    $data = json_decode($raw, true);

    return is_array($data) ? $data : null;
}

/**
 * @return array<string, mixed>|null
 */
function ia_google_http_get(string $url, string $accessToken): ?array
{
    $ctx = stream_context_create([
        'http' => [
            'method' => 'GET',
            'header' => "Authorization: Bearer {$accessToken}\r\nAccept: application/json\r\n",
            'timeout' => 20,
            'ignore_errors' => true,
        ],
        'ssl' => [
            'verify_peer' => true,
            'verify_peer_name' => true,
        ],
    ]);
    $raw = @file_get_contents($url, false, $ctx);
    if ($raw === false || $raw === '') {
        return null;
    }
    $data = json_decode($raw, true);

    return is_array($data) ? $data : null;
}

function ia_google_oauth_start(string $redirect): never
{
    if (!ia_google_oauth_enabled()) {
        ia_flash('pub_error', 'Вход через Google не настроен.');
        ia_redirect(ia_public_url('login.php?form=1'));
    }

    ia_google_ensure_schema();

    $state = bin2hex(random_bytes(16));
    $_SESSION['ia_google_oauth_state'] = $state;
    $_SESSION['ia_google_oauth_redirect'] = ia_public_safe_redirect_full($redirect);

    $params = http_build_query([
        'client_id' => ia_google_oauth_client_id(),
        'redirect_uri' => ia_google_oauth_redirect_uri(),
        'response_type' => 'code',
        'scope' => 'openid email profile',
        'state' => $state,
        'access_type' => 'online',
        'prompt' => 'select_account',
    ]);

    ia_redirect('https://accounts.google.com/o/oauth2/v2/auth?' . $params);
}

function ia_google_oauth_handle_callback(): never
{
    if (!ia_google_oauth_enabled()) {
        ia_flash('pub_error', 'Вход через Google не настроен.');
        ia_redirect(ia_public_url('login.php?form=1'));
    }

    ia_google_ensure_schema();

    $oauthError = trim((string) ($_GET['error'] ?? ''));
    if ($oauthError !== '') {
        ia_flash('pub_error', 'Вход через Google отменён.');
        ia_redirect(ia_public_url('login.php?form=1'));
    }

    $state = (string) ($_GET['state'] ?? '');
    $expectedState = (string) ($_SESSION['ia_google_oauth_state'] ?? '');
    unset($_SESSION['ia_google_oauth_state']);
    if ($expectedState === '' || $state === '' || !hash_equals($expectedState, $state)) {
        ia_flash('pub_error', 'Неверный ответ Google. Попробуйте снова.');
        ia_redirect(ia_public_url('login.php?form=1'));
    }

    $code = trim((string) ($_GET['code'] ?? ''));
    if ($code === '') {
        ia_flash('pub_error', 'Код авторизации не получен.');
        ia_redirect(ia_public_url('login.php?form=1'));
    }

    $token = ia_google_http_post('https://oauth2.googleapis.com/token', [
        'code' => $code,
        'client_id' => ia_google_oauth_client_id(),
        'client_secret' => ia_google_oauth_client_secret(),
        'redirect_uri' => ia_google_oauth_redirect_uri(),
        'grant_type' => 'authorization_code',
    ]);

    if ($token === null || empty($token['access_token'])) {
        error_log('google_oauth token: ' . json_encode($token));
        ia_flash('pub_error', 'Не удалось получить токен Google.');
        ia_redirect(ia_public_url('login.php?form=1'));
    }

    $profile = ia_google_http_get(
        'https://www.googleapis.com/oauth2/v3/userinfo',
        (string) $token['access_token']
    );

    if ($profile === null || empty($profile['sub'])) {
        error_log('google_oauth profile: ' . json_encode($profile));
        ia_flash('pub_error', 'Не удалось получить профиль Google.');
        ia_redirect(ia_public_url('login.php?form=1'));
    }

    $redirect = ia_public_safe_redirect_full((string) ($_SESSION['ia_google_oauth_redirect'] ?? 'index.php'));
    unset($_SESSION['ia_google_oauth_redirect']);

    $result = ia_platform_login_or_register_google($profile);
    if (empty($result['ok']) || empty($result['user'])) {
        ia_flash('pub_error', (string) ($result['error'] ?? 'Не удалось войти через Google.'));
        ia_redirect(ia_public_url('login.php?form=1'));
    }

    ia_platform_login_user($result['user']);
    ia_redirect(ia_public_url($redirect));
}
