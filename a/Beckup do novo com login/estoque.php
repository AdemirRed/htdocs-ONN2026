<?php
session_start();
    
// Verificar se o usuário está logado
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

// Conexão com o banco de dados
$conn = new mysqli("192.168.0.201", "root", "", "onnmoveis");
    
if ($conn->connect_error) {
    die("Falha na conexão: " . $conn->connect_error);
}

// Variáveis para mensagens
$success_message = "";
$error_message = "";
    
// Processar exclusão de item
if (isset($_POST['delete_item']) && isset($_POST['item_id'])) {
    $item_id = $conn->real_escape_string($_POST['item_id']);
    
    $sql = "DELETE FROM itens WHERE Codigo = '$item_id'";
    
    if ($conn->query($sql) === TRUE) {
        $success_message = "Item excluído com sucesso!";
    } else {
        $error_message = "Erro ao excluir item: " . $conn->error;
    }
}
    
// Processar atualização de item
if (isset($_POST['update_item'])) {
    $codigo = $conn->real_escape_string($_POST['codigo']);
    $nome = $conn->real_escape_string($_POST['nome']);
    $unidade = $conn->real_escape_string($_POST['unidade']);
    $valor = str_replace(array('R$', '.', ','), array('', '', '.'), $_POST['valor']);
    $descricao = $conn->real_escape_string($_POST['descricao']);
    $estoque_minimo = $conn->real_escape_string($_POST['estoque_minimo']);
    $estoque_atual = $conn->real_escape_string($_POST['estoque_atual']);
    $data_alteracao = date('Y-m-d H:i:s');
    
    $sql = "UPDATE itens SET 
            Nome = '$nome', 
            Unidade = '$unidade', 
            Valor = '$valor', 
            Descricao = '$descricao', 
            EstoqueMinimo = '$estoque_minimo', 
            EstoqueAtual = '$estoque_atual', 
            DataAlteracao = '$data_alteracao' 
            WHERE Codigo = '$codigo'";
    
    if ($conn->query($sql) === TRUE) {
        $success_message = "Item atualizado com sucesso!";
    } else {
        $error_message = "Erro ao atualizar item: " . $conn->error;
    }
}
    
// Processar adição de novo item
if (isset($_POST['add_item'])) {
    $nome = $conn->real_escape_string($_POST['nome']);
    $unidade = $conn->real_escape_string($_POST['unidade']);
    $valor = str_replace(array('R$', '.', ','), array('', '', '.'), $_POST['valor']);
    $descricao = $conn->real_escape_string($_POST['descricao']);
    $estoque_minimo = $conn->real_escape_string($_POST['estoque_minimo']);
    $estoque_atual = $conn->real_escape_string($_POST['estoque_atual']);
    $data_criacao = date('Y-m-d H:i:s');
    $data_alteracao = date('Y-m-d H:i:s');
    
    $sql = "INSERT INTO itens (Nome, Unidade, Valor, Descricao, EstoqueMinimo, EstoqueAtual, DataCriacao, DataAlteracao) 
            VALUES ('$nome', '$unidade', '$valor', '$descricao', '$estoque_minimo', '$estoque_atual', '$data_criacao', '$data_alteracao')";
    
    if ($conn->query($sql) === TRUE) {
        $success_message = "Novo item adicionado com sucesso!";
    } else {
        $error_message = "Erro ao adicionar item: " . $conn->error;
    }
}
    
