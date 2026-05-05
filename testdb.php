<?php
include "db.php";

if ($conn) {
    echo json_encode(["status"=>"success","message"=>"DB connected"]);
}
?>
