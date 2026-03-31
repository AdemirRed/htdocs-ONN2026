<?php
session_start();

if (!isset($_SESSION['usuario'])) {
    header("Location: index.php");
    exit();
}

$conn = new mysqli("192.168.0.201", "root", "", "onnmoveis");
if ($conn->connect_error) {
    die("Erro: " . $conn->connect_error);
}

// Buscar todos os fornecedores
$fornecedores = [];
$res = $conn->query("SELECT Nome FROM fornecedor ORDER BY Nome ASC");
while ($f = $res->fetch_assoc()) {
    $fornecedores[] = $f['Nome'];
}

// Buscar dados do pedido
$pedido = null;
if (isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $sql = "SELECT * FROM pedidos WHERE id = $id";
    $result = $conn->query($sql);
    if ($result->num_rows > 0) {
        $pedido = $result->fetch_assoc();
    } else {
        $_SESSION['error'] = "Pedido não encontrado!";
        header("Location: pedidos.php");
        exit();
    }
} else {
    $_SESSION['error'] = "ID do pedido não especificado!";
    header("Location: pedidos.php");
    exit();
}

// Atualizar pedido
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['atualizar_pedido'])) {
    $id = intval($_POST['id']);
    $produto = $conn->real_escape_string($_POST['NomeProduto']);
    $fornecedor = $conn->real_escape_string($_POST['NomeFornecedor']);
    $quantidade = intval($_POST['Quantidade']);
    $unidade = $conn->real_escape_string($_POST['Unidade']);
    $obs = $conn->real_escape_string($_POST['Observacao']);
    $marceneiro = $conn->real_escape_string($_POST['PessoaMarceneiro']);

    // Converter status numérico em texto
    $statusOptions = [
        "1" => "Pendente",
        "2" => "Em andamento",
        "3" => "Concluído",
        "4" => "Cancelado"
    ];
    $status = $statusOptions[$_POST['Status']] ?? "Pendente";

    $sql = "UPDATE pedidos SET 
            NomeProduto = ?, 
            NomeFornecedor = ?, 
            Quantidade = ?, 
            Unidade = ?, 
            Observacao = ?, 
            PessoaMarceneiro = ?, 
            Status = ?,
            DataAlteracaoPedido = NOW()
            WHERE id = ?";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssissssi", $produto, $fornecedor, $quantidade, $unidade, $obs, $marceneiro, $status, $id);

    if ($stmt->execute()) {
        $_SESSION['success'] = "Pedido atualizado com sucesso!";
        header("Location: pedidos.php");
        exit();
    } else {
        $_SESSION['error'] = "Erro ao atualizar pedido: " . $conn->error;
    }
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Pedido - ONN Móveis</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="pedidos.css">
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><i class="fas fa-edit"></i> Editar Pedido #<?= $pedido['id'] ?></h1>
            <a href="pedidos.php" class="btn btn-primary">
                <i class="fas fa-arrow-left"></i> Voltar
            </a>
        </div>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger">
                <?= $_SESSION['error'] ?>
                <?php unset($_SESSION['error']); ?>
            </div>
        <?php endif; ?>

        <form method="POST" class="pedido-form">
            <input type="hidden" name="atualizar_pedido" value="1">
            <input type="hidden" name="id" value="<?= $pedido['id'] ?>">
            
            <div class="form-row">
                <div class="form-group">
                    <label><i class="fas fa-box"></i> Produto</label>
                    <input type="text" name="NomeProduto" required class="form-control" 
                           value="<?= htmlspecialchars($pedido['NomeProduto']) ?>">
                </div>
                <div class="form-group">
                    <label><i class="fas fa-truck"></i> Fornecedor</label>
                    <select name="NomeFornecedor" required class="form-control">
                        <option value="">Selecione um fornecedor</option>
                        <?php foreach ($fornecedores as $nome): ?>
                            <option value="<?= htmlspecialchars($nome) ?>" 
                                <?= $nome == $pedido['NomeFornecedor'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($nome) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label><i class="fas fa-hashtag"></i> Quantidade</label>
                    <input type="number" name="Quantidade" required class="form-control" 
                           min="1" value="<?= htmlspecialchars($pedido['Quantidade']) ?>">
                </div>
                <div class="form-group">
                    <label><i class="fas fa-ruler"></i> Unidade</label>
                    <select name="Unidade" required class="form-control">
                        <option value="UN" <?= $pedido['Unidade'] == 'UN' ? 'selected' : '' ?>>Unidade (UN)</option>
                        <option value="PC" <?= $pedido['Unidade'] == 'PC' ? 'selected' : '' ?>>Peça (PC)</option>
                        <option value="MT" <?= $pedido['Unidade'] == 'MT' ? 'selected' : '' ?>>Metro (MT)</option>
                        <option value="KG" <?= $pedido['Unidade'] == 'KG' ? 'selected' : '' ?>>Quilograma (KG)</option>
                        <option value="LT" <?= $pedido['Unidade'] == 'LT' ? 'selected' : '' ?>>Litro (LT)</option>
                    </select>
                </div>
                <div class="form-group">
                    <label><i class="fas fa-user-hard-hat"></i> Marceneiro</label>
                    <input type="text" name="PessoaMarceneiro" class="form-control" 
                           value="<?= htmlspecialchars($pedido['PessoaMarceneiro']) ?>">
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label><i class="fas fa-info-circle"></i> Status</label>
                    <select name="Status" required class="form-control">
                        <option value="1" <?= $pedido['Status'] == 'Pendente' ? 'selected' : '' ?>>Pendente</option>
                        <option value="2" <?= $pedido['Status'] == 'Em andamento' ? 'selected' : '' ?>>Em andamento</option>
                        <option value="3" <?= $pedido['Status'] == 'Concluído' ? 'selected' : '' ?>>Concluído</option>
                        <option value="4" <?= $pedido['Status'] == 'Cancelado' ? 'selected' : '' ?>>Cancelado</option>
                    </select>
                </div>
            </div>
            
            <div class="form-group">
                <label><i class="fas fa-comment"></i> Observação</label>
                <textarea name="Observacao" class="form-control"><?= htmlspecialchars($pedido['Observacao']) ?></textarea>
            </div>
            
            <div class="form-group">
                <label>Data do Pedido:</label>
                <p><?= date("d/m/Y H:i", strtotime($pedido['DataPedido'])) ?></p>
            </div>
            
            <?php if ($pedido['DataAlteracaoPedido']): ?>
            <div class="form-group">
                <label>Última Alteração:</label>
                <p><?= date("d/m/Y H:i", strtotime($pedido['DataAlteracaoPedido'])) ?></p>
            </div>
            <?php endif; ?>
            
            <div class="form-footer">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Salvar Alterações
                </button>
                <a href="pedidos.php" class="btn btn-danger">
                    <i class="fas fa-times"></i> Cancelar
                </a>
            </div>
        </form>
    </div>
</body>
</html>
