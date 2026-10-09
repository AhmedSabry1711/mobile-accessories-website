<?php
/**
 * Mobi Boost — Media upload API (admin).
 * Accepts product images (jpg, png, webp, gif) and ad videos (mp4, webm).
 *
 * POST /api/upload.php  (multipart/form-data, field "file")
 */
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    fail('Method not allowed', 405);
}

require_admin();

if (empty($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    fail('File upload failed', 400);
}

$file = $_FILES['file'];

if ($file['size'] > $CONFIG['maxUploadBytes']) {
    fail('File is too large (max 8 MB)', 400);
}

$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime = (string)$finfo->file($file['tmp_name']);

$allowed = [
    'image/jpeg' => 'jpg',
    'image/png' => 'png',
    'image/webp' => 'webp',
    'image/gif' => 'gif',
    'video/mp4' => 'mp4',
    'video/webm' => 'webm',
];

if (!isset($allowed[$mime])) {
    fail('Unsupported file type. Allowed: JPG, PNG, WebP, GIF, MP4, WebM', 400);
}

if (!is_dir($CONFIG['uploadDir']) && !mkdir($CONFIG['uploadDir'], 0755, true) && !is_dir($CONFIG['uploadDir'])) {
    fail('Upload directory is not writable', 500);
}

$filename = bin2hex(random_bytes(8)) . '.' . $allowed[$mime];
$destination = $CONFIG['uploadDir'] . '/' . $filename;

if (!move_uploaded_file($file['tmp_name'], $destination)) {
    fail('Could not save the uploaded file', 500);
}

ok(['url' => $CONFIG['uploadUrl'] . '/' . $filename, 'type' => str_starts_with($mime, 'video/') ? 'video' : 'image']);
