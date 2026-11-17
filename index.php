
<?php
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: https://mdrrmo-constituents-zlnw.hatchable.site");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    http_response_code(204);
    exit;
}

function respond($status, $data) {
    http_response_code($status);
    echo json_encode($data);
    exit;
}

$path = parse_url($_SERVER["REQUEST_URI"], PHP_URL_PATH);
$method = $_SERVER["REQUEST_METHOD"];

// API health check
if ($path === "/health" && $method === "GET") {
    respond(200, [
        "success" => true,
        "message" => "MDRRMO Emergency API is running."
    ]);
}

// Emergency report submission
if ($path !== "/api/reports" || $method !== "POST") {
    respond(404, [
        "success" => false,
        "message" => "API endpoint not found."
    ]);
}

$type = trim($_POST["emergency_type"] ?? "");
$description = trim($_POST["description"] ?? "");
$persons = filter_var(
    $_POST["persons_affected"] ?? null,
    FILTER_VALIDATE_INT
);

$latitude = filter_var($_POST["latitude"] ?? null, FILTER_VALIDATE_FLOAT);
$longitude = filter_var($_POST["longitude"] ?? null, FILTER_VALIDATE_FLOAT);
$accuracy = filter_var($_POST["gps_accuracy"] ?? null, FILTER_VALIDATE_FLOAT);

if (
    $type === "" ||
    $description === "" ||
    $persons === false || $persons < 0 ||
    $latitude === false || $latitude < -90 || $latitude > 90 ||
    $longitude === false || $longitude < -180 || $longitude > 180 ||
    $accuracy === false || $accuracy < 0
) {
    respond(422, [
        "success" => false,
        "message" => "Please provide valid and complete report details."
    ]);
}

if (strlen($description) > 3000) {
    respond(422, [
        "success" => false,
        "message" => "Description is too long."
    ]);
}

$storage = __DIR__ . "/storage";
$uploads = __DIR__ . "/uploads";

foreach ([$storage, $uploads] as $folder) {
    if (!is_dir($folder) && !mkdir($folder, 0755, true)) {
        respond(500, [
            "success" => false,
            "message" => "Unable to prepare storage."
        ]);
    }
}

$photoName = null;

// Optional photo upload
if (isset($_FILES["incident_photo"]) &&
    $_FILES["incident_photo"]["error"] !== UPLOAD_ERR_NO_FILE) {

    $photo = $_FILES["incident_photo"];

    if ($photo["error"] !== UPLOAD_ERR_OK ||
        $photo["size"] > 5 * 1024 * 1024) {
        respond(422, [
            "success" => false,
            "message" => "Photo upload failed or exceeds 5 MB."
        ]);
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($photo["tmp_name"]);

    $allowed = [
        "image/jpeg" => "jpg",
        "image/png" => "png",
        "image/webp" => "webp"
    ];

    if (!isset($allowed[$mime])) {
        respond(422, [
            "success" => false,
            "message" => "Only JPG, PNG, and WEBP photos are allowed."
        ]);
    }

    $photoName = bin2hex(random_bytes(16)) . "." . $allowed[$mime];

    if (!move_uploaded_file(
        $photo["tmp_name"],
        $uploads . "/" . $photoName
    )) {
        respond(500, [
            "success" => false,
            "message" => "Unable to save incident photo."
        ]);
    }
}

$report = [
    "report_id" => bin2hex(random_bytes(8)),
    "emergency_type" => $type,
    "description" => $description,
    "persons_affected" => $persons,
    "latitude" => (float)$latitude,
    "longitude" => (float)$longitude,
    "gps_accuracy" => (float)$accuracy,
    "incident_photo" => $photoName,
    "submitted_at" => gmdate("c")
];

// Save report as JSON
$file = $storage . "/reports.json";

$handle = fopen($file, "c+");

if (!$handle) {
    respond(500, [
        "success" => false,
        "message" => "Unable to save emergency report."
    ]);
}

if (!flock($handle, LOCK_EX)) {
    fclose($handle);
    respond(500, [
        "success" => false,
        "message" => "Unable to lock report storage."
    ]);
}

$contents = stream_get_contents($handle);
$reports = $contents ? json_decode($contents, true) : [];

if (!is_array($reports)) {
    $reports = [];
}

$reports[] = $report;

rewind($handle);
ftruncate($handle, 0);

$saved = fwrite(
    $handle,
    json_encode($reports, JSON_PRETTY_PRINT)
);

fflush($handle);
flock($handle, LOCK_UN);
fclose($handle);

if ($saved === false) {
    respond(500, [
        "success" => false,
        "message" => "Report could not be saved."
    ]);
}

respond(201, [
    "success" => true,
    "message" => "Emergency report submitted successfully.",
    "report_id" => $report["report_id"]
]);
