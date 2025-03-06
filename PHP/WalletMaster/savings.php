<?php
include '../conexion.php';

// Obtener el ID del usuario de la sesión actual
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: sesionform.php");
    exit;
}
$user_id = $_SESSION['user_id'];

// Procesar formulario para añadir nuevo ahorro
if (isset($_POST['add_saving'])) {
    $goal_name = $_POST['goal_name'];
    $goal_amount = $_POST['goal_amount'];
    $description = $_POST['description'];
    
    $sql = "INSERT INTO savings (user_id, goal_name, goal_amount, current_savings, description) 
            VALUES (?, ?, ?, 0, ?)";
    $stmt = $conexion->prepare($sql);
    $stmt->bind_param("isds", $user_id, $goal_name, $goal_amount, $description);
    
    if ($stmt->execute()) {
        $success_message = "Meta de ahorro creada correctamente";
    } else {
        $error_message = "Error al crear la meta de ahorro: " . $conexion->error;
    }
}

// Eliminar ahorro
if (isset($_POST['delete_saving'])) {
    $savings_id = $_POST['savings_id'];
    
    $conexion->begin_transaction();
    
    try {
        // Eliminar historial de ahorros
        $sql = "DELETE FROM savings_history WHERE savings_id = ?";
        $stmt = $conexion->prepare($sql);
        $stmt->bind_param("i", $savings_id);
        $stmt->execute();
        
        // Eliminar ahorro
        $sql = "DELETE FROM savings WHERE savings_id = ? AND user_id = ?";
        $stmt = $conexion->prepare($sql);
        $stmt->bind_param("ii", $savings_id, $user_id);
        $stmt->execute();
        
        $conexion->commit();
        $success_message = "Ahorro eliminado correctamente";
    } catch (Exception $e) {
        $conexion->rollback();
        $error_message = "Error al eliminar ahorro: " . $e->getMessage();
    }
}

// Editar ahorro
if (isset($_POST['edit_saving'])) {
    $savings_id = $_POST['savings_id'];
    $goal_name = $_POST['goal_name'];
    $goal_amount = $_POST['goal_amount'];
    $description = $_POST['description'];
    
    $sql = "UPDATE savings SET goal_name = ?, goal_amount = ?, description = ? 
            WHERE savings_id = ? AND user_id = ?";
    $stmt = $conexion->prepare($sql);
    $stmt->bind_param("sdsii", $goal_name, $goal_amount, $description, $savings_id, $user_id);
    
    if ($stmt->execute()) {
        $success_message = "Meta de ahorro actualizada correctamente";
    } else {
        $error_message = "Error al actualizar la meta de ahorro: " . $conexion->error;
    }
}

