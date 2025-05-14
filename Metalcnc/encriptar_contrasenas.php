<?php
require 'db_connection.php'; // Incluye la conexión a la base de datos

$query = "SELECT id_usuario, contrasena FROM usuarios";
$result = $mysqli->query($query);

while ($row = $result->fetch_assoc()) {
    $id = $row['id_usuario'];
    $contrasena_plana = $row['contrasena'];
    $contrasena_encriptada = password_hash($contrasena_plana, PASSWORD_DEFAULT);

    // Actualiza la contraseña en la base de datos
    $update_query = "UPDATE usuarios SET contrasena = ? WHERE id_usuario = ?";
    $stmt = $mysqli->prepare($update_query);
    $stmt->bind_param("si", $contrasena_encriptada, $id);
    $stmt->execute();
}

echo "Contraseñas encriptadas correctamente.";
?>