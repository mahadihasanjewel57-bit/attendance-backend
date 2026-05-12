<?php

include "db.php";

$empId = $_GET['empid'] ?? '';

if ($empId == '') {
    exit;
}

$sql = "SELECT pyeimage FROM pyempmas WHERE pyempcde = ? LIMIT 1";

$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $empId);
$stmt->execute();

$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {

    // Try to auto-detect image type
    $finfo = finfo_open();
    $mime = finfo_buffer($finfo, $row['pyeimage'], FILEINFO_MIME_TYPE);

    header("Content-Type: " . $mime);

    echo $row['pyeimage'];

} else {
    http_response_code(404);
}
?>
