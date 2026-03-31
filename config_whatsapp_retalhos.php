<?php
/**
 * Página de verificação e conexão da sessão WhatsApp para o sistema de retalhos
 */

// Configurações da API WhatsApp
$whatsapp_api_url = 'http://192.168.0.201:200';
$whatsapp_api_key = 'redblack';
$session_id = 'ademir';

// Função para verificar status da sessão
function verificarStatusSessao($api_url, $session_id, $api_key) {
    $url = "{$api_url}/session/status/{$session_id}";
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'accept: application/json',
        'x-api-key: ' . $api_key
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200) {
        $data = json_decode($response, true);
        return $data;
    }
    
    return null;
}

// Função para iniciar sessão
function iniciarSessao($api_url, $session_id, $api_key) {
    $url = "{$api_url}/session/start/{$session_id}";
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'accept: application/json',
        'x-api-key: ' . $api_key
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return ($httpCode === 200 || $httpCode === 201);
}

// Função para obter QR Code
function obterQRCode($api_url, $session_id, $api_key) {
    $url = "{$api_url}/session/qr/{$session_id}/image";
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'accept: image/png',
        'x-api-key: ' . $api_key
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200) {
        return 'data:image/png;base64,' . base64_encode($response);
    }
    
    return null;
}

// Processar ações AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    switch ($_POST['action']) {
        case 'verificar_status':
            $status = verificarStatusSessao($whatsapp_api_url, $session_id, $whatsapp_api_key);
            echo json_encode([
                'success' => true,
                'status' => $status
            ]);
            exit();
            
        case 'iniciar_sessao':
            $resultado = iniciarSessao($whatsapp_api_url, $session_id, $whatsapp_api_key);
            echo json_encode([
                'success' => $resultado,
                'message' => $resultado ? 'Sessão iniciada' : 'Erro ao iniciar sessão'
            ]);
            exit();
            
        case 'obter_qr':
            $qrImage = obterQRCode($whatsapp_api_url, $session_id, $whatsapp_api_key);
            echo json_encode([
                'success' => ($qrImage !== null),
                'qr_image' => $qrImage
            ]);
            exit();
    }
}

