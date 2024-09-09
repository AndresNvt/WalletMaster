<?php

include 'conexion.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $fname = $_POST['fname'];
    $email = $_POST['email'];
    $phone = $_POST['phone'];
    $dbirth = $_POST['dbirth'];
    $gender = $_POST['gender'];
    $department = $_POST["department"];
    $city = $_POST["city"];
    $password = $_POST['password'];
    $cpassword = $_POST["cpassword"];
    $registration_date = date("Y-m-d H:i:s");

    if ($password !== $cpassword) {
        die("Las contraseñas no coinciden.");
    }

    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    $sql = "INSERT INTO users (fname, email, phone, dbirth, gender, department, city, password, registration_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conexion->prepare($sql);

    if ($stmt === false) {
        die("Error en la preparación de la consulta: " . $conexion->error);
    }

    $stmt->bind_param("sssssssss", $fname, $email, $phone, $dbirth, $gender, $department, $city, $hashed_password, $registration_date);

    if ($stmt->execute()) {
        echo "Registro exitoso.";
    } else {
        echo "Error: " . $stmt->error;
    }

    $stmt->close();
    $conexion->close();
}
?>