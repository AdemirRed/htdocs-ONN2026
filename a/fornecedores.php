<?php
/**
 * Gestão de Fornecedores - ONN Móveis
 * Cadastro, edição e exclusão
 */

ob_start();
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');

session_start();

if (!isset($_SESSION['usuario'])) {
    header("Location: index.php");
    exit();
}

class DatabaseConnection {
    private $conn;
    private $host = "192.168.0.201";
    private $port = 3306;
    private $username = "root";
    private $password = "";
    private $database = "onnmoveis";

    public function __construct() {
        $this->connect();
    }

    private function connect() {
        try {
            $configuredHost = $this->host;
            $serverAddr = $_SERVER['SERVER_ADDR'] ?? '';
            $effectiveHost = ($serverAddr && $configuredHost === $serverAddr) ? '127.0.0.1' : $configuredHost;

            $this->conn = new mysqli($effectiveHost, $this->username, $this->password, $this->database, $this->port);
            $this->conn->set_charset("utf8");

            if ($this->conn->connect_error) {
                throw new Exception("Erro na conexão: " . $this->conn->connect_error);
            }
        } catch (Exception $e) {
            error_log("Erro de conexão: " . $e->getMessage());
            die("Erro interno do servidor. Tente novamente mais tarde.");
        }
    }

    public function getConnection() {
        return $this->conn;
    }

    public function close() {
        if ($this->conn) {
            $this->conn->close();
        }
    }
}

function sanitize_text($input) {
    return htmlspecialchars(trim((string)$input), ENT_QUOTES, 'UTF-8');
}

function get_columns_map($conn) {
    $map = [];
    $result = $conn->query("SHOW COLUMNS FROM fornecedor");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $map[strtolower($row['Field'])] = $row['Field'];
        }
    }
    return $map;
}

function pick_column($map, $candidates) {
    foreach ($candidates as $candidate) {
        $key = strtolower($candidate);
        if (isset($map[$key])) {
            return $map[$key];
        }
    }
    return null;
}

$db = new DatabaseConnection();
$conn = $db->getConnection();

$columnMap = get_columns_map($conn);
$colId = pick_column($columnMap, ['id']);
$colNome = pick_column($columnMap, ['nome', 'nome_fornecedor', 'nomefornecedor', 'Nome']);
$colContato = pick_column($columnMap, ['contato', 'telefone', 'Contato', 'Telefone']);
$colEndereco = pick_column($columnMap, ['endereco', 'Endereço', 'Endereco']);
$colCategoria = pick_column($columnMap, ['categoria', 'Categoria']);

$errors = [];
$success = '';

if (!$colId || !$colNome) {
    $errors[] = 'Estrutura da tabela fornecedor inválida: coluna ID ou Nome não encontrada.';
}

