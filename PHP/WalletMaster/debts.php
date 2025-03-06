<?php
session_start();
require_once '../conexion.php'; 

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: sesionform.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Handle adding a new debt
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_debt'])) {
    $description = $_POST['description'];
    $total_amount = $_POST['total_amount'];
    $category = $_POST['category'];
    $debt_type = $_POST['debt_type'];
    $has_interest = isset($_POST['has_interest']) ? 1 : 0;
    $interest_rate = $has_interest ? $_POST['interest_rate'] : null;
    $interest_frequency = $has_interest ? $_POST['interest_frequency'] : null;
    $payment_term = !empty($_POST['payment_term']) ? intval($_POST['payment_term']) : null;
    $due_date = !empty($_POST['due_date']) ? $_POST['due_date'] : null;
    $creditor = !empty($_POST['creditor']) ? $_POST['creditor'] : null;
    
    // Start transaction
    $conexion->begin_transaction();
    
    try {
        // Insert debt record
        $stmt = $conexion->prepare("INSERT INTO debts (user_id, total_amount, interest_rate, description, amount_paid, category, debt_type, has_interest, payment_term, due_date, creditor, interest_frequency) VALUES (?, ?, ?, ?, 0.00, ?, ?, ?, ?, ?, ?, ?)");
        
        // Determine parameter types dynamically
        $param_types = "idssssiiiss";
        $params = [
            &$user_id, 
            &$total_amount, 
            &$interest_rate, 
            &$description, 
            &$category, 
            &$debt_type, 
            &$has_interest, 
            &$payment_term, 
            &$due_date, 
            &$creditor,
            &$interest_frequency
        ];
        
        // Use call_user_func_array to bind parameters
        call_user_func_array([$stmt, 'bind_param'], array_merge([$param_types], $params));
        
        if (!$stmt->execute()) {
            throw new Exception("Error al insertar la deuda: " . $stmt->error);
        }
        
        $debt_id = $conexion->insert_id;
        
        // If it has interest, record initial interest rate in history
        if ($has_interest && $interest_rate) {
            $stmt_history = $conexion->prepare("INSERT INTO interest_rate_history (debt_id, user_id, old_rate, new_rate, change_date, change_reason) VALUES (?, ?, NULL, ?, NOW(), 'Tasa inicial')");
            $stmt_history->bind_param("iid", $debt_id, $user_id, $interest_rate);
            
            if (!$stmt_history->execute()) {
                throw new Exception("Error al registrar historial de tasa: " . $stmt_history->error);
            }
        }
        
        // Commit transaction
        $conexion->commit();
        $success_message = "Deuda agregada exitosamente.";
    } catch (Exception $e) {
        // Rollback on error
        $conexion->rollback();
        $error_message = $e->getMessage();
    }
}

// Handle updating amount paid
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_payment'])) {
    $debt_id = $_POST['debt_id'];
    $payment_amount = $_POST['payment_amount'];
    $apply_to_interest = isset($_POST['apply_to_interest']) ? 1 : 0;
    $apply_to_principal = isset($_POST['apply_to_principal']) ? 1 : 0;
    $interest_amount = isset($_POST['interest_amount']) ? $_POST['interest_amount'] : 0;
    $principal_amount = isset($_POST['principal_amount']) ? $_POST['principal_amount'] : $payment_amount;
    
    // Start a transaction
    $conexion->begin_transaction();
    
    try {
        // Update debt amount paid
        $stmt_update_debt = $conexion->prepare("UPDATE debts SET amount_paid = amount_paid + ? WHERE debt_id = ? AND user_id = ?");
        $stmt_update_debt->bind_param("dii", $payment_amount, $debt_id, $user_id);
        $stmt_update_debt->execute();
        
        // Record payment in payment history with interest allocation
        $stmt_record_payment = $conexion->prepare(
            "INSERT INTO debt_payments (debt_id, user_id, payment_amount, applied_to_interest, applied_to_principal) 
             VALUES (?, ?, ?, ?, ?)"
        );
        $stmt_record_payment->bind_param("iiddd", $debt_id, $user_id, $payment_amount, $interest_amount, $principal_amount);
        $stmt_record_payment->execute();
        
        // Commit transaction
        $conexion->commit();
        $success_message = "Pago registrado exitosamente.";
    } catch (Exception $e) {
        // Rollback transaction if something went wrong
        $conexion->rollback();
        $error_message = "Error al registrar el pago: " . $e->getMessage();
    }
}

