<?php
declare(strict_types=1);

class UploadService
{
    public function __construct(private PDO $pdo, private array $config)
    {
    }

    public function storeAnimalImages(array $fileInput, int $animalId): array
    {
        return $this->storeImages($fileInput, 'animals', $animalId, static function (array $upload) use ($animalId): array {
            return [
                'animal_id' => $animalId,
                'file_path' => $upload['file_path'],
                'thumbnail_path' => $upload['thumbnail_path'],
                'mime_type' => $upload['mime_type'],
                'original_name' => $upload['original_name'],
            ];
        });
    }

    public function storeShelterLogo(array $fileInput, int $shelterId): ?string
    {
        $uploads = $this->storeImages($fileInput, 'shelters', $shelterId, static fn (array $upload): array => $upload, false);

        return $uploads[0]['file_path'] ?? null;
    }

    private function storeImages(array $fileInput, string $bucket, int $ownerId, callable $payloadMapper, bool $insertAnimalImage = true): array
    {
        $files = $this->normalizeFiles($fileInput);

        if ($files === []) {
            return [];
        }

        $stored = [];
        $root = rtrim((string) config('upload_dir'), '/\\') . DIRECTORY_SEPARATOR . $bucket . DIRECTORY_SEPARATOR . date('Y') . DIRECTORY_SEPARATOR . date('m');
        $thumbRoot = $root . DIRECTORY_SEPARATOR . 'thumbs';

        if (!is_dir($thumbRoot) && !mkdir($thumbRoot, 0755, true) && !is_dir($thumbRoot)) {
            throw new RuntimeException('Upload directory is not writable.');
        }

        foreach ($files as $file) {
            if ((int) $file['error'] === UPLOAD_ERR_NO_FILE) {
                continue;
            }

            $upload = $this->validate($file);
            $extension = $upload['extension'];
            $baseName = slugify(pathinfo((string) $file['name'], PATHINFO_FILENAME)) ?: 'image';
            $fileName = $baseName . '-' . $ownerId . '-' . bin2hex(random_bytes(8)) . '.' . $extension;
            $diskPath = $root . DIRECTORY_SEPARATOR . $fileName;

            if (!move_uploaded_file((string) $file['tmp_name'], $diskPath)) {
                throw new RuntimeException('The uploaded file could not be saved.');
            }

            $thumbName = pathinfo($fileName, PATHINFO_FILENAME) . '-thumb.' . $extension;
            $thumbPath = $thumbRoot . DIRECTORY_SEPARATOR . $thumbName;
            $this->resizeImage($diskPath, $diskPath, (string) $upload['mime_type'], (int) $this->config['animal_max_width']);
            $this->resizeImage($diskPath, $thumbPath, (string) $upload['mime_type'], (int) $this->config['thumbnail_width']);

            $relativeDir = 'uploads/' . $bucket . '/' . date('Y') . '/' . date('m');
            $payload = [
                'file_path' => $relativeDir . '/' . $fileName,
                'thumbnail_path' => $relativeDir . '/thumbs/' . $thumbName,
                'mime_type' => (string) $upload['mime_type'],
                'original_name' => substr((string) $file['name'], 0, 255),
            ];

            if ($insertAnimalImage) {
                $insertPayload = $payloadMapper($payload);
                $statement = $this->pdo->prepare(
                    'INSERT INTO animal_images (animal_id, file_path, thumbnail_path, mime_type, original_name, sort_order)
                    VALUES (:animal_id, :file_path, :thumbnail_path, :mime_type, :original_name, 100)'
                );
                $statement->execute($insertPayload);
            }

            $stored[] = $payloadMapper($payload);
        }

        return $stored;
    }

    private function normalizeFiles(array $file): array
    {
        if (empty($file)) {
            return [];
        }

        if (is_array($file['name'] ?? null)) {
            $files = [];

            foreach ($file['name'] as $index => $name) {
                $files[] = [
                    'name' => $name,
                    'type' => $file['type'][$index] ?? '',
                    'tmp_name' => $file['tmp_name'][$index] ?? '',
                    'error' => (int) ($file['error'][$index] ?? UPLOAD_ERR_NO_FILE),
                    'size' => (int) ($file['size'][$index] ?? 0),
                ];
            }

            return $files;
        }

        return [[
            'name' => $file['name'] ?? '',
            'type' => $file['type'] ?? '',
            'tmp_name' => $file['tmp_name'] ?? '',
            'error' => (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE),
            'size' => (int) ($file['size'] ?? 0),
        ]];
    }