// Processar ações
if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($errors)) {
    $acao = $_POST['acao'] ?? '';

    if ($acao === 'salvar') {
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        $nome = sanitize_text($_POST['nome'] ?? '');
        $contato = sanitize_text($_POST['contato'] ?? '');
        $endereco = sanitize_text($_POST['endereco'] ?? '');
        $categoria = sanitize_text($_POST['categoria'] ?? '');

        if ($nome === '') {
            $errors[] = 'Informe o nome do fornecedor.';
        } else {
            $fields = [];
            $values = [];
            $types = '';

            $fields[] = $colNome;
            $values[] = $nome;
            $types .= 's';

            if ($colContato) {
                $fields[] = $colContato;
                $values[] = $contato;
                $types .= 's';
            }
            if ($colEndereco) {
                $fields[] = $colEndereco;
                $values[] = $endereco;
                $types .= 's';
            }
            if ($colCategoria) {
                $fields[] = $colCategoria;
                $values[] = $categoria;
                $types .= 's';
            }

            if ($id > 0) {
                $setParts = [];
                foreach ($fields as $field) {
                    $setParts[] = "$field = ?";
                }
                $sql = "UPDATE fornecedor SET " . implode(', ', $setParts) . " WHERE $colId = ?";
                $types .= 'i';
                $values[] = $id;
                $stmt = $conn->prepare($sql);
                if ($stmt) {
                    $stmt->bind_param($types, ...$values);
                    if ($stmt->execute()) {
                        $success = 'Fornecedor atualizado com sucesso.';
                    } else {
                        $errors[] = 'Erro ao atualizar fornecedor: ' . $stmt->error;
                    }
                } else {
                    $errors[] = 'Erro ao preparar atualização.';
                }
            } else {
                $sql = "INSERT INTO fornecedor (" . implode(', ', $fields) . ") VALUES (" . implode(', ', array_fill(0, count($fields), '?')) . ")";
                $stmt = $conn->prepare($sql);
                if ($stmt) {
                    $stmt->bind_param($types, ...$values);
                    if ($stmt->execute()) {
                        $success = 'Fornecedor cadastrado com sucesso.';
                    } else {
                        $errors[] = 'Erro ao cadastrar fornecedor: ' . $stmt->error;
                    }
                } else {
                    $errors[] = 'Erro ao preparar cadastro.';
                }
            }
        }
    }

    if ($acao === 'excluir') {
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        if ($id > 0) {
            $stmt = $conn->prepare("DELETE FROM fornecedor WHERE $colId = ?");
            if ($stmt) {
                $stmt->bind_param("i", $id);
                if ($stmt->execute()) {
                    $success = 'Fornecedor excluído com sucesso.';
                } else {
                    $errors[] = 'Erro ao excluir fornecedor: ' . $stmt->error;
                }
            } else {
                $errors[] = 'Erro ao preparar exclusão.';
            }
        } else {
            $errors[] = 'ID inválido para exclusão.';
        }
    }
}

// Buscar fornecedor para edição
$fornecedorEdicao = [
    'id' => 0,
    'nome' => '',
    'contato' => '',
    'endereco' => '',
    'categoria' => ''
];

if (isset($_GET['edit']) && empty($errors) && $colId && $colNome) {
    $editId = intval($_GET['edit']);
    if ($editId > 0) {
        $selects = [
            "$colId AS id",
            ($colNome ? "$colNome AS nome" : "'' AS nome"),
            ($colContato ? "$colContato AS contato" : "'' AS contato"),
            ($colEndereco ? "$colEndereco AS endereco" : "'' AS endereco"),
            ($colCategoria ? "$colCategoria AS categoria" : "'' AS categoria")
        ];
        $sql = "SELECT " . implode(', ', $selects) . " FROM fornecedor WHERE $colId = ? LIMIT 1";
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $stmt->bind_param("i", $editId);
            if ($stmt->execute()) {
                $result = $stmt->get_result();
                $row = $result->fetch_assoc();
                if ($row) {
                    $fornecedorEdicao = $row;
                }
            }
        }
    }
}

