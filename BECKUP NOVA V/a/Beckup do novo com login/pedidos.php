<?php
/**
 * Sistema de Gestão de Pedidos - ONN Móveis
 * Versão Melhorada com foco em segurança, organização e suporte a envio de imagens
 * Atualizado em: 2025-01-22
 */

// Configurações iniciais
error_reporting(E_ALL);
ini_set('display_errors', 1); // ATIVAR temporariamente para ver erros
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
    private $whatsappManager;
    
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
    
    public function __construct() {
        $this->db = new DatabaseConnection();
        $this->conn = $this->db->getConnection();
        $this->verificarAutenticacao();
        
        // Inicializar gerenciador de WhatsApp
        if (file_exists('WhatsAppSessionManager.php')) {
            require_once 'WhatsAppSessionManager.php';
            $this->whatsappManager = new WhatsAppSessionManager($this->conn);
        }
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
                if ($this->whatsappManager) {
                    $mensagem = $this->gerarMensagemWhatsAppRH($produto, $quantidade, $unidade, $usuario, $marceneiro, $obs);
                    $envio = $this->enviarWhatsAppRH($mensagem);
                } else {
                    $envio = [['success' => false, 'error' => 'WhatsApp Manager não inicializado']];
                }
                
                return [
                    'success' => true,
                    'pedido_id' => $pedido_id,
                    'whatsapp_envio' => $envio
                ];
            }
            
            throw new Exception("Erro ao inserir pedido");
            
        } catch (Exception $e) {
            error_log("Erro ao adicionar pedido: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    private function enviarWhatsAppRH($mensagem, $numeros = null) {
        if (!$this->whatsappManager) {
            return [[
                'success' => false,
                'error' => 'WhatsApp Manager não disponível'
            ]];
        }
        
        // Obter sessão do usuário logado
        $sessaoInfo = $this->whatsappManager->obterSessaoUsuario($_SESSION['usuario']);
        $sessionId = $sessaoInfo['session_id'];
        
        // Verificar se está conectado
        $status = $this->whatsappManager->verificarStatus($sessionId);
        if (($status['state'] ?? '') !== 'CONNECTED') {
            return [[
                'success' => false,
                'error' => 'WhatsApp não conectado. Por favor, conecte seu WhatsApp.',
                'redirect' => 'whatsapp_connection.php'
            ]];
        }
        
        $url = $this->zdg_api_url . $sessionId;
        
        if ($numeros === null) {
            $numeros = ["555131026660", "555199326748"];
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
            . "📊 *Acesse o sistema:* https://tinyurl.com/dkuta32r";
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
            
            $sql .= " ORDER BY p.DataPedido DESC, p.id DESC";
            
            $stmt = $this->conn->prepare($sql);
            
            if (!empty($params)) {
                $stmt->bind_param($types, ...$params);
            }
            
            if (!$stmt->execute()) {
                error_log("Erro SQL ao buscar pedidos: " . $stmt->error);
                return false;
            }
            
            $result = $stmt->get_result();
            
            // Debug: contar registros
            error_log("Total de pedidos encontrados: " . $result->num_rows);
            
            return $result;
            
        } catch (Exception $e) {
            error_log("Erro ao buscar pedidos: " . $e->getMessage());
            return false;
        }
    }
    
   public function gerarMensagemWhatsAppFornecedor($pedido) {
    $observacao = !empty($pedido['Observacao']) ? $pedido['Observacao'] : 'Sem observações';
    $dataAtual = date('d/m/Y \à\s H:i');
    
    return "🏢 *PEDIDO DE COMPRA - ONN MÓVEIS* 🏢\n\n"
        . "━━━━━━━━━━━━━━━━━━━━━━━━\n\n"
        . "📦 *Produto:* {$pedido['NomeProduto']}\n"
        . "📊 *Quantidade:* {$pedido['Quantidade']} {$pedido['Unidade']}\n"
        . "👤 *Solicitante:* {$pedido['NomeUsuario']}\n"
        . "📝 *Observações:* $observacao\n"
        . "📅 *Data do Pedido:* $dataAtual\n\n"
        . "━━━━━━━━━━━━━━━━━━━━━━━━\n\n"
        . "📍 *LOCAL DE ENTREGA:*\n"
        . "R. Arno Wickert, 44\n"
        . "Bairro Industrial\n"
        . "Dois Irmãos - RS\n"
        . "CEP: 93950-000\n\n"
        . "🗺️ *Localização:*\n"
        . "https://maps.app.goo.gl/cPVRLrGF4vZkxEqh7\n\n"
        . "━━━━━━━━━━━━━━━━━━━━━━━━\n\n"
        . "Agradecemos pela parceria! 🤝\n\n"
        . "_Mensagem automática - Sistema ONN Móveis_\n"
        . "_Desenvolvido por RedBlack Sistemas_";
}
    
    /**
 * Envia mensagem do pedido diretamente para o WhatsApp do fornecedor via API
 */
public function enviarWhatsAppFornecedor($pedidoId) {
    try {
        if (!$this->whatsappManager) {
            return ['success' => false, 'error' => 'WhatsApp Manager não disponível'];
        }
        
        // Buscar dados do pedido
        $stmt = $this->conn->prepare("
            SELECT p.*, f.Contato AS ContatoFornecedor 
            FROM pedidos p
            LEFT JOIN fornecedor f ON p.NomeFornecedor = f.Nome
            WHERE p.id = ?
        ");
        $stmt->bind_param("i", $pedidoId);
        $stmt->execute();
        $result = $stmt->get_result();
        $pedido = $result->fetch_assoc();
        
        if (!$pedido) {
            return ['success' => false, 'error' => 'Pedido não encontrado'];
        }
        
        // Se já estiver concluído, considerar como já enviado
        if ($pedido['Status'] === 'Concluído') {
            return [
                'success' => true,
                'already_sent' => true,
                'message' => 'Pedido já está concluído'
            ];
        }
        
        // Validar telefone do fornecedor e limpar formatação
        $telefone = Validator::validatePhone($pedido['ContatoFornecedor']);
        if (!$telefone) {
            return ['success' => false, 'error' => 'Telefone do fornecedor inválido'];
        }
        
        // Garantir formato: 555197756708 (sem espaços, +55, parênteses, etc)
        $telefone = preg_replace('/[^0-9]/', '', $telefone);
        
        // Se tiver 11 dígitos (com DDD), adicionar 55 na frente
        if (strlen($telefone) == 11) {
            $telefone = '55' . $telefone;
        }
        // Se tiver 13 dígitos e começar com 55, está correto
        // Se não, tentar usar como está
        
        // Obter sessão do usuário logado
        $sessaoInfo = $this->whatsappManager->obterSessaoUsuario($_SESSION['usuario']);
        $sessionId = $sessaoInfo['session_id'];
        
        // Verificar se está conectado
        $status = $this->whatsappManager->verificarStatus($sessionId);
        if (($status['state'] ?? '') !== 'CONNECTED') {
            return [
                'success' => false,
                'error' => 'WhatsApp não conectado. Conecte seu WhatsApp primeiro.',
                'redirect' => 'whatsapp_connection.php'
            ];
        }
        
        // Gerar mensagem
        $mensagem = $this->gerarMensagemWhatsAppFornecedor($pedido);
        
        // Enviar via API
        $url = $this->zdg_api_url . $sessionId;
        $chatId = $telefone . "@c.us"; // Usar telefone sem formatação extra
        
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
            
            // Marcar como enviado no banco
            $this->registrarEnvioWhatsApp($pedidoId, $telefone);
            
            // Marcar pedido como concluído automaticamente
            $this->concluirPedido($pedidoId);
            
            return [
                'success' => true,
                'message_id' => $data['message']['id'] ?? null,
                'timestamp' => $data['message']['timestamp'] ?? date('Y-m-d H:i:s'),
                'fornecedor' => $pedido['NomeFornecedor'],
                'telefone' => $telefone
            ];
        } else {
            return [
                'success' => false,
                'error' => $curlError ?: 'Erro ao enviar mensagem',
                'response' => $response
            ];
        }
        
    } catch (Exception $e) {
        error_log("Erro ao enviar WhatsApp para fornecedor: " . $e->getMessage());
        return ['success' => false, 'error' => $e->getMessage()];
    }
}
    
    /**
     * Registra envio de WhatsApp no banco
     */
    private function registrarEnvioWhatsApp($pedidoId, $telefone) {
        try {
            // Criar tabela de histórico se não existir
            $sql = "CREATE TABLE IF NOT EXISTS whatsapp_envios (
                id INT AUTO_INCREMENT PRIMARY KEY,
                pedido_id INT NOT NULL,
                telefone VARCHAR(20) NOT NULL,
                usuario_envio VARCHAR(100) NOT NULL,
                data_envio TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_pedido (pedido_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
            $this->conn->query($sql);
            
            // Registrar envio
            $stmt = $this->conn->prepare(
                "INSERT INTO whatsapp_envios (pedido_id, telefone, usuario_envio) VALUES (?, ?, ?)"
            );
            $usuario = $_SESSION['usuario'];
            $stmt->bind_param("iss", $pedidoId, $telefone, $usuario);
            $stmt->execute();
            
        } catch (Exception $e) {
            error_log("Erro ao registrar envio WhatsApp: " . $e->getMessage());
        }
    }
    
    /**
     * Verifica se WhatsApp já foi enviado para o pedido
     */
    public function verificarWhatsAppEnviado($pedidoId) {
        try {
            $stmt = $this->conn->prepare(
                "SELECT COUNT(*) as total FROM whatsapp_envios WHERE pedido_id = ?"
            );
            $stmt->bind_param("i", $pedidoId);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();
            
            return ($row['total'] ?? 0) > 0;
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Enviar imagens do pedido para o fornecedor
     */
    public function enviarImagensFornecedor($pedidoId, $imagensUrls) {
        try {
            if (!$this->whatsappManager) {
                return ['success' => false, 'error' => 'WhatsApp Manager não disponível'];
            }
            
            // Buscar dados do pedido
            $stmt = $this->conn->prepare("
                SELECT p.*, f.Contato AS ContatoFornecedor 
                FROM pedidos p
                LEFT JOIN fornecedor f ON p.NomeFornecedor = f.Nome
                WHERE p.id = ?
            ");
            $stmt->bind_param("i", $pedidoId);
            $stmt->execute();
            $result = $stmt->get_result();
            $pedido = $result->fetch_assoc();
            
            if (!$pedido) {
                return ['success' => false, 'error' => 'Pedido não encontrado'];
            }
            
            $telefone = Validator::validatePhone($pedido['ContatoFornecedor']);
            if (!$telefone) {
                return ['success' => false, 'error' => 'Telefone inválido'];
            }
            
            // Obter sessão
            $sessaoInfo = $this->whatsappManager->obterSessaoUsuario($_SESSION['usuario']);
            $sessionId = $sessaoInfo['session_id'];
            
            $url = $this->zdg_api_url . $sessionId;
            $chatId = "55" . $telefone . "@c.us";
            
            $resultados = [];
            foreach ($imagensUrls as $imagemUrl) {
                $payload = [
                    "chatId" => $chatId,
                    "contentType" => "MessageMediaFromURL",
                    "content" => $imagemUrl
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
                curl_close($ch);
                
                $resultados[] = [
                    'success' => ($httpCode === 200),
                    'url' => $imagemUrl
                ];
                
                // Aguardar 1 segundo entre envios
                sleep(1);
            }
            
            return [
                'success' => true,
                'resultados' => $resultados
            ];
            
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Salvar URLs de imagens associadas ao pedido
     */
    public function salvarImagensPedido($pedidoId, $imagensUrls) {
        try {
            // Criar tabela de imagens se não existir
            $sql = "CREATE TABLE IF NOT EXISTS pedido_imagens (
                id INT AUTO_INCREMENT PRIMARY KEY,
                pedido_id INT NOT NULL,
                url_imagem TEXT NOT NULL,
                data_upload TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_pedido (pedido_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
            $this->conn->query($sql);
            
            // Inserir cada imagem
            $stmt = $this->conn->prepare(
                "INSERT INTO pedido_imagens (pedido_id, url_imagem) VALUES (?, ?)"
            );
            
            foreach ($imagensUrls as $url) {
                $stmt->bind_param("is", $pedidoId, $url);
                $stmt->execute();
            }
            
            return true;
        } catch (Exception $e) {
            error_log("Erro ao salvar imagens: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Buscar imagens do pedido
     */
    public function buscarImagensPedido($pedidoId) {
        try {
            $stmt = $this->conn->prepare(
                "SELECT url_imagem FROM pedido_imagens WHERE pedido_id = ? ORDER BY data_upload ASC"
            );
            $stmt->bind_param("i", $pedidoId);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $imagens = [];
            while ($row = $result->fetch_assoc()) {
                $imagens[] = $row['url_imagem'];
            }
            
            return $imagens;
        } catch (Exception $e) {
            return [];
        }
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
    $sessionId = strtolower(trim(preg_replace('/[^a-zA-Z0-9]/', '', $_SESSION['usuario'])));
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
    
    return $resultados;
}

// Inicializar sistema
$pedidosManager = new PedidosManager();

// Processar requisições POST
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // Handler para envio de WhatsApp para fornecedor via AJAX
    if (isset($_POST['enviar_whatsapp_fornecedor'])) {
        header('Content-Type: application/json');
        
        $pedidoId = $_POST['pedido_id'];
        $resultado = $pedidosManager->enviarWhatsAppFornecedor($pedidoId);
        
        // Se houver imagens associadas, enviar também
        if ($resultado['success'] && isset($_POST['imagens_urls']) && !empty($_POST['imagens_urls'])) {
            $imagensUrls = json_decode($_POST['imagens_urls'], true);
            $resultadoImagens = $pedidosManager->enviarImagensFornecedor($pedidoId, $imagensUrls);
            $resultado['imagens'] = $resultadoImagens;
        }
        
        echo json_encode($resultado);
        exit();
    }
    
    // Adicionar novo pedido
    if (isset($_POST['adicionar_pedido'])) {
        $resultado = $pedidosManager->adicionarPedido($_POST);

        if ($resultado['success']) {
            $pedido_id = $resultado['pedido_id'];
            $imagensEnviadas = [];
            
            // --- Salvar e ENVIAR IMAGENS se houver ---
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

                        $imagensEnviadas[] = $urlImagem;
                        
                        // Enviar para todos os números padrão (RH)
                        $resultadosEnvio = enviarImagemWhatsApp($urlImagem);
                    }
                }
                
                // Salvar URLs no banco associadas ao pedido
                if (!empty($imagensEnviadas)) {
                    $pedidosManager->salvarImagensPedido($pedido_id, $imagensEnviadas);
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

// Verificar se WhatsApp foi enviado (para AJAX)
if ($_SERVER["REQUEST_METHOD"] == "GET" && isset($_GET['verificar_enviado'])) {
    header('Content-Type: application/json');
    $pedidoId = $_GET['pedido_id'];
    $enviado = $pedidosManager->verificarWhatsAppEnviado($pedidoId);
    echo json_encode(['enviado' => $enviado]);
    exit();
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
<?php if (file_exists('dashboard.php')): ?>
                    <a href="dashboard.php" class="btn btn-info">
                        <i class="fas fa-file-invoice"></i> Sistema NFE
                    </a>
                <?php endif; ?>
                <a href="whatsapp_connection.php" class="btn btn-success" id="btnWhatsApp">
                    <i class="fab fa-whatsapp"></i> WhatsApp
                    <span id="whatsappStatus" style="display: none;"></span>
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
                $whatsappEnviado = $pedidosManager->verificarWhatsAppEnviado($row['id']);
                $pedidoConcluido = ($row['Status'] === 'Concluído');
                $jaEnviado = $whatsappEnviado || $pedidoConcluido;
        ?>
            <button 
                class="whatsapp-btn <?= $jaEnviado ? 'enviado' : '' ?>" 
                data-pedido-id="<?= htmlspecialchars($row['id']) ?>"
                data-telefone="<?= htmlspecialchars($contato) ?>"
                data-fornecedor="<?= htmlspecialchars($row['NomeFornecedor']) ?>"
                data-status="<?= htmlspecialchars($row['Status']) ?>"
                onclick="enviarWhatsAppFornecedor(this)"
                <?= $jaEnviado ? 'disabled' : '' ?>
                style="border: none; cursor: <?= $jaEnviado ? 'not-allowed' : 'pointer' ?>;">
                <i class="fab fa-whatsapp"></i> 
                <?= $jaEnviado ? 'Enviado' : 'Enviar' ?>
            </button>
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

    <script src="pedidos.js"></script>
</body>
</html>