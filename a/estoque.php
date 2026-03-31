<?php
session_start();
    
// Verificar se o usuário está logado
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

// Conexão com o banco de dados
$dbHost = "192.168.0.201";
if (!empty($_SERVER['SERVER_ADDR']) && $_SERVER['SERVER_ADDR'] === $dbHost) {
    $dbHost = "127.0.0.1";
}
$conn = new mysqli($dbHost, "root", "", "onnmoveis");
    
if ($conn->connect_error) {
    die("Falha na conexão: " . $conn->connect_error);
}

// Variáveis para mensagens
$success_message = "";
$error_message = "";
    
// Processar exclusão de item
if (isset($_POST['delete_item']) && isset($_POST['item_id'])) {
    $item_id = $conn->real_escape_string($_POST['item_id']);
    
    // Iniciar transação para garantir integridade
    $conn->begin_transaction();
    
    try {
        // Primeiro, deletar registros relacionados na tabela log_estoque
        $sql_log = "DELETE FROM log_estoque WHERE item_id = '$item_id'";
        $conn->query($sql_log);
        
        // Depois, deletar o item
        $sql = "DELETE FROM itens WHERE Codigo = '$item_id'";
        
        if ($conn->query($sql) === TRUE) {
            $conn->commit();
            $_SESSION['success_message'] = "Item excluído com sucesso!";
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit();
        } else {
            $conn->rollback();
            $error_message = "Erro ao excluir item: " . $conn->error;
        }
    } catch (Exception $e) {
        $conn->rollback();
        $error_message = "Erro ao excluir item: " . $e->getMessage();
    }
}
    
// Processar atualização de item
if (isset($_POST['update_item'])) {
    $codigo = $conn->real_escape_string($_POST['codigo']);
    $nome = $conn->real_escape_string($_POST['nome']);
    $unidade = $conn->real_escape_string($_POST['unidade']);
    
    // Limpar e padronizar valor
    $valor = $_POST['valor'];
    $valor = str_replace(['R$', ' '], '', $valor); // Remove R$ e espaços
    $valor = str_replace(',', '.', $valor); // Troca vírgula por ponto
    $valor = floatval($valor); // Converte para float
    $valor = round($valor * 100); // Converte para centavos e arredonda
    
    $descricao = $conn->real_escape_string($_POST['descricao']);
    $estoque_minimo = $conn->real_escape_string($_POST['estoque_minimo']);
    $estoque_atual = $conn->real_escape_string($_POST['estoque_atual']);
    $data_alteracao = date('Y-m-d H:i:s');
    
    // Upload de imagem (se enviada)
    $imagem_update = "";
    if (isset($_FILES['imagem']) && $_FILES['imagem']['error'] == 0) {
        $upload_dir = __DIR__ . '/uploads/materiais/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        $extensao = strtolower(pathinfo($_FILES['imagem']['name'], PATHINFO_EXTENSION));
        $extensoes_permitidas = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        
        if (in_array($extensao, $extensoes_permitidas)) {
            $nome_arquivo = uniqid() . '_' . time() . '.' . $extensao;
            $caminho_completo = $upload_dir . $nome_arquivo;
            
            if (move_uploaded_file($_FILES['imagem']['tmp_name'], $caminho_completo)) {
                $imagem_path = 'uploads/materiais/' . $nome_arquivo;
                $imagem_update = ", imagem = '$imagem_path'";
            }
        }
    }
    
    $sql = "UPDATE itens SET 
            Nome = '$nome', 
            Unidade = '$unidade', 
            Valor = '$valor', 
            Descricao = '$descricao', 
            EstoqueMinimo = '$estoque_minimo', 
            EstoqueAtual = '$estoque_atual', 
            DataAlteracao = '$data_alteracao'
            $imagem_update 
            WHERE Codigo = '$codigo'";
    
    if ($conn->query($sql) === TRUE) {
        $_SESSION['success_message'] = "Item atualizado com sucesso!";
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit();
    } else {
        $error_message = "Erro ao atualizar item: " . $conn->error;
    }
}
    
