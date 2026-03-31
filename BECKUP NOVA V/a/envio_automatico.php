private function enviarWhatsAppAutomatico($dados) {
    try {
        // Números para receber notificações (RH e responsáveis)
        $numerosNotificacao = [
            "555131026660", // RH
            // Adicione outros números conforme necessário
        ];
        
        $mensagem = $this->criarMensagemPedido($dados);
        $resultados = [];
        
        foreach ($numerosNotificacao as $numero) {
            // Formatar número para o padrão internacional (55 + DDD + número)
            $numeroFormatado = preg_replace('/[^0-9]/', '', $numero);
            if (strlen($numeroFormatado) < 12) {
                $numeroFormatado = '55' . $numeroFormatado;
            }
            $chatId = $numeroFormatado . '@c.us';
            
            // Dados para envio
            $payload = [
                'chatId' => $chatId,
                'contentType' => 'string',
                'content' => $mensagem
            ];
            
            // Configurar requisição cURL
            $ch = curl_init($this->whatsapp_api_url . $this->whatsapp_session_id);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json'
            ]);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            $resultados[$numero] = [
                'status' => ($httpCode == 200) ? 'enviado' : 'erro',
                'http_code' => $httpCode,
                'response' => $response,
                'timestamp' => date('Y-m-d H:i:s')
            ];
        }
        
        return [
            'success' => true,
            'method' => 'whatsapp_api',
            'sent_count' => count($numerosNotificacao),
            'details' => $resultados
        ];
        
    } catch (Exception $e) {
        error_log("Erro WhatsApp API: " . $e->getMessage());
        return ['success' => false, 'error' => $e->getMessage()];
    }
}