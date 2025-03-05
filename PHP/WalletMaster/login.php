<?php
session_start();
include('../conexion.php'); // Archivo de conexión a la base de datos

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = $_POST['email'];
    $password = $_POST['password'];

    // Consulta a la base de datos para verificar el usuario
    $query = "SELECT * FROM users WHERE email = ? LIMIT 1";
    $stmt = $conexion->prepare($query);
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();

        // Verifica la contraseña
        if (password_verify($password, $user['password_hash'])) {
            // Autenticación exitosa
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['email'] = $user['email'];

            // Redirigir a la página principal
            header('Location: appweb.php');
        } else {
            // Contraseña incorrecta
            echo "Contraseña incorrecta";
        }
    } else {
        // Usuario no encontrado
        echo "Usuario no encontrado";
    }
}
?>


<script src="../../JS/scriptsapp.js"></script>