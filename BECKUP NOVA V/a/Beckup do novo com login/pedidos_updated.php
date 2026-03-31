<?php
// Adicionar no início da classe PedidosManager, após as propriedades existentes:

private $whatsappManager;

public function __construct() {
    $this->db = new DatabaseConnection();
    $this->conn = $this->db->getConnection();
    $this->verificarAutenticacao();
    
    // Inicializar gerenciador de WhatsApp
    require_once 'WhatsAppSessionManager.php';
    $this->whatsappManager = new WhatsAppSessionManager($this->conn);
}

// Atualizar o método enviarWhatsAppRH:
private function enviarWhatsAppRH($mensagem, $numeros = null) {
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