<?php
declare(strict_types=1);

if (!defined('APP_ROOT')) {
    define('APP_ROOT', dirname(__DIR__, 2));
}

function env(string $key, mixed $default = null): mixed
{
    $value = getenv($key);

    return $value === false ? $default : $value;
}

function config(string $key, mixed $default = null): mixed
{
    static $appConfig = null;
    static $dbConfig = null;

    if ($appConfig === null) {
        $appConfig = require APP_ROOT . '/config/app.php';
    }

    if ($dbConfig === null) {
        $dbConfig = require APP_ROOT . '/config/database.php';
    }

    $segments = explode('.', $key);

    if ($segments[0] === 'database') {
        $source = $dbConfig;
        $segments = array_slice($segments, 1);
    } else {
        $source = $appConfig;
    }

    $value = $source;

    foreach ($segments as $segment) {
        if (!is_array($value) || !array_key_exists($segment, $value)) {
            return $default;
        }

        $value = $value[$segment];
    }

    return $value;
}

function app_is_secure_request(): bool
{
    return (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off')
        || (int) ($_SERVER['SERVER_PORT'] ?? 0) === 443;
}

function app_start(): void
{
    if (defined('APP_STARTED')) {
        return;
    }

    define('APP_STARTED', true);

    if (!headers_sent()) {
        header('X-Frame-Options: SAMEORIGIN');
        header('X-Content-Type-Options: nosniff');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
    }

    if (session_status() !== PHP_SESSION_ACTIVE) {
        ini_set('session.use_strict_mode', '1');
        ini_set('session.cookie_httponly', '1');
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'domain' => '',
            'secure' => app_is_secure_request(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }
}

function db_config(): array
{
    return config('database', []);
}

function db_dsn(?string $database = null): string
{
    $config = db_config();
    $charset = $config['charset'] ?? 'utf8mb4';
    $dsn = 'mysql:host=' . $config['host'] . ';port=' . (int) $config['port'] . ';charset=' . $charset;

    if ($database !== null && $database !== '') {
        $dsn .= ';dbname=' . $database;
    }

    return $dsn;
}

function db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $config = db_config();
    $pdo = new PDO(db_dsn((string) $config['database']), (string) $config['username'], (string) $config['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    return $pdo;
}

function db_available(): bool
{
    try {
        db()->query('SELECT 1');

        return true;
    } catch (Throwable) {
        return false;
    }
}

function db_table_exists(string $table): bool
{
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $table)) {
        return false;
    }

    try {
        $statement = db()->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?');
        $statement->execute([$table]);

        return (int) $statement->fetchColumn() > 0;
    } catch (Throwable) {
        return false;
    }
}

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function slugify(string $value): string
{
    $value = strtolower(trim($value));
    $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? '';

    return trim($value, '-');
}

function app_base_path(): string
{
    $configured = trim((string) config('base_path', ''));

    if ($configured !== '') {
        return '/' . trim($configured, '/');
    }

    $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    $publicPos = strpos($script, '/public/');

    if ($publicPos !== false) {
        return rtrim(substr($script, 0, $publicPos + 7), '/');
    }

    return '';
}

function url(string $path = ''): string
{
    if (preg_match('/^https?:\/\//i', $path)) {
        return $path;
    }

    return app_base_path() . '/' . ltrim($path, '/');
}

function asset(string $path): string
{
    return url('/assets/' . ltrim($path, '/'));
}

function uploaded_url(?string $path): string
{
    $path = trim((string) $path);

    if ($path === '') {
        return '';
    }

    return url('/' . ltrim($path, '/'));
}

function redirect(string $path): void
{
    header('Location: ' . url($path));
    exit;
}

function isLoggedIn(): bool
{
    return isset($_SESSION['user_id'], $_SESSION['user']) && is_array($_SESSION['user']);
}

function currentUser(): ?array
{
    return isLoggedIn() ? $_SESSION['user'] : null;
}

function requireRole(string ...$roles): void
{
    if (!isLoggedIn()) {
        redirect('/login.php');
    }

    $user = currentUser();

    if ($user === null || !in_array($user['role'], $roles, true) || ($user['status'] ?? '') === 'suspended') {
        http_response_code(403);
        exit('Forbidden');
    }
}

function csrfToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function verifyCsrf(): void
{
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) {
        http_response_code(419);
        exit('CSRF validation failed');
    }
}

function flash(string $key, ?string $message = null): ?string
{
    if ($message !== null) {
        $_SESSION['flash'][$key] = $message;

        return null;
    }

    $value = $_SESSION['flash'][$key] ?? null;
    unset($_SESSION['flash'][$key]);

    return $value;
}

function selected(mixed $current, mixed $expected): string
{
    return (string) $current === (string) $expected ? 'selected' : '';
}

function checked(mixed $current): string
{
    return (bool) $current ? 'checked' : '';
}

function status_label(string $status): string
{
    return ucwords(str_replace('_', ' ', $status));
}

function bool_label(mixed $value): string
{
    return (int) $value === 1 ? 'Yes' : 'No';
}

function client_identity_hash(): string
{
    $ip = trim(explode(',', (string) ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? ''))[0] ?? '');
    $agent = (string) ($_SERVER['HTTP_USER_AGENT'] ?? '');
    $salt = (string) env('APP_KEY', config('database.database', 'ngo_pet_adoption'));

    return hash_hmac('sha256', $ip . '|' . $agent, $salt);
}

function audit_log(PDO $pdo, string $action, string $targetType, ?int $targetId = null, array $details = []): void
{
    try {
        $statement = $pdo->prepare(
            'INSERT INTO activity_log (actor_id, action, target_type, target_id, details)
            VALUES (:actor_id, :action, :target_type, :target_id, :details)'
        );
        $statement->execute([
            'actor_id' => currentUser()['id'] ?? null,
            'action' => substr($action, 0, 120),
            'target_type' => substr($targetType, 0, 80),
            'target_id' => $targetId,
            'details' => $details === [] ? null : json_encode($details, JSON_UNESCAPED_SLASHES),
        ]);
    } catch (Throwable) {
        return;
    }
}

function excerpt(?string $value, int $length = 140): string
{
    $value = trim((string) $value);

    if ($value === '') {
        return '';
    }

    if (function_exists('mb_strlen') && function_exists('mb_substr')) {
        return mb_strlen($value) > $length ? mb_substr($value, 0, $length - 1) . '...' : $value;
    }

    return strlen($value) > $length ? substr($value, 0, $length - 1) . '...' : $value;
}
