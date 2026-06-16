<?php
$mysqli = new mysqli('localhost', 'root', '', 'quanly_chitieu');
if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}
$result = $mysqli->query('SHOW TABLES');
while ($row = $result->fetch_row()) {
    $table = $row[0];
    echo "TABLE: " . $table . "\n";
    $res = $mysqli->query("DESCRIBE " . $table);
    while ($r = $res->fetch_assoc()) {
        echo "  " . $r['Field'] . " - " . $r['Type'] . "\n";
    }
}
?>
