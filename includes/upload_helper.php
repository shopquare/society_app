<?php
// includes/upload_helper.php
// Centralized secure image and document upload handler with MIME magic verification.

/**
 * Validates and persists a Base64 data URL asset cleanly into /uploads/{$subDir}/.
 * When $isPrivate is true, stores inside /uploads/private_docs/ with strict .htaccess denial.
 *
 * @return array ['filename' => string, 'mime' => string, 'size' => int] or throws RuntimeException
 */
function saveBase64Upload(
    string $dataUrl,
    string $subDir,
    array $allowedMimes = ['image/jpeg', 'image/png', 'image/webp'],
    int $maxBytes = 10485760,
    bool $isPrivate = false
): array {
    if (!preg_match('#^data:([a-zA-Z0-9/+.-]+);base64,(.+)$#s', $dataUrl, $matches)) {
        throw new InvalidArgumentException('Invalid uploaded file encoding.');
    }

    $binary = base64_decode($matches[2], true);
    if ($binary === false || $binary === '') {
        throw new InvalidArgumentException('Corrupted uploaded file payload.');
    }
    if (strlen($binary) > $maxBytes) {
        $mb = round($maxBytes / 1048576, 1);
        throw new InvalidArgumentException("File size exceeds the {$mb} MB limit.");
    }

    // Real binary MIME detection (never trust client header alone)
    $mime = null;
    if (function_exists('finfo_open')) {
        $finfo = @finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo) {
            $mime = @finfo_buffer($finfo, $binary) ?: null;
            @finfo_close($finfo);
        }
    }
    if ($mime === null || $mime === 'application/octet-stream') {
        $imgInfo = @getimagesizefromstring($binary);
        if ($imgInfo && !empty($imgInfo['mime'])) {
            $mime = $imgInfo['mime'];
        }
    }

    if ($mime === null || !in_array($mime, $allowedMimes, true)) {
        throw new InvalidArgumentException('Unsupported or unsafe file type.');
    }

    $extMap = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'application/pdf' => 'pdf',
    ];
    $ext = $extMap[$mime] ?? null;
    if ($ext === null) {
        throw new InvalidArgumentException('Unsupported file extension.');
    }

    $cleanSubDir = preg_replace('/[^a-zA-Z0-9_-]/', '', $subDir);
    $rootUploads = dirname(__DIR__) . '/uploads/';
    $targetDir = $isPrivate
        ? ($rootUploads . 'private_docs/' . $cleanSubDir . '/')
        : ($rootUploads . $cleanSubDir . '/');

    if (!is_dir($targetDir)) {
        if (!@mkdir($targetDir, 0755, true) && !is_dir($targetDir)) {
            throw new RuntimeException('Unable to initialize upload directory.');
        }
    }

    if ($isPrivate) {
        $ht = $rootUploads . 'private_docs/.htaccess';
        if (!is_file($ht)) {
            @file_put_contents($ht, "Require all denied\nDeny from all\n");
        }
    }

    $fileName = date('Ymd_His') . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
    if (file_put_contents($targetDir . $fileName, $binary) === false) {
        throw new RuntimeException('Failed to write uploaded file.');
    }

    return [
        'filename' => $fileName,
        'relative_path' => ($isPrivate ? 'private_docs/' : '') . $cleanSubDir . '/' . $fileName,
        'mime' => $mime,
        'size' => strlen($binary),
    ];
}