// Consultar itens no estoque
$sql = "SELECT * FROM itens ORDER BY Nome ASC";
$result = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ONN Móveis - Gerenciamento de Estoque</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/datatables/1.10.21/css/jquery.dataTables.min.css">
    <style>
        :root {
            --primary-color: #3a7bd5;
            --secondary-color: #00d2ff;
            --danger-color: #ff4444;
            --success-color: #44aa44;
            --warning-color: #ffbb33;
            --light-grey: #f5f7fa;
            --dark-grey: #333;
            --white: #fff;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            background-color: var(--light-grey);
            min-height: 100vh;
        }

        .header {
            background: linear-gradient(45deg, var(--primary-color), var(--secondary-color));
            color: var(--white);
            padding: 15px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .header h1 {
            font-size: 22px;
        }

        .user-info {
            display: flex;
            align-items: center;
        }

        .user-info span {
            margin-right: 15px;
        }

        .logout-btn {
            background-color: rgba(255, 255, 255, 0.2);
            color: var(--white);
            border: none;
            padding: 6px 12px;
            border-radius: 4px;
            cursor: pointer;
            transition: background-color 0.3s;
        }

        .logout-btn:hover {
            background-color: rgba(255, 255, 255, 0.3);
        }

        .container {
            max-width: 1200px;
            margin: 20px auto;
            padding: 0 20px;
        }

        .card {
            background-color: var(--white);
            border-radius: 8px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.05);
            padding: 20px;
            margin-bottom: 20px;
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid var(--light-grey);
        }

        .btn {
            padding: 10px 16px;
            border-radius: 4px;
            border: none;
            cursor: pointer;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            transition: all 0.3s ease;
        }

        .btn i {
            margin-right: 8px;
        }

        .btn-primary {
            background: linear-gradient(45deg, var(--primary-color), var(--secondary-color));
            color: var(--white);
        }

        .btn-primary:hover {
            box-shadow: 0 5px 15px rgba(0, 210, 255, 0.3);
            transform: translateY(-2px);
        }

        .btn-danger {
            background-color: var(--danger-color);
            color: var(--white);
        }

        .btn-warning {
            background-color: var(--warning-color);
            color: var(--dark-grey);
        }

        .btn-sm {
            padding: 6px 10px;
            font-size: 14px;
        }

        .table-responsive {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }

        th, td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid var(--light-grey);
        }

        th {
            font-weight: 600;
            background-color: rgba(58, 123, 213, 0.1);
        }

        tr:hover {
            background-color: rgba(245, 247, 250, 0.6);
        }

        .actions {
            display: flex;
            gap: 8px;
        }

        .badge {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
        }

        .badge-danger {
            background-color: rgba(255, 68, 68, 0.2);
            color: var(--danger-color);
        }

        .badge-warning {
            background-color: rgba(255, 187, 51, 0.2);
            color: var(--warning-color);
        }

        .badge-success {
            background-color: rgba(68, 170, 68, 0.2);
            color: var(--success-color);
        }

        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            overflow: auto;
        }

        .modal-content {
            background-color: var(--white);
            border-radius: 8px;
            margin: 5% auto;
            width: 80%;
            max-width: 700px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.2);
            animation: slideDown 0.3s ease;
        }

        .modal-header {
            padding: 15px 20px;
            background: linear-gradient(45deg, var(--primary-color), var(--secondary-color));
            color: var(--white);
            border-top-left-radius: 8px;
            border-top-right-radius: 8px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-body {
            padding: 20px;
        }

        .modal-footer {
            padding: 15px 20px;
            border-top: 1px solid var(--light-grey);
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }

        .close {
            color: var(--white);
            font-size: 24px;
            font-weight: bold;
            cursor: pointer;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
        }

        .form-control {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 16px;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(58, 123, 213, 0.1);
        }

        .form-row {
            display: flex;
            gap: 15px;
            margin-bottom: 15px;
        }

        .form-row .form-group {
            flex: 1;
            margin-bottom: 0;
        }

        .alert {
            padding: 10px 15px;
            border-radius: 4px;
            margin-bottom: 15px;
        }

        .alert-success {
            background-color: rgba(68, 170, 68, 0.2);
            color: var(--success-color);
            border: 1px solid rgba(68, 170, 68, 0.3);
        }

        .alert-danger {
            background-color: rgba(255, 68, 68, 0.2);
            color: var(--danger-color);
            border: 1px solid rgba(255, 68, 68, 0.3);
        }

        @keyframes slideDown {
            from { transform: translateY(-50px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }

        .dataTables_wrapper .dataTables_filter input {
            padding: 6px 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            margin-left: 5px;
        }

        .dataTables_wrapper .dataTables_length select {
            padding: 6px 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }

        .dataTables_wrapper .dataTables_paginate .paginate_button {
            padding: 6px 12px;
            margin-left: 2px;
            border-radius: 4px;
        }

        .dataTables_wrapper .dataTables_paginate .paginate_button.current {
            background: linear-gradient(45deg, var(--primary-color), var(--secondary-color));
            color: var(--white) !important;
            border: none;
        }
    </style>
</head>
<body>
    <header class="header">
        <h1>ONN Móveis - Gerenciamento de Estoque</h1>
        <div class="user-info">
<div class="notification-bell">
    <i class="fas fa-bell" id="notificationBell"></i>
    <span class="notification-count" id="notificationCount" style="display: none;">0</span>
</div>

<a href="pedidos.php" class="btn btn-primary" style="margin-right: 10px;">
    <i class="fas fa-clipboard-list"></i> Pedidos
</a>
            <span>Olá, <?php echo htmlspecialchars($_SESSION['usuario']); ?></span>
            <a href="logout.php" class="logout-btn">
                <i class="fas fa-sign-out-alt"></i> Sair
            </a>
        </div>
    </header>

    <div class="container">
        <?php
        if (!empty($success_message)) {
            echo '<div class="alert alert-success">' . htmlspecialchars($success_message) . '</div>';
        }
        if (!empty($error_message)) {
            echo '<div class="alert alert-danger">' . htmlspecialchars($error_message) . '</div>';
        }
        ?>
        
        <div class="card">
            <div class="card-header">
                <h2>Itens em Estoque</h2>
                <button type="button" class="btn btn-primary" onclick="openAddModal()">
                    <i class="fas fa-plus"></i> Novo Item
                </button>
            </div>
            
            <div class="table-responsive">
                <table id="itemsTable" class="display">
                    <thead>
                        <tr>
                            <th>Código</th>
                            <th>Nome</th>
                            <th>Unidade</th>
                            <th>Valor (R$)</th>
                            <th>Estoque Atual</th>
                            <th>Status</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        if ($result->num_rows > 0) {
                            while($row = $result->fetch_assoc()) {
                                $status = '';
                                if ($row['EstoqueAtual'] <= 0) {
                                    $status = '<span class="badge badge-danger">Sem Estoque</span>';
                                } else if ($row['EstoqueAtual'] <= $row['EstoqueMinimo']) {
                                    $status = '<span class="badge badge-warning">Estoque Baixo</span>';
                                } else {
                                    $status = '<span class="badge badge-success">Estoque OK</span>';
                                }
                                
                                $item_data = array(
                                    'codigo' => $row['Codigo'],
                                    'nome' => $row['Nome'],
                                    'unidade' => $row['Unidade'],
                                    'valor' => $row['Valor'],
                                    'descricao' => $row['Descricao'],
                                    'estoque_minimo' => $row['EstoqueMinimo'],
                                    'estoque_atual' => $row['EstoqueAtual']
                                );
                                $item_json = htmlspecialchars(json_encode($item_data), ENT_QUOTES, 'UTF-8');
                                
                                echo '<tr>';
                                echo '<td>' . htmlspecialchars($row['Codigo']) . '</td>';
                                echo '<td>' . htmlspecialchars($row['Nome']) . '</td>';
                                echo '<td>' . htmlspecialchars($row['Unidade']) . '</td>';
                                echo '<td>' . number_format((float)str_replace(array('R$', ','), array('','.'), $row['Valor']), 2, ',', '.') . '</td>';
                                echo '<td>' . htmlspecialchars($row['EstoqueAtual']) . '</td>';
                                echo '<td>' . $status . '</td>';
                                echo '<td class="actions">
                                        <button class="btn btn-warning btn-sm" onclick="openEditModal(' . $item_json . ')">
                                            <i class="fas fa-edit"></i> Editar
                                        </button>
                                        <form method="post" style="display: inline;" onsubmit="return confirm(\'Tem certeza que deseja excluir este item?\');">
                                            <input type="hidden" name="item_id" value="' . htmlspecialchars($row['Codigo']) . '">
                                            <button type="submit" name="delete_item" class="btn btn-danger btn-sm">
                                                <i class="fas fa-trash"></i> Excluir
                                            </button>
                                        </form>
                                      </td>';
                                echo '</tr>';
                            }
                        } else {
                            echo '<tr><td colspan="7" style="text-align:center;">Nenhum item encontrado no estoque</td></tr>';
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div id="editModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Editar Item - Código: <span id="edit_codigo_display"></span></h2>
                <span class="close" onclick="closeEditModal()">&times;</span>
            </div>
            <div class="modal-body">
                <form method="post" id="editForm">
                    <input type="hidden" id="edit_codigo" name="codigo">
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="edit_nome">Nome do Item</label>
                            <input type="text" class="form-control" id="edit_nome" name="nome" required>
                        </div>
                        <div class="form-group">
                            <label for="edit_unidade">Unidade de Medida</label>
                            <select class="form-control" id="edit_unidade" name="unidade" required>
                                <option value="UN">Unidade (UN)</option>
                                <option value="PC">Peça (PC)</option>
                                <option value="MT">Metro (MT)</option>
                                <option value="KG">Quilograma (KG)</option>
                                <option value="LT">Litro (LT)</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="edit_valor">Valor Unitário (R$)</label>
                            <input type="text" class="form-control money" id="edit_valor" name="valor" required>
                        </div>
                        <div class="form-group">
                            <label for="edit_estoque_minimo">Estoque Mínimo</label>
                            <input type="number" min="0" class="form-control" id="edit_estoque_minimo" name="estoque_minimo" required>
                        </div>
                        <div class="form-group">
                            <label for="edit_estoque_atual">Estoque Atual</label>
                            <input type="number" min="0" class="form-control" id="edit_estoque_atual" name="estoque_atual" required>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_descricao">Descrição Detalhada</label>
                        <textarea class="form-control" id="edit_descricao" name="descricao" rows="4"></textarea>
                    </div>
                    
                    <div class="modal-footer">
                        <button type="button" class="btn btn-danger" onclick="closeEditModal()">
                            <i class="fas fa-times"></i> Cancelar
                        </button>
                        <button type="submit" name="update_item" class="btn btn-primary">
                            <i class="fas fa-save"></i> Salvar Alterações
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div id="addModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Adicionar Novo Item</h2>
                <span class="close" onclick="closeAddModal()">&times;</span>
            </div>
            <div class="modal-body">
                <form method="post" id="addForm">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="add_nome">Nome</label>
                            <input type="text" class="form-control" id="add_nome" name="nome" required>
                        </div>
                        <div class="form-group">
                            <label for="add_unidade">Unidade</label>
                            <input type="text" class="form-control" id="add_unidade" name="unidade" required>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="add_valor">Valor (R$)</label>
                            <input type="number" step="0.01" class="form-control" id="add_valor" name="valor" required>
                        </div>
                        <div class="form-group">
                            <label for="add_estoque_minimo">Estoque Mínimo</label>
                            <input type="number" class="form-control" id="add_estoque_minimo" name="estoque_minimo" required>
                        </div>
                        <div class="form-group">
                            <label for="add_estoque_atual">Estoque Atual</label>
                            <input type="number" class="form-control" id="add_estoque_atual" name="estoque_atual" required>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="add_descricao">Descrição</label>
                        <textarea class="form-control" id="add_descricao" name="descricao" rows="3"></textarea>
                    </div>
                    
                    <div class="modal-footer">
                        <button type="button" class="btn btn-danger" onclick="closeAddModal()">Cancelar</button>
                        <button type="submit" name="add_item" class="btn btn-primary">Adicionar Item</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/datatables/1.10.21/js/jquery.dataTables.min.js"></script>
    <script>
function atualizarNotificacoes() {
  fetch("check_novos_pedidos.php")
    .then(response => response.json())
    .then(data => {
      const countEl = document.getElementById("notificationCount");
      if (data.total > 0) {
        countEl.style.display = "inline-block";
        countEl.textContent = data.total;
      } else {
        countEl.style.display = "none";
        countEl.textContent = "0";
      }
    })
    .catch(error => console.error("Erro ao buscar notificações:", error));
}

setInterval(atualizarNotificacoes, 10000); // Atualiza a cada 10 segundos
atualizarNotificacoes(); // Executa na primeira carga

        $(document).ready(function() {
    $('#itemsTable').DataTable({
        language: {
            url: 'Portuguese-Brasil.json'
        },
        responsive: true
    });
            
            setTimeout(function() {
                $('.alert').fadeOut('slow');
            }, 5000);
        });
        
        var editModal = document.getElementById("editModal");

        function openEditModal(itemData) {
            var valorFormatado = parseFloat(itemData.valor).toFixed(2);
            
            document.getElementById("edit_codigo").value = itemData.codigo;
            document.getElementById("edit_codigo_display").textContent = itemData.codigo;
            document.getElementById("edit_nome").value = itemData.nome;
            document.getElementById("edit_unidade").value = itemData.unidade;
            document.getElementById("edit_valor").value = valorFormatado;
            document.getElementById("edit_descricao").value = itemData.descricao;
            document.getElementById("edit_estoque_minimo").value = itemData.estoque_minimo;
            document.getElementById("edit_estoque_atual").value = itemData.estoque_atual;
            
            $('#edit_valor').on('blur', function() {
                var valor = $(this).val();
                if(valor !== '') {
                    valor = parseFloat(valor.replace(/[^\d,]/g, '').replace(',', '.')).toFixed(2);
                    $(this).val(valor);
                }
            });
            
            editModal.style.display = "block";
        }

        function closeEditModal() {
            editModal.style.display = "none";
            $('#edit_valor').off('blur');
        }
        
        var addModal = document.getElementById("addModal");
        
        function openAddModal() {
            document.getElementById("addForm").reset();
            addModal.style.display = "block";
        }
        
        function closeAddModal() {
            addModal.style.display = "none";
        }
        
        window.onclick = function(event) {
            if (event.target == editModal) {
                closeEditModal();
            }
            if (event.target == addModal) {
                closeAddModal();
            }
        }
    </script>
</body>
</html>