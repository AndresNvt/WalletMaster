<?php
// Conexión a la base de datos
$servername = "localhost"; // o el nombre de tu servidor
$username = "root"; // tu usuario de MySQL
$password = ""; // tu contraseña de MySQL
$dbname = "WalletMaster"; // Nombre de la base de datos

// Crear conexión
$conexion = new mysqli($servername, $username, $password, $dbname);

// Verificar conexión
if ($conexion->connect_error) {
    die("Conexión fallida: " . $conexion->connect_error);
}

// Procesar el formulario de inicio de sesión
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['email']) && isset($_POST['password']) && !isset($_POST['cpassword'])) {
    $email = $_POST['email'];
    $password = $_POST['password'];

    // Consulta para verificar si el usuario existe
    $sql = "SELECT user_id, full_name, password_hash FROM users WHERE email = ?";
    $stmt = $conexion->prepare($sql);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows == 1) {
        // Obtener datos del usuario
        $row = $result->fetch_assoc();
        $user_id = $row['user_id'];
        $full_name = $row['full_name'];
        $password_hash = $row['password_hash'];

        // Verificar si la contraseña es correcta
        if (password_verify($password, $password_hash)) {
            // Iniciar sesión (podrías usar sesiones de PHP para gestionar el login)
            session_start();
            $_SESSION['user_id'] = $user_id;
            $_SESSION['email'] = $email;
            $_SESSION['full_name'] = $full_name;

            // Redirigir a la página principal
            header("Location: appweb.php");
            exit();
        } else {
            echo "<p style='color:red;'>Contraseña incorrecta. Inténtalo de nuevo.</p>";
        }
    } else {
        echo "<p style='color:red;'>No existe una cuenta con ese correo.</p>";
    }

    $stmt->close();
}

// Procesar el formulario de registro
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['email']) && isset($_POST['password']) && isset($_POST['cpassword'])) {
    $email = $_POST['email'];
    $password = $_POST['password'];
    $cpassword = $_POST['cpassword'];

    // Verificar que las contraseñas coincidan
    if ($password !== $cpassword) {
        echo "<p style='color:red;'>Las contraseñas no coinciden. Inténtalo de nuevo.</p>";
    } else {
        // Verificar si el correo ya existe
        $sql = "SELECT user_id FROM users WHERE email = ?";
        $stmt = $conexion->prepare($sql);
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            echo "<p style='color:red;'>Ya existe una cuenta con ese correo. Inténtalo con otro.</p>";
        } else {
            // Encriptar la contraseña
            $password_hash = password_hash($password, PASSWORD_DEFAULT);

            // Insertar el nuevo usuario
            $sql = "INSERT INTO users (email, password_hash, registration_time) VALUES (?, ?, NOW())";
            $stmt = $conexion->prepare($sql);
            $stmt->bind_param("ss", $email, $password_hash);

            if ($stmt->execute()) {
                // Obtener el ID del usuario insertado
                $user_id = $conexion->insert_id;

                // Iniciar sesión con el nuevo usuario
                session_start();
                $_SESSION['user_id'] = $user_id;
                $_SESSION['email'] = $email;

                // Redirigir a la página principal
                header("Location: appweb.php");
                exit();
            } else {
                echo "<p style='color:red;'>Error al registrar: " . $stmt->error . "</p>";
            }
        }
        $stmt->close();
    }
}

$conexion->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inicio de Sesión - WallerMaster</title>

    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Open+Sans:400,400i,700&display=swap&subset=latin-ext">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="../../CSS/style.css">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>

    <!-- Favicon -->
    <link rel="icon" href="../../imagenes/Favicon.png">