// Procesar eliminación de un aporte individual
if (isset($_POST['delete_contribution'])) {
    $history_id = $_POST['history_id'];
    $savings_id = $_POST['savings_id'];
    
    $conexion->begin_transaction();
    
    try {
        // Obtener el monto del aporte que se va a eliminar
        $sql = "SELECT amount_added FROM savings_history WHERE history_id = ? AND savings_id = ?";
        $stmt = $conexion->prepare($sql);
        $stmt->bind_param("ii", $history_id, $savings_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $contribution = $result->fetch_assoc();
        
        if ($contribution) {
            $amount_to_remove = $contribution['amount_added'];
            
            // Restar el monto del ahorro actual
            $sql = "UPDATE savings SET current_savings = current_savings - ? WHERE savings_id = ? AND user_id = ?";
            $stmt = $conexion->prepare($sql);
            $stmt->bind_param("dii", $amount_to_remove, $savings_id, $user_id);
            $stmt->execute();
            
            // Eliminar el registro de historial
            $sql = "DELETE FROM savings_history WHERE history_id = ? AND savings_id = ?";
            $stmt = $conexion->prepare($sql);
            $stmt->bind_param("ii", $history_id, $savings_id);
            $stmt->execute();
            
            $conexion->commit();
            $success_message = "Aporte eliminado correctamente";
        } else {
            throw new Exception("No se encontró el aporte");
        }
    } catch (Exception $e) {
        $conexion->rollback();
        $error_message = "Error al eliminar el aporte: " . $e->getMessage();
    }
}

// Modificar el código de "add_money" para verificar que no supere la meta
if (isset($_POST['add_money'])) {
    $savings_id = $_POST['savings_id'];
    $amount_added = $_POST['amount_added'];
    
    // Verificar primero si la meta ya está completada o si este aporte la completaría
    $sql = "SELECT goal_amount, current_savings FROM savings WHERE savings_id = ? AND user_id = ?";
    $stmt = $conexion->prepare($sql);
    $stmt->bind_param("ii", $savings_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $saving = $result->fetch_assoc();
    
    if ($saving['current_savings'] >= $saving['goal_amount']) {
        $error_message = "Esta meta ya está completa. No se pueden añadir más fondos.";
    } elseif (($saving['current_savings'] + $amount_added) > $saving['goal_amount']) {
        $error_message = "El monto excede la meta. Máximo a añadir: $" . number_format(($saving['goal_amount'] - $saving['current_savings']), 2, ',', '.') . " COP";
    } else {
        // Proceder con la transacción como antes
        $conexion->begin_transaction();
        
        try {
            // Actualizar el ahorro actual
            $sql = "UPDATE savings SET current_savings = current_savings + ? WHERE savings_id = ? AND user_id = ?";
            $stmt = $conexion->prepare($sql);
            $stmt->bind_param("dii", $amount_added, $savings_id, $user_id);
            $stmt->execute();
            
            // Registrar en el historial
            $sql = "INSERT INTO savings_history (savings_id, amount_added, added_date) VALUES (?, ?, NOW())";
            $stmt = $conexion->prepare($sql);
            $stmt->bind_param("id", $savings_id, $amount_added);
            $stmt->execute();
            
            $conexion->commit();
            $success_message = "Dinero añadido correctamente a tu ahorro";
        } catch (Exception $e) {
            $conexion->rollback();
            $error_message = "Error al añadir dinero: " . $e->getMessage();
        }
    }
}

// Obtener todos los ahorros del usuario
$sql = "SELECT * FROM savings WHERE user_id = ? ORDER BY savings_id DESC";
$stmt = $conexion->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$savings_result = $stmt->get_result();
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
    <div class="container mt-4">
        <?php if(isset($success_message)): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php echo $success_message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        
        <?php if(isset($error_message)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php echo $error_message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        
        <div class="row mb-4">
            <div class="col-md-6">
                <h2 class="h2-savings"><i class="fas fa-piggy-bank"></i> Mis Ahorros</h2>
            </div>
            <div class="col-md-6 text-end">
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addSavingModal">
                    <i class="fas fa-plus"></i> Nueva Meta de Ahorro
                </button>
            </div>
        </div>
        
        <?php if($savings_result->num_rows > 0): ?>
            <div class="row">
                <?php while($saving = $savings_result->fetch_assoc()): 
                    // Calcular porcentaje de progreso
                    $progress = ($saving['current_savings'] / $saving['goal_amount']) * 100;
                    $progress = min($progress, 100); // Asegurarse que no excede 100%
                    
                    // Obtener historial para este ahorro
                    $history_sql = "SELECT * FROM savings_history WHERE savings_id = ? ORDER BY added_date DESC";
                    $history_stmt = $conexion->prepare($history_sql);
                    $history_stmt->bind_param("i", $saving['savings_id']);
                    $history_stmt->execute();
                    $history_result = $history_stmt->get_result();
                ?>
                <div class="col-md-6 mb-4">
                    <div class="card shadow border-0">
                        <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
                            <h5 class="m-0">
                                <?php echo $saving['goal_name']; ?>
                                <?php if($progress >= 100): ?>
                                    <span class="badge bg-success ms-2">Completado</span>
                                <?php endif; ?>
                            </h5>
                            <div>
                                <?php if($progress < 100): ?>
                                <button class="btn btn-sm btn-outline-light me-1" 
                                        data-bs-toggle="modal" 
                                        data-bs-target="#addMoneyModal" 
                                        data-savings-id="<?php echo $saving['savings_id']; ?>"
                                        data-goal-name="<?php echo $saving['goal_name']; ?>">
                                    <i class="fas fa-plus"></i> Añadir
                                </button>
                                <?php endif; ?>
                                <button class="btn btn-sm btn-outline-light me-1"
                                        data-bs-toggle="modal"
                                        data-bs-target="#editSavingModal"
                                        data-savings-id="<?php echo $saving['savings_id']; ?>"
                                        data-goal-name="<?php echo $saving['goal_name']; ?>"
                                        data-goal-amount="<?php echo $saving['goal_amount']; ?>"
                                        data-description="<?php echo $saving['description']; ?>">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="btn btn-sm btn-outline-danger"
                                        data-bs-toggle="modal"
                                        data-bs-target="#deleteSavingModal"
                                        data-savings-id="<?php echo $saving['savings_id']; ?>"
                                        data-goal-name="<?php echo $saving['goal_name']; ?>">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </div>
                        <div class="card-body">
                        <?php if(!empty($saving['description'])): ?>
                            <p class="card-text text-muted mb-3"><?php echo $saving['description']; ?></p>
                        <?php endif; ?>
                        
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="fw-bold">Progreso:</span>
                            <span class="badge bg-success">
                                <?php echo number_format($progress, 1); ?>%
                            </span>
                        </div>
                        
                        <div class="progress mb-3" style="height: 25px;">
                            <div class="progress-bar bg-success progress-bar-striped progress-bar-animated" 
                                role="progressbar" 
                                style="width: <?php echo $progress; ?>%" 
                                aria-valuenow="<?php echo $progress; ?>" 
                                aria-valuemin="0" 
                                aria-valuemax="100">
                            </div>
                        </div>
                        
                        <div class="d-flex justify-content-between">
                            <span>$<?php echo number_format($saving['current_savings'], 2, ',', '.'); ?> COP</span>
                            <span>$<?php echo number_format($saving['goal_amount'], 2, ',', '.'); ?> COP</span>
                        </div>
                        
                        <!-- New section to show remaining amount -->
                        <div class="text-muted mt-2 text-end">
                            <small>Faltan: $<?php 
                                $remaining = $saving['goal_amount'] - $saving['current_savings'];
                                echo number_format($remaining, 2, ',', '.'); 
                            ?> COP</small>
                        </div>
                        
                            <?php if($history_result->num_rows > 0): ?>
                                <div class="mt-4">
                                    <h6 class="border-bottom pb-2">Historial de aportes</h6>
                                    
                                    <!-- Búsqueda -->
                                    <div class="mb-3">
                                        <input type="text" class="form-control form-control-sm" id="searchHistory-<?php echo $saving['savings_id']; ?>" 
                                            placeholder="Buscar por fecha o monto...">
                                    </div>
                                    
                                    <div class="table-responsive t-savings" style="max-height: 200px; overflow-y: auto;">
                                        <table class="table table-sm table-hover">
                                            <thead class="table-light">
                                                <tr>
                                                    <th>Fecha</th>
                                                    <th>Monto</th>
                                                    <th>Acción</th>
                                                </tr>
                                            </thead>
                                            <tbody id="historyTableBody-<?php echo $saving['savings_id']; ?>">
                                                <?php while($history = $history_result->fetch_assoc()): ?>
                                                <tr class="history-row" data-date="<?php echo date('d/m/Y H:i', strtotime($history['added_date'])); ?>" 
                                                    data-amount="<?php echo $history['amount_added']; ?>">
                                                    <td><?php echo date('d/m/Y H:i', strtotime($history['added_date'])); ?></td>
                                                    <td class="text-success">+$<?php echo number_format($history['amount_added'], 2, ',', '.'); ?> COP</td>
                                                    <td>
                                                        <button class="btn btn-sm btn-outline-danger delete-contribution" 
                                                                data-bs-toggle="modal" 
                                                                data-bs-target="#deleteContributionModal" 
                                                                data-history-id="<?php echo $history['history_id']; ?>"
                                                                data-savings-id="<?php echo $saving['savings_id']; ?>"
                                                                data-amount="<?php echo number_format($history['amount_added'], 2, ',', '.'); ?>">
                                                            <i class="fas fa-trash-alt"></i>
                                                        </button>
                                                    </td>
                                                </tr>
                                                <?php endwhile; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endwhile; ?>
            </div>
        <?php else: ?>
            <div class="text-center py-5 my-5">
                <i class="fas fa-piggy-bank fa-4x mb-3 text-muted"></i>
                <h3 class="text-muted">No tienes metas de ahorro</h3>
                <p class="text-muted mb-4">Crea tu primera meta para empezar a ahorrar</p>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addSavingModal">
                    <i class="fas fa-plus"></i> Nueva Meta de Ahorro
                </button>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal para añadir nuevo ahorro -->
<div class="modal fade" id="addSavingModal" tabindex="-1" aria-labelledby="addSavingModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title" id="addSavingModalLabel">Nueva Meta de Ahorro</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form action="" method="POST" id="addSavingForm">
                    <div class="mb-3">
                        <label for="goal_name" class="form-label">Nombre de la meta *</label>
                        <input type="text" class="form-control" id="goal_name" name="goal_name" required>
                    </div>
                    <div class="mb-3">
                        <label for="goal_amount" class="form-label">Monto objetivo (COP) *</label>
                        <input type="number" class="form-control" id="goal_amount" name="goal_amount" min="0" step="0.01" required>
                    </div>
                    <div class="mb-3">
                        <label for="description" class="form-label">Descripción (opcional)</label>
                        <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                    </div>
                    <input type="hidden" name="add_saving" value="1">
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" form="addSavingForm" class="btn btn-primary">Crear Meta</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal para añadir dinero a un ahorro -->
<div class="modal fade" id="addMoneyModal" tabindex="-1" aria-labelledby="addMoneyModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title" id="addMoneyModalLabel">Añadir a "<span id="savingName"></span>"</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form action="" method="POST" id="addMoneyForm">
                    <div class="mb-3">
                        <label for="amount_added" class="form-label">Monto a añadir (COP) *</label>
                        <input type="number" class="form-control" id="amount_added" name="amount_added" min="0" step="0.01" required>
                    </div>
                    <input type="hidden" name="savings_id" id="savings_id">
                    <input type="hidden" name="add_money" value="1">
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" form="addMoneyForm" class="btn btn-success">Añadir Dinero</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal para editar un ahorro -->
<div class="modal fade" id="editSavingModal" tabindex="-1" aria-labelledby="editSavingModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title" id="editSavingModalLabel">Editar Meta de Ahorro</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form action="" method="POST" id="editSavingForm">
                    <div class="mb-3">
                        <label for="edit_goal_name" class="form-label">Nombre de la meta *</label>
                        <input type="text" class="form-control" id="edit_goal_name" name="goal_name" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit_goal_amount" class="form-label">Monto objetivo (COP) *</label>
                        <input type="number" class="form-control" id="edit_goal_amount" name="goal_amount" min="0" step="0.01" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit_description" class="form-label">Descripción (opcional)</label>
                        <textarea class="form-control" id="edit_description" name="description" rows="3"></textarea>
                    </div>
                    <input type="hidden" name="savings_id" id="edit_savings_id">
                    <input type="hidden" name="edit_saving" value="1">
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" form="editSavingForm" class="btn btn-primary">Guardar Cambios</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal para confirmar eliminación -->
<div class="modal fade" id="deleteSavingModal" tabindex="-1" aria-labelledby="deleteSavingModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title" id="deleteSavingModalLabel">Confirmar Eliminación</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>¿Estás seguro que deseas eliminar la meta de ahorro "<span id="deleteSavingName"></span>"?</p>
                <p class="text-danger"><strong>Esta acción eliminará también todo el historial asociado y no se puede deshacer.</strong></p>
                <form action="" method="POST" id="deleteSavingForm">
                    <input type="hidden" name="savings_id" id="delete_savings_id">
                    <input type="hidden" name="delete_saving" value="1">
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" form="deleteSavingForm" class="btn btn-danger">Eliminar</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal para confirmar eliminación de aporte individual -->
<div class="modal fade" id="deleteContributionModal" tabindex="-1" aria-labelledby="deleteContributionModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title" id="deleteContributionModalLabel">Confirmar Eliminación de Aporte</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>¿Estás seguro que deseas eliminar este aporte de <span id="deleteContributionAmount"></span> COP?</p>
                <p class="text-danger"><strong>Esta acción reducirá el ahorro actual y no se puede deshacer.</strong></p>
                <form action="" method="POST" id="deleteContributionForm">
                    <input type="hidden" name="history_id" id="delete_history_id">
                    <input type="hidden" name="savings_id" id="delete_contribution_savings_id">
                    <input type="hidden" name="delete_contribution" value="1">
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" form="deleteContributionForm" class="btn btn-danger">Eliminar</button>
            </div>
        </div>
    </div>
</div>

<script>
// Script para manejar todos los formularios con AJAX
document.addEventListener('DOMContentLoaded', function() {
    // Función para manejar el envío de formularios con AJAX
    function setupAjaxForm(formId, modalId, successCallback) {
        const form = document.getElementById(formId);
        if (form) {
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                
                const formData = new FormData(form);
                
                fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.text())
                .then(html => {
                    // Cerrar el modal
                    if (modalId) {
                        const modalInstance = bootstrap.Modal.getInstance(document.getElementById(modalId));
                        modalInstance.hide();
                    }
                    
                    // Extraer el mensaje de éxito o error
                    const parser = new DOMParser();
                    const doc = parser.parseFromString(html, 'text/html');
                    const successMessage = doc.querySelector('.alert-success')?.textContent.trim();
                    const errorMessage = doc.querySelector('.alert-danger')?.textContent.trim();
                    
                    // Obtener el nuevo contenido sin incluir las alertas
                    const newContent = doc.querySelector('.main-content .container');
                    if (newContent) {
                        // Eliminar las alertas del nuevo contenido
                        const alerts = newContent.querySelectorAll('.alert');
                        alerts.forEach(alert => alert.remove());
                        
                        // Actualizar el contenido principal sin las alertas
                        document.querySelector('.main-content .container').innerHTML = newContent.innerHTML;
                    }
                    
                    // Reinicializar todos los event listeners
                    setupModals();
                    
                    // Resetear el formulario
                    form.reset();
                    
                    // Mostrar mensaje de éxito o error (solo uno)
                    if (successMessage) {
                        showAlert('success', successMessage);
                    } else if (errorMessage) {
                        showAlert('danger', errorMessage);
                    }
                    
                    // Ejecutar callback específico si existe
                    if (successCallback) {
                        successCallback();
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showAlert('danger', 'Ha ocurrido un error al procesar la solicitud');
                });
            });
        }
    }
    
    // Función para mostrar alertas
    function showAlert(type, message) {
        // Primero, eliminar cualquier alerta existente
        const existingAlerts = document.querySelectorAll('.alert');
        existingAlerts.forEach(alert => {
            if (alert.parentNode) {
                alert.parentNode.removeChild(alert);
            }
        });
        
        // Crear nueva alerta
        const alertContainer = document.createElement('div');
        alertContainer.className = `alert alert-${type} alert-dismissible fade show`;
        alertContainer.setAttribute('role', 'alert');
        alertContainer.innerHTML = `
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        `;
        
        // Insertar la alerta
        const container = document.querySelector('.main-content .container');
        container.insertBefore(alertContainer, container.firstChild);
        
        // Auto-cerrar alerta después de 5 segundos
        setTimeout(() => {
            if (alertContainer.parentNode) {
                alertContainer.classList.remove('show');
                setTimeout(() => {
                    if (alertContainer.parentNode) {
                        alertContainer.parentNode.removeChild(alertContainer);
                    }
                }, 150);
            }
        }, 5000);
    }
    
    // Configurar modales
    function setupModals() {
        // Modal para añadir dinero
        const addMoneyModal = document.getElementById('addMoneyModal');
        if (addMoneyModal) {
            addMoneyModal.addEventListener('show.bs.modal', function(event) {
                const button = event.relatedTarget;
                const savingsId = button.getAttribute('data-savings-id');
                const goalName = button.getAttribute('data-goal-name');
                
                document.getElementById('savings_id').value = savingsId;
                document.getElementById('savingName').textContent = goalName;
            });
        }
        
        // Modal para editar ahorro
        const editSavingModal = document.getElementById('editSavingModal');
        if (editSavingModal) {
            editSavingModal.addEventListener('show.bs.modal', function(event) {
                const button = event.relatedTarget;
                const savingsId = button.getAttribute('data-savings-id');
                const goalName = button.getAttribute('data-goal-name');
                const goalAmount = button.getAttribute('data-goal-amount');
                const description = button.getAttribute('data-description');
                
                document.getElementById('edit_savings_id').value = savingsId;
                document.getElementById('edit_goal_name').value = goalName;
                document.getElementById('edit_goal_amount').value = goalAmount;
                document.getElementById('edit_description').value = description;
            });
        }
        
        // Modal para eliminar ahorro
        const deleteSavingModal = document.getElementById('deleteSavingModal');
        if (deleteSavingModal) {
            deleteSavingModal.addEventListener('show.bs.modal', function(event) {
                const button = event.relatedTarget;
                const savingsId = button.getAttribute('data-savings-id');
                const goalName = button.getAttribute('data-goal-name');
                
                document.getElementById('delete_savings_id').value = savingsId;
                document.getElementById('deleteSavingName').textContent = goalName;
            });
        }
    }
    
    // Configurar todos los formularios
    setupAjaxForm('addSavingForm', 'addSavingModal');
    setupAjaxForm('addMoneyForm', 'addMoneyModal');
    setupAjaxForm('editSavingForm', 'editSavingModal');
    setupAjaxForm('deleteSavingForm', 'deleteSavingModal');
    
    // Inicializar modales
    setupModals();
    
    // Eliminar alertas existentes que puedan venir del servidor
    document.querySelectorAll('.alert').forEach(alert => {
        // Auto-cerrar alerta después de 5 segundos
        setTimeout(() => {
            if (alert.parentNode) {
                alert.classList.remove('show');
                setTimeout(() => {
                    if (alert.parentNode) {
                        alert.parentNode.removeChild(alert);
                    }
                }, 150);
            }
        }, 5000);
    });

// Configurar búsqueda en el historial
document.querySelectorAll('[id^="searchHistory-"]').forEach(searchInput => {
    searchInput.addEventListener('keyup', function() {
        const searchValue = this.value.toLowerCase().trim();
        const savingsId = this.id.split('-')[1];
        const tableBody = document.getElementById(`historyTableBody-${savingsId}`);
        
        if (tableBody) {
            const rows = tableBody.querySelectorAll('.history-row');
            
            rows.forEach(row => {
                const date = row.querySelector('td:first-child').textContent.toLowerCase();
                const amount = row.querySelector('td:nth-child(2)').textContent.toLowerCase();
                
                // Eliminar símbolos de moneda, puntos y comas para búsqueda
                const cleanAmount = amount.replace(/[+$\s,\.]/g, '');
                const cleanSearch = searchValue.replace(/[+$\s,\.]/g, '');
                
                // Buscar por fecha o por monto
                if (date.includes(searchValue) || 
                    cleanAmount.includes(cleanSearch)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        }
    });
});

    // Configurar modal para eliminar aporte
    const deleteContributionModal = document.getElementById('deleteContributionModal');
    if (deleteContributionModal) {
        deleteContributionModal.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            const historyId = button.getAttribute('data-history-id');
            const savingsId = button.getAttribute('data-savings-id');
            const amount = button.getAttribute('data-amount');
            
            document.getElementById('delete_history_id').value = historyId;
            document.getElementById('delete_contribution_savings_id').value = savingsId;
            document.getElementById('deleteContributionAmount').textContent = amount;
        });
    }

    // Configurar formulario para eliminar aporte
    setupAjaxForm('deleteContributionForm', 'deleteContributionModal');
});

// Validación para prevenir números negativos
document.querySelectorAll('input[type="number"]').forEach(input => {
    input.addEventListener('input', function(e) {
        if (parseFloat(this.value) < 0) {
            this.value = 0;
        }
    });
    
    // También validar en el envío del formulario
    input.closest('form')?.addEventListener('submit', function(e) {
        const numInputs = this.querySelectorAll('input[type="number"]');
        let hasNegative = false;
        
        numInputs.forEach(inp => {
            if (parseFloat(inp.value) < 0) {
                inp.value = 0;
                hasNegative = true;
            }
        });
        
        if (hasNegative) {
            e.preventDefault();
            alert('No se permiten valores negativos.');
        }
    });
});
</script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>