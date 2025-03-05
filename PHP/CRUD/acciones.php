<?php

session_start();
require 'conexion.php';

if (isset($_POST['create_usuario'])) {
    $full_name = mysqli_real_escape_string($conexion, trim($_POST['full_name']));
    $email = mysqli_real_escape_string($conexion, trim($_POST['email']));
    $birthdate = mysqli_real_escape_string($conexion, trim($_POST['birthdate']));
    $password = isset($_POST['password_hash']) ? mysqli_real_escape_string($conexion, password_hash (trim($_POST['password_hash']), PASSWORD_DEFAULT)) : '';

    $sql = "INSERT INTO users (full_name, email, birthdate, password_hash) VALUES ('$full_name', '$email', '$birthdate', '$password')";

    mysqli_query($conexion, $sql);

    if (mysqli_affected_rows($conexion) > 0) {
        $_SESSION['mensaje'] = 'Usuario creado exitosamente.';
        header('Location: indexcrud.php');
        exit;
    } else {
        $_SESSION['mensaje'] = 'Usuario no creado.';
        header('Location: indexcrud.php');
    }
}

if (isset($_POST['update_usuario'])) {
    $user_id = mysqli_real_escape_string($conexion, $_POST['user_id']);

    $full_name = mysqli_real_escape_string($conexion, trim($_POST['full_name']));
    $email = mysqli_real_escape_string($conexion, trim($_POST['email']));
    $birthdate = mysqli_real_escape_string($conexion, trim($_POST['birthdate']));
    $password = mysqli_real_escape_string($conexion, trim($_POST['password_hash']));

    $sql = "UPDATE users SET full_name = '$full_name', email = '$email', birthdate = '$birthdate'";

    if (!empty($password)) {
        $sql .= ", password_hash='" . password_hash($password, PASSWORD_DEFAULT) . "'";
    }

    $sql .= " WHERE user_id = '$user_id'";

    mysqli_query($conexion, $sql);

    if (mysqli_affected_rows($conexion) > 0) {
        $_SESSION['mensaje'] = 'Usuario actualizado exitosamente.';
        header('Location: indexcrud.php');
        exit;
    } else {
        $_SESSION['mensaje'] = 'El usuario no fue actualizado.';
        header('Location: indexcrud.php');
    }
}

if (isset($_POST['delete_usuario'])) {
    $user_id = mysqli_real_escape_string($conexion, $_POST['delete_usuario']);

    $sql = "DELETE FROM users WHERE user_id = '$user_id'";

    mysqli_query($conexion, $sql);

    if (mysqli_affected_rows($conexion) > 0) {
        $_SESSION['Mensaje'] = 'Usuario eliminado exitosamente.';
        header('Location: indexcrud.php');
        exit;
    } else {
        $_SESSION['Mensaje'] = 'El usuario no fue eliminado';
        header('Location: indexcrud.php');
        exit;
    }
}


?>