// Processar adição de novo item
if (isset($_POST['add_item'])) {
    $nome = $conn->real_escape_string($_POST['nome']);
    $unidade = $conn->real_escape_string($_POST['unidade']);
    
    // Limpar e padronizar valor
    $valor = $_POST['valor'];
    $valor = str_replace(['R$', ' '], '', $valor); // Remove R$ e espaços
    $valor = str_replace(',', '.', $valor); // Troca vírgula por ponto
    $valor = floatval($valor); // Converte para float
    $valor = round($valor * 100); // Converte para centavos e arredonda
    
    $descricao = $conn->real_escape_string($_POST['descricao']);
    $estoque_minimo = $conn->real_escape_string($_POST['estoque_minimo']);
    $estoque_atual = $conn->real_escape_string($_POST['estoque_atual']);
    $data_criacao = date('Y-m-d H:i:s');
    $data_alteracao = date('Y-m-d H:i:s');
    
    // Upload de imagem
    $imagem_path = null;
    if (isset($_FILES['imagem']) && $_FILES['imagem']['error'] == 0) {
        $upload_dir = __DIR__ . '/uploads/materiais/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        $extensao = strtolower(pathinfo($_FILES['imagem']['name'], PATHINFO_EXTENSION));
        $extensoes_permitidas = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        
        if (in_array($extensao, $extensoes_permitidas)) {
            $nome_arquivo = uniqid() . '_' . time() . '.' . $extensao;
            $caminho_completo = $upload_dir . $nome_arquivo;
            
            if (move_uploaded_file($_FILES['imagem']['tmp_name'], $caminho_completo)) {
                $imagem_path = 'uploads/materiais/' . $nome_arquivo;
            }
        }
    }
    
    $sql = "INSERT INTO itens (Nome, Unidade, Valor, Descricao, EstoqueMinimo, EstoqueAtual, DataCriacao, DataAlteracao, imagem) 
            VALUES ('$nome', '$unidade', '$valor', '$descricao', '$estoque_minimo', '$estoque_atual', '$data_criacao', '$data_alteracao', " . 
            ($imagem_path ? "'$imagem_path'" : "NULL") . ")";
    
    if ($conn->query($sql) === TRUE) {
        $_SESSION['success_message'] = "Novo item adicionado com sucesso!";
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit();
    } else {
        $error_message = "Erro ao adicionar item: " . $conn->error;
    }
}
    
// Recuperar mensagem de sucesso da sessão
if (isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
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
            --bg-dark: #0f1117;
            --bg-darker: #1a1d23;
            --bg-card: #23262d;
            --bg-input: #2d3139;
            --bg-hover: #353945;
            --primary: #4a9eff;
            --primary-hover: #357abd;
            --success: #10b981;
            --danger: #ef4444;
            --warning: #f59e0b;
            --text-primary: #e5e7eb;
            --text-secondary: #9ca3af;
            --text-muted: #6b7280;
            --border: #374151;
            --shadow-sm: 0 2px 8px rgba(0, 0, 0, 0.3);
            --shadow-md: 0 4px 16px rgba(0, 0, 0, 0.4);
            --shadow-lg: 0 8px 24px rgba(0, 0, 0, 0.5);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
        }

        body {
            background: linear-gradient(135deg, #0f1117 0%, #1a1d23 100%);
            color: var(--text-primary);
            min-height: 100vh;
        }

        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px 32px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: var(--shadow-lg);
        }

        .header h1 {
            font-size: 1.5rem;
            font-weight: 700;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .user-info span {
            font-weight: 500;
        }

        .logout-btn,
        .btn {
            padding: 10px 20px;
            border-radius: 8px;
            border: none;
            cursor: pointer;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s ease;
        }

        .logout-btn {
            background: rgba(239, 68, 68, 0.9);
            color: white;
        }

        .logout-btn:hover {
            background: var(--danger);
            transform: translateY(-2px);
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 32px 20px;
        }

        .card {
            background: var(--bg-card);
            border-radius: 16px;
            box-shadow: var(--shadow-md);
            padding: 32px;
            margin-bottom: 32px;
            border: 1px solid var(--border);
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 28px;
            padding-bottom: 20px;
            border-bottom: 2px solid var(--border);
        }

        .card-header h2 {
            color: var(--text-primary);
            font-size: 1.5rem;
            font-weight: 700;
        }

        .btn-primary {
            background: var(--primary);
            color: white;
        }

        .btn-primary:hover {
            background: var(--primary-hover);
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }

        .btn-danger {
            background: var(--danger);
            color: white;
        }

        .btn-warning {
            background: var(--warning);
            color: #1a1d23;
        }

        .btn-sm {
            padding: 8px 14px;
            font-size: 0.875rem;
        }

        .btn i {
            margin-right: 6px;
        }

        .table-responsive {
            overflow-x: auto;
            background: var(--bg-darker);
            border-radius: 12px;
            border: 1px solid var(--border);
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 0;
        }

        th, td {
            padding: 16px;
            text-align: left;
            border-bottom: 1px solid var(--border);
        }

        th {
            font-weight: 600;
            background: var(--bg-input);
            color: var(--text-primary);
            border-bottom: 2px solid var(--border);
        }

        tbody tr {
            background: var(--bg-card);
        }

        td {
            color: var(--text-secondary);
        }

        tbody tr:hover {
            background: var(--bg-hover);
        }

        .actions {
            display: flex;
            gap: 8px;
        }

        .badge {
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .badge-danger {
            background: rgba(239, 68, 68, 0.2);
            color: #fca5a5;
            border: 1px solid rgba(239, 68, 68, 0.3);
        }

        .badge-warning {
            background: rgba(245, 158, 11, 0.2);
            color: #fbbf24;
            border: 1px solid rgba(245, 158, 11, 0.3);
        }

        .badge-success {
            background: rgba(16, 185, 129, 0.2);
            color: #6ee7b7;
            border: 1px solid rgba(16, 185, 129, 0.3);
        }

        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.7);
            overflow: auto;
        }

        .modal-content {
            background: var(--bg-card);
            border-radius: 16px;
            margin: 3% auto;
            width: 90%;
            max-width: 700px;
            box-shadow: var(--shadow-lg);
            border: 1px solid var(--border);
            animation: slideDown 0.3s ease;
        }

        @keyframes slideDown {
            from { transform: translateY(-50px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }

        .modal-header {
            padding: 24px 28px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-top-left-radius: 16px;
            border-top-right-radius: 16px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-header h2 {
            margin: 0;
            font-size: 1.25rem;
            font-weight: 700;
        }

        .modal-body {
            padding: 28px;
            background: var(--bg-darker);
        }

        .modal-footer {
            padding: 20px 28px;
            border-top: 1px solid var(--border);
            display: flex;
            justify-content: flex-end;
            gap: 12px;
            background: var(--bg-darker);
            border-bottom-left-radius: 16px;
            border-bottom-right-radius: 16px;
        }

        .close {
            color: white;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            transition: transform 0.2s;
        }

        .close:hover {
            transform: rotate(90deg);
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--text-primary);
        }

        .form-control {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid var(--border);
            border-radius: 8px;
            font-size: 1rem;
            background: var(--bg-input);
            color: var(--text-primary);
            transition: all 0.2s ease;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            background: var(--bg-card);
            box-shadow: 0 0 0 4px rgba(74, 158, 255, 0.1);
        }

        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }

        .form-row .form-group {
            margin-bottom: 0;
        }

        .alert {
            padding: 16px 20px;
            border-radius: 12px;
            margin-bottom: 24px;
            animation: fadeIn 0.3s ease;
            border: 1px solid;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .alert-success {
            background: rgba(16, 185, 129, 0.15);
            color: #6ee7b7;
            border-color: rgba(16, 185, 129, 0.3);
        }

        .alert-danger {
            background: rgba(239, 68, 68, 0.15);
            color: #fca5a5;
            border-color: rgba(239, 68, 68, 0.3);
        }

        .dataTables_wrapper .dataTables_filter input {
            padding: 10px 16px;
            border: 2px solid var(--border);
            border-radius: 8px;
            margin-left: 8px;
            background: var(--bg-input);
            color: var(--text-primary);
        }

        .dataTables_wrapper .dataTables_length select {
            padding: 8px 12px;
            border: 2px solid var(--border);
            border-radius: 8px;
            background: var(--bg-input);
            color: var(--text-primary);
        }

        .dataTables_wrapper .dataTables_paginate .paginate_button {
            padding: 8px 14px;
            margin-left: 4px;
            border-radius: 8px;
            background: var(--bg-input);
            color: var(--text-primary) !important;
            border: 1px solid var(--border);
        }

        .dataTables_wrapper .dataTables_paginate .paginate_button.current {
            background: var(--primary) !important;
            color: white !important;
            border: none;
        }

        .dataTables_wrapper .dataTables_info {
            color: var(--text-secondary);
        }

        .dataTables_wrapper .dataTables_length,
        .dataTables_wrapper .dataTables_filter {
            color: var(--text-primary);
        }

        /* Scrollbar */
        ::-webkit-scrollbar {
            width: 12px;
            height: 12px;
        }

        ::-webkit-scrollbar-track {
            background: var(--bg-darker);
            border-radius: 10px;
        }

        ::-webkit-scrollbar-thumb {
            background: var(--border);
            border-radius: 10px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: var(--text-muted);
        }

        @media (max-width: 768px) {
            .header {
                flex-direction: column;
                gap: 16px;
                text-align: center;
            }

            .container {
                padding: 20px 12px;
            }

            .card {
                padding: 20px;
            }

            .card-header {
                flex-direction: column;
                gap: 16px;
                align-items: stretch;
            }

            .form-row {
                grid-template-columns: 1fr;
            }

            .actions {
                flex-direction: column;
            }

            table {
                font-size: 0.875rem;
            }

            th, td {
                padding: 12px 8px;
            }
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

<a href="../requisicoes/dashboard.php" class="btn btn-primary" style="margin-right: 10px;">
    <i class="fas fa-chart-bar"></i> Dashboard
</a>
<a href="../requisicoes/index.php" class="btn btn-primary" style="margin-right: 10px;">
    <i class="fas fa-clipboard-list"></i> Requisições
</a>
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
                                echo '<td>' . number_format((float)$row['Valor'] / 100, 2, ',', '.') . '</td>';
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
                <form method="post" id="editForm" enctype="multipart/form-data">
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
                    
                    <div class="form-group">
                        <label for="edit_imagem"><i class="fas fa-image"></i> Alterar Imagem (opcional)</label>
                        <input type="file" class="form-control" id="edit_imagem" name="imagem" accept="image/*" onchange="previewImage(this, 'preview_edit')">
                        <div id="preview_edit" style="margin-top: 10px; display: none;">
                            <img src="" style="max-width: 200px; max-height: 200px; border-radius: 8px; border: 2px solid var(--border);">
                        </div>
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
                <form method="post" id="addForm" enctype="multipart/form-data">
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
                    
                    <div class="form-group">
                        <label for="add_imagem"><i class="fas fa-image"></i> Imagem do Material (opcional)</label>
                        <input type="file" class="form-control" id="add_imagem" name="imagem" accept="image/*" onchange="previewImage(this, 'preview_add')">
                        <div id="preview_add" style="margin-top: 10px; display: none;">
                            <img src="" style="max-width: 200px; max-height: 200px; border-radius: 8px; border: 2px solid var(--border);">
                        </div>
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
            var valorFormatado = (parseFloat(itemData.valor) / 100).toFixed(2);
            
            document.getElementById("edit_codigo").value = itemData.codigo;
            document.getElementById("edit_codigo_display").textContent = itemData.codigo;
            document.getElementById("edit_nome").value = itemData.nome;
            document.getElementById("edit_unidade").value = itemData.unidade;
            document.getElementById("edit_valor").value = valorFormatado;
            document.getElementById("edit_descricao").value = itemData.descricao;
            document.getElementById("edit_estoque_minimo").value = itemData.estoque_minimo;
            document.getElementById("edit_estoque_atual").value = itemData.estoque_atual;
            
            // Mostrar imagem atual se existir
            var previewEdit = document.getElementById("preview_edit");
            if (itemData.imagem && itemData.imagem !== '' && itemData.imagem !== 'NULL') {
                previewEdit.style.display = 'block';
                previewEdit.querySelector('img').src = itemData.imagem;
            } else {
                previewEdit.style.display = 'none';
            }
            
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
            
            // Adicionar formatação de valor para o campo de adicionar
            $('#add_valor').on('blur', function() {
                var valor = $(this).val();
                if(valor !== '') {
                    valor = parseFloat(valor.replace(/[^\d,]/g, '').replace(',', '.')).toFixed(2);
                    $(this).val(valor);
                }
            });
            
            addModal.style.display = "block";
        }
        
        function closeAddModal() {
            addModal.style.display = "none";
            $('#add_valor').off('blur');
        }
        
        window.onclick = function(event) {
            if (event.target == editModal) {
                closeEditModal();
            }
            if (event.target == addModal) {
                closeAddModal();
            }
        }
        
        // Preview de imagem ao selecionar arquivo
        function previewImage(input, previewId) {
            const preview = document.getElementById(previewId);
            const img = preview.querySelector('img');
            
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                
                reader.onload = function(e) {
                    img.src = e.target.result;
                    preview.style.display = 'block';
                }
                
                reader.readAsDataURL(input.files[0]);
            } else {
                preview.style.display = 'none';
            }
        }
    </script>
</body>
</html>