// Handle updating interest rate
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_interest_rate'])) {
    $debt_id = $_POST['interest_debt_id'];
    $old_rate = $_POST['old_interest_rate'];
    $new_rate = $_POST['new_interest_rate'];
    $change_reason = $_POST['change_reason'];
    
    // Start a transaction
    $conexion->begin_transaction();
    
    try {
        // Update debt interest rate
        $stmt_update_rate = $conexion->prepare("UPDATE debts SET interest_rate = ? WHERE debt_id = ? AND user_id = ?");
        $stmt_update_rate->bind_param("dii", $new_rate, $debt_id, $user_id);
        $stmt_update_rate->execute();
        
        // Record in interest rate history
        $stmt_record_history = $conexion->prepare(
            "INSERT INTO interest_rate_history (debt_id, user_id, old_rate, new_rate, change_date, change_reason) 
             VALUES (?, ?, ?, ?, NOW(), ?)"
        );
        $stmt_record_history->bind_param("iidds", $debt_id, $user_id, $old_rate, $new_rate, $change_reason);
        $stmt_record_history->execute();
        
        // Commit transaction
        $conexion->commit();
        $success_message = "Tasa de interés actualizada exitosamente.";
    } catch (Exception $e) {
        // Rollback transaction if something went wrong
        $conexion->rollback();
        $error_message = "Error al actualizar la tasa de interés: " . $e->getMessage();
    }
}

// Handle deleting a debt
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_debt'])) {
    $debt_id = $_POST['debt_id'];
    
    // Start a transaction to ensure data integrity
    $conexion->begin_transaction();
    
    try {
        // First, delete interest rate history
        $stmt_delete_interest_history = $conexion->prepare("DELETE FROM interest_rate_history WHERE debt_id = ? AND user_id = ?");
        $stmt_delete_interest_history->bind_param("ii", $debt_id, $user_id);
        $stmt_delete_interest_history->execute();
        
        // Second, delete related payment history
        $stmt_delete_payments = $conexion->prepare("DELETE FROM debt_payments WHERE debt_id = ? AND user_id = ?");
        $stmt_delete_payments->bind_param("ii", $debt_id, $user_id);
        $stmt_delete_payments->execute();
        
        // Then delete the debt itself
        $stmt_delete_debt = $conexion->prepare("DELETE FROM debts WHERE debt_id = ? AND user_id = ?");
        $stmt_delete_debt->bind_param("ii", $debt_id, $user_id);
        $stmt_delete_debt->execute();
        
        // Commit the transaction
        $conexion->commit();
        
        $success_message = "Deuda eliminada exitosamente.";
    } catch (Exception $e) {
        // Rollback the transaction if something goes wrong
        $conexion->rollback();
        $error_message = "Error al eliminar la deuda: " . $e->getMessage();
    }
}

