<?php
session_start();
require 'conexion.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Usuarios - WalletMaster</title>

    <!-- Google Fonts -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Open+Sans:400,400i,700&display=swap&subset=latin-ext">
    
    <!-- Bootstrap 5.3 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" 
    integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    
    <!-- Boxicons -->
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="../../CSS/styleCRUD.css">

    <!-- DataTables CSS for enhanced table functionality -->
    <link href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css" rel="stylesheet">
</head>
<body>
    <?php include('navbarcrud.php'); ?>
    
    <div class="container-fluid px-4 mt-4">
        <?php include('mensaje.php'); ?>
        
        <div class="row">
            <div class="col-md-12">
                <div class="card shadow-sm">
                    <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                        <h4 class="mb-0">
                            <i class="bx bx-user me-2"></i>Lista de Usuarios
                        </h4>
                        <a href="usuario-create.php" class="btn btn-light">
                            <i class="bx bx-plus me-1"></i>Agregar usuario
                        </a>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table id="userTable" class="table table-hover table-striped">
                                <thead class="table-light">
                                    <tr>
                                        <th>ID</th>
                                        <th>Nombre</th>
                                        <th>Email</th>
                                        <th>Fecha de Nacimiento</th>
                                        <th class="text-center">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $sql = 'SELECT * FROM users';
                                    $users = mysqli_query($conexion, $sql);
                                    if (mysqli_num_rows($users) > 0) {
                                        foreach($users as $user) {
                                    ?>
                                    <tr>
                                        <td><?= htmlspecialchars($user['user_id']) ?></td>
                                        <td><?= htmlspecialchars($user['full_name']) ?></td>
                                        <td><?= htmlspecialchars($user['email']) ?></td>
                                        <td><?= date('d/m/Y', strtotime($user['birthdate'])) ?></td>
                                        <td class="text-center">
                                            <div class="btn-group" role="group">
                                                <a href="users-view.php?user_id=<?= $user['user_id'] ?>" class="btn btn-sm btn-outline-secondary" title="Visualizar">
                                                    <i class="bx bx-show"></i>
                                                </a>
                                                <a href="users-edit.php?user_id=<?= $user['user_id'] ?>" class="btn btn-sm btn-outline-success" title="Editar">
                                                    <i class="bx bx-edit"></i>
                                                </a>
                                                <form action="acciones.php" method="POST" class="d-inline">
                                                    <button onclick="return confirm('¿Está seguro que desea eliminar este usuario?')" 
                                                            type="submit" 
                                                            name="delete_usuario" 
                                                            value="<?= $user['user_id'] ?>" 
                                                            class="btn btn-sm btn-outline-danger" 
                                                            title="Eliminar">
                                                        <i class="bx bx-trash"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php
                                        }
                                    } else {
                                        echo '<tr><td colspan="5" class="text-center">Ningún usuario encontrado.</td></tr>';
                                    }
                                    ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- JavaScript Libraries -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
    integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz"
    crossorigin="anonymous"></script>
    
    <!-- Custom Scripts -->
    <script src="JS/scripts.js"></script>
    <script>
        $(document).ready(function() {
            $('#userTable').DataTable({
                language: {
                    url: '//cdn.datatables.net/plug-ins/1.11.5/i18n/es-ES.json'
                },
                pageLength: 10,
                lengthMenu: [[10, 25, 50, -1], [10, 25, 50, "Todos"]]
            });
        });
    </script>
</body>
</html>