<?php
/**
 * Sistema de Gestão de Pedidos - ONN Móveis
 * Versão Melhorada com foco em segurança, organização e suporte a envio de imagens
 * Atualizado em: 2025-07-10
 */

// Configurações iniciais
error_reporting(E_ALL);
ini_set('display_errors', 0); // Desabilitar em produção
header('Content-Type: text/html; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');

session_start();

// Classe para gerenciar conexão com banco
class DatabaseConnection {
    private $conn;
    private $host = "192.168.0.201";
    private $username = "root";
    private $password = "";
    private $database = "onnmoveis";
    
    public function __construct() {
        $this->connect();
    }
    
    private function connect() {
        try {
            $this->conn = new mysqli($this->host, $this->username, $this->password, $this->database);
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

// Classe para validação e sanitização
class Validator {
    public static function sanitizeString($input) {
        return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
    }
    
    public static function validateInteger($input, $min = 1) {
        $value = filter_var($input, FILTER_VALIDATE_INT);
        return ($value !== false && $value >= $min) ? $value : false;
    }
    
    public static function validatePhone($phone) {
        $cleaned = preg_replace('/[^0-9]/', '', $phone);
        return (strlen($cleaned) >= 10 && strlen($cleaned) <= 15) ? $cleaned : false;
    }
    
    public static function validateStatus($status) {
        $validStatuses = ['pendente', 'concluido', 'cancelado', 'analise'];
        return in_array($status, $validStatuses) ? $status : false;
    }
}

// Classe principal do sistema
class PedidosManager {
    private $db;
    private $conn;
    
    private $statusMap = [
        'pendente' => 'Pendente',
        'concluido' => 'Concluído',
        'cancelado' => 'Cancelado',
        'analise' => 'Análise'
    ];
    
    private $statusParaClasse = [
        'Pendente' => 'pendente',
        'Concluído' => 'concluido',
        'Cancelado' => 'cancelado',
        'Análise' => 'analise'
    ];

    private $zdg_api_url = 'http://192.168.0.201:200/client/sendMessage/';
    private $zdg_session_id = 'red';
    
    public function __construct() {
        $this->db = new DatabaseConnection();
        $this->conn = $this->db->getConnection();
        $this->verificarAutenticacao();
    }
    
    private function verificarAutenticacao() {
        if (!isset($_SESSION['usuario'])) {
            header("Location: index.php");
            exit();
        }
    }
    
    public function getFornecedores() {
        try {
            $fornecedores = [];
            $stmt = $this->conn->prepare("SELECT Nome FROM fornecedor ORDER BY Nome ASC");
            $stmt->execute();
            $result = $stmt->get_result();
            
            while ($row = $result->fetch_assoc()) {
                $fornecedores[] = $row['Nome'];
            }
            
            return $fornecedores;
        } catch (Exception $e) {
            error_log("Erro ao buscar fornecedores: " . $e->getMessage());
            return [];
        }
    }
    
    public function adicionarPedido($dados) {
        try {
            // Validação dos dados
            $produto = Validator::sanitizeString($dados['NomeProduto']);
            $fornecedor = Validator::sanitizeString($dados['NomeFornecedor']);
            $quantidade = Validator::validateInteger($dados['Quantidade']);
            $unidade = Validator::sanitizeString($dados['Unidade']);
            $obs = Validator::sanitizeString($dados['Observacao'] ?? '');
            $marceneiro = Validator::sanitizeString($dados['PessoaMarceneiro'] ?? '');
            $usuario = $_SESSION['usuario'];
            
            if (!$produto || !$fornecedor || !$quantidade || !$unidade) {
                throw new Exception("Dados inválidos fornecidos");
            }
            
            // Verificar se fornecedor existe
            if (!$this->verificarFornecedor($fornecedor)) {
                throw new Exception("Fornecedor não encontrado");
            }
            
            // Inserir pedido
            $sql = "INSERT INTO pedidos (
                NomeProduto, NomeFornecedor, Quantidade, Unidade, Observacao,
                DataPedido, NomeUsuario, Status, AdicionadoAoEstoque, item_id, PessoaMarceneiro
            ) VALUES (?, ?, ?, ?, ?, NOW(), ?, 'Pendente', 0, NULL, ?)";
            
            $stmt = $this->conn->prepare($sql);
            $stmt->bind_param("ssissss", $produto, $fornecedor, $quantidade, $unidade, $obs, $usuario, $marceneiro);
            
            if ($stmt->execute()) {
                $pedido_id = $this->conn->insert_id;
                $this->registrarNotificacao('novo_pedido', $pedido_id, "Novo pedido criado: $produto");

                // Enviar mensagem para o RH via API ZDG
                $mensagem = $this->gerarMensagemWhatsAppRH($produto, $quantidade, $unidade, $usuario, $marceneiro, $obs);
                $envio = $this->enviarWhatsAppRH($mensagem);
                
                return [
                    'success' => true,
                    'pedido_id' => $pedido_id,
                    'whatsapp_envio' => $envio
                ];
                
                // Log do resultado do envio
                error_log("Resultado envio WhatsApp: " . json_encode($envio));
                
            }
            
            throw new Exception("Erro ao inserir pedido");
            
        } catch (Exception $e) {
            error_log("Erro ao adicionar pedido: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    private function enviarWhatsAppRH($mensagem, $numeros = null) {
        $sessionId = $this->zdg_session_id;
        $url = $this->zdg_api_url . $sessionId;
        // Permitir múltiplos números, separados por vírgula ou array
        if ($numeros === null) {
            $numeros = ["555131026660", "555199326748"]; // padrão
        } elseif (is_string($numeros)) {
            $numeros = array_map('trim', explode(',', $numeros));
        }
    
        $resultados = [];
        foreach ($numeros as $numero) {
            $chatId = $numero . "@c.us";
            $payload = [
                "chatId" => $chatId,
                "contentType" => "string",
                "content" => $mensagem
            ];
    
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'x-api-key: redblack'
            ]);
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);
    
            if ($httpCode === 200 && $response) {
                $data = json_decode($response, true);
                $resultados[] = [
                    'success' => true,
                    'message_id' => $data['message']['id'] ?? null,
                    'timestamp' => $data['message']['timestamp'] ?? date('Y-m-d H:i:s'),
                    'recipient' => $chatId,
                    'message' => $mensagem
                ];
            } else {
                $resultados[] = [
                    'success' => false,
                    'error' => $curlError ?: $response,
                    'recipient' => $chatId
                ];
            }
        }
        return $resultados;
    }

    private function gerarMensagemWhatsAppRH($produto, $quantidade, $unidade, $usuario, $marceneiro, $obs) {
        return "✍️(◔◡◔) *NOVO PEDIDO REGISTRADO - ONN MÓVEIS* ✍️(◔◡◔)\n\n"
            . "🔹 *Produto:* $produto\n"
            . "🔹 *Quantidade:* $quantidade $unidade\n"
            . "🔹 *Solicitante:* $usuario\n"
            . (!empty($marceneiro) ? "🔹 *Responsável:* $marceneiro 👈(ﾟヮﾟ👈)\n" : "")
            . (!empty($obs) ? "🔹 *Observações:* $obs\n\n" : "\n")
            . "⏱ *Data/Hora:* " . date('d/m/Y H:i') . "\n\n"
            . "📊 *Acesse o sistema:* https://encurtador.com.br/Swyys";
    }
    
    private function verificarFornecedor($nome) {
        $stmt = $this->conn->prepare("SELECT id FROM fornecedor WHERE Nome = ? LIMIT 1");
        $stmt->bind_param("s", $nome);
        $stmt->execute();
        return $stmt->get_result()->num_rows > 0;
    }
    
    private function registrarNotificacao($tipo, $pedido_id, $mensagem) {
        try {
            $stmt = $this->conn->prepare("INSERT INTO notificacoes (tipo, pedido_id, mensagem, data_criacao) VALUES (?, ?, ?, NOW())");
            $stmt->bind_param("sis", $tipo, $pedido_id, $mensagem);
            $stmt->execute();
        } catch (Exception $e) {
            error_log("Erro ao registrar notificação: " . $e->getMessage());
        }
    }
    
    public function concluirPedido($id) {
        try {
            $id = Validator::validateInteger($id);
            if (!$id) {
                throw new Exception("ID inválido");
            }
            
            $stmt = $this->conn->prepare("UPDATE pedidos SET Status = 'Concluído', DataAlteracaoPedido = NOW() WHERE id = ?");
            $stmt->bind_param("i", $id);
            
            return $stmt->execute();
        } catch (Exception $e) {
            error_log("Erro ao concluir pedido: " . $e->getMessage());
            return false;
        }
    }
    
    public function getPedidos($statusFiltro = '') {
        try {
            $sql = "SELECT p.*, f.Contato AS ContatoFornecedor 
                    FROM pedidos p
                    LEFT JOIN fornecedor f ON p.NomeFornecedor = f.Nome";
            
            $params = [];
            $types = "";
            
            if ($statusFiltro && isset($this->statusMap[$statusFiltro])) {
                $sql .= " WHERE p.Status = ?";
                $params[] = $this->statusMap[$statusFiltro];
                $types .= "s";
            }
            
            $sql .= " ORDER BY p.Status ASC, p.DataPedido DESC";
            
            $stmt = $this->conn->prepare($sql);
            if (!empty($params)) {
                $stmt->bind_param($types, ...$params);
            }
            
            $stmt->execute();
            return $stmt->get_result();
            
        } catch (Exception $e) {
            error_log("Erro ao buscar pedidos: " . $e->getMessage());
            return false;
        }
    }
    
    public function gerarMensagemWhatsAppFornecedor($pedido) {
        $observacao = !empty($pedido['Observacao']) ? $pedido['Observacao'] : 'Nenhuma';
        
        return "Mensagem Automática Para Pedidos.\n\n" .
            "Nome do Item: *{$pedido['NomeProduto']}*\n" .
            "Quantidade: {$pedido['Quantidade']}\n" .
            "Unidade: {$pedido['Unidade']}\n" .
            "Observação: $observacao\n" .
            "Solicitante: {$pedido['NomeUsuario']}\n\n" .
            "Endereço para a entrega: R. Arno Wickert, 44 - Industrial, Dois Irmãos - RS, 93950-000\n" .
            "https://maps.app.goo.gl/cPVRLrGF4vZkxEqh7\n\n" .
            "Feito por _RedBlack_";
    }
    
    public function getStatusMap() {
        return $this->statusMap;
    }
    
    public function getStatusParaClasse() {
        return $this->statusParaClasse;
    }
    
    public function __destruct() {
        if ($this->db) {
            $this->db->close();
        }
    }
}

// Função para envio de imagens via WhatsApp API
function enviarImagemWhatsApp($urlImagem, $chatId = null) {
    // Números padrão de destino
    $numeros = ["555131026660", "555199326748"];
    $sessionId = "red2";
    $apiUrl = "http://192.168.0.201:200/client/sendMessage/" . $sessionId;
    
    $resultados = [];
    
    // Se foi passado um chatId específico, use-o, senão envie para todos os números padrão
    if ($chatId !== null) {
        $destinatarios = [$chatId];
    } else {
        $destinatarios = array_map(function($numero) {
            return $numero . "@c.us";
        }, $numeros);
    }
    
    foreach ($destinatarios as $destinatario) {
        $payload = [
            "chatId" => $destinatario,
            "contentType" => "MessageMediaFromURL",
            "content" => $urlImagem
        ];
        
        $ch = curl_init($apiUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'x-api-key: redblack'
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        
        $resultados[] = [
            'success' => ($httpCode === 200),
            'destinatario' => $destinatario,
            'response' => $response,
            'error' => $curlError
        ];
    }
    
    // Opcional: registrar log dos resultados de envio
    // file_put_contents('log_envio_img.txt', date('Y-m-d H:i:s') . " - " . json_encode($resultados) . "\n", FILE_APPEND);
    
    return $resultados;
}

// Inicializar sistema
$pedidosManager = new PedidosManager();

// Processar requisições POST
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // Adicionar novo pedido
    if (isset($_POST['adicionar_pedido'])) {
        $resultado = $pedidosManager->adicionarPedido($_POST);

        if ($resultado['success']) {
            // --- Salvar e ENVIAR IMAGENS se houver ---
            $imagensEnviadas = [];
            if (!empty($_FILES['imagens']['name'][0])) {
                foreach ($_FILES['imagens']['tmp_name'] as $i => $tmpName) {
                    $nomeOriginal = $_FILES['imagens']['name'][$i];
                    $ext = strtolower(pathinfo($nomeOriginal, PATHINFO_EXTENSION));
                    if (in_array($ext, ['jpg','jpeg','png','gif','bmp','webp'])) {
                        $nomeFinal = uniqid('pedidoimg_', true) . '.' . $ext;
                        $destino = __DIR__ . '/uploads/' . $nomeFinal;
                        if (!is_dir(__DIR__ . '/uploads')) mkdir(__DIR__ . '/uploads', 0777, true);
                        move_uploaded_file($tmpName, $destino);
                        $urlImagem = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on' ? "https" : "http") 
                            . "://$_SERVER[HTTP_HOST]" . dirname($_SERVER['PHP_SELF']) . '/uploads/' . $nomeFinal;

                        // Enviar para todos os números padrão - sem especificar chatId para usar os padrões
                        $resultadosEnvio = enviarImagemWhatsApp($urlImagem);
                        $imagensEnviadas[] = $urlImagem;
                    }
                }
            }
            // --- feedback visual permanece igual ---
            echo "<!DOCTYPE html>
<html lang='pt-BR'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Pedido Registrado - ONN Móveis</title>
    <link rel='stylesheet' href='https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css'>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); margin: 0; padding: 20px; min-height: 100vh; display: flex; align-items: center; justify-content: center; }
        .container { background: white; padding: 40px; border-radius: 15px; box-shadow: 0 15px 35px rgba(0,0,0,0.1); text-align: center; max-width: 500px; }
        .success-icon { color: #28a745; font-size: 4em; margin-bottom: 20px; }
        h2 { color: #333; margin-bottom: 20px; }
        p { color: #666; margin-bottom: 15px; line-height: 1.6; }
        .btn { display: inline-block; padding: 12px 25px; margin: 10px; text-decoration: none; border-radius: 8px; font-weight: 500; transition: all 0.3s ease; }
        .btn-voltar { background: #007bff; color: white; }
        .btn-voltar:hover { background: #0056b3; transform: translateY(-2px); }
        .countdown { font-size: 0.9em; color: #888; margin-top: 20px; }
        .img-preview { margin: 10px 4px 0 4px; border-radius: 6px; border: 1px solid #ccc; width: 60px; vertical-align: middle;}
    </style>
    <script>
        setTimeout(function() {
            window.location.href = 'pedidos.php';
        }, 3000);
    </script>
</head>
<body>
    <div class='container'>
        <i class='fas fa-check-circle success-icon'></i>
        <h2>✅ Pedido registrado com sucesso!</h2>
        <p>O pedido e as imagens foram enviados ao WhatsApp.<br>Você será redirecionado em 3 segundos.</p>
        <div>
            <a href='pedidos.php' class='btn btn-voltar'>
                <i class='fas fa-arrow-left'></i> Voltar
            </a>
        </div>";
            if (!empty($imagensEnviadas)) {
                echo "<div style='margin-top:18px;'>Imagens enviadas:<br>";
                foreach ($imagensEnviadas as $url) {
                    echo "<img src='{$url}' class='img-preview'>";
                }
                echo "</div>";
            }
    echo "</div></body></html>";
            exit();
        } else {
            $erro_message = $resultado['error'] ?? 'Erro desconhecido';
            echo "<script>alert('Erro: $erro_message'); window.location.href='pedidos.php';</script>";
            exit();
        }
    }
    
    // Concluir pedido
    if (isset($_POST['concluir_id'])) {
        $id = $_POST['concluir_id'];
        if ($pedidosManager->concluirPedido($id)) {
            header("Location: pedidos.php?success=concluido");
        } else {
            header("Location: pedidos.php?error=erro_concluir");
        }
        exit();
    }
    
    // Marcar WhatsApp como enviado (AJAX)
    if (isset($_POST['marcar_whatsapp_enviado'])) {
        $id = $_POST['pedido_id'];
        $success = $pedidosManager->concluirPedido($id);
        
        header('Content-Type: application/json');
        echo json_encode(['success' => $success]);
        exit();
    }
}

// Obter dados para exibição
$fornecedores = $pedidosManager->getFornecedores();
$statusFiltro = Validator::validateStatus($_GET['status'] ?? '') ?: '';
$pedidosResult = $pedidosManager->getPedidos($statusFiltro);
$statusMap = $pedidosManager->getStatusMap();
$statusParaClasse = $pedidosManager->getStatusParaClasse();
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestão de Pedidos - ONN Móveis</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="pedidos.css">
</head>
<body>
    <div class="notification-container" id="notificationContainer"></div>
    
    <div class="container">
        <?php if (isset($_GET['success'])): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <span>Pedido concluído com sucesso!</span>
            </div>
        <?php endif; ?>
        
        <?php if (isset($_GET['error'])): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i>
                <span>Erro ao processar solicitação. Tente novamente.</span>
            </div>
        <?php endif; ?>
        
        <div class="header">
            <h1><i class="fas fa-clipboard-list"></i> Gestão de Pedidos</h1>
            <div class="user-info">
                <div class="notification-bell">
                    <i class="fas fa-bell" id="notificationBell"></i>
                    <span class="notification-count" id="notificationCount" style="display: none;">0</span>
                </div>
                <span>Olá, <?php echo htmlspecialchars($_SESSION['usuario']); ?></span>
                <a href="estoque.php" class="btn btn-primary">
                    <i class="fas fa-boxes"></i> Estoque
                </a>
                <a href="logout.php" class="btn btn-danger">
                    <i class="fas fa-sign-out-alt"></i> Sair
                </a>
            </div>
        </div>

        <section class="pedido-section">
            <h2><i class="fas fa-plus-circle"></i> Novo Pedido</h2>
            <form method="POST" class="pedido-form" id="formNovoPedido" enctype="multipart/form-data">
                <input type="hidden" name="adicionar_pedido" value="1">
                <div class="form-row">
                    <div class="form-group">
                        <label><i class="fas fa-box"></i> Produto *</label>
                        <input type="text" name="NomeProduto" required class="form-control" 
                               placeholder="Nome do produto" maxlength="255">
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-truck"></i> Fornecedor *</label>
                        <select name="NomeFornecedor" required class="form-control">
                            <option value="">Selecione um fornecedor</option>
                            <?php foreach ($fornecedores as $nome): ?>
                                <option value="<?= htmlspecialchars($nome) ?>"><?= htmlspecialchars($nome) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label><i class="fas fa-hashtag"></i> Quantidade *</label>
                        <input type="number" name="Quantidade" required class="form-control" min="1" max="99999">
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-ruler"></i> Unidade *</label>
                        <select name="Unidade" required class="form-control">
                            <option value="PC">Peça (PC)</option>
                            <option value="PL">Pallet (PL)</option>
                            <option value="MT">Metro (MT)</option>
                            <option value="KG">Quilograma (KG)</option>
                            <option value="LT">Litro (LT)</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-user-hard-hat"></i> Marceneiro</label>
                        <input type="text" name="PessoaMarceneiro" class="form-control" 
                               placeholder="Responsável" maxlength="255">
                    </div>
                </div>
                <div class="form-group">
                    <label><i class="fas fa-comment"></i> Observação</label>
                    <textarea name="Observacao" class="form-control" placeholder="Detalhes adicionais" 
                              maxlength="1000" rows="3"></textarea>
                </div>
                <div class="form-group">
                    <label><i class="fas fa-image"></i> Imagens do Pedido</label>
                    <input 
                        type="file" 
                        name="imagens[]" 
                        accept="image/*" 
                        multiple 
                        class="form-control"
                        id="inputImagens"
                    >
                    <small>Selecione ou cole imagens (PrintScreen, Ctrl+V)</small>
                    <div id="previewImagens" style="margin-top:10px; display:flex; flex-wrap: wrap; gap:6px;"></div>
                </div>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Adicionar Pedido
                </button>
            </form>
        </section>

        <section class="lista-pedidos">
            <h2><i class="fas fa-list"></i> Lista de Pedidos</h2>

            <div class="filter-container">
                <form method="GET">
                    <label for="status"><i class="fas fa-filter"></i> Filtrar por Status:</label>
                    <select name="status" id="status" onchange="this.form.submit()" class="form-control" style="width: auto;">
                        <option value="">Todos</option>
                        <option value="pendente" <?= $statusFiltro === 'pendente' ? 'selected' : '' ?>>Pendente</option>
                        <option value="concluido" <?= $statusFiltro === 'concluido' ? 'selected' : '' ?>>Concluído</option>
                        <option value="cancelado" <?= $statusFiltro === 'cancelado' ? 'selected' : '' ?>>Cancelado</option>
                        <option value="analise" <?= $statusFiltro === 'analise' ? 'selected' : '' ?>>Análise</option>
                    </select>
                </form>
            </div>

            <div class="table-responsive">
                <table id="pedidosTable">
                    <thead>
                        <tr>
                            <th><i class="fas fa-box"></i> Produto</th>
                            <th><i class="fas fa-truck"></i> Fornecedor</th>
                            <th><i class="fas fa-hashtag"></i> Qtd</th>
                            <th><i class="fas fa-ruler"></i> Unid</th>
                            <th><i class="fas fa-info-circle"></i> Status</th>
                            <th><i class="fas fa-calendar"></i> Data</th>
                            <th><i class="fab fa-whatsapp"></i> WhatsApp</th>
                            <th><i class="fas fa-cog"></i> Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($pedidosResult && $pedidosResult->num_rows > 0): ?>
                            <?php while($row = $pedidosResult->fetch_assoc()): ?>
                            <tr data-pedido-id="<?= htmlspecialchars($row['id']) ?>">
                                <td><?= htmlspecialchars($row['NomeProduto']) ?></td>
                                <td><?= htmlspecialchars($row['NomeFornecedor']) ?></td>
                                <td><?= htmlspecialchars($row['Quantidade']) ?></td>
                                <td><?= htmlspecialchars($row['Unidade']) ?></td>
                                <td>
                                    <?php 
                                        $statusBanco = $row['Status'];
                                        $statusClasse = $statusParaClasse[$statusBanco] ?? 'desconhecido';
                                    ?>
                                    <span class="status-badge status-<?= $statusClasse ?>">
                                        <?= htmlspecialchars($statusBanco) ?>
                                    </span>
                                </td>
                                <td><?= date("d/m/Y H:i", strtotime($row['DataPedido'])) ?></td>
                                <td>
                                    <?php if (!empty($row['ContatoFornecedor'])): ?>
                                        <?php
                                            $contato = Validator::validatePhone($row['ContatoFornecedor']);
                                            if ($contato):
                                                $mensagem = $pedidosManager->gerarMensagemWhatsAppFornecedor($row);
                                                $link = "https://wa.me/55$contato?text=" . urlencode($mensagem);
                                        ?>
                                            <a class="whatsapp-btn <?= $row['Status'] == 'Concluído' ? 'enviado' : '' ?>" 
                                               target="_blank" 
                                               href="<?= htmlspecialchars($link) ?>"
                                               data-pedido-id="<?= htmlspecialchars($row['id']) ?>"
                                               onclick="marcarWhatsAppEnviado(<?= htmlspecialchars($row['id']) ?>)">
                                                <i class="fab fa-whatsapp"></i> 
                                                <?= $row['Status'] == 'Concluído' ? 'Enviado' : 'Enviar' ?>
                                            </a>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span style="color: #999; font-size: 0.9em;">Sem contato</span>
                                    <?php endif; ?>
                                </td>
                                <td class="actions">
                                    <a href="editar_pedido.php?id=<?= htmlspecialchars($row['id']) ?>" 
                                       class="btn btn-primary btn-sm" title="Editar pedido">
                                        <i class="fas fa-edit"></i> Editar
                                    </a>
                                    <?php if ($row['Status'] != 'Concluído'): ?>
                                    <form method="POST" action="" style="display:inline;" 
                                          onsubmit="return confirm('Tem certeza que deseja marcar este pedido como concluído?')">
                                        <input type="hidden" name="concluir_id" value="<?= htmlspecialchars($row['id']) ?>">
                                        <button type="submit" class="btn btn-success btn-sm" title="Marcar como concluído">
                                            <i class="fas fa-check"></i> Concluir
                                        </button>
                                    </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" style="text-align: center; padding: 40px; color: #999;">
                                    <i class="fas fa-inbox" style="font-size: 2em; margin-bottom: 10px; display: block;"></i>
                                    Nenhum pedido encontrado
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </div>

    <script>
        // Permitir colar imagens no campo de upload
        document.addEventListener('paste', function(event) {
            const items = (event.clipboardData || event.originalEvent.clipboardData).items;
            const input = document.getElementById('inputImagens');
            let files = input.files ? Array.from(input.files) : [];
            let changed = false;
            for (let item of items) {
                if (item.type.indexOf('image') !== -1) {
                    const file = item.getAsFile();
                    files.push(file);
                    changed = true;
                }
            }
            if (changed) {
                // Cria um DataTransfer para atribuir múltiplos arquivos
                const dataTransfer = new DataTransfer();
                files.forEach(f => dataTransfer.items.add(f));
                input.files = dataTransfer.files;
                mostrarPreviewImagens();
                
                // Mostrar feedback visual do sucesso
                notificationSystem.mostrarNotificacao('Imagem colada com sucesso!', 'success', 2000);
            }
        });

        // Mostrar preview das imagens selecionadas/coladas
        document.getElementById('inputImagens').addEventListener('change', mostrarPreviewImagens);
        function mostrarPreviewImagens() {
            const input = document.getElementById('inputImagens');
            const preview = document.getElementById('previewImagens');
            preview.innerHTML = '';
            Array.from(input.files).forEach(file => {
                const reader = new FileReader();
                reader.onload = e => {
                    const img = document.createElement('img');
                    img.src = e.target.result;
                    img.width = 60;
                    img.style.borderRadius = '6px';
                    img.style.border = '1px solid #ccc';
                    img.title = file.name;
                    preview.appendChild(img);
                };
                reader.readAsDataURL(file);
            });
        }

        // Sistema de notificações melhorado
        class NotificationSystem {
            constructor() {
                this.container = document.getElementById('notificationContainer');
                this.notificationCount = 0;
                this.ultimoCheckPedidos = Date.now();
                this.init();
            }
            
            init() {
                this.verificarNovosPedidos();
                setInterval(() => this.verificarNovosPedidos(), 10000);
                
                // Verificar quando a aba fica ativa novamente
                document.addEventListener('visibilitychange', () => {
                    if (!document.hidden) {
                        this.verificarNovosPedidos();
                    }
                });
            }
            
            mostrarNotificacao(mensagem, tipo = 'info', duracao = 5000) {
                const notification = document.createElement('div');
                
                const icons = {
                    'success': 'fas fa-check-circle',
                    'info': 'fas fa-info-circle',
                    'novo-pedido': 'fas fa-plus-circle',
                    'error': 'fas fa-exclamation-circle'
                };
                
                notification.className = `notification ${tipo}`;
                notification.innerHTML = `
                    <i class="${icons[tipo]} icon"></i>
                    <span>${mensagem}</span>
                    <button class="close-btn" onclick="notificationSystem.fecharNotificacao(this)">×</button>
                `;
                
                this.container.appendChild(notification);
                this.notificationCount++;
                this.atualizarContador();
                
                // Auto-remover após duração especificada
                setTimeout(() => {
                    if (notification.parentNode) {
                        this.fecharNotificacao(notification.querySelector('.close-btn'));
                    }
                }, duracao);
            }
            
            fecharNotificacao(btn) {
                const notification = btn.closest('.notification');
                notification.style.animation = 'slideOut 0.3s ease-out';
                setTimeout(() => {
                    if (notification.parentNode) {
                        notification.remove();
                        this.notificationCount--;
                        this.atualizarContador();
                    }
                }, 300);
            }
            
            atualizarContador() {
                const counter = document.getElementById('notificationCount');
                if (this.notificationCount > 0) {
                    counter.textContent = this.notificationCount;
                    counter.style.display = 'flex';
                } else {
                    counter.style.display = 'none';
                }
            }
            
            async verificarNovosPedidos() {
                try {
                    const response = await fetch(`check_novos_pedidos.php?ultimo_check=${this.ultimoCheckPedidos}`);
                    const data = await response.json();
                    
                    if (data.novos_pedidos && data.novos_pedidos.length > 0) {
                        data.novos_pedidos.forEach(pedido => {
                            this.mostrarNotificacao(
                                `Novo pedido: ${pedido.NomeProduto} (${pedido.Quantidade} ${pedido.Unidade})`,
                                'novo-pedido',
                                8000
                            );
                        });
                        
                        this.ultimoCheckPedidos = Date.now();
                    }
                } catch (error) {
                    console.error('Erro ao verificar novos pedidos:', error);
                }
            }
        }
        
        // Inicializar sistema de notificações
        const notificationSystem = new NotificationSystem();
        
        // Função para marcar WhatsApp como enviado
        async function marcarWhatsAppEnviado(pedidoId) {
            setTimeout(async () => {
                try {
                    const formData = new FormData();
                    formData.append('marcar_whatsapp_enviado', '1');
                    formData.append('pedido_id', pedidoId);
                    
                    const response = await fetch('pedidos.php', {
                        method: 'POST',
                        body: formData
                    });
                    
                    const data = await response.json();
                    
                    if (data.success) {
                        // Atualizar visualmente o botão
                        const btn = document.querySelector(`a[data-pedido-id="${pedidoId}"]`);
                        if (btn) {
                            btn.classList.add('enviado');
                            btn.innerHTML = '<i class="fab fa-whatsapp"></i> Enviado';
                        }
                        
                        // Atualizar status na tabela
                        const row = document.querySelector(`tr[data-pedido-id="${pedidoId}"]`);
                        if (row) {
                            const statusBadge = row.querySelector('.status-badge');
                            statusBadge.className = 'status-badge status-concluido';
                            statusBadge.textContent = 'Concluído';
                            
                            // Remover botão de concluir se existir
                            const concluirBtn = row.querySelector('button[type="submit"]');
                            if (concluirBtn) {
                                concluirBtn.closest('form').remove();
                            }
                        }
                        
                        notificationSystem.mostrarNotificacao('Pedido marcado como concluído!', 'success');
                    }
                } catch (error) {
                    console.error('Erro:', error);
                    notificationSystem.mostrarNotificacao('Erro ao marcar pedido como concluído', 'error');
                }
            }, 1000);
        }
        
        // Validação do formulário
        document.getElementById('formNovoPedido').addEventListener('submit', function(e) {
            const produto = this.querySelector('[name="NomeProduto"]').value.trim();
            const fornecedor = this.querySelector('[name="NomeFornecedor"]').value;
            const quantidade = this.querySelector('[name="Quantidade"]').value;
            
            if (!produto || !fornecedor || !quantidade || quantidade < 1) {
                e.preventDefault();
                notificationSystem.mostrarNotificacao('Por favor, preencha todos os campos obrigatórios', 'error');
                return false;
            }
            
            // Mostrar loading
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processando...';
            submitBtn.disabled = true;
            
            // Restaurar botão após 3 segundos (caso algo dê errado)
            setTimeout(() => {
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            }, 3000);
        });
        
        // Adicionar CSS para animação de saída
        const style = document.createElement('style');
        style.textContent = `
            @keyframes slideOut {
                from { transform: translateX(0); opacity: 1; }
                to { transform: translateX(100%); opacity: 0; }
            }
            
            .notification {
                position: relative;
                overflow: hidden;
            }
            
            .notification::before {
                content: '';
                position: absolute;
                top: 0;
                left: -100%;
                width: 100%;
                height: 100%;
                background: linear-gradient(90deg, transparent, rgba(255,255,255,0.4), transparent);
                animation: shimmer 2s infinite;
            }
            
            @keyframes shimmer {
                0% { left: -100%; }
                100% { left: 100%; }
            }
            
            /* Melhorias de responsividade */
            @media (max-width: 480px) {
                .form-row {
                    grid-template-columns: 1fr;
                    gap: 15px;
                }
                
                .header h1 {
                    font-size: 1.4em;
                }
                
                .btn {
                    padding: 10px 15px;
                    font-size: 0.9em;
                }
                
                table {
                    font-size: 0.9em;
                }
                
                th, td {
                    padding: 8px;
                }
            }
            
            /* Melhorias de acessibilidade */
            .btn:focus,
            .form-control:focus {
                outline: 2px solid #667eea;
                outline-offset: 2px;
            }
            
            .btn:disabled {
                opacity: 0.6;
                cursor: not-allowed;
            }
            
            /* Indicadores de carregamento */
            .loading {
                position: relative;
                pointer-events: none;
            }
            
            .loading::after {
                content: '';
                position: absolute;
                top: 50%;
                left: 50%;
                width: 20px;
                height: 20px;
                margin: -10px 0 0 -10px;
                border: 2px solid #f3f3f3;
                border-top: 2px solid #667eea;
                border-radius: 50%;
                animation: spin 1s linear infinite;
            }
            
            @keyframes spin {
                0% { transform: rotate(0deg); }
                100% { transform: rotate(360deg); }
            }
        `;
        document.head.appendChild(style);
        
        // Auto-hide alerts após 5 segundos
        document.addEventListener('DOMContentLoaded', function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                setTimeout(() => {
                    alert.style.transition = 'opacity 0.5s ease-out';
                    alert.style.opacity = '0';
                    setTimeout(() => {
                        if (alert.parentNode) {
                            alert.remove();
                        }
                    }, 500);
                }, 5000);
            });
        });
        
        // Confirmar ações críticas
        document.querySelectorAll('form[onsubmit*="confirm"]').forEach(form => {
            form.addEventListener('submit', function(e) {
                if (!confirm('Tem certeza que deseja realizar esta ação?')) {
                    e.preventDefault();
                    return false;
                }
            });
        });
    </script>
</body>
</html>