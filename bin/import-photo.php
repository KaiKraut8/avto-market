<?php
// Attach an image file to a car from the command line, with the same checks as the upload form.
// Large images are scaled down to 1600 px on the longest side.
//
// Usage: php bin/import-photo.php <car_id> <image file>

if ($argc !== 3) {
    fwrite(STDERR, "Usage: php bin/import-photo.php <car_id> <image file>\n");
    exit(1);
}

$carId = filter_var($argv[1], FILTER_VALIDATE_INT);
$source = $argv[2];
if ($carId === false || !is_file($source)) {
    fwrite(STDERR, "Bad car id or file not found.\n");
    exit(1);
}

mysqli_report(MYSQLI_REPORT_OFF);
$conn = new mysqli("db", "app", "secret", "app");
if ($conn->connect_error) {
    fwrite(STDERR, "Connection failed: " . $conn->connect_error . "\n");
    exit(1);
}

$stmt = $conn->prepare("SELECT name FROM cars WHERE id = ? AND deleted_at IS NULL");
$stmt->bind_param("i", $carId);
$stmt->execute();
$car = $stmt->get_result()->fetch_assoc();
if (!$car) {
    fwrite(STDERR, "Car #$carId not found.\n");
    exit(1);
}

$allowed = ["image/jpeg" => "jpg", "image/png" => "png", "image/webp" => "webp", "image/gif" => "gif"];
$mime = (new finfo(FILEINFO_MIME_TYPE))->file($source);
$size = getimagesize($source);
if (!isset($allowed[$mime]) || !$size) {
    fwrite(STDERR, "Not a JPG, PNG, WebP or GIF image: $source ($mime)\n");
    exit(1);
}

$filename = bin2hex(random_bytes(16)) . "." . $allowed[$mime];
$target = __DIR__ . "/../public/uploads/" . $filename;

[$w, $h] = $size;
$scale = min(1, 1600 / max($w, $h));
if ($scale < 1 && $mime !== "image/gif") {
    $img = match ($mime) {
        "image/jpeg" => imagecreatefromjpeg($source),
        "image/png" => imagecreatefrompng($source),
        "image/webp" => imagecreatefromwebp($source),
    };
    $resized = imagescale($img, (int)round($w * $scale), (int)round($h * $scale));
    if ($mime === "image/png") {
        imagesavealpha($resized, true);
    }
    match ($mime) {
        "image/jpeg" => imagejpeg($resized, $target, 85),
        "image/png" => imagepng($resized, $target),
        "image/webp" => imagewebp($resized, $target, 85),
    };
} else {
    copy($source, $target);
}
chmod($target, 0644);

$stmt = $conn->prepare("INSERT INTO car_photos (car_id, filename) VALUES (?, ?)");
$stmt->bind_param("is", $carId, $filename);
$stmt->execute();

echo "Added " . basename($source) . " to #$carId {$car["name"]} as uploads/$filename\n";
