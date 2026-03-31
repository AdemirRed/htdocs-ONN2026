<?php
/**
 * Gerenciador de Sessões WhatsApp por Usuário
 * Integra com a API ZDG para criar e gerenciar sessões individuais
 */

class WhatsAppSessionManager {
    private $api_url = 'http://192.168.0.201:200';
    private $api_key = 'redblack';
    private $conn;
    
    public function __construct($dbConnection) {
        $this->conn = $dbConnection;
        $this->criarTabelaSessoes();
    }
    
    /**
     * Cria tabela para armazenar sessões do WhatsApp se não existir
     */
    private function criarTabelaSessoes() {
        $sql = "CREATE TABLE IF NOT EXISTS whatsapp_sessions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            usuario VARCHAR(100) NOT NULL UNIQUE,
            session_id VARCHAR(100) NOT NULL UNIQUE,
            status ENUM('disconnected', 'connecting', 'connected') DEFAULT 'disconnected',
            qr_code TEXT NULL,
            ultima_atualizacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_usuario (usuario),
            INDEX idx_session_id (session_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        
        $this->conn->query($sql);
    }
    
    /**
     * Obtém ou cria uma sessão para o usuário
     */
    public function obterSessaoUsuario($nomeUsuario) {
        // Normalizar nome do usuário (lowercase, sem espaços)
        $sessionId = strtolower(trim(preg_replace('/[^a-zA-Z0-9]/', '', $nomeUsuario)));
        
        // Verificar se já existe sessão
        $stmt = $this->conn->prepare("SELECT * FROM whatsapp_sessions WHERE usuario = ?");
        $stmt->bind_param("s", $nomeUsuario);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            // Atualizar status da sessão
            $this->atualizarStatusSessao($sessionId);
            return $row;
        }
        
        // Criar nova sessão
        return $this->criarNovaSessao($nomeUsuario, $sessionId);
    }
    
    /**
     * Cria uma nova sessão na API e no banco
     */
    private function criarNovaSessao($usuario, $sessionId) {
        // Iniciar sessão na API ZDG
        $response = $this->iniciarSessaoAPI($sessionId);
        
        if ($response['success']) {
            // Salvar no banco
            $stmt = $this->conn->prepare(
                "INSERT INTO whatsapp_sessions (usuario, session_id, status) 
                 VALUES (?, ?, 'connecting') 
                 ON DUPLICATE KEY UPDATE session_id = ?, status = 'connecting'"
            );
            $stmt->bind_param("sss", $usuario, $sessionId, $sessionId);
            $stmt->execute();
            
            // Buscar QR Code
            sleep(2); // Aguardar 2 segundos para API gerar QR
            $qrData = $this->obterQRCode($sessionId);
            
            return [
                'usuario' => $usuario,
                'session_id' => $sessionId,
                'status' => 'connecting',
                'qr_code' => $qrData['qr'] ?? null,
                'criado_agora' => true
            ];
        }
        
        return null;
    }
    
    /**
     * Inicia sessão na API ZDG
     */
    private function iniciarSessaoAPI($sessionId) {
        $url = "{$this->api_url}/session/start/{$sessionId}";
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'accept: application/json',
            'x-api-key: ' . $this->api_key
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        return [
            'success' => ($httpCode === 200 || $httpCode === 201),
            'response' => json_decode($response, true)
        ];
    }

    /**
     * Dispara /session/start/{sessionId} novamente.
     * Útil para recuperar quando a API retorna session_not_found.
     */
    public function startSessao($sessionId) {
        $start = $this->iniciarSessaoAPI($sessionId);

        $payload = is_array($start['response'] ?? null) ? $start['response'] : [];

        // Marcar como connecting e limpar QR salvo
        $status = ($start['success'] ?? false) ? 'connecting' : 'disconnected';
        $stmt = $this->conn->prepare(
            "UPDATE whatsapp_sessions SET status = ?, qr_code = NULL WHERE session_id = ?"
        );
        $stmt->bind_param("ss", $status, $sessionId);
        $stmt->execute();

        return [
            'success' => (bool)($payload['success'] ?? ($start['success'] ?? false)),
            'message' => $payload['message'] ?? null,
            'state' => $payload['state'] ?? null,
            'raw' => $payload
        ];
    }
    