// Listagem
$fornecedores = [];
if (empty($errors) && $colId) {
    $selects = [
        "$colId AS id",
        ($colNome ? "$colNome AS nome" : "'' AS nome"),
        ($colContato ? "$colContato AS contato" : "'' AS contato"),
        ($colEndereco ? "$colEndereco AS endereco" : "'' AS endereco"),
        ($colCategoria ? "$colCategoria AS categoria" : "'' AS categoria")
    ];
    $orderBy = $colNome ?: $colId;
    $result = $conn->query("SELECT " . implode(', ', $selects) . " FROM fornecedor ORDER BY $orderBy ASC");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $fornecedores[] = $row;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestão de Fornecedores - ONN Móveis</title>
    <link rel="icon" type="image/x-icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'%3E%3Ctext y='.9em' font-size='90'%3E🚚%3C/text%3E%3C/svg%3E">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="pedidos.css">
    <style>
        .fornecedor-form { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px; }
        .fornecedor-form .form-group { margin: 0; }
        .actions-inline { display: flex; gap: 8px; align-items: center; }
        .table-responsive { margin-top: 10px; }
        .btn-warning { background: #f0ad4e; color: #fff; }
        .btn-warning:hover { background: #ec971f; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><i class="fas fa-truck"></i> Gestão de Fornecedores</h1>
            <div class="user-info">
                <span>Olá, <?php echo htmlspecialchars($_SESSION['usuario']); ?></span>
                <a href="pedidos.php" class="btn btn-primary">
                    <i class="fas fa-arrow-left"></i> Pedidos
                </a>
                <a href="logout.php" class="btn btn-danger">
                    <i class="fas fa-sign-out-alt"></i> Sair
                </a>
            </div>
        </div>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i>
                <span><?php echo implode(' ', $errors); ?></span>
            </div>
        <?php elseif (!empty($success)): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <span><?php echo htmlspecialchars($success); ?></span>
            </div>
        <?php endif; ?>

        <section class="pedido-section">
            <h2><i class="fas fa-plus-circle"></i> <?php echo $fornecedorEdicao['id'] ? 'Editar Fornecedor' : 'Novo Fornecedor'; ?></h2>
            <form method="POST" class="fornecedor-form">
                <input type="hidden" name="acao" value="salvar">
                <input type="hidden" name="id" value="<?php echo htmlspecialchars($fornecedorEdicao['id']); ?>">

                <div class="form-group">
                    <label><i class="fas fa-id-badge"></i> Nome *</label>
                    <input type="text" name="nome" required class="form-control" maxlength="255" value="<?php echo htmlspecialchars($fornecedorEdicao['nome']); ?>">
                </div>

                <div class="form-group">
                    <label><i class="fas fa-phone"></i> Contato</label>
                    <input type="text" name="contato" class="form-control" maxlength="255" value="<?php echo htmlspecialchars($fornecedorEdicao['contato']); ?>">
                </div>

                <div class="form-group">
                    <label><i class="fas fa-map-marker-alt"></i> Endereço</label>
                    <input type="text" name="endereco" class="form-control" maxlength="500" value="<?php echo htmlspecialchars($fornecedorEdicao['endereco']); ?>">
                </div>

                <div class="form-group">
                    <label><i class="fas fa-tags"></i> Categoria</label>
                    <input type="text" name="categoria" class="form-control" maxlength="255" value="<?php echo htmlspecialchars($fornecedorEdicao['categoria']); ?>">
                </div>

                <div class="form-group">
                    <label>&nbsp;</label>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-save"></i> <?php echo $fornecedorEdicao['id'] ? 'Atualizar' : 'Cadastrar'; ?>
                    </button>
                    <?php if ($fornecedorEdicao['id']): ?>
                        <a href="fornecedores.php" class="btn btn-secondary">
                            <i class="fas fa-times"></i> Cancelar
                        </a>
                    <?php endif; ?>
                </div>
            </form>
        </section>

        <section class="lista-pedidos">
            <h2><i class="fas fa-list"></i> Fornecedores Cadastrados</h2>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Nome</th>
                            <th>Contato</th>
                            <th>Endereço</th>
                            <th>Categoria</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($fornecedores)): ?>
                            <?php foreach ($fornecedores as $f): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($f['id']); ?></td>
                                    <td><?php echo htmlspecialchars($f['nome']); ?></td>
                                    <td><?php echo htmlspecialchars($f['contato']); ?></td>
                                    <td><?php echo htmlspecialchars($f['endereco']); ?></td>
                                    <td><?php echo htmlspecialchars($f['categoria']); ?></td>
                                    <td>
                                        <div class="actions-inline">
                                            <a href="fornecedores.php?edit=<?php echo htmlspecialchars($f['id']); ?>" class="btn btn-primary btn-sm">
                                                <i class="fas fa-edit"></i> Editar
                                            </a>
                                            <form method="POST" onsubmit="return confirm('Deseja excluir este fornecedor?');" style="display:inline;">
                                                <input type="hidden" name="acao" value="excluir">
                                                <input type="hidden" name="id" value="<?php echo htmlspecialchars($f['id']); ?>">
                                                <button type="submit" class="btn btn-danger btn-sm">
                                                    <i class="fas fa-trash"></i> Excluir
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" style="text-align:center; padding: 30px; color: #999;">
                                    <i class="fas fa-inbox" style="font-size: 2em; margin-bottom: 10px; display:block;"></i>
                                    Nenhum fornecedor encontrado
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/js/all.min.js"></script>
</body>
</html>
