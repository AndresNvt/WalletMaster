<?php
session_start();

// Verificar si el usuario está logueado
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

// Procesar las peticiones POST para añadir transacciones, limpiar registros o eliminar una transacción individual
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Si se solicita eliminar una transacción individual
    if (isset($_POST['action']) && $_POST['action'] === 'delete_transaction' && isset($_POST['transaction_id'])) {
        $transaction_id = intval($_POST['transaction_id']);
        
        // Obtener información de la transacción antes de "eliminarla"
        $stmt = $pdo->prepare("SELECT type, amount FROM transactions WHERE transaction_id = ? AND user_id = ? AND status = 'active'");
        $stmt->execute([$transaction_id, $_SESSION['user_id']]);
        $transaction = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($transaction) {
            // Si es un ingreso, verificar que eliminarlo no resulte en saldo negativo
            if ($transaction['type'] === 'income') {
                // Calcular el saldo actual
                $stmt = $pdo->prepare("
                    SELECT 
                        COALESCE(SUM(CASE WHEN type = 'income' THEN amount ELSE -amount END), 0) as balance
                    FROM transactions 
                    WHERE user_id = ? AND status = 'active'
                ");
                $stmt->execute([$_SESSION['user_id']]);
                $currentBalance = $stmt->fetchColumn();
                
                // Verificar si eliminar el ingreso resultaría en un saldo negativo
                if ($currentBalance - $transaction['amount'] < 0) {
                    echo json_encode(['success' => false, 'error' => 'No puedes eliminar este ingreso porque resultaría en un saldo negativo.']);
                    exit;
                }
            }
            
            $pdo->beginTransaction();
            
            try {
                // Actualizar el estado de la transacción a "deleted"
                $stmt = $pdo->prepare("
                    UPDATE transactions 
                    SET status = 'deleted', deleted = 1 
                    WHERE transaction_id = ? AND user_id = ? AND status = 'active'
                ");
                $stmt->execute([$transaction_id, $_SESSION['user_id']]);
                
                // Registrar en el historial
                $stmt = $pdo->prepare("
                    INSERT INTO transactions_history (transaction_id, user_id, type, amount, description, transaction_date, action, action_time)
                    SELECT transaction_id, user_id, type, amount, description, transaction_date, 'deleted', CURRENT_TIMESTAMP
                    FROM transactions
                    WHERE transaction_id = ? AND user_id = ? AND status = 'deleted' AND deleted = 1
                ");
                $stmt->execute([$transaction_id, $_SESSION['user_id']]);
                
                $pdo->commit();
                echo json_encode(['success' => true]);
            } catch (Exception $e) {
                $pdo->rollBack();
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
            exit;
        } else {
            echo json_encode(['success' => false, 'error' => 'Transacción no encontrada o ya eliminada.']);
            exit;
        }
    }
    // Si se solicita eliminar registros
    elseif (isset($_POST['action']) && $_POST['action'] === 'clear' && isset($_POST['clear_type'])) {
        $type = $_POST['clear_type'] === 'income' ? 'income' : 'expense';
        
        // Verificar saldo antes de eliminar gastos (si estamos eliminando ingresos)
        if ($type === 'income') {
            // Calcular el saldo actual
            $stmt = $pdo->prepare("
                SELECT 
                    COALESCE(SUM(CASE WHEN type = 'income' THEN amount ELSE -amount END), 0) as balance
                FROM transactions 
                WHERE user_id = ? AND status = 'active'
            ");
            $stmt->execute([$_SESSION['user_id']]);
            $currentBalance = $stmt->fetchColumn();
            
            // Calcular el total de ingresos
            $stmt = $pdo->prepare("
                SELECT COALESCE(SUM(amount), 0) as total_income
                FROM transactions 
                WHERE user_id = ? AND type = 'income' AND status = 'active'
            ");
            $stmt->execute([$_SESSION['user_id']]);
            $totalIncome = $stmt->fetchColumn();
            
            // Verificar si eliminar los ingresos resultaría en un saldo negativo
            if ($currentBalance - $totalIncome < 0) {
                echo json_encode(['success' => false, 'error' => 'No se pueden eliminar todos los ingresos porque resultaría en un saldo negativo.']);
                exit;
            }
        }
        
        // Modificar el estado de las transacciones en lugar de eliminarlas
        $pdo->beginTransaction();
        
        try {
            // Actualizar el estado de las transacciones a "deleted" y registrar en historial
            $stmt = $pdo->prepare("
                UPDATE transactions 
                SET status = 'deleted', deleted = 1 
                WHERE user_id = ? AND type = ? AND status = 'active'
            ");
            $stmt->execute([$_SESSION['user_id'], $type]);
            
            // Registrar en el historial cada transacción "eliminada"
            $stmt = $pdo->prepare("
                INSERT INTO transactions_history (transaction_id, user_id, type, amount, description, transaction_date, action, action_time)
                SELECT transaction_id, user_id, type, amount, description, transaction_date, 'deleted', CURRENT_TIMESTAMP
                FROM transactions
                WHERE user_id = ? AND type = ? AND status = 'deleted' AND deleted = 1 
                    AND transaction_id NOT IN (
                        SELECT transaction_id FROM transactions_history WHERE action = 'deleted'
                    )
            ");
            $stmt->execute([$_SESSION['user_id'], $type]);
            
            $pdo->commit();
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    } 
    // Si se añade una nueva transacción
    elseif (isset($_POST['type']) && isset($_POST['amount'])) {
        $type = $_POST['type'] === 'income' ? 'income' : 'expense';
        $amount = floatval($_POST['amount']);
        $description = isset($_POST['description']) ? trim($_POST['description']) : '';
        
        if ($amount > 0) {
            // Si es un gasto, verificamos que no resulte en saldo negativo
            if ($type === 'expense') {
                $stmt = $pdo->prepare("
                    SELECT COALESCE(SUM(CASE WHEN type = 'income' THEN amount ELSE -amount END), 0) as balance
                    FROM transactions 
                    WHERE user_id = ? AND status = 'active'
                ");
                $stmt->execute([$_SESSION['user_id']]);
                $currentBalance = $stmt->fetchColumn();
                
                if ($currentBalance - $amount < 0) {
                    echo json_encode([
                        'success' => false, 
                        'error' => 'No puedes agregar un gasto que haga que el monto total sea negativo.'
                    ]);
                    exit;
                }
            }
            
            $stmt = $pdo->prepare("
                INSERT INTO transactions (user_id, type, amount, description, transaction_date, status, deleted) 
                VALUES (?, ?, ?, ?, CURRENT_TIMESTAMP, 'active', 0)
            ");
            $stmt->execute([$_SESSION['user_id'], $type, $amount, $description]);
            
            // Obtener el ID de la transacción recién insertada
            $newTransactionId = $pdo->lastInsertId();
            
            // Obtener los datos completos de la nueva transacción
            $stmt = $pdo->prepare("
                SELECT 
                    transaction_id, 
                    type, 
                    amount, 
                    description, 
                    transaction_date,
                    status
                FROM transactions 
                WHERE transaction_id = ?
            ");
            $stmt->execute([$newTransactionId]);
            $newTransaction = $stmt->fetch(PDO::FETCH_ASSOC);
            
            echo json_encode(['success' => true, 'transaction' => $newTransaction]);
            exit;
        } else {
            echo json_encode(['success' => false, 'error' => 'El monto debe ser mayor que cero.']);
            exit;
        }
    }
    
    // Endpoint para obtener datos actualizados (para AJAX)
    if (isset($_POST['action']) && $_POST['action'] === 'get_data') {
        $userTotals = getUserTotals($pdo, $_SESSION['user_id']);
        $searchQuery = isset($_POST['search']) ? trim($_POST['search']) : '';
        $transactionLimit = isset($_POST['limit']) ? intval($_POST['limit']) : 50;
        $transactionHistory = getTransactionHistory($pdo, $_SESSION['user_id'], $searchQuery, $transactionLimit);
        
        echo json_encode([
            'success' => true, 
            'totals' => $userTotals,
            'transactions' => $transactionHistory
        ]);
        exit;
    }
    
    // Agregar este código en la sección de procesamiento de peticiones POST
    if (isset($_POST['action']) && $_POST['action'] === 'get_trend_data') {
        $days = isset($_POST['days']) ? intval($_POST['days']) : 7;
        $userId = $_SESSION['user_id']; // Asegúrate de tener el ID del usuario en la sesión
        
        $trendData = getTrendData($pdo, $userId, $days);
        
        echo json_encode([
            'success' => true,
            'trend_data' => $trendData
        ]);
        exit;
    }
}

// Obtener totales del usuario (solo de transacciones activas)
function getUserTotals($pdo, $userId) {
    $stmt = $pdo->prepare("
        SELECT 
            COALESCE(SUM(CASE WHEN type = 'income' THEN amount ELSE 0 END), 0) as total_income,
            COALESCE(SUM(CASE WHEN type = 'expense' THEN amount ELSE 0 END), 0) as total_expenses,
            COALESCE(SUM(CASE WHEN type = 'income' THEN amount ELSE -amount END), 0) as balance
        FROM transactions 
        WHERE user_id = ? AND status = 'active'
    ");
    $stmt->execute([$userId]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// Obtener historial de transacciones mejorado para no mostrar duplicados
function getTransactionHistory($pdo, $userId, $search = '', $limit = 10) {
    $searchTerm = "%$search%";
    
    // Consulta mejorada para obtener las transacciones más recientes sin duplicados
    // Primero obtenemos las transacciones activas
    $sql = "
        (SELECT 
            t.transaction_id, 
            t.type, 
            t.amount, 
            t.description, 
            t.transaction_date,
            t.status,
            NULL as action_time
        FROM transactions t
        WHERE t.user_id = :user_id AND t.status = 'active'";
    
    if (!empty($search)) {
        $sql .= " AND (t.description LIKE :search OR t.type LIKE :search)";
    }
    
    $sql .= ")
        UNION
        (SELECT 
            t.transaction_id, 
            t.type, 
            t.amount, 
            t.description, 
            t.transaction_date,
            t.status,
            MAX(th.action_time) as action_time
        FROM transactions t
        JOIN transactions_history th ON t.transaction_id = th.transaction_id
        WHERE t.user_id = :user_id AND t.status = 'deleted'
        AND th.action = 'deleted'";
    
    if (!empty($search)) {
        $sql .= " AND (t.description LIKE :search OR t.type LIKE :search)";
    }
    
    $sql .= " GROUP BY t.transaction_id)
        ORDER BY transaction_date DESC LIMIT :limit";
    
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
    if (!empty($search)) {
        $stmt->bindParam(':search', $searchTerm, PDO::PARAM_STR);
    }
    $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Agregar esta función para obtener datos de tendencia
function getTrendData($pdo, $userId, $days = 7) {
    // Obtener transacciones de los últimos X días
    $stmt = $pdo->prepare("
        SELECT DATE(transaction_date) as date, type, SUM(amount) as total 
        FROM transactions 
        WHERE user_id = ? AND status = 'active' 
        AND transaction_date >= DATE_SUB(CURRENT_DATE(), INTERVAL ? DAY) 
        GROUP BY DATE(transaction_date), type 
        ORDER BY date ASC
    ");
    $stmt->execute([$userId, $days]);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Preparar arrays para los datos
    $dates = [];
    $incomeByDate = [];
    $expenseByDate = [];
    $balanceByDate = [];

    // Obtener todas las fechas en el rango
    $endDate = new DateTime();
    $startDate = new DateTime();
    $startDate->modify('-' . ($days - 1) . ' days');
    $interval = new DateInterval('P1D');
    $dateRange = new DatePeriod($startDate, $interval, $endDate);

    foreach ($dateRange as $date) {
        $dateStr = $date->format('Y-m-d');
        $dates[] = $date->format('D j'); // Ejemplo: "Lun 15"
        $incomeByDate[$dateStr] = 0;
        $expenseByDate[$dateStr] = 0;
    }

    // Rellenar los datos reales
    foreach ($results as $row) {
        if ($row['type'] === 'income') {
            $incomeByDate[$row['date']] = floatval($row['total']);
        } else {
            $expenseByDate[$row['date']] = floatval($row['total']);
        }
    }

    // Calcular balance diario
    foreach ($incomeByDate as $date => $income) {
        $expense = $expenseByDate[$date];
        $balanceByDate[$date] = $income - $expense;
    }

    // Convertir a arrays indexados (para Chart.js)
    $incomeData = array_values($incomeByDate);
    $expenseData = array_values($expenseByDate);
    $balanceData = array_values($balanceByDate);

    return [
        'labels' => $dates,
        'incomeData' => $incomeData,
        'expenseData' => $expenseData,
        'balanceData' => $balanceData
    ];
}

// Establecer el límite de transacciones a mostrar
$transactionLimit = isset($_GET['limit']) ? intval($_GET['limit']) : 50;
$searchQuery = isset($_GET['search']) ? trim($_GET['search']) : '';

$userTotals = getUserTotals($pdo, $_SESSION['user_id']);
$transactionHistory = getTransactionHistory($pdo, $_SESSION['user_id'], $searchQuery, $transactionLimit);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Gestor de Finanzas</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../../CSS/stylesapp.css">
    <!-- Favicon -->
    <link rel="icon" href="../../imagenes/Favicon.png">
</head>
<body>

<?php include 'sidebar.php'; ?>
<div class="main-content">
    <div class="container mt-5">
        <h1 class="text-center mb-5">WalletMaster</h1>

        <!-- Monto Total -->
        <div class="row">
            <div class="col text-center">
                <div class="card mb-4">
                    <div class="card-body">
                        <h5 class="card-title">Monto Total</h5>
                        <h1 id="totalAmount">$<?php echo number_format($userTotals['balance'], 2, ',', '.'); ?> COP</h1>
                    </div>
                </div>
            </div>
        </div>

        <!-- Gastos y Transacciones -->
        <div class="row">
            <!-- Transacciones -->
            <div class="col">
                <div class="card mb-4">
                    <div class="card-body">
                        <h5 class="card-title">Ingresos</h5>
                        <h4 id="transactionTotal">$<?php echo number_format($userTotals['total_income'], 2, ',', '.'); ?> COP</h4>
                        <div class="mb-3">
                            <input type="number" id="transactionAmount" class="form-control mb-2" placeholder="Agregar Transacción" min="0" step="1000">
                            <input type="text" id="transactionDescription" class="form-control" placeholder="Descripción (opcional)">
                            <div class="d-flex gap-2 mt-2">
                                <button onclick="addTransaction()" class="btn btn-primary flex-grow-1">Agregar</button>
                                <button onclick="clearAmount('income')" class="btn btn-outline-primary">Limpiar</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Gastos -->
            <div class="col">
                <div class="card mb-4">
                    <div class="card-body">
                        <h5 class="card-title">Gastos</h5>
                        <h4 id="expenseTotal">$<?php echo number_format($userTotals['total_expenses'], 2, ',', '.'); ?> COP</h4>
                        <div class="mb-3">
                            <input type="number" id="expenseAmount" class="form-control mb-2" placeholder="Agregar Gasto" min="0" step="1000">
                            <input type="text" id="expenseDescription" class="form-control" placeholder="Descripción (opcional)">
                            <div class="d-flex gap-2 mt-2">
                                <button onclick="addExpense()" class="btn btn-danger flex-grow-1">Agregar</button>
                                <button onclick="clearAmount('expense')" class="btn btn-outline-danger">Limpiar</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Historial de Transacciones -->
        <div class="row">
            <div class="col">
                <div class="card mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">Historial de Transacciones</h5>
                        <button class="btn btn-outline-light btn-sm" id="toggleHistory">
                            <i class="fas fa-chevron-down"></i>
                        </button>
                    </div>
                    <div class="card-body" id="historyContent" style="display: none;">
                        <!-- Buscador de transacciones -->
                        <div class="mb-3">
                            <div class="d-flex gap-2">
                                <input type="text" id="searchTransactions" class="form-control" placeholder="Buscar transacciones..." value="<?php echo htmlspecialchars($searchQuery); ?>">
                                <button type="button" id="searchButton" class="btn btn-outline-primary"><i class="fas fa-search"></i></button>
                                <?php if (!empty($searchQuery)): ?>
                                <button type="button" id="clearSearchButton" class="btn btn-outline-secondary"><i class="fas fa-times"></i></button>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="table-responsive">
                            <table class="table table-hover table-dark">
                                <thead>
                                    <tr>
                                        <th>Fecha</th>
                                        <th>Tipo</th>
                                        <th>Monto</th>
                                        <th>Descripción</th>
                                        <th>Estado</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody id="transactionsTableBody">
                                    <?php foreach ($transactionHistory as $transaction): ?>
                                    <tr class="<?php echo $transaction['type'] === 'income' ? 'table-primary' : 'table-danger'; ?> <?php echo $transaction['status'] === 'deleted' ? 'transaction-deleted' : ''; ?>" 
                                        data-transaction-id="<?php echo $transaction['transaction_id']; ?>">
                                        <td><?php echo date('d/m/Y H:i', strtotime($transaction['transaction_date'])); ?></td>
                                        <td><?php echo $transaction['type'] === 'income' ? 'Ingreso' : 'Gasto'; ?></td>
                                        <td><?php echo '$' . number_format($transaction['amount'], 2, ',', '.') . ' COP'; ?></td>
                                        <td><?php echo $transaction['description'] ? htmlspecialchars($transaction['description']) : '<em>Sin descripción</em>'; ?></td>
                                        <td>
                                            <?php if ($transaction['status'] === 'deleted'): ?>
                                                <span class="badge bg-secondary">Eliminado</span>
                                            <?php else: ?>
                                                <span class="badge bg-success">Activo</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($transaction['status'] !== 'deleted'): ?>
                                                <i class="fas fa-trash-alt delete-btn action-btn" 
                                                   title="Eliminar transacción" 
                                                   onclick="deleteTransaction(<?php echo $transaction['transaction_id']; ?>)"></i>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <?php if (empty($transactionHistory)): ?>
                                    <tr>
                                        <td colspan="6" class="text-center">No hay transacciones registradas</td>
                                    </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- Controles de paginación -->
                        <div class="d-flex justify-content-between align-items-center mt-3">
                            <div>
                                <label for="limit-select" class="text-light">Mostrar:</label>
                                <select id="limit-select" class="form-select form-select-sm d-inline-block ms-2" style="width: auto;">
                                    <option value="10" <?php echo $transactionLimit == 10 ? 'selected' : ''; ?>>10</option>
                                    <option value="25" <?php echo $transactionLimit == 25 ? 'selected' : ''; ?>>25</option>
                                    <option value="50" <?php echo $transactionLimit == 50 ? 'selected' : ''; ?>>50</option>
                                    <option value="100" <?php echo $transactionLimit == 100 ? 'selected' : ''; ?>>100</option>
                                    <option value="200" <?php echo $transactionLimit == 200 ? 'selected' : ''; ?>>200</option>
                                </select>
                                <label class="text-light">entradas</label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Gráficos -->
        <div class="row mb-4">
            <div class="col">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">Gráficos Financieros</h5>
                        <div class="btn-group">
                            <button class="btn btn-outline-light btn-sm active" id="viewBarChart">
                                <i class="fas fa-chart-bar"></i>
                            </button>
                            <button class="btn btn-outline-light btn-sm" id="viewPieChart">
                                <i class="fas fa-chart-pie"></i>
                            </button>
                            <button class="btn btn-outline-light btn-sm" id="viewTrendChart">
                                <i class="fas fa-chart-line"></i>
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="chart-container" style="position: relative; height: 300px;">
                            <!-- Gráfico de barras (visible por defecto) -->
                            <div id="barChartContainer">
                                <canvas id="incomeExpenseChart"></canvas>
                            </div>
                            
                            <!-- Gráfico de torta (oculto inicialmente) -->
                            <div id="pieChartContainer" style="display: none;">
                                <canvas id="balanceDistributionChart"></canvas>
                            </div>
                            
                            <!-- Gráfico de línea para tendencias (oculto inicialmente) -->
                            <div id="trendChartContainer" style="display: none;">
                                <canvas id="trendChart"></canvas>
                            </div>
                        </div>
                    </div>
                    <div class="card-footer">
                        <div class="row text-center">
                            <div class="col">
                                <div class="text-primary">
                                    <h6>Ingresos</h6>
                                    <h5 id="chartIncomeTotal">$0</h5>
                                </div>
                            </div>
                            <div class="col">
                                <div class="text-danger">
                                    <h6>Gastos</h6>
                                    <h5 id="chartExpenseTotal">$0</h5>
                                </div>
                            </div>
                            <div class="col">
                                <div class="text-success">
                                    <h6>Balance</h6>
                                    <h5 id="chartBalanceTotal">$0</h5>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    <!-- Modal de Alerta -->
    <div class="modal fade" id="alertModal" tabindex="-1" aria-labelledby="alertModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-dark">
                    <h5 class="modal-title" id="alertModalLabel">Alerta</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-white" id="alertModalBody">
                    Mensaje de alerta
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de Confirmación -->
    <div class="modal fade" id="confirmModal" tabindex="-1" aria-labelledby="confirmModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content modal-color">
                <div class="modal-header bg-dark">
                    <h5 class="modal-title" id="confirmModalLabel">Confirmar acción</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-white" id="confirmModalBody">
                    ¿Estás seguro de que deseas realizar esta acción?
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-danger" id="confirmModalButton">Confirmar</button>
                </div>
            </div>
        </div>
    </div>

    <script>
    let totalAmount = <?php echo $userTotals['balance']; ?>;
    let transactionTotal = <?php echo $userTotals['total_income']; ?>;
    let expenseTotal = <?php echo $userTotals['total_expenses']; ?>;
    let myChart;

    // Función para formatear números en pesos colombianos
    function formatCOP(amount) {
        return '$' + amount.toLocaleString('es-CO', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        }) + ' COP';
    }

    function updateDisplayValues() {
        document.getElementById('totalAmount').innerText = formatCOP(totalAmount);
        document.getElementById('transactionTotal').innerText = formatCOP(transactionTotal);
        document.getElementById('expenseTotal').innerText = formatCOP(expenseTotal);
        updateChart();
    }

    function showAlert(message) {
        document.getElementById('alertModalBody').textContent = message;
        const alertModal = new bootstrap.Modal(document.getElementById('alertModal'));
        alertModal.show();
    }

    function showConfirmDialog(message, confirmCallback) {
        document.getElementById('confirmModalBody').textContent = message;
        document.getElementById('confirmModalButton').onclick = confirmCallback;
        const confirmModal = new bootstrap.Modal(document.getElementById('confirmModal'));
        confirmModal.show();
    }

    // Función para actualizar todos los datos desde el servidor
    function refreshData(searchQuery = '', limit = 50) {
        return $.post(window.location.href, {
            action: 'get_data',
            search: searchQuery,
            limit: limit
        }).done(function(response) {
            try {
                const result = typeof response === 'object' ? response : JSON.parse(response);
                if (result.success) {
                    // Actualizar totales
                    totalAmount = parseFloat(result.totals.balance);
                    transactionTotal = parseFloat(result.totals.total_income);
                    expenseTotal = parseFloat(result.totals.total_expenses);
                    updateDisplayValues();
                    
                    // Actualizar tabla de transacciones
                    updateTransactionTable(result.transactions);
                    
                    // Actualizar los totales en el pie de los gráficos
                    updateChartTotals();
                }
            } catch (e) {
                console.error('Error al procesar respuesta', e);
            }
        }).fail(function(error) {
            console.error('Error al obtener datos actualizados', error);
        });
    }

    // Actualizar la tabla de transacciones
    function updateTransactionTable(transactions) {
        const tableBody = document.getElementById('transactionsTableBody');
        tableBody.innerHTML = '';
        
        if (transactions.length === 0) {
            const row = document.createElement('tr');
            row.innerHTML = '<td colspan="6" class="text-center">No hay transacciones registradas</td>';
            tableBody.appendChild(row);
            return;
        }
        
        transactions.forEach(transaction => {
            const row = document.createElement('tr');
            row.dataset.transactionId = transaction.transaction_id;
            
            // Aplicar clases según el tipo y estado
            row.className = transaction.type === 'income' ? 'table-primary' : 'table-danger';
            if (transaction.status === 'deleted') {
                row.classList.add('transaction-deleted');
            }
            
            // Formatear fecha
            const date = new Date(transaction.transaction_date);
            const formattedDate = `${date.getDate().toString().padStart(2, '0')}/${(date.getMonth() + 1).toString().padStart(2, '0')}/${date.getFullYear()} ${date.getHours().toString().padStart(2, '0')}:${date.getMinutes().toString().padStart(2, '0')}`;
            
            // Crear celdas
            row.innerHTML = `
                <td>${formattedDate}</td>
                <td>${transaction.type === 'income' ? 'Ingreso' : 'Gasto'}</td>
                <td>${'$' + parseFloat(transaction.amount).toLocaleString('es-CO', {minimumFractionDigits: 2, maximumFractionDigits: 2}) + ' COP'}</td>
                <td>${transaction.description ? transaction.description : '<em>Sin descripción</em>'}</td>
                <td>
                    ${transaction.status === 'deleted' ? 
                      '<span class="badge bg-secondary">Eliminado</span>' : 
                      '<span class="badge bg-success">Activo</span>'}
                </td>
                <td>
                    ${transaction.status !== 'deleted' ? 
                      '<i class="fas fa-trash-alt delete-btn action-btn" title="Eliminar transacción" onclick="deleteTransaction(' + transaction.transaction_id + ')"></i>' : 
                      ''}
                </td>
            `;
            
            tableBody.appendChild(row);
        });
    }

    function addTransaction() {
        const amount = parseFloat(document.getElementById('transactionAmount').value) || 0;
        const description = document.getElementById('transactionDescription').value;
        if (amount <= 0) {
            showAlert('El monto debe ser mayor que cero.');
            return;
        }

        // Enviar datos al servidor
        $.post(window.location.href, {
            type: 'income',
            amount: amount,
            description: description
        }).done(function(response) {
            try {
                const result = typeof response === 'object' ? response : JSON.parse(response);
                if (result.success) {
                    document.getElementById('transactionAmount').value = '';
                    document.getElementById('transactionDescription').value = '';
                    
                    // Actualizar datos sin recargar
                    refreshData();
                } else {
                    showAlert(result.error || 'Error al registrar la transacción');
                }
            } catch (e) {
                console.error('Error al procesar respuesta', e);
                refreshData();
            }
        }).fail(function(error) {
            console.error('Error al registrar la transacción', error);
            showAlert('Error al comunicarse con el servidor');
        });
    }

    function addExpense() {
        const amount = parseFloat(document.getElementById('expenseAmount').value) || 0;
        const description = document.getElementById('expenseDescription').value;
        if (amount <= 0) {
            showAlert('El monto debe ser mayor que cero.');
            return;
        }
        
        // Enviar datos al servidor
        $.post(window.location.href, {
            type: 'expense',
            amount: amount,
            description: description
        }).done(function(response) {
            try {
                const result = typeof response === 'object' ? response : JSON.parse(response);
                if (result.success) {
                    document.getElementById('expenseAmount').value = '';
                    document.getElementById('expenseDescription').value = '';
                    
                    // Actualizar datos sin recargar
                    refreshData();
                } else {
                    showAlert(result.error || 'Error al registrar el gasto');
                }
            } catch (e) {
                console.error('Error al procesar respuesta', e);
                refreshData();
            }
        }).fail(function(error) {
            console.error('Error al registrar el gasto', error);
            showAlert('Error al comunicarse con el servidor');
        });
    }

    function clearAmount(type) {
        showConfirmDialog(`¿Estás seguro de que quieres eliminar todos los ${type === 'income' ? 'ingresos' : 'gastos'}?`, function() {
            // Cerrar el modal de confirmación
            const confirmModal = bootstrap.Modal.getInstance(document.getElementById('confirmModal'));
            confirmModal.hide();
            
            // Enviar solicitud al servidor
            $.post(window.location.href, {
                action: 'clear',
                clear_type: type
            }).done(function(response) {
                try {
                    const result = typeof response === 'object' ? response : JSON.parse(response);
                    if (result.success) {
                        // Actualizar datos sin recargar
                        refreshData();
                    } else {
                        showAlert(result.error || `Error al eliminar los ${type === 'income' ? 'ingresos' : 'gastos'}`);
                    }
                } catch (e) {
                    console.error('Error al procesar respuesta', e);
                    refreshData();
                }
            }).fail(function(error) {
                console.error('Error al eliminar registros', error);
                showAlert('Error al comunicarse con el servidor');
            });
        });
    }

    function deleteTransaction(transactionId) {
        showConfirmDialog('¿Estás seguro de que quieres eliminar esta transacción?', function() {
            // Cerrar el modal de confirmación
            const confirmModal = bootstrap.Modal.getInstance(document.getElementById('confirmModal'));
            confirmModal.hide();
            
            // Enviar solicitud al servidor
            $.post(window.location.href, {
                action: 'delete_transaction',
                transaction_id: transactionId
            }).done(function(response) {
                try {
                    const result = typeof response === 'object' ? response : JSON.parse(response);
                    if (result.success) {
                        // Actualizar datos sin recargar
                        refreshData(document.getElementById('searchTransactions').value, parseInt(document.getElementById('limit-select').value));
                    } else {
                        showAlert(result.error || 'Error al eliminar la transacción');
                    }
                } catch (e) {
                    console.error('Error al procesar respuesta', e);
                    refreshData();
                }
            }).fail(function(error) {
                console.error('Error al eliminar la transacción', error);
                showAlert('Error al comunicarse con el servidor');
            });
        });
    }

    function initializeCharts() {
        // Obtener el contexto para los gráficos
        const barCtx = document.getElementById('incomeExpenseChart').getContext('2d');
        const pieCtx = document.getElementById('balanceDistributionChart').getContext('2d');
        const lineCtx = document.getElementById('trendChart').getContext('2d');

        // Colores con mejor paleta
        const colors = {
            income: {
                background: 'rgba(46, 134, 193, 0.7)',
                border: 'rgba(46, 134, 193, 1)'
            },
            expense: {
                background: 'rgba(192, 57, 43, 0.7)',
                border: 'rgba(192, 57, 43, 1)'
            },
            balance: {
                background: 'rgba(39, 174, 96, 0.7)',
                border: 'rgba(39, 174, 96, 1)'
            }
        };

        // 1. Gráfico de barras mejorado para ingresos vs gastos
        const barChart = new Chart(barCtx, {
            type: 'bar',
            data: {
                labels: ['Ingresos', 'Gastos'],
                datasets: [{
                    label: 'Monto',
                    data: [transactionTotal, expenseTotal],
                    backgroundColor: [colors.income.background, colors.expense.background],
                    borderColor: [colors.income.border, colors.expense.border],
                    borderWidth: 2,
                    borderRadius: 8,
                    maxBarThickness: 100
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(255, 255, 255, 0.1)'
                        },
                        ticks: {
                            color: 'rgba(255, 255, 255, 0.7)',
                            callback: function(value) {
                                return '$' + value.toLocaleString('es-CO');
                            }
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        },
                        ticks: {
                            color: 'rgba(255, 255, 255, 0.7)'
                        }
                    }
                },
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        backgroundColor: 'rgba(0, 0, 0, 0.7)',
                        titleColor: 'white',
                        bodyColor: 'white',
                        callbacks: {
                            label: function(context) {
                                return formatCOP(context.raw);
                            }
                        }
                    }
                }
            }
        });

        // 2. Gráfico de torta para distribución del balance - CORREGIDO
        const pieChart = new Chart(pieCtx, {
            type: 'doughnut',
            data: {
                labels: ['Disponible', 'Gastado'],
                datasets: [{
                    data: [totalAmount, expenseTotal],
                    backgroundColor: [colors.balance.background, colors.expense.background],
                    borderColor: [colors.balance.border, colors.expense.border],
                    borderWidth: 2,
                    hoverOffset: 15
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true, // Asegura que respete el tamaño del contenedor
                aspectRatio: 2, // Establece una relación de aspecto más apropiada
                layout: {
                    padding: 10 // Añade un poco de padding interno
                },
                cutout: '60%', // Reducido ligeramente para mejor visualización
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            color: 'rgba(255, 255, 255, 0.7)',
                            padding: 15,
                            font: {
                                size: 12
                            }
                        }
                    },
                    tooltip: {
                        backgroundColor: 'rgba(0, 0, 0, 0.7)',
                        callbacks: {
                            label: function(context) {
                                const label = context.label || '';
                                const value = formatCOP(context.raw);
                                const percentage = Math.round((context.raw / (totalAmount + expenseTotal)) * 100);
                                return `${label}: ${value} (${percentage}%)`;
                            }
                        }
                    }
                }
            }
        });

        // 3. Configurar el gráfico de tendencias
        // Llamamos directamente a createTrendChartWithSampleData con datos simulados
        // para asegurar que se muestre algo inicialmente
        setupTrendChart(lineCtx, colors);

        // Añadir a un objeto global para poder actualizar posteriormente
        window.appCharts = {
            barChart,
            pieChart,
            lineChart: window.appCharts ? window.appCharts.lineChart : null
        };
    }

    // Función para configurar el gráfico de tendencias
    function setupTrendChart(ctx, colors) {
        // Esta función obtendría datos del servidor para mostrar tendencias
        // En este ejemplo, solicitamos datos de los últimos 7 días
        $.post(window.location.href, {
            action: 'get_trend_data',
            days: 7
        }).done(function(response) {
            try {
                const result = typeof response === 'object' ? response : JSON.parse(response);
                if (result.success && result.trend_data) {
                    createTrendChart(ctx, result.trend_data, colors);
                } else {
                    // Si no hay datos de tendencia disponibles, mostrar mensaje
                    document.getElementById('trendChartContainer').innerHTML = 
                        '<div class="text-center text-muted p-4">No hay suficientes datos para mostrar tendencias</div>';
                }
            } catch (e) {
                console.error('Error al procesar datos de tendencia', e);
            }
        }).fail(function() {
            // Si falla la petición, intentar con datos simulados para demostración
            createTrendChartWithSampleData(ctx, colors);
        });
    }

    // Función para crear el gráfico de tendencias con datos de muestra
    function createTrendChartWithSampleData(ctx, colors) {
        // Datos de ejemplo para demostración
        const today = new Date();
        const labels = Array(7).fill().map((_, i) => {
            const date = new Date(today);
            date.setDate(date.getDate() - (6 - i));
            return date.toLocaleDateString('es-CO', { weekday: 'short', day: 'numeric' });
        });
        
        const incomeData = [15000, 22000, 18000, 25000, 17000, 30000, 28000];
        const expenseData = [12000, 18000, 13000, 22000, 14000, 19000, 21000];
        const balanceData = incomeData.map((inc, i) => inc - expenseData[i]);
        
        createTrendChart(ctx, { labels, incomeData, expenseData, balanceData }, colors);
    }

    // Función para crear el gráfico de tendencias con los datos proporcionados
    function createTrendChart(ctx, data, colors) {
        const lineChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: data.labels,
                datasets: [
                    {
                        label: 'Balance',
                        data: data.balanceData,
                        backgroundColor: 'transparent',
                        borderColor: colors.balance.border,
                        borderWidth: 3,
                        pointBackgroundColor: colors.balance.border,
                        tension: 0.2,
                        fill: false,
                        order: 1
                    },
                    {
                        label: 'Ingresos',
                        data: data.incomeData,
                        backgroundColor: 'transparent',
                        borderColor: colors.income.border,
                        borderWidth: 2,
                        pointBackgroundColor: colors.income.border,
                        borderDash: [5, 5],
                        tension: 0.2,
                        order: 2
                    },
                    {
                        label: 'Gastos',
                        data: data.expenseData,
                        backgroundColor: 'transparent',
                        borderColor: colors.expense.border,
                        borderWidth: 2,
                        pointBackgroundColor: colors.expense.border,
                        borderDash: [5, 5],
                        tension: 0.2,
                        order: 3
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    mode: 'index',
                    intersect: false
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(255, 255, 255, 0.1)'
                        },
                        ticks: {
                            color: 'rgba(255, 255, 255, 0.7)',
                            callback: function(value) {
                                return '$' + value.toLocaleString('es-CO', { 
                                    notation: 'compact',
                                    compactDisplay: 'short'
                                });
                            }
                        }
                    },
                    x: {
                        grid: {
                            color: 'rgba(255, 255, 255, 0.1)'
                        },
                        ticks: {
                            color: 'rgba(255, 255, 255, 0.7)'
                        }
                    }
                },
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            color: 'rgba(255, 255, 255, 0.7)',
                            boxWidth: 12,
                            padding: 15
                        }
                    },
                    tooltip: {
                        backgroundColor: 'rgba(0, 0, 0, 0.7)',
                        callbacks: {
                            label: function(context) {
                                let label = context.dataset.label || '';
                                return label + ': ' + formatCOP(context.raw);
                            }
                        }
                    }
                }
            }
        });
        
        // Guardar referencia al gráfico
        if (window.appCharts) {
            window.appCharts.lineChart = lineChart;
        }
    }

    // Función mejorada para actualizar todos los gráficos
    function updateCharts() {
        if (!window.appCharts) return;
        
        const { barChart, pieChart, lineChart } = window.appCharts;
        
        // Actualizar gráfico de barras
        if (barChart) {
            barChart.data.datasets[0].data = [transactionTotal, expenseTotal];
            barChart.update();
        }
        
        // Actualizar gráfico de torta
        if (pieChart) {
            pieChart.data.datasets[0].data = [totalAmount, expenseTotal];
            pieChart.update();
        }
        
        // No actualizamos el gráfico de tendencias aquí, ya que requeriría datos históricos
        // Eso se haría en una función separada que obtiene datos del servidor
    }

    // Reemplazar la función updateChart actual con esta versión mejorada
    function updateChart() {
        updateCharts();
    }

    // Función para actualizar los totales mostrados en el pie del gráfico
    function updateChartTotals() {
        document.getElementById('chartIncomeTotal').textContent = formatCOP(transactionTotal);
        document.getElementById('chartExpenseTotal').textContent = formatCOP(expenseTotal);
        document.getElementById('chartBalanceTotal').textContent = formatCOP(totalAmount);
    }

    function toggleChartView(containerId) {
    // Ocultar todos los contenedores de gráficos
        document.getElementById('barChartContainer').style.display = 'none';
        document.getElementById('pieChartContainer').style.display = 'none';
        document.getElementById('trendChartContainer').style.display = 'none';
        
        // Mostrar el contenedor seleccionado
        document.getElementById(containerId).style.display = 'block';
        
        // Forzamos el reajuste de tamaño después de mostrar el contenedor
        window.dispatchEvent(new Event('resize'));
        
        // Si es el gráfico de tendencias, asegurarnos de que se muestre
        if (containerId === 'trendChartContainer') {
            if (window.appCharts && !window.appCharts.lineChart) {
                const colors = {
                    income: { background: 'rgba(46, 134, 193, 0.7)', border: 'rgba(46, 134, 193, 1)' },
                    expense: { background: 'rgba(192, 57, 43, 0.7)', border: 'rgba(192, 57, 43, 1)' },
                    balance: { background: 'rgba(39, 174, 96, 0.7)', border: 'rgba(39, 174, 96, 1)' }
                };
                setupTrendChart(
                    document.getElementById('trendChart').getContext('2d'),
                    colors
                );
            }
        }
    }

    // Función para marcar el botón activo
    function setActiveButton(button) {
        // Quitar la clase active de todos los botones
        document.querySelectorAll('.btn-group .btn').forEach(btn => {
            btn.classList.remove('active');
        });
        
        // Añadir la clase active al botón seleccionado
        button.classList.add('active');
    }

    // Modificar la función refreshData para actualizar también los totales de los gráficos
    const originalRefreshData = refreshData;
    refreshData = function(searchQuery = '', limit = 50) {
        return originalRefreshData(searchQuery, limit).then(function() {
            updateChartTotals();
            return Promise.resolve();
        });
    };
    
    // Vincular eventos cuando el DOM esté listo
    document.addEventListener('DOMContentLoaded', function() {
        // Añadir estilos adicionales para los contenedores de gráficos
        const style = document.createElement('style');
        style.textContent = `
            .chart-container {
                position: relative;
                height: 300px;
                width: 100%;
                margin: 0 auto;
            }
            #barChartContainer, #pieChartContainer, #trendChartContainer {
                position: relative;
                height: 100%;
                width: 100%;
            }
        `;
        document.head.appendChild(style);
    
        // Validación de entrada para permitir solo números positivos
        document.getElementById('transactionAmount').addEventListener('input', function() {
            if (this.value < 0) this.value = 0;
        });

        document.getElementById('expenseAmount').addEventListener('input', function() {
            if (this.value < 0) this.value = 0;
        });

        // Mostrar/ocultar historial de transacciones
        document.getElementById('toggleHistory').addEventListener('click', function() {
            const historyContent = document.getElementById('historyContent');
            const icon = this.querySelector('i');
            
            if (historyContent.style.display === 'none') {
                historyContent.style.display = 'block';
                icon.classList.remove('fa-chevron-down');
                icon.classList.add('fa-chevron-up');
            } else {
                historyContent.style.display = 'none';
                icon.classList.remove('fa-chevron-up');
                icon.classList.add('fa-chevron-down');
            }
        });

        // Manejar la búsqueda de transacciones
        document.getElementById('searchButton').addEventListener('click', function() {
            const searchQuery = document.getElementById('searchTransactions').value;
            const limit = parseInt(document.getElementById('limit-select').value);
            refreshData(searchQuery, limit);
        });

        // Manejar la pulsación de Enter en el campo de búsqueda
        document.getElementById('searchTransactions').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                document.getElementById('searchButton').click();
            }
        });

        // Limpiar búsqueda si existe el botón
        const clearSearchButton = document.getElementById('clearSearchButton');
        if (clearSearchButton) {
            clearSearchButton.addEventListener('click', function() {
                document.getElementById('searchTransactions').value = '';
                document.getElementById('searchButton').click();
            });
        }

        // Cambiar el límite de transacciones a mostrar
        document.getElementById('limit-select').addEventListener('change', function() {
            const searchQuery = document.getElementById('searchTransactions').value;
            const limit = parseInt(this.value);
            refreshData(searchQuery, limit);
        });
        
        // Inicializar los gráficos mejorados
        initializeCharts();
        
        // Actualizar los totales en el pie de los gráficos
        updateChartTotals();
        
        // Configurar los botones para cambiar entre tipos de gráficos
        document.getElementById('viewBarChart').addEventListener('click', function() {
            toggleChartView('barChartContainer');
            setActiveButton(this);
        });
        
        document.getElementById('viewPieChart').addEventListener('click', function() {
            toggleChartView('pieChartContainer');
            setActiveButton(this);
        });
        
        document.getElementById('viewTrendChart').addEventListener('click', function() {
            toggleChartView('trendChartContainer');
            setActiveButton(this);
        });
    });
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>