    /**
     * Obtém QR Code da sessão
     */
    public function obterQRCode($sessionId) {
        $url = "{$this->api_url}/session/qr/{$sessionId}";
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'accept: */*',
            'x-api-key: ' . $this->api_key
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $data = json_decode($response, true);
        if (!is_array($data)) {
            $data = [];
        }

        if ($httpCode === 200 && isset($data['qr']) && !empty($data['qr'])) {
            // Atualizar QR no banco
            $stmt = $this->conn->prepare(
                "UPDATE whatsapp_sessions SET qr_code = ? WHERE session_id = ?"
            );
            $stmt->bind_param("ss", $data['qr'], $sessionId);
            $stmt->execute();

            $data['success'] = $data['success'] ?? true;
            return $data;
        }

        return [
            'success' => false,
            'qr' => null,
            'state' => $data['state'] ?? null,
            'message' => $data['message'] ?? ($data['error'] ?? 'qr_unavailable'),
            'http_code' => $httpCode,
            'raw' => $response
        ];
    }
    
    /**
     * Verifica status da conexão
     */
    public function verificarStatus($sessionId) {
        $url = "{$this->api_url}/session/status/{$sessionId}";
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'accept: application/json',
            'x-api-key: ' . $this->api_key
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $data = json_decode($response, true);
        if (is_array($data)) {
            $data['http_code'] = $httpCode;
            return $data;
        }

        if ($httpCode === 200) {
            return ['success' => true, 'state' => null, 'message' => 'status_empty_response', 'http_code' => $httpCode];
        }

        return ['success' => false, 'state' => null, 'message' => 'error_checking_status', 'http_code' => $httpCode, 'raw' => $response];
    }

    /**
     * Reinicia uma sessão na API (útil quando expira e some o QR)
     */
    public function restartSessao($sessionId) {
        $url = "{$this->api_url}/session/restart/{$sessionId}";

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'accept: application/json',
            'x-api-key: ' . $this->api_key
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $data = json_decode($response, true);
        if (!is_array($data)) {
            $data = [];
        }

        return [
            'success' => (bool)($data['success'] ?? ($httpCode === 200 || $httpCode === 201)),
            'state' => $data['state'] ?? null,
            'message' => $data['message'] ?? null,
            'http_code' => $httpCode,
            'raw' => $response
        ];
    }
    
    /**
     * Atualiza status da sessão no banco
     */
    public function atualizarStatusSessao($sessionId) {
        $statusAPI = $this->verificarStatus($sessionId);
        
        $status = 'disconnected';
        if ($statusAPI['success'] && $statusAPI['state'] === 'CONNECTED') {
            $status = 'connected';
        } elseif ($statusAPI['state'] === 'CONNECTING') {
            $status = 'connecting';
        }
        
        $stmt = $this->conn->prepare(
            "UPDATE whatsapp_sessions SET status = ?, qr_code = NULL WHERE session_id = ?"
        );
        $stmt->bind_param("ss", $status, $sessionId);
        $stmt->execute();
        
        return $status;
    }
    
    /**
     * Gera imagem do QR Code
     */
    public function gerarImagemQR($qrText) {
        if (empty($qrText)) {
            return null;
        }
        
        // Usar API pública para gerar QR Code
        $qrCodeURL = 'https://api.qrserver.com/v1/create-qr-code/?' . http_build_query([
            'size' => '300x300',
            'data' => $qrText,
            'format' => 'png'
        ]);
        
        return $qrCodeURL;
    }
    
    /**
     * Verifica se API está rodando
     */
    public function verificarAPI() {
        $url = "{$this->api_url}/ping";
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['accept: */*']);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        return ($httpCode === 200);
    }
}