<?php
declare(strict_types=1);

class RateLimiter
{
    public function __construct(private PDO $pdo)
    {
    }

    public function allow(string $action, string $identityHash, int $maxAttempts, int $decaySeconds): bool
    {
        $action = substr(preg_replace('/[^a-z0-9_\-]/i', '', $action) ?? 'action', 0, 80);
        $maxAttempts = max(1, $maxAttempts);
        $decaySeconds = max(60, $decaySeconds);

        $this->pdo->beginTransaction();

        try {
            $statement = $this->pdo->prepare('SELECT * FROM rate_limits WHERE action = ? AND identity_hash = ? LIMIT 1 FOR UPDATE');
            $statement->execute([$action, $identityHash]);
            $row = $statement->fetch();

            if (!$row) {
                $insert = $this->pdo->prepare(
                    'INSERT INTO rate_limits (action, identity_hash, attempts, window_started_at, expires_at)
                    VALUES (?, ?, 1, NOW(), DATE_ADD(NOW(), INTERVAL ? SECOND))'
                );
                $insert->execute([$action, $identityHash, $decaySeconds]);
                $this->pdo->commit();

                return true;
            }

            $expiresAt = strtotime((string) $row['expires_at']);

            if ($expiresAt <= time()) {
                $reset = $this->pdo->prepare(
                    'UPDATE rate_limits
                    SET attempts = 1, window_started_at = NOW(), expires_at = DATE_ADD(NOW(), INTERVAL ? SECOND), updated_at = NOW()
                    WHERE id = ?'
                );
                $reset->execute([$decaySeconds, (int) $row['id']]);
                $this->pdo->commit();

                return true;
            }

            if ((int) $row['attempts'] >= $maxAttempts) {
                $this->pdo->commit();

                return false;
            }

            $update = $this->pdo->prepare('UPDATE rate_limits SET attempts = attempts + 1, updated_at = NOW() WHERE id = ?');
            $update->execute([(int) $row['id']]);
            $this->pdo->commit();

            return true;
        } catch (Throwable $exception) {
            $this->pdo->rollBack();
            throw $exception;
        }
    }
}
