<?php

declare(strict_types=1);

function config(string $key, mixed $default = null): mixed
{
    static $cfg;
    if ($cfg === null) {
        $cfg = require ROOT_PATH . '/config/config.php';
        $local = ROOT_PATH . '/config/config.local.php';
        if (is_file($local)) {
            $cfg = array_merge($cfg, require $local);
        }
    }
    return $cfg[$key] ?? $default;
}

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function redirect(string $path): never
{
    $base = rtrim((string) config('base_url', ''), '/');
    header('Location: ' . $base . $path);
    exit;
}

function url(string $path = '/'): string
{
    $base = rtrim((string) config('base_url', ''), '/');
    if ($path === '') {
        $path = '/';
    }
    if ($path[0] !== '/') {
        $path = '/' . $path;
    }
    return $base . $path;
}

function flash(string $type, string $message): void
{
    $_SESSION['_flash'][] = ['type' => $type, 'message' => $message];
}

function consume_flashes(): array
{
    $messages = $_SESSION['_flash'] ?? [];
    unset($_SESSION['_flash']);
    return $messages;
}

function csrf_token(): string
{
    if (empty($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

function verify_csrf(): void
{
    $token = $_POST['_csrf'] ?? '';
    if (!is_string($token) || !hash_equals(csrf_token(), $token)) {
        http_response_code(400);
        echo 'Invalid CSRF token.';
        exit;
    }
}

function render(string $template, array $data = []): void
{
    extract($data, EXTR_SKIP);
    $flashes = consume_flashes();
    $currentUser = Auth::user();
    $navIsAdmin = Auth::isAdmin();
    $navIsCommissioner = Auth::isCommissioner();
    $activeSeason = Database::activeSeason();
    $appName = (string) config('app_name', 'Hoops League');
    $templateFile = ROOT_PATH . '/templates/' . $template . '.php';
    if (!is_file($templateFile)) {
        http_response_code(500);
        echo 'Template not found.';
        exit;
    }
    require ROOT_PATH . '/templates/layout.php';
}

function request_path(): string
{
    $uri = $_SERVER['REQUEST_URI'] ?? '/';
    $path = parse_url($uri, PHP_URL_PATH) ?: '/';
    $base = rtrim((string) config('base_url', ''), '/');
    if ($base !== '' && str_starts_with($path, $base)) {
        $path = substr($path, strlen($base)) ?: '/';
    }
    return $path === '' ? '/' : $path;
}

function method(): string
{
    return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
}

function format_tipoff(string $utc, string $format = 'D, M j · g:i A'): string
{
    $dt = new DateTimeImmutable($utc, new DateTimeZone('UTC'));
    return $dt->setTimezone(new DateTimeZone((string) config('timezone')))->format($format);
}

function format_avg(?float $value, int $decimals = 1): string
{
    return $value === null ? '—' : number_format($value, $decimals);
}

function format_diff(?float $value, int $decimals = 1): string
{
    if ($value === null) {
        return '—';
    }
    $formatted = number_format($value, $decimals);
    return $value > 0 ? '+' . $formatted : $formatted;
}

function tipoff_local_input(?string $utc): string
{
    if (!$utc) {
        return '';
    }
    $dt = new DateTimeImmutable($utc, new DateTimeZone('UTC'));
    return $dt->setTimezone(new DateTimeZone((string) config('timezone')))->format('Y-m-d\TH:i');
}

/**
 * Set left/right display sides for a game: better seed first when known, else A–Z by team name.
 * Expects home_name / away_name (and optional scores) already set.
 *
 * @param array<string, mixed> $row
 * @return array<string, mixed>
 */
function apply_matchup_display_order(array $row, ?int $homeSeed = null, ?int $awaySeed = null): array
{
    $homeFirst = true;
    if ($homeSeed !== null && $awaySeed !== null) {
        $homeFirst = $homeSeed <= $awaySeed;
    } else {
        $homeFirst = strcasecmp((string) ($row['home_name'] ?? ''), (string) ($row['away_name'] ?? '')) <= 0;
    }

    if ($homeFirst) {
        $row['left_name'] = $row['home_name'] ?? '';
        $row['right_name'] = $row['away_name'] ?? '';
        $row['left_abbrev'] = $row['home_abbrev'] ?? '';
        $row['right_abbrev'] = $row['away_abbrev'] ?? '';
        $row['left_score'] = $row['home_score'] ?? null;
        $row['right_score'] = $row['away_score'] ?? null;
        $row['left_seed'] = $homeSeed;
        $row['right_seed'] = $awaySeed;
        $row['left_is_home'] = true;
        $row['left_team_id'] = $row['home_team_id'] ?? null;
        $row['right_team_id'] = $row['away_team_id'] ?? null;
    } else {
        $row['left_name'] = $row['away_name'] ?? '';
        $row['right_name'] = $row['home_name'] ?? '';
        $row['left_abbrev'] = $row['away_abbrev'] ?? '';
        $row['right_abbrev'] = $row['home_abbrev'] ?? '';
        $row['left_score'] = $row['away_score'] ?? null;
        $row['right_score'] = $row['home_score'] ?? null;
        $row['left_seed'] = $awaySeed;
        $row['right_seed'] = $homeSeed;
        $row['left_is_home'] = false;
        $row['left_team_id'] = $row['away_team_id'] ?? null;
        $row['right_team_id'] = $row['home_team_id'] ?? null;
    }
    $row['matchup_label'] = $row['left_name'] . ' vs ' . $row['right_name'];
    return $row;
}