    private function validate(array $file): array
    {
        if ((int) $file['error'] !== UPLOAD_ERR_OK) {
            throw new RuntimeException($this->uploadErrorMessage((int) $file['error']));
        }

        if ((int) $file['size'] <= 0 || (int) $file['size'] > (int) $this->config['max_size']) {
            throw new RuntimeException('Images must be smaller than ' . number_format((int) $this->config['max_size'] / 1024 / 1024) . 'MB.');
        }

        $extension = strtolower(pathinfo((string) $file['name'], PATHINFO_EXTENSION));
        $allowedExtensions = $this->config['allowed_extensions'];

        if (!in_array($extension, $allowedExtensions, true)) {
            throw new RuntimeException('Only JPG, PNG, and WebP images can be uploaded.');
        }

        $mime = $this->detectMime((string) $file['tmp_name']);
        $allowedMimeTypes = $this->config['allowed_mime_types'];

        if (!isset($allowedMimeTypes[$mime])) {
            throw new RuntimeException('The uploaded file is not a supported image.');
        }

        if ($mime === 'image/jpeg') {
            $extension = 'jpg';
        }

        $image = @getimagesize((string) $file['tmp_name']);

        if (!is_array($image)) {
            throw new RuntimeException('The uploaded file is not a valid image.');
        }

        return [
            'extension' => $extension,
            'mime_type' => $mime,
        ];
    }

    private function detectMime(string $path): string
    {
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);

            if ($finfo) {
                $mime = finfo_file($finfo, $path);
                finfo_close($finfo);

                if (is_string($mime) && $mime !== '') {
                    return $mime;
                }
            }
        }

        $image = @getimagesize($path);

        return is_array($image) ? (string) ($image['mime'] ?? '') : '';
    }

    private function resizeImage(string $source, string $destination, string $mime, int $maxWidth): void
    {
        if (!function_exists('imagecreatetruecolor')) {
            if ($source !== $destination) {
                copy($source, $destination);
            }

            return;
        }

        $imageInfo = @getimagesize($source);

        if (!is_array($imageInfo)) {
            if ($source !== $destination) {
                copy($source, $destination);
            }

            return;
        }

        [$width, $height] = $imageInfo;
        $targetWidth = min($width, $maxWidth);
        $targetHeight = (int) round($height * ($targetWidth / max(1, $width)));
        $sourceImage = match ($mime) {
            'image/jpeg' => @imagecreatefromjpeg($source),
            'image/png' => @imagecreatefrompng($source),
            'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($source) : false,
            default => false,
        };

        if (!$sourceImage) {
            if ($source !== $destination) {
                copy($source, $destination);
            }

            return;
        }

        $canvas = imagecreatetruecolor($targetWidth, $targetHeight);

        if ($mime === 'image/png' || $mime === 'image/webp') {
            imagealphablending($canvas, false);
            imagesavealpha($canvas, true);
        }

        imagecopyresampled($canvas, $sourceImage, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);
        $quality = (int) ($this->config['quality'] ?? 82);

        match ($mime) {
            'image/jpeg' => imagejpeg($canvas, $destination, $quality),
            'image/png' => imagepng($canvas, $destination, 7),
            'image/webp' => function_exists('imagewebp') ? imagewebp($canvas, $destination, $quality) : ($source === $destination || copy($source, $destination)),
            default => copy($source, $destination),
        };

        imagedestroy($sourceImage);
        imagedestroy($canvas);
    }

    private function uploadErrorMessage(int $error): string
    {
        return match ($error) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'One of the images is larger than the server allows.',
            UPLOAD_ERR_PARTIAL => 'One of the images only uploaded partially. Please try again.',
            UPLOAD_ERR_NO_TMP_DIR => 'The server upload folder is not available.',
            UPLOAD_ERR_CANT_WRITE => 'The image could not be written to disk.',
            UPLOAD_ERR_EXTENSION => 'The image upload was stopped by a server extension.',
            default => 'The image could not be uploaded.',
        };
    }
}
