<?php
declare(strict_types=1);

require dirname(__DIR__, 2) . '/app/bootstrap.php';
requireRole('admin');

$type = (string) ($_GET['type'] ?? '');
$allowed = [
    'shelters' => 'SELECT id, name, contact_email, contact_phone, city, region, country, status, created_at FROM shelters ORDER BY created_at DESC',
    'animals' => 'SELECT a.id, a.name, a.species, a.breed, a.age, a.gender, a.size, a.status, s.name AS shelter, a.views_count, a.favorites_count, a.created_at FROM animals a INNER JOIN shelters s ON s.id = a.shelter_id ORDER BY a.created_at DESC',
    'inquiries' => 'SELECT i.id, i.name, i.email, i.phone, a.name AS animal, s.name AS shelter, i.status, i.appointment_at, i.created_at FROM inquiries i LEFT JOIN animals a ON a.id = i.animal_id LEFT JOIN shelters s ON s.id = i.shelter_id ORDER BY i.created_at DESC',
    'applications' => 'SELECT aa.id, aa.name, aa.email, aa.phone, a.name AS animal, s.name AS shelter, aa.status, aa.appointment_at, aa.created_at FROM adoption_applications aa INNER JOIN animals a ON a.id = aa.animal_id INNER JOIN shelters s ON s.id = aa.shelter_id ORDER BY aa.created_at DESC',
    'reports' => 'SELECT r.id, r.reporter_name, r.reporter_email, a.name AS animal, s.name AS shelter, r.status, r.created_at FROM reports r LEFT JOIN animals a ON a.id = r.animal_id LEFT JOIN shelters s ON s.id = r.shelter_id ORDER BY r.created_at DESC',
];

if (!array_key_exists($type, $allowed)) {
    http_response_code(404);
    exit('Export not found.');
}

$pdo = db();
$rows = $pdo->query($allowed[$type])->fetchAll();

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $type . '-' . date('Ymd-His') . '.csv"');

$output = fopen('php://output', 'wb');

if ($output === false) {
    exit;
}

if ($rows !== []) {
    fputcsv($output, array_keys($rows[0]));
}

foreach ($rows as $row) {
    fputcsv($output, $row);
}

fclose($output);
exit;
