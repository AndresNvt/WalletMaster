<?php

    $server = "localhost";
    $username = "root";
    $password = "";
    $database = "WalletMaster";

    $conexion = mysqli_connect($server, $username, $password, $database);

    
    if ($conexion->connect_error) {
    die("Conexión Fallida" . $conexion->connect_error);
    } 
    echo "Conectado";
    


?>