// Fetch user's debts with additional details
$debts_query = $conexion->prepare("
    SELECT d.*, 
        (SELECT GROUP_CONCAT(payment_amount ORDER BY payment_date SEPARATOR ', ') 
         FROM debt_payments dp 
         WHERE dp.debt_id = d.debt_id) AS payment_history,
        (SELECT GROUP_CONCAT(
            CONCAT(
                'De ', IFNULL(old_rate, 'Inicial'), '% a ', new_rate, '% (', 
                DATE_FORMAT(change_date, '%d/%m/%Y'), 
                CASE WHEN change_reason != '' THEN CONCAT(' - ', change_reason) ELSE '' END,
                ')'
            ) 
            ORDER BY change_date SEPARATOR '|||'
         ) 
         FROM interest_rate_history irh 
         WHERE irh.debt_id = d.debt_id) AS interest_rate_history
    FROM debts d 
    WHERE d.user_id = ?
    ORDER BY d.due_date ASC, d.debt_id DESC");
$debts_query->bind_param("i", $user_id);
$debts_query->execute();
$debts_result = $debts_query->get_result();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Deudas - Gestor de Finanzas</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../../CSS/stylesapp.css">
    <!-- Favicon -->
    <link rel="icon" href="../../imagenes/Favicon.png">
</head>
<body>
    <?php include 'sidebar.php'; ?>

    <main class="main-content">
        <div class="container-fluid">
            <div class="row">
                <div class="col-12">
                    <div class="page-title-box">
                        <h2 class="page-title"><i class="fas fa-hand-holding-usd"></i>Mis Deudas</h2>
                        <div class="page-title-right pb-5">
                            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addDebtModal">
                                + Nueva Deuda
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <?php if(isset($success_message)): ?>
                <div class="alert alert-success"><?php echo $success_message; ?></div>
            <?php endif; ?>
            <?php if(isset($error_message)): ?>
                <div class="alert alert-danger"><?php echo $error_message; ?></div>
            <?php endif; ?>

            <div class="row">
                <?php while($debt = $debts_result->fetch_assoc()): ?>
                    <div class="col-md-6 mb-4">
                        <div class="card">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h5 class="card-title m-0"><?php echo htmlspecialchars($debt['description']); ?></h5>
                                    <span class="badge bg-info"><?php echo htmlspecialchars($debt['category']); ?></span>
                                </div>
                                <div class="progress mb-3">
                                    <?php 
                                    $progress = ($debt['amount_paid'] / $debt['total_amount']) * 100;
                                    $remaining = $debt['total_amount'] - $debt['amount_paid'];
                                    ?>
                                    <div class="progress-bar" role="progressbar" 
                                        style="width: <?php echo $progress; ?>%" 
                                        aria-valuenow="<?php echo $progress; ?>" 
                                        aria-valuemin="0" 
                                        aria-valuemax="100">
                                        <?php echo number_format($progress, 2); ?>%
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6">
                                        <p class="mb-1 text-light"><strong>Monto Total:</strong> $<?php echo number_format($debt['total_amount'], 2); ?> COP</p>
                                        <p class="mb-1 text-white"><strong>Pagado:</strong> $<?php echo number_format($debt['amount_paid'], 2); ?> COP</p>
                                        <p class="mb-1 text-light"><strong>Pendiente:</strong> $<?php echo number_format($remaining, 2); ?> COP</p>
                                        <p class="mb-1 text-light"><strong>Tipo:</strong> <?php echo htmlspecialchars($debt['debt_type']); ?></p>
                                        <p class="mb-1 text-light">
                                            <strong>Interés:</strong> 
                                            <?php if($debt['has_interest']): ?>
                                                <?php echo $debt['interest_rate']; ?>% 
                                                <?php if($debt['interest_frequency']): ?>
                                                    (<?php echo htmlspecialchars($debt['interest_frequency']); ?>)
                                                <?php endif; ?>
                                                <?php if($debt['debt_type'] == 'Variable'): ?>
                                                    <button class="btn btn-sm btn-warning ms-2" data-bs-toggle="modal" 
                                                            data-bs-target="#updateInterestModal" 
                                                            data-debt-id="<?php echo $debt['debt_id']; ?>"
                                                            data-current-rate="<?php echo $debt['interest_rate']; ?>"
                                                            data-description="<?php echo htmlspecialchars($debt['description']); ?>">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                Sin interés
                                            <?php endif; ?>
                                        </p>
                                        <?php if($debt['due_date'] && $debt['due_date'] != '0000-00-00'): ?>
                                            <?php 
                                            $due_timestamp = strtotime($debt['due_date']);
                                            if ($due_timestamp && $due_timestamp > 0) {
                                                echo '<p class="mb-1 text-light"><strong>Vencimiento:</strong> ' . date('d/m/Y', $due_timestamp) . '</p>';
                                            }
                                            ?>
                                        <?php endif; ?>
                                        <?php if($debt['creditor']): ?>
                                            <p class="mb-1 text-light"><strong>Acreedor:</strong> <?php echo htmlspecialchars($debt['creditor']); ?></p>
                                        <?php endif; ?>
                                    </div>
                                    <div class="col-md-6 text-end">
                                        <button class="btn btn-success btn-sm mb-2" data-bs-toggle="modal" 
                                                data-bs-target="#payDebtModal" 
                                                data-debt-id="<?php echo $debt['debt_id']; ?>"
                                                data-description="<?php echo htmlspecialchars($debt['description']); ?>"
                                                data-remaining="<?php echo $remaining; ?>"
                                                data-has-interest="<?php echo $debt['has_interest']; ?>"
                                                data-interest-rate="<?php echo $debt['interest_rate']; ?>">
                                            Abonar
                                        </button>
                                        <button class="btn btn-info btn-sm mb-2" data-bs-toggle="modal" 
                                                data-bs-target="#debtHistoryModal"
                                                data-payment-history="<?php echo htmlspecialchars($debt['payment_history'] ?? ''); ?>">
                                            Historial de Pagos
                                        </button>
                                        <?php if($debt['has_interest'] && !empty($debt['interest_rate_history'])): ?>
                                            <button class="btn btn-secondary btn-sm mb-2" data-bs-toggle="modal" 
                                                    data-bs-target="#interestHistoryModal"
                                                    data-interest-history="<?php echo htmlspecialchars($debt['interest_rate_history']); ?>">
                                                Historial de Tasas
                                            </button>
                                        <?php endif; ?>
                                        <button class="btn btn-danger btn-sm" data-bs-toggle="modal" 
                                                data-bs-target="#deleteDebtModal" 
                                                data-debt-id="<?php echo $debt['debt_id']; ?>"
                                                data-description="<?php echo htmlspecialchars($debt['description']); ?>"
                                                data-total-amount="<?php echo $debt['total_amount']; ?>">
                                            Eliminar
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        </div>
    </main>

    <!-- Add Debt Modal -->
    <div class="modal fade" id="addDebtModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Añadir Nueva Deuda</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Descripción</label>
                                <input type="text" class="form-control" name="description" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Categoría</label>
                                <select class="form-control" name="category">
                                    <option value="Deuda de consumo">Deuda de consumo</option>
                                    <option value="Deuda hipotecaria">Deuda hipotecaria</option>
                                    <option value="Deuda vehicular">Deuda vehicular</option>
                                    <option value="Deuda estudiantil">Deuda estudiantil</option>
                                    <option value="Deuda médica">Deuda médica</option>
                                    <option value="Deuda empresarial">Deuda empresarial</option>
                                    <option value="Deuda con familiares o amigos">Deuda con familiares o amigos</option>
                                    <option value="Deuda con el gobierno">Deuda con el gobierno</option>
                                    <option value="Otros">Otros</option>
                                </select>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Tipo de Deuda</label>
                                <select class="form-control" name="debt_type">
                                    <option value="Fija">Fija</option>
                                    <option value="Variable">Variable</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input" name="has_interest" id="hasInterestCheckbox">
                                    <label class="form-check-label text-light" for="hasInterestCheckbox">¿Tiene Interés?</label>
                                </div>
                                <div id="interestOptionsContainer" style="display:none;">
                                    <div class="row mt-2">
                                        <div class="col-6">
                                            <input type="number" step="0.01" class="form-control" name="interest_rate" id="interestRateInput" min="0" placeholder="Tasa de Interés (%)">
                                        </div>
                                        <div class="col-6">
                                            <select class="form-control" name="interest_frequency" id="interestFrequencyInput">
                                                <option value="Mensual">Mensual</option>
                                                <option value="Trimestral">Trimestral</option>
                                                <option value="Semestral">Semestral</option>
                                                <option value="Anual">Anual</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Monto Total</label>
                                <input type="number" step="0.01" class="form-control" name="total_amount" min="0" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Plazo de Pago (meses)</label>
                                <input type="number" class="form-control" name="payment_term" min="0">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Fecha de Vencimiento</label>
                                <input type="date" class="form-control" name="due_date">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Acreedor</label>
                                <input type="text" class="form-control" name="creditor">
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" name="add_debt" class="btn btn-primary">Guardar Deuda</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Pay Debt Modal -->
    <div class="modal fade" id="payDebtModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Abonar a Deuda</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="debt_id" id="payDebtId">
                        <div class="mb-3">
                            <label class="form-label">Descripción</label>
                            <input type="text" class="form-control" id="payDebtDescription" readonly>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Monto Pendiente</label>
                            <input type="number" class="form-control" id="payDebtRemaining" readonly>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Monto a Abonar</label>
                            <input type="number" step="0.01" class="form-control" name="payment_amount" id="paymentAmount" min="0" required>
                        </div>
                        <div id="interestPaymentSection" style="display: none;">
                            <div class="alert alert-info">
                                <p><strong>Deuda con interés.</strong> Puedes dividir el pago entre interés y capital.</p>
                                <p id="interestRateInfo"></p>
                            </div>
                            <div class="row mb-3">
                                <div class="col-6">
                                    <label class="form-label">Para interés</label>
                                    <input type="number" step="0.01" class="form-control" name="interest_amount" id="interestAmount" min="0" value="0">
                                </div>
                                <div class="col-6">
                                    <label class="form-label">Para capital</label>
                                    <input type="number" step="0.01" class="form-control" name="principal_amount" id="principalAmount" min="0">
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" name="update_payment" class="btn btn-primary">Registrar Pago</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Update Interest Rate Modal -->
    <div class="modal fade" id="updateInterestModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Actualizar Tasa de Interés</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="interest_debt_id" id="interestDebtId">
                        <input type="hidden" name="old_interest_rate" id="oldInterestRate">
                        
                        <div class="mb-3">
                            <label class="form-label">Deuda</label>
                            <input type="text" class="form-control" id="interestDebtDescription" readonly>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Tasa Actual</label>
                            <div class="input-group">
                                <input type="number" class="form-control" id="currentInterestRate" readonly>
                                <span class="input-group-text">%</span>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Nueva Tasa</label>
                            <div class="input-group">
                                <input type="number" step="0.01" class="form-control" name="new_interest_rate" min="0" required>
                                <span class="input-group-text">%</span>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Motivo del Cambio</label>
                            <textarea class="form-control" name="change_reason" rows="3" placeholder="Explica por qué cambia la tasa de interés..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" name="update_interest_rate" class="btn btn-primary">Actualizar Tasa</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Debt Payment History Modal -->
    <div class="modal fade" id="debtHistoryModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Historial de Pagos</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="paymentHistoryContent"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Interest Rate History Modal -->
    <div class="modal fade" id="interestHistoryModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Historial de Tasas de Interés</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="interestHistoryContent"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Debt Confirmation Modal -->
    <div class="modal fade" id="deleteDebtModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Confirmar Eliminación de Deuda</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="debt_id" id="deleteDebtId">
                        <div class="alert alert-warning">
                            <p>¿Estás seguro de que deseas eliminar la siguiente deuda?</p>
                            <strong id="deleteDebtDescription"></strong>
                            <p class="mt-2" id="deleteDebtAmount"></p>
                        </div>
                        <div class="form-group">
                            <label>Motivo de eliminación (opcional)</label>
                            <textarea class="form-control" name="deletion_reason" rows="3" placeholder="Razón para eliminar la deuda"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" name="delete_debt" class="btn btn-danger">Eliminar Definitivamente</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
    // Interest checkbox toggle in add debt form
    const hasInterestCheckbox = document.getElementById('hasInterestCheckbox');
    const interestOptionsContainer = document.getElementById('interestOptionsContainer');
    const interestRateInput = document.getElementById('interestRateInput');
    const interestFrequencyInput = document.getElementById('interestFrequencyInput');

    if (hasInterestCheckbox) {
        hasInterestCheckbox.addEventListener('change', function() {
            interestOptionsContainer.style.display = this.checked ? 'block' : 'none';
            if (!this.checked) {
                interestRateInput.value = '';
            }
        });
    }

    // Populate Pay Debt Modal
    const payDebtModal = document.getElementById('payDebtModal');
    if (payDebtModal) {
        payDebtModal.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            const debtId = button.getAttribute('data-debt-id');
            const description = button.getAttribute('data-description');
            const remaining = button.getAttribute('data-remaining');
            const hasInterest = button.getAttribute('data-has-interest');
            const interestRate = button.getAttribute('data-interest-rate');
            
            const modalDebtId = payDebtModal.querySelector('#payDebtId');
            const modalDescription = payDebtModal.querySelector('#payDebtDescription');
            const modalRemaining = payDebtModal.querySelector('#payDebtRemaining');
            const interestPaymentSection = document.getElementById('interestPaymentSection');
            const interestRateInfo = document.getElementById('interestRateInfo');
            const paymentAmountInput = document.getElementById('paymentAmount');
            const principalAmountInput = document.getElementById('principalAmount');
            
            modalDebtId.value = debtId;
            modalDescription.value = description;
            modalRemaining.value = remaining;
            
            // Reset payment fields
            paymentAmountInput.value = '';
            
            // Show interest section if debt has interest
            if (hasInterest === '1' && interestRate) {
                interestPaymentSection.style.display = 'block';
                interestRateInfo.textContent = `Tasa de interés actual: ${interestRate}%`;
                
                // Update principal amount when payment amount changes
                paymentAmountInput.addEventListener('input', function() {
                    principalAmountInput.value = this.value;
                });
                
                // Initialize principal amount
                principalAmountInput.value = paymentAmountInput.value;
            } else {
                interestPaymentSection.style.display = 'none';
            }
        });
    }

    // Populate Interest Rate Update Modal
    const updateInterestModal = document.getElementById('updateInterestModal');
    if (updateInterestModal) {
        updateInterestModal.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            const debtId = button.getAttribute('data-debt-id');
            const currentRate = button.getAttribute('data-current-rate');
            const description = button.getAttribute('data-description');
            
            const modalDebtId = updateInterestModal.querySelector('#interestDebtId');
            const modalDescription = updateInterestModal.querySelector('#interestDebtDescription');
            const modalCurrentRate = updateInterestModal.querySelector('#currentInterestRate');
            const modalOldRate = updateInterestModal.querySelector('#oldInterestRate');
            
            modalDebtId.value = debtId;
            modalDescription.value = description;
            modalCurrentRate.value = currentRate;
            modalOldRate.value = currentRate;
        });
    }

    // Populate Payment History Modal
    const debtHistoryModal = document.getElementById('debtHistoryModal');
    const paymentHistoryContent = document.getElementById('paymentHistoryContent');
    if (debtHistoryModal && paymentHistoryContent) {
        debtHistoryModal.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            const paymentHistory = button.getAttribute('data-payment-history');
            
            if (paymentHistory && paymentHistory.trim() !== '') {
                const payments = paymentHistory.split(', ');
                let historyHtml = '<table class="table"><thead><tr><th>#</th><th>Monto</th></tr></thead><tbody>';
                
                payments.forEach((payment, index) => {
                    historyHtml += `
                        <tr>
                            <td>${index + 1}</td>
                            <td>$${parseFloat(payment).toLocaleString('es-CO')} COP</td>
                        </tr>`;
                });
                
                historyHtml += '</tbody></table>';
                paymentHistoryContent.innerHTML = historyHtml;
            } else {
                paymentHistoryContent.innerHTML = '<p class="text-center my-3">No hay historial de pagos registrados.</p>';
            }
        });
    }
    
    // Populate Interest Rate History Modal
    const interestHistoryModal = document.getElementById('interestHistoryModal');
    const interestHistoryContent = document.getElementById('interestHistoryContent');
    if (interestHistoryModal && interestHistoryContent) {
        interestHistoryModal.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            const interestHistory = button.getAttribute('data-interest-history');
            
            if (interestHistory && interestHistory.trim() !== '') {
                const historyItems = interestHistory.split('|||');
                let historyHtml = '<table class="table"><thead><tr><th>Cambio</th></tr></thead><tbody>';
                
                historyItems.forEach((item) => {
                    historyHtml += `<tr><td>${item}</td></tr>`;
                });
                
                historyHtml += '</tbody></table>';
                interestHistoryContent.innerHTML = historyHtml;
            } else {
                interestHistoryContent.innerHTML = '<p class="text-center my-3">No hay historial de cambios en la tasa de interés.</p>';
            }
        });
    }

    // Populate Delete Debt Modal
    const deleteDebtModal = document.getElementById('deleteDebtModal');
    if (deleteDebtModal) {
        deleteDebtModal.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            const debtId = button.getAttribute('data-debt-id');
            const description = button.getAttribute('data-description');
            const totalAmount = button.getAttribute('data-total-amount');
            
            const modalDebtId = deleteDebtModal.querySelector('#deleteDebtId');
            const modalDescription = deleteDebtModal.querySelector('#deleteDebtDescription');
            const modalAmount = deleteDebtModal.querySelector('#deleteDebtAmount');
            
            modalDebtId.value = debtId;
            modalDescription.textContent = description;
            modalAmount.textContent = `Monto total: $${parseFloat(totalAmount).toLocaleString('es-CO')} COP`;
        });
    }

    // Auto-dismiss alerts after 5 seconds
    document.querySelectorAll('.alert').forEach(alert => {
        setTimeout(() => {
            if (alert.parentNode) {
                alert.classList.add('fade');
                setTimeout(() => {
                    if (alert.parentNode) {
                        alert.parentNode.removeChild(alert);
                    }
                }, 500);
            }
        }, 5000);
    });

    // Validate payment amount doesn't exceed remaining balance
    const paymentForm = document.querySelector('#payDebtModal form');
    if (paymentForm) {
        paymentForm.addEventListener('submit', function(event) {
            const paymentAmount = parseFloat(document.getElementById('paymentAmount').value);
            const remainingAmount = parseFloat(document.getElementById('payDebtRemaining').value);
            
            if (paymentAmount > remainingAmount) {
                event.preventDefault();
                alert('El monto a abonar no puede ser mayor que el saldo pendiente.');
            }
        });
    }

    // Split payment between interest and principal
    const interestAmountInput = document.getElementById('interestAmount');
    const principalAmountInput = document.getElementById('principalAmount');
    const paymentAmountInput = document.getElementById('paymentAmount');
    
    if (interestAmountInput && principalAmountInput && paymentAmountInput) {
        // Update principal when interest or total payment changes
        const updatePrincipal = function() {
            const totalPayment = parseFloat(paymentAmountInput.value) || 0;
            const interestPayment = parseFloat(interestAmountInput.value) || 0;
            principalAmountInput.value = (totalPayment - interestPayment).toFixed(2);
        };
        
        interestAmountInput.addEventListener('input', updatePrincipal);
        paymentAmountInput.addEventListener('input', updatePrincipal);
        
        // Validate that interest + principal equals total payment
        paymentForm.addEventListener('submit', function(event) {
            if (document.getElementById('interestPaymentSection').style.display !== 'none') {
                const totalPayment = parseFloat(paymentAmountInput.value) || 0;
                const interestPayment = parseFloat(interestAmountInput.value) || 0;
                const principalPayment = parseFloat(principalAmountInput.value) || 0;
                
                if (Math.abs((interestPayment + principalPayment) - totalPayment) > 0.01) {
                    event.preventDefault();
                    alert('La suma del pago a interés y capital debe ser igual al monto total del abono.');
                }
            }
        });
    }

        // Validar que todos los inputs numéricos tengan valores positivos
    document.querySelectorAll('input[type="number"]').forEach(input => {
        input.addEventListener('input', function() {
            if (this.value < 0) {
                this.value = 0;
            }
        });
    });
});
    </script>
</body>
</html>