</head>
<body data-spy="scroll" data-bs-target=".navbar" class="body-log-sig">

    <!-- Precarga -->
   
    <!-- Final precarga -->

 <!-- Navegación -->
 <nav class="navbar navbar-expand-lg fixed-top navbar-light">
  <div class="container-fluid">
    <a class="navbar-brand me-auto" href="../../index.html">
      <img src="../../imagenes/WM header-top.png" alt="Logo" class="home-logo d-inline-block align-text-top">
    </a>
    <button class="navbar-toggler collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#navbarCollapse" aria-controls="navbarCollapse" aria-expanded="false" aria-label="Toggle navigation">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="navbarCollapse">
      <!-- Elementos de la izquierda -->
       <div class="navbar-nav ms-auto">
        <a class="nav-item nav-link active" href="../../index.html#header">Inicio</a>
        <a class="nav-item nav-link" href="../../index.html#features">Características</a>
        <a class="nav-item nav-link" href="../../index.html#details">Detalles</a>
        <a class="nav-item nav-link" href="../../index.html">Contacto</a>
       </div>
        <a class="btn btn-light text-white px-3 py-2" href="sesionform.php">INICIAR SESIÓN</a>
        <a class="btn btn-primary text-white px-3 py-2" href="sesionform.php#register">REGÍSTRATE</a>
    </div>
  </div>
</nav>
<!-- Fin navegación -->


<!-- Formulario -->
 <div class="container logsig-container">
  <div class="row">
    <div class="col">
      <div class="curved-shapelog"></div>
      <div class="curved-shapesig"></div>
      <div id="login" class="form-box login">
        <h2 class="animation" style="--D:0; --S:21">Login</h2>
        <form action="sesionform.php" method="POST">
          <div class="input-box animation" style="--D:1; --S:22">
            <input type="email" id="lemail" name="email" required>
            <label for="lemail">Correo electrónico</label>
            <i class='bx bxs-user'></i>
          </div>
          <div class="input-box animation" style="--D:2; --S:23">
            <input type="password" id="lpassword" name="password" required>
            <label for="lpassword">Contraseña</label>
            <i class='bx bxs-lock-alt' ></i>
          </div>
          <div class="input-box animation" style="--D:3; --S:24">
            <button class="btn-log" type="submit">Iniciar Sesión</button>
          </div>
          <div class="regi-link animation" style="--D:4; --S:25">
            <p>¿No tienes una cuenta? <a href="#" class="SignUpLink">Regístrate</a></p>
            <p>¿Admin? <a href="../CRUD/indexcrud.php"> ¡aquí!.</a></p>
          </div>
        </form>
      </div>
    </div>
    <div class="info-content login">
      <h2 class="animation" style="--D:0; --S:20">¡Bienvenido!</h2>
      <p class="animation" style="--D:1; --S:21">Bienvenido a tu espacio financiero, ¡administra tu dinero con facilidad!</p>
    </div>
    <div id="register" class="form-box register">
      <h2 class="animation" style="--li:17; --S:0;">Registro</h2>
      <form action="sesionform.php" method="POST">
        <div class="input-box animation" style="--li:18; --S:1;">
          <input type="email" id="email" name="email" maxlength="50" required>
          <label for="email">Correo electrónico</label>
          <i class='bx bxs-user'></i>
        </div>
        <div class="input-box animation" style="--li:19; --S:2;">
          <input type="password" id="password" name="password" minlength="8" required>
          <label for="password">Contraseña</label>
          <i class='bx bxs-lock-alt' ></i>
        </div>
        <div class="input-box animation" style="--li:19; --S:2;">
          <input type="password" id="cpassword" name="cpassword" minlength="8" required>
          <label for="cpassword">Confirmar contraseña</label>
          <i class='bx bxs-lock-alt' ></i>
        </div>
        <div class="input-box animation" style="--li:20; --S:3;">
          <button type="submit" value="register" class="btn-log">Crear cuenta</button>
        </div>
        <div class="regi-link animation" style="--li:21; --S:4;">
          <p class="">¿Ya tienes una cuenta? <a href="#" class="SignInLink">Inicia Sesión</a></p>
        </div>
      </form>
    </div>
    <div class="info-content register">
      <h2 class="animation" style="--li:17; --S:0;">¡Bienvenido!</h2>
      <p class="animation" style="--li:18; --S:1;">¿Qué esperas para registrarte? ¡Regístrate y comienza a manejar mejor tus finanzas!</p>
    </div>
  </div>
 </div>




 <script src="../../JS/scripts.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
</body>

</html>