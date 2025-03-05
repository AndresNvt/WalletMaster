<?php
session_start();
include '../conexion.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = $_POST['email'];
    $password = $_POST['password'];
    $cpassword = $_POST["cpassword"];
    $registration_date = date("Y-m-d H:i:s");

    if ($password !== $cpassword) {
        die("Las contraseñas no coinciden.");
    }

    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    $sql = "INSERT INTO users (email, password_hash, registration_time) VALUES (?, ?, ?)";
    $stmt = $conexion->prepare($sql);

    if ($stmt === false) {
        die("Error en la preparación de la consulta: " . $conexion->error);
    }

    $stmt->bind_param("sss", $email, $hashed_password, $registration_date);

    if ($stmt->execute()) {
        $stmt->close();
        $conexion->close();
        // Guardar mensaje en la sesión
        $_SESSION['mensaje'] = "Registro exitoso! Por favor, inicia sesión.";
        $_SESSION['tipo_mensaje'] = "success";
        header("Location: sesionform.php");
        exit();
    } else {
        echo "Error: " . $stmt->error;
    }

    $stmt->close();
    $conexion->close();
}
?>