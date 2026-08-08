<?php

declare(strict_types=1);

final class Auth
{
    private static ?array $user = null;
    private static ?array $player = null;

    public static function boot(): void
    {
        if (!empty($_SESSION['user_id'])) {
            $stmt = Database::pdo()->prepare('SELECT * FROM users WHERE id = ? AND is_active = 1');
            $stmt->execute([(int) $_SESSION['user_id']]);
            $user = $stmt->fetch();
            if ($user) {
                self::$user = $user;
                $p = Database::pdo()->prepare('SELECT * FROM players WHERE user_id = ?');
                $p->execute([(int) $user['id']]);
                $player = $p->fetch();
                self::$player = $player ?: null;
            } else {
                unset($_SESSION['user_id']);
            }
        }
    }

    public static function user(): ?array
    {
        return self::$user;
    }

    public static function player(): ?array
    {
        return self::$player;
    }

    public static function check(): bool
    {
        return self::$user !== null;
    }

    public static function isAdmin(): bool
    {
        return self::check() && !empty(self::$user['is_admin']);
    }

    public static function isCommissioner(): bool
    {
        return self::check() && (!empty(self::$user['is_admin']) || !empty(self::$user['is_commissioner']));
    }

    public static function isCaptain(): bool
    {
        return self::$player && !empty(self::$player['is_captain']);
    }

    public static function isCaptainOf(int $teamId): bool
    {
        return self::isCaptain() && (int) self::$player['team_id'] === $teamId;
    }

    public static function canReportScore(array $game): bool
    {
        if (!self::check()) {
            return false;
        }
        if (self::isCommissioner()) {
            return true;
        }
        if (!self::isCaptain()) {
            return false;
        }
        $teamId = (int) self::$player['team_id'];
        return $teamId === (int) $game['home_team_id'] || $teamId === (int) $game['away_team_id'];
    }

    public static function canManageRoster(int $teamId): bool
    {
        return self::isCommissioner() || self::isCaptainOf($teamId);
    }

    public static function attempt(string $email, string $password): bool
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM users WHERE email = ? AND is_active = 1');
        $stmt->execute([strtolower(trim($email))]);
        $user = $stmt->fetch();
        if (!$user || !password_verify($password, $user['password_hash'])) {
            return false;
        }
        $_SESSION['user_id'] = (int) $user['id'];
        self::$user = $user;
        $p = Database::pdo()->prepare('SELECT * FROM players WHERE user_id = ?');
        $p->execute([(int) $user['id']]);
        self::$player = $p->fetch() ?: null;
        return true;
    }

    public static function logout(): void
    {
        unset($_SESSION['user_id']);
        self::$user = null;
        self::$player = null;
    }

    public static function requireLogin(): void
    {
        if (!self::check()) {
            flash('error', 'Please log in to continue.');
            redirect('/login');
        }
    }

    public static function requireCommissioner(): void
    {
        self::requireLogin();
        if (!self::isCommissioner()) {
            http_response_code(403);
            echo 'Forbidden';
            exit;
        }
    }

    public static function requireAdmin(): void
    {
        self::requireLogin();
        if (!self::isAdmin()) {
            http_response_code(403);
            echo 'Forbidden';
            exit;
        }
    }

    public static function displayName(?array $user = null): string
    {
        $user ??= self::$user;
        if (!$user) {
            return '';
        }
        $full = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
        return $full !== '' ? $full : (string) $user['email'];
    }
}
