<?php
declare(strict_types=1);

class AnimalRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function search(array $filters, int $page, int $perPage): array
    {
        [$where, $params] = $this->publicWhere($filters);
        $offset = max(0, ($page - 1) * $perPage);
        $limit = max(1, min(60, $perPage));
        $countSql = "SELECT COUNT(*)
            FROM animals a
            INNER JOIN shelters s ON s.id = a.shelter_id
            WHERE {$where}";
        $count = $this->pdo->prepare($countSql);
        $count->execute($params);

        $sql = "SELECT a.*, s.name AS shelter_name, s.slug AS shelter_slug, s.city, s.region, s.country,
                ai.file_path AS image_path, ai.thumbnail_path AS thumbnail_path,
                (SELECT COUNT(*) FROM votes v WHERE v.winner_animal_id = a.id) AS vote_wins
            FROM animals a
            INNER JOIN shelters s ON s.id = a.shelter_id
            LEFT JOIN animal_images ai ON ai.id = (
                SELECT ai2.id FROM animal_images ai2
                WHERE ai2.animal_id = a.id
                ORDER BY ai2.sort_order ASC, ai2.id ASC
                LIMIT 1
            )
            WHERE {$where}
            ORDER BY a.is_featured DESC, a.created_at DESC, a.id DESC
            LIMIT {$limit} OFFSET {$offset}";
        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);

        return [
            'items' => $statement->fetchAll(),
            'total' => (int) $count->fetchColumn(),
            'page' => $page,
            'per_page' => $limit,
        ];
    }

    public function featuredForHome(int $limit = 8): array
    {
        $limit = max(1, min(24, $limit));
        $sql = "SELECT a.*, s.name AS shelter_name, s.slug AS shelter_slug, s.city, s.country,
                ai.file_path AS image_path, ai.thumbnail_path AS thumbnail_path,
                (SELECT COUNT(*) FROM votes v WHERE v.winner_animal_id = a.id) AS vote_wins
            FROM animals a
            INNER JOIN shelters s ON s.id = a.shelter_id
            LEFT JOIN animal_images ai ON ai.id = (
                SELECT ai2.id FROM animal_images ai2
                WHERE ai2.animal_id = a.id
                ORDER BY ai2.sort_order ASC, ai2.id ASC
                LIMIT 1
            )
            WHERE s.status = 'approved' AND a.status IN ('available', 'reserved', 'medical_hold')
            ORDER BY a.is_featured DESC, a.created_at DESC, a.id DESC
            LIMIT {$limit}";

        return $this->pdo->query($sql)->fetchAll();
    }

    public function findPublic(int $id): ?array
    {
        $statement = $this->pdo->prepare(
            "SELECT a.*, s.name AS shelter_name, s.slug AS shelter_slug, s.description AS shelter_description,
                    s.contact_email, s.contact_phone, s.website, s.facebook_url, s.instagram_url,
                    s.city, s.region, s.country, s.logo_path,
                    (SELECT COUNT(*) FROM votes v WHERE v.winner_animal_id = a.id) AS vote_wins
            FROM animals a
            INNER JOIN shelters s ON s.id = a.shelter_id
            WHERE a.id = ? AND s.status = 'approved'
            LIMIT 1"
        );
        $statement->execute([$id]);
        $animal = $statement->fetch();

        return $animal ?: null;
    }

    public function findForShelter(int $id, int $shelterId): ?array
    {
        $statement = $this->pdo->prepare('SELECT * FROM animals WHERE id = ? AND shelter_id = ? LIMIT 1');
        $statement->execute([$id, $shelterId]);
        $animal = $statement->fetch();

        return $animal ?: null;
    }

    public function imagesForAnimal(int $animalId): array
    {
        $statement = $this->pdo->prepare('SELECT * FROM animal_images WHERE animal_id = ? ORDER BY sort_order ASC, id ASC');
        $statement->execute([$animalId]);

        return $statement->fetchAll();
    }

    public function incrementViews(int $animalId): void
    {
        $statement = $this->pdo->prepare('UPDATE animals SET views_count = views_count + 1 WHERE id = ?');
        $statement->execute([$animalId]);
    }

    public function forShelter(int $shelterId): array
    {
        $statement = $this->pdo->prepare(
            "SELECT a.*, ai.thumbnail_path
            FROM animals a
            LEFT JOIN animal_images ai ON ai.id = (
                SELECT ai2.id FROM animal_images ai2 WHERE ai2.animal_id = a.id ORDER BY ai2.sort_order ASC, ai2.id ASC LIMIT 1
            )
            WHERE a.shelter_id = ?
            ORDER BY a.created_at DESC, a.id DESC"
        );
        $statement->execute([$shelterId]);

        return $statement->fetchAll();
    }

    public function publicForShelter(int $shelterId, int $limit = 60): array
    {
        $limit = max(1, min(120, $limit));
        $statement = $this->pdo->prepare(
            "SELECT a.*, s.name AS shelter_name, s.slug AS shelter_slug, s.city, s.region, s.country,
                    ai.file_path AS image_path, ai.thumbnail_path AS thumbnail_path,
                    (SELECT COUNT(*) FROM votes v WHERE v.winner_animal_id = a.id) AS vote_wins
            FROM animals a
            INNER JOIN shelters s ON s.id = a.shelter_id
            LEFT JOIN animal_images ai ON ai.id = (
                SELECT ai2.id FROM animal_images ai2 WHERE ai2.animal_id = a.id ORDER BY ai2.sort_order ASC, ai2.id ASC LIMIT 1
            )
            WHERE a.shelter_id = ? AND s.status = 'approved' AND a.status IN ('available', 'reserved', 'medical_hold')
            ORDER BY a.is_featured DESC, a.created_at DESC, a.id DESC
            LIMIT {$limit}"
        );
        $statement->execute([$shelterId]);

        return $statement->fetchAll();
    }

    public function create(int $shelterId, array $data): int
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO animals
                (shelter_id, name, species, breed, age, gender, size, color, status,
                good_with_children, good_with_dogs, good_with_cats, energy_level, temperament,
                vaccinated, spayed_neutered, medical_conditions, special_needs, video_url, is_senior)
            VALUES
                (:shelter_id, :name, :species, :breed, :age, :gender, :size, :color, :status,
                :good_with_children, :good_with_dogs, :good_with_cats, :energy_level, :temperament,
                :vaccinated, :spayed_neutered, :medical_conditions, :special_needs, :video_url, :is_senior)'
        );
        $statement->execute($this->animalPayload($shelterId, $data));

        return (int) $this->pdo->lastInsertId();
    }

    public function update(int $animalId, int $shelterId, array $data): void
    {
        $payload = $this->animalPayload($shelterId, $data);
        $payload['id'] = $animalId;
        $statement = $this->pdo->prepare(
            'UPDATE animals
            SET name = :name, species = :species, breed = :breed, age = :age, gender = :gender, size = :size,
                color = :color, status = :status, good_with_children = :good_with_children,
                good_with_dogs = :good_with_dogs, good_with_cats = :good_with_cats, energy_level = :energy_level,
                temperament = :temperament, vaccinated = :vaccinated, spayed_neutered = :spayed_neutered,
                medical_conditions = :medical_conditions, special_needs = :special_needs, video_url = :video_url,
                is_senior = :is_senior
            WHERE id = :id AND shelter_id = :shelter_id'
        );
        $statement->execute($payload);
    }

    public function createFavorite(int $animalId, ?int $userId, string $sessionId): bool
    {
        $statement = $this->pdo->prepare('SELECT id FROM favorites WHERE animal_id = ? AND (user_id = ? OR session_id = ?) LIMIT 1');
        $statement->execute([$animalId, $userId, $sessionId]);

        if ($statement->fetch()) {
            return false;
        }

        $insert = $this->pdo->prepare('INSERT INTO favorites (animal_id, user_id, session_id) VALUES (?, ?, ?)');
        $insert->execute([$animalId, $userId, $sessionId]);
        $this->pdo->prepare('UPDATE animals SET favorites_count = favorites_count + 1 WHERE id = ?')->execute([$animalId]);

        return true;
    }

    public function votePair(): ?array
    {
        $animals = $this->featuredForHome(20);

        if (count($animals) < 2) {
            return null;
        }

        usort($animals, static fn (array $a, array $b): int => ($a['views_count'] <=> $b['views_count']) ?: ($a['created_at'] <=> $b['created_at']));

        return [$animals[0], $animals[1]];
    }

    private function publicWhere(array $filters): array
    {
        $where = ["s.status = 'approved'"];
        $params = [];
        $status = trim((string) ($filters['status'] ?? ''));

        if ($status !== '' && in_array($status, ['available', 'reserved', 'adopted', 'medical_hold'], true)) {
            $where[] = 'a.status = ?';
            $params[] = $status;
        } else {
            $where[] = "a.status IN ('available', 'reserved', 'medical_hold')";
        }

        $keyword = trim((string) ($filters['q'] ?? ''));

        if ($keyword !== '') {
            $where[] = '(a.name LIKE ? OR a.species LIKE ? OR a.breed LIKE ? OR s.name LIKE ?)';
            $needle = '%' . $keyword . '%';
            array_push($params, $needle, $needle, $needle, $needle);
        }

        foreach (['species', 'breed', 'age', 'gender', 'size'] as $field) {
            $value = trim((string) ($filters[$field] ?? ''));

            if ($value !== '') {
                $where[] = 'a.' . $field . ' LIKE ?';
                $params[] = '%' . $value . '%';
            }
        }

        $location = trim((string) ($filters['location'] ?? ''));

        if ($location !== '') {
            $where[] = '(s.city LIKE ? OR s.region LIKE ? OR s.country LIKE ?)';
            $needle = '%' . $location . '%';
            array_push($params, $needle, $needle, $needle);
        }

        if (!empty($filters['special_needs'])) {
            $where[] = "a.special_needs IS NOT NULL AND a.special_needs <> ''";
        }

        if (!empty($filters['child_friendly'])) {
            $where[] = 'a.good_with_children = 1';
        }

        return [implode(' AND ', $where), $params];
    }

    private function animalPayload(int $shelterId, array $data): array
    {
        $age = substr(trim((string) ($data['age'] ?? '')), 0, 80) ?: null;
        $ageValue = trim((string) ($data['age_value'] ?? ''));
        $ageUnit = trim((string) ($data['age_unit'] ?? ''));

        if ($ageValue !== '' && in_array($ageUnit, ['weeks', 'months', 'years'], true)) {
            $ageNumber = max(0, (int) $ageValue);
            $unit = $ageNumber === 1 ? rtrim($ageUnit, 's') : $ageUnit;
            $age = $ageNumber . ' ' . $unit;
        }

        return [
            'shelter_id' => $shelterId,
            'name' => substr(trim((string) ($data['name'] ?? '')), 0, 120),
            'species' => substr(trim((string) ($data['species'] ?? '')), 0, 80),
            'breed' => substr(trim((string) ($data['breed'] ?? '')), 0, 120) ?: null,
            'age' => $age,
            'gender' => in_array(($data['gender'] ?? ''), ['Female', 'Male', 'Unknown'], true) ? $data['gender'] : null,
            'size' => in_array(($data['size'] ?? ''), ['Small', 'Medium', 'Large', 'Extra large'], true) ? $data['size'] : null,
            'color' => substr(trim((string) ($data['color'] ?? '')), 0, 80) ?: null,
            'status' => in_array(($data['status'] ?? ''), ['available', 'reserved', 'adopted', 'medical_hold'], true) ? $data['status'] : 'available',
            'good_with_children' => !empty($data['good_with_children']) ? 1 : 0,
            'good_with_dogs' => !empty($data['good_with_dogs']) ? 1 : 0,
            'good_with_cats' => !empty($data['good_with_cats']) ? 1 : 0,
            'energy_level' => substr(trim((string) ($data['energy_level'] ?? '')), 0, 30) ?: null,
            'temperament' => substr(trim((string) ($data['temperament'] ?? '')), 0, 255) ?: null,
            'vaccinated' => !empty($data['vaccinated']) ? 1 : 0,
            'spayed_neutered' => !empty($data['spayed_neutered']) ? 1 : 0,
            'medical_conditions' => trim((string) ($data['medical_conditions'] ?? '')) ?: null,
            'special_needs' => trim((string) ($data['special_needs'] ?? '')) ?: null,
            'video_url' => filter_var(trim((string) ($data['video_url'] ?? '')), FILTER_VALIDATE_URL) ? trim((string) $data['video_url']) : null,
            'is_senior' => !empty($data['is_senior']) ? 1 : 0,
        ];
    }
}
