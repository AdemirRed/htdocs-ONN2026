<?php
/**
 * API Endpoint para enviar solicitações de retalhos via WhatsApp
 */

header('Content-Type: application/json');

// Configurações da API WhatsApp
$whatsapp_api_url = 'http://192.168.0.201:200';
$whatsapp_api_key = 'redblack';
$session_id = 'ademir'; // Sessão dedicada para o sistema de retalhos

// Números de destino disponíveis
$destinatarios = [
    '1' => ['nome' => 'Ademir', 'numero' => '555197756708'],
    '2' => ['nome' => 'Pedro', 'numero' => '555193123053']
];

// Validar requisição
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Método não permitido']);
    exit();
}

// Obter dados do POST
$codigo = $_POST['codigo'] ?? '';
$codigo_material = $_POST['codigo_material'] ?? '';
$material = $_POST['material'] ?? '';
$quantidade = $_POST['quantidade'] ?? '';
$altura = $_POST['altura'] ?? '';
$largura = $_POST['largura'] ?? '';
$descricao = $_POST['descricao'] ?? '';
$espessura = $_POST['espessura'] ?? '';
$nome_solicitante = $_POST['nome'] ?? '';
$destinatario_escolhido = $_POST['destinatario'] ?? '1'; // Padrão: Ademir

// Validar destinatário
if (!isset($destinatarios[$destinatario_escolhido])) {
    $destinatario_escolhido = '1'; // Fallback para Ademir
}

$destinatario = $destinatarios[$destinatario_escolhido];
$numero_destino = $destinatario['numero'];
$nome_destinatario = $destinatario['nome'];

// Log dos dados recebidos para debug
error_log("WhatsApp API - Dados recebidos: " . json_encode($_POST));

// Validar dados obrigatórios
if (empty($codigo) || empty($material) || empty($quantidade) || empty($nome_solicitante)) {
    error_log("WhatsApp API - Dados incompletos: codigo=" . ($codigo ?: 'VAZIO') . 
              ", material=" . ($material ?: 'VAZIO') . 
              ", quantidade=" . ($quantidade ?: 'VAZIO') . 
              ", nome=" . ($nome_solicitante ?: 'VAZIO'));
    echo json_encode([
        'success' => false, 
        'error' => 'Dados incompletos',
        'details' => [
            'codigo' => !empty($codigo),
            'material' => !empty($material),
            'quantidade' => !empty($quantidade),
            'nome' => !empty($nome_solicitante)
        ]
    ]);
    exit();
}

// Montar mensagem
$mensagem = "🔥 *SOLICITAÇÃO DE RETALHO* 🔥\n\n";
$mensagem .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
$mensagem .= "📅 *Data:* " . date('d/m/Y') . "\n";
$mensagem .= "🕐 *Hora:* " . date('H:i:s') . "\n";
$mensagem .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
$mensagem .= "🔢 *Código Material:* M{$codigo_material}\n";
$mensagem .= "🏗️ *Material:* {$material}\n";
if (!empty($codigo) && $codigo !== "M{$codigo_material}") {
    $mensagem .= "🏷️ *Código Retalho:* {$codigo}\n";
}
$mensagem .= "📦 *Quantidade Solicitada:* {$quantidade} unidade(s)\n";
$mensagem .= "📏 *Dimensões:* {$altura}mm x {$largura}mm\n";

if (!empty($espessura)) {
    $mensagem .= "📐 *Espessura:* {$espessura}mm\n";
}

$mensagem .= "📝 *Descrição:* {$descricao}\n";
$mensagem .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
$mensagem .= "👤 *Solicitante:* {$nome_solicitante}";

// Formatar número no padrão internacional
$numeroFormatado = preg_replace('/[^0-9]/', '', $numero_destino);
if (substr($numeroFormatado, 0, 2) !== '55') {
    $numeroFormatado = '55' . $numeroFormatado;
}
$chatId = $numeroFormatado . '@c.us';

// Preparar payload para a API
$payload = [
    'chatId' => $chatId,
    'contentType' => 'string',
    'content' => $mensagem
];

try {
    // Enviar mensagem via API WhatsApp
    $url = "{$whatsapp_api_url}/client/sendMessage/{$session_id}";
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'accept: */*',
        'x-api-key: ' . $whatsapp_api_key
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    
    // Verificar resposta
    if ($httpCode === 200 || $httpCode === 201) {
        $responseData = json_decode($response, true);
        
        // Log de sucesso (opcional)
        error_log("WhatsApp enviado com sucesso - Código: {$codigo}, Quantidade: {$quantidade}, Destinatário: {$nome_destinatario}");
        
        echo json_encode([
            'success' => true,
            'message' => "Solicitação enviada com sucesso para {$nome_destinatario}!",
            'destinatario' => $nome_destinatario,
            'response' => $responseData
        ]);
    } elseif ($httpCode === 404) {
        // Sessão não encontrada ou não conectada
        error_log("Erro WhatsApp - Sessão '{$session_id}' não encontrada ou desconectada");
        
        echo json_encode([
            'success' => false,
            'error' => 'WhatsApp desconectado',
            'message' => 'A sessão do WhatsApp não está conectada. Clique no botão "📱 WhatsApp Config" para conectar.',
            'details' => [
                'http_code' => $httpCode,
                'session_id' => $session_id,
                'action_required' => 'Conectar WhatsApp em config_whatsapp_retalhos.php'
            ]
        ]);
    } else {
        // Log de erro
        error_log("Erro ao enviar WhatsApp - HTTP {$httpCode}: {$response}");
        
        echo json_encode([
            'success' => false,
            'error' => 'Erro ao enviar mensagem',
            'message' => "Erro HTTP {$httpCode}. Verifique a conexão do WhatsApp.",
            'details' => [
                'http_code' => $httpCode,
                'response' => substr($response, 0, 500),
                'curl_error' => $curlError
            ]
        ]);
    }
    
} catch (Exception $e) {
    error_log("Exceção ao enviar WhatsApp: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'error' => 'Erro no servidor',
        'message' => $e->getMessage()
    ]);
}
