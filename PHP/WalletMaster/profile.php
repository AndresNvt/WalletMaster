<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: sesionform.php');
    exit();
}

// Configuración de la base de datos
$db_config = [
    'host' => 'localhost',
    'username' => 'root',
    'password' => '',
    'database' => 'walletmaster'
];

try {
    $pdo = new PDO(
        "mysql:host={$db_config['host']};dbname={$db_config['database']};charset=utf8",
        $db_config['username'],
        $db_config['password']
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Error de conexión: " . $e->getMessage());
}

// Obtener información del usuario
$stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Procesar actualización del perfil
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = $_POST['full_name'] ?? '';
    $email = $_POST['email'] ?? '';
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    
    $updates = [];
    $params = [];
    
    if (!empty($full_name)) {
        $updates[] = "full_name = ?";
        $params[] = $full_name;
    }
    
    if (!empty($email)) {
        $updates[] = "email = ?";
        $params[] = $email;
    }
    
    if (!empty($current_password) && !empty($new_password)) {
        // Verificar la contraseña actual
        $stmt = $pdo->prepare("SELECT password_hash FROM users WHERE user_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $stored_hash = $stmt->fetchColumn();
        
        if ($stored_hash && password_verify($current_password, $stored_hash)) {
            $updates[] = "password_hash = ?";
            $params[] = password_hash($new_password, PASSWORD_DEFAULT);
        } else {
            $error = "La contraseña actual es incorrecta.";
        }
    }
    
    if (!empty($updates)) {
        $params[] = $_SESSION['user_id'];
        $sql = "UPDATE users SET " . implode(", ", $updates) . " WHERE user_id = ?";
        $stmt = $pdo->prepare($sql);
        
        if ($stmt->execute($params)) {
            $success = "Perfil actualizado correctamente.";

            // Actualizar la información en sesión
            $_SESSION['full_name'] = $full_name;
            $_SESSION['email'] = $email;

            // Recargar los datos del usuario
            $stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
        } else {
            $error = "Error al actualizar el perfil.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Perfil - WalletMaster</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../../CSS/stylesapp.css">
    <!-- Favicon -->
    <link rel="icon" href="../../imagenes/Favicon.png">
</head>
<body>
    <?php include 'sidebar.php'; ?>
<div class="main-content">
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h3 class="mb-0">Mi Perfil</h3>
                    </div>
                    <div class="card-body">
                        <?php if (isset($error)): ?>
                            <div class="alert alert-danger"><?php echo $error; ?></div>
                        <?php endif; ?>
                        <?php if (isset($success)): ?>
                            <div class="alert alert-success"><?php echo $success; ?></div>
                        <?php endif; ?>

                        <form method="POST" id="profileForm">
                            <div class="mb-3">
                                <label for="full_name" class="form-label">Nombre</label>
                                <input type="text" class="form-control" id="full_name" name="full_name" 
                                       value="<?php echo htmlspecialchars($user['full_name'] ?? ''); ?>">
                            </div>

                            <div class="mb-3">
                                <label for="email" class="form-label">Correo Electrónico</label>
                                <input type="email" class="form-control" id="email" name="email" 
                                       value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>">
                            </div>

                            <hr class="my-4">

                            <h4>Cambiar Contraseña</h4>
                            <div class="mb-3">
                                <label for="current_password" class="form-label">Contraseña Actual</label>
                                <input type="password" class="form-control" id="current_password" name="current_password">
                            </div>

                            <div class="mb-3">
                                <label for="new_password" class="form-label">Nueva Contraseña</label>
                                <input type="password" class="form-control" id="new_password" name="new_password">
                            </div>

                            <button type="submit" class="btn btn-primary">Actualizar Perfil</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            // Asegurar que el nombre se refleje después de la actualización
            document.getElementById("full_name").value = "<?php echo addslashes($_SESSION['full_name'] ?? ''); ?>";
        });
    </script>
</body>
</html>