<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WalletMaster - Gestor Principal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../CSS/style.css">
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Barra de navegación lateral -->
            <nav id="sidebarMenu" class="col-md-3 col-lg-2 d-md-block bg-light sidebar collapse">
                <div class="position-sticky">
                    <div class="pt-3">
                        <a class="navbar-brand d-flex align-items-center mb-3" href="#">
                            <img src="logo.png" alt="Logo WalletMaster" width="30" height="30" class="me-2">
                            <span>DigiPocket</span>
                        </a>
                        <ul class="nav flex-column">
                            <li class="nav-item">
                                <a class="nav-link active" href="#">
                                    <span data-feather="home"></span>
                                    Overview
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="#">
                                    <span data-feather="message-square"></span>
                                    Message
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="#">
                                    <span data-feather="bar-chart-2"></span>
                                    Analytics
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="#">
                                    <span data-feather="credit-card"></span>
                                    Payment
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="#">
                                    <span data-feather="file-text"></span>
                                    Transaction
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="#">
                                    <span data-feather="wallet"></span>
                                    My Wallet
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="#">
                                    <span data-feather="user"></span>
                                    Profile
                                </a>
                            </li>
                        </ul>
                    </div>
                </div>
            </nav>

            <!-- Contenido principal -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Dashboard</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <button class="btn btn-sm btn-outline-secondary">
                            <span data-feather="bar-chart"></span>
                        </button>
                        <button class="btn btn-sm btn-outline-secondary">
                            <span data-feather="settings"></span>
                        </button>
                        <button class="btn btn-sm btn-outline-secondary">
                            <span data-feather="user"></span>
                            Lakshita Manie
                        </button>
                    </div>
                </div>
                <!-- Aquí puedes agregar el contenido adicional del dashboard -->
            </main>
        </div>
    </div>

    <!-- Bootstrap JS and Feather Icons -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://unpkg.com/feather-icons"></script>
    <script>
        feather.replace()
    </script>
</body>
</html>