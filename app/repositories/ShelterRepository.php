<?php
declare(strict_types=1);

class ShelterRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function findByUserId(int $userId): ?array
    {
        $statement = $this->pdo->prepare('SELECT * FROM shelters WHERE user_id = ? LIMIT 1');
        $statement->execute([$userId]);
        $shelter = $statement->fetch();

        return $shelter ?: null;
    }

    public function findPublicBySlug(string $slug): ?array
    {
        $statement = $this->pdo->prepare(
            "SELECT s.*,
                    (SELECT COUNT(*) FROM animals a WHERE a.shelter_id = s.id AND a.status IN ('available', 'reserved', 'medical_hold')) AS active_animals,
                    (SELECT COUNT(*) FROM animals a WHERE a.shelter_id = s.id AND a.status = 'adopted') AS adopted_animals
            FROM shelters s
            WHERE s.slug = ? AND s.status = 'approved'
            LIMIT 1"
        );
        $statement->execute([$slug]);
        $shelter = $statement->fetch();

        return $shelter ?: null;
    }

    public function publicList(): array
    {
        return $this->pdo->query(
            "SELECT s.*,
                    (SELECT COUNT(*) FROM animals a WHERE a.shelter_id = s.id AND a.status IN ('available', 'reserved', 'medical_hold')) AS active_animals,
                    (SELECT COUNT(*) FROM animals a WHERE a.shelter_id = s.id AND a.status = 'adopted') AS adopted_animals
            FROM shelters s
            WHERE s.status = 'approved'
            ORDER BY active_animals DESC, s.name ASC"
        )->fetchAll();
    }

    public function allForAdmin(): array
    {
        return $this->pdo->query(
            'SELECT s.*, u.email, u.status AS user_status
            FROM shelters s
            LEFT JOIN users u ON u.id = s.user_id
            ORDER BY FIELD(s.status, "applied", "pending_review", "approved", "rejected"), s.created_at DESC'
        )->fetchAll();
    }

    public function updateStatus(int $shelterId, string $status): void
    {
        if (!in_array($status, ['applied', 'pending_review', 'approved', 'rejected'], true)) {
            throw new RuntimeException('Invalid shelter status.');
        }

        $this->pdo->beginTransaction();

        try {
            $statement = $this->pdo->prepare('SELECT user_id FROM shelters WHERE id = ? LIMIT 1');
            $statement->execute([$shelterId]);
            $shelter = $statement->fetch();

            if (!$shelter) {
                throw new RuntimeException('Shelter not found.');
            }

            $this->pdo->prepare('UPDATE shelters SET status = ? WHERE id = ?')->execute([$status, $shelterId]);
            $userStatus = $status === 'approved' ? 'active' : 'pending';
            $this->pdo->prepare('UPDATE users SET status = ? WHERE id = ?')->execute([$userStatus, (int) $shelter['user_id']]);
            $this->pdo->commit();
        } catch (Throwable $exception) {
            $this->pdo->rollBack();
            throw $exception;
        }
    }

    public function updateProfile(int $shelterId, array $data): void
    {
        $payload = [
            'name' => substr(trim((string) ($data['name'] ?? '')), 0, 180),
            'description' => trim((string) ($data['description'] ?? '')) ?: null,
            'contact_email' => filter_var(trim((string) ($data['contact_email'] ?? '')), FILTER_VALIDATE_EMAIL) ? trim((string) $data['contact_email']) : null,
            'contact_phone' => substr(trim((string) ($data['contact_phone'] ?? '')), 0, 50) ?: null,
            'website' => filter_var(trim((string) ($data['website'] ?? '')), FILTER_VALIDATE_URL) ? trim((string) $data['website']) : null,
            'facebook_url' => filter_var(trim((string) ($data['facebook_url'] ?? '')), FILTER_VALIDATE_URL) ? trim((string) $data['facebook_url']) : null,
            'instagram_url' => filter_var(trim((string) ($data['instagram_url'] ?? '')), FILTER_VALIDATE_URL) ? trim((string) $data['instagram_url']) : null,
            'address' => substr(trim((string) ($data['address'] ?? '')), 0, 255) ?: null,
            'city' => substr(trim((string) ($data['city'] ?? '')), 0, 120) ?: null,
            'region' => substr(trim((string) ($data['region'] ?? '')), 0, 120) ?: null,
            'country' => substr(trim((string) ($data['country'] ?? '')), 0, 120) ?: null,
            'id' => $shelterId,
        ];

        $statement = $this->pdo->prepare(
            'UPDATE shelters
            SET name = :name, description = :description, contact_email = :contact_email, contact_phone = :contact_phone,
                website = :website, facebook_url = :facebook_url, instagram_url = :instagram_url,
                address = :address, city = :city, region = :region, country = :country
            WHERE id = :id'
        );
        $statement->execute($payload);
    }

    public function updateLogo(int $shelterId, string $path): void
    {
        $statement = $this->pdo->prepare('UPDATE shelters SET logo_path = ? WHERE id = ?');
        $statement->execute([$path, $shelterId]);
    }

    public function uniqueSlug(string $name): string
    {
        $base = slugify($name) ?: 'shelter';
        $candidate = $base;
        $counter = 2;

        while ($this->slugExists($candidate)) {
            $candidate = $base . '-' . $counter;
            $counter++;
        }

        return $candidate;
    }

    private function slugExists(string $slug): bool
    {
        $statement = $this->pdo->prepare('SELECT id FROM shelters WHERE slug = ? LIMIT 1');
        $statement->execute([$slug]);

        return (bool) $statement->fetch();
    }
}
