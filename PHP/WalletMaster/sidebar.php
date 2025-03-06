<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (isset($_POST['logout'])) {
    session_destroy();
    header('Location: sesionform.php');
    exit();
}

// Get current page filename to set active class
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <!-- Link to external CSS files -->
    <link rel="stylesheet" href="../../css/bootstrap.min.css">
    <link rel="stylesheet" href="../../css/sidebar.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
</head>

<div class="sidebar-container" id="sidebar">
    <div class="sidebar-header">
        <img src="../../imagenes/Capa 4.png" alt="WalletMaster Logo" class="sidebar-logo">
        <h4 class="sidebar-title">WalletMaster</h4>
    </div>
    
    <nav class="sidebar-nav">
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link <?php echo ($current_page == 'appweb.php') ? 'active' : ''; ?>" href="appweb.php">
                    <i class="fas fa-exchange-alt"></i>
                    <span>Transacciones</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo ($current_page == 'savings.php') ? 'active' : ''; ?>" href="savings.php">
                    <i class="fas fa-piggy-bank"></i>
                    <span>Ahorros</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo ($current_page == 'debts.php') ? 'active' : ''; ?>" href="debts.php">
                    <i class="fas fa-hand-holding-usd"></i>
                    <span>Deudas</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo ($current_page == 'profile.php') ? 'active' : ''; ?>" href="profile.php">
                    <i class="fas fa-user"></i>
                    <span>Perfil</span>
                </a>
            </li>
        </ul>
    </nav>
    
    <div class="sidebar-footer">
        <form method="POST" class="w-100">
            <button type="submit" name="logout" class="btn btn-danger w-100">
                <i class="fas fa-sign-out-alt"></i>
                <span class="text-danger">Cerrar Sesión</span>
            </button>
        </form>
    </div>
</div>