// Verificar status inicial
$statusSessao = verificarStatusSessao($whatsapp_api_url, $session_id, $whatsapp_api_key);
$conectado = isset($statusSessao['state']) && $statusSessao['state'] === 'CONNECTED';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configuração WhatsApp - Sistema de Retalhos</title>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>▲</text></svg>">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Helvetica, Arial, sans-serif;
            background: linear-gradient(135deg, #0d1117 0%, #161b22 100%);
            color: #f0f6fc;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .container {
            max-width: 600px;
            width: 100%;
            background: #161b22;
            border-radius: 16px;
            border: 1px solid #30363d;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.5);
            overflow: hidden;
        }
        
        .header {
            background: linear-gradient(135deg, #25D366 0%, #128C7E 100%);
            padding: 30px;
            text-align: center;
        }
        
        .header h1 {
            font-size: 1.8rem;
            margin-bottom: 10px;
        }
        
        .header p {
            opacity: 0.9;
            font-size: 0.95rem;
        }
        
        .content {
            padding: 40px;
        }
        
        .status-card {
            background: #21262d;
            border: 2px solid #30363d;
            border-radius: 12px;
            padding: 25px;
            text-align: center;
            margin-bottom: 25px;
        }
        
        .status-indicator {
            width: 20px;
            height: 20px;
            border-radius: 50%;
            display: inline-block;
            margin-right: 10px;
            animation: pulse 2s infinite;
        }
        
        .status-connected { background: #3fb950; }
        .status-disconnected { background: #f85149; }
        .status-connecting { background: #ffa657; }
        
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }
        
        .qr-container {
            background: white;
            padding: 20px;
            border-radius: 12px;
            display: inline-block;
            margin: 20px 0;
        }
        
        .qr-container img {
            max-width: 300px;
            width: 100%;
            height: auto;
            display: block;
        }
        
        .btn {
            padding: 14px 28px;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            transition: all 0.3s ease;
            display: inline-block;
            margin: 10px 5px;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #58a6ff, #a5a5ff);
            color: white;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(88, 166, 255, 0.4);
        }
        
        .btn-success {
            background: linear-gradient(135deg, #3fb950, #2ea043);
            color: white;
        }
        
        .info-text {
            color: #8b949e;
            font-size: 0.9rem;
            margin-top: 15px;
            line-height: 1.6;
        }
        
        .loading {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(255,255,255,0.3);
            border-top-color: white;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        .hidden { display: none; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📱 WhatsApp - Sistema de Retalhos</h1>
            <p>Configure a conexão para envio automático de solicitações</p>
        </div>
        
        <div class="content">
            <div class="status-card">
                <h2 style="margin-bottom: 15px;">Status da Conexão</h2>
                <div id="status-display">
                    <?php if ($conectado): ?>
                        <span class="status-indicator status-connected"></span>
                        <strong style="color: #3fb950;">CONECTADO</strong>
                        <p class="info-text">O sistema está pronto para enviar mensagens automaticamente.</p>
                    <?php else: ?>
                        <span class="status-indicator status-disconnected"></span>
                        <strong style="color: #f85149;">DESCONECTADO</strong>
                        <p class="info-text">Conecte o WhatsApp para habilitar o envio automático de solicitações.</p>
                    <?php endif; ?>
                </div>
            </div>
            
            <div id="qr-section" class="<?= $conectado ? 'hidden' : '' ?>">
                <div style="text-align: center;">
                    <h3 style="margin-bottom: 20px;">Escaneie o QR Code</h3>
                    <div id="qr-display">
                        <p class="info-text">Clique no botão abaixo para gerar o QR Code</p>
                    </div>
                    <button class="btn btn-primary" onclick="iniciarConexao()">
                        🔗 Iniciar Conexão
                    </button>
                </div>
            </div>
            
            <div id="connected-section" class="<?= !$conectado ? 'hidden' : '' ?>">
                <div style="text-align: center;">
                    <button class="btn btn-success" onclick="window.location.href='retalhos_filtro.php'">
                        ✅ Voltar ao Sistema
                    </button>
                    <p class="info-text" style="margin-top: 20px;">
                        Sessão ID: <strong><?= htmlspecialchars($session_id) ?></strong>
                    </p>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        let verificacaoInterval = null;
        
        function iniciarConexao() {
            const qrDisplay = document.getElementById('qr-display');
            qrDisplay.innerHTML = '<div class="loading"></div><p class="info-text">Iniciando sessão...</p>';
            
            fetch('', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'action=iniciar_sessao'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    setTimeout(obterQRCode, 2000);
                } else {
                    qrDisplay.innerHTML = '<p style="color: #f85149;">❌ Erro ao iniciar sessão</p>';
                }
            })
            .catch(error => {
                console.error('Erro:', error);
                qrDisplay.innerHTML = '<p style="color: #f85149;">❌ Erro de conexão</p>';
            });
        }
        
        function obterQRCode() {
            const qrDisplay = document.getElementById('qr-display');
            
            fetch('', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'action=obter_qr'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success && data.qr_image) {
                    qrDisplay.innerHTML = `
                        <div class="qr-container">
                            <img src="${data.qr_image}" alt="QR Code WhatsApp">
                        </div>
                        <p class="info-text">
                            1. Abra o WhatsApp no celular<br>
                            2. Toque em ⋮ (Menu) > Aparelhos conectados<br>
                            3. Toque em "Conectar um aparelho"<br>
                            4. Escaneie o QR Code acima
                        </p>
                    `;
                    
                    // Iniciar verificação periódica de conexão
                    if (!verificacaoInterval) {
                        verificacaoInterval = setInterval(verificarStatus, 3000);
                    }
                } else {
                    qrDisplay.innerHTML = '<p style="color: #f85149;">❌ Erro ao obter QR Code</p>';
                }
            })
            .catch(error => {
                console.error('Erro:', error);
                qrDisplay.innerHTML = '<p style="color: #f85149;">❌ Erro de conexão</p>';
            });
        }
        
        function verificarStatus() {
            fetch('', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'action=verificar_status'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success && data.status && data.status.state === 'CONNECTED') {
                    clearInterval(verificacaoInterval);
                    window.location.reload();
                }
            })
            .catch(error => console.error('Erro ao verificar status:', error));
        }
    </script>
</body>
</html>
