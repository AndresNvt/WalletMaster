<?php
session_start();
require 'conexion.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Usuario</title>

    <link rel="stylesheet"
    href="https://fonts.googleapis.com/css?family=Open+Sans:400,400i,700&display=swap&subset=latin-ext">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"
    integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
  <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
  <link rel="stylesheet" href="CSS/style.css">

</head>
<body>
    <?php include('navbarcrud.php'); ?>
    <div class="container mt-5">
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h4>Editar usuario
                            <a href="indexcrud.php" class="btn btn-danger float-end">Volver</a>
                        </h4>
                    </div>
                    <div class="card-body">
                        <?php
                        if (isset($_GET['user_id'])) {
                            $user_id = mysqli_real_escape_string($conexion, $_GET['user_id']);
                            $sql = "SELECT * FROM users WHERE user_id='$user_id'";
                            $query = mysqli_query($conexion, $sql);

                            if (mysqli_num_rows($query) > 0) {
                                $users = mysqli_fetch_array($query);
                        ?>
                        <form action="acciones.php" method="POST">
                            <input type="hidden" name="user_id" value="<?=$users['user_id']?>">
                            <div class="mb-3">
                                <label for="">Nombre</label>
                                <input type="text" name="full_name" value="<?=$users['full_name']?>" class="form-control">
                            </div>
                            <div class="mb-3">
                                <label for="">Email</label>
                                <input type="email" name="email" value="<?=$users['email']?>" class="form-control">
                            </div>
                            <div class="mb-3">
                                <label for="">Fecha de nacimiento</label>
                                <input type="date" name="birthdate" value="<?=$users['birthdate']?>" class="form-control">
                            </div>
                            <div class="mb-3">
                                <label for="">Contraseña</label>
                                <input type="password" name="password_hash" class="form-control">
                            </div>
                            <div class="mb-3">
                                <button type="submit" name="update_usuario" class="btn btn-primary">Guardar</button>
                            </div>
                        </form>
                        <?php
                        } else {
                            echo "<h5>Usuario no encontrado.</h5>";
                        }
                    }
                    ?>
                    </div>
                </div>
            </div>
        </div>
    </div>









    <script src="../../JS/scripts.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz"
crossorigin="anonymous"></script>
</body>
</html>