<?php
$mysqli = new mysqli("localhost", "root", "", "Metalcnc");

if ($mysqli->connect_error) {
    die("Error de conexión: " . $mysqli->connect_error);
}
?>