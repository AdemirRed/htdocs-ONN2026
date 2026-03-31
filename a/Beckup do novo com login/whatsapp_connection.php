<?php
/**
 * Página de Gerenciamento de Conexão WhatsApp
 */

session_start();

// Verificar autenticação
if (!isset($_SESSION['usuario'])) {
    header("Location: index.php");
    exit();
}

require_once 'WhatsAppSessionManager.php';

// Conectar ao banco
$conn = new mysqli("192.168.0.201", "root", "", "onnmoveis");
$conn->set_charset("utf8");

$whatsappManager = new WhatsAppSessionManager($conn);
$usuario = $_SESSION['usuario'];

// Processar ações AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    switch ($_POST['action']) {
        case 'obter_status':
            $sessao = $whatsappManager->obterSessaoUsuario($usuario);
            $status = $whatsappManager->verificarStatus($sessao['session_id']);
            
            $response = [
                'success' => true,
                'session_id' => $sessao['session_id'],
                'status' => $status['state'] ?? 'DISCONNECTED',
                'connected' => ($status['state'] ?? '') === 'CONNECTED'
            ];
            
            // Se desconectado, obter QR
            if (!$response['connected']) {
                $qrData = $whatsappManager->obterQRCode($sessao['session_id']);
                if ($qrData['success'] ?? false) {
                    $response['qr_code'] = $whatsappManager->gerarImagemQR($qrData['qr']);
                    $response['qr_text'] = $qrData['qr'];
                }
            }
            
            echo json_encode($response);
            exit();
            
        case 'iniciar_sessao':
            $sessao = $whatsappManager->obterSessaoUsuario($usuario);
            echo json_encode([
                'success' => true,
                'session_id' => $sessao['session_id'],
                'message' => 'Sessão iniciada. Aguarde o QR Code.'
            ]);
            exit();
    }
}

// Obter informações da sessão
$sessaoInfo = $whatsappManager->obterSessaoUsuario($usuario);
$apiOnline = $whatsappManager->verificarAPI();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Conexão WhatsApp - ONN Móveis</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        .container {
            max-width: 600px;
            margin: 50px auto;
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            overflow: hidden;
        }
        .header {
            background: linear-gradient(135deg, #25D366 0%, #128C7E 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        .header i { font-size: 3em; margin-bottom: 15px; }
        .header h1 { font-size: 1.8em; margin-bottom: 10px; }
        .content {
            padding: 40px 30px;
        }
        .status-card {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 25px;
            text-align: center;
        }
        .status-indicator {
            display: inline-block;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            margin-right: 10px;
            animation: pulse 2s infinite;
        }
        .status-connected { background: #28a745; }
        .status-connecting { background: #ffc107; }
        .status-disconnected { background: #dc3545; }
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }
        .qr-container {
            text-align: center;
            padding: 30px;
            background: #f8f9fa;
            border-radius: 12px;
            margin: 20px 0;
        }
        .qr-container img {
            max-width: 100%;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }
        .btn {
            display: inline-block;
            padding: 12px 30px;
            background: #667eea;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            border: none;
            cursor: pointer;
            font-size: 1em;
            transition: all 0.3s;
            margin: 5px;
        }
        .btn:hover { background: #5568d3; transform: translateY(-2px); }
        .btn-success { background: #28a745; }
        .btn-success:hover { background: #218838; }
        .info-box {
            background: #e7f3ff;
            border-left: 4px solid #2196F3;
            padding: 15px;
            margin: 20px 0;
            border-radius: 4px;
        }
        .info-box i { color: #2196F3; margin-right: 10px; }
        .loading {
            text-align: center;
            padding: 20px;
        }
        .loading i {
            font-size: 2em;
            color: #667eea;
            animation: spin 1s linear infinite;
        }
        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
        .session-info {
            background: #fff3cd;
            border: 1px solid #ffc107;
            border-radius: 8px;
            padding: 15px;
            margin: 15px 0;
        }
        .session-info strong { color: #856404; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <i class="fab fa-whatsapp"></i>
            <h1>Conexão WhatsApp</h1>
            <p>Olá, <?= htmlspecialchars($usuario) ?>!</p>
        </div>
        
        <div class="content">
            <?php if (!$apiOnline): ?>
                <div class="info-box" style="background: #ffebee; border-color: #f44336;">
                    <i class="fas fa-exclamation-triangle" style="color: #f44336;"></i>
                    <strong>API WhatsApp Offline</strong><br>
                    O servidor da API não está respondendo. Verifique se o serviço está rodando.
                </div>
            <?php else: ?>
                <div class="session-info">
                    <i class="fas fa-id-card"></i>
                    <strong>Session ID:</strong> <?= htmlspecialchars($sessaoInfo['session_id']) ?>
                </div>
                
                <div class="status-card" id="statusCard">
                    <div class="loading">
                        <i class="fas fa-spinner"></i>
                        <p>Verificando status da conexão...</p>
                    </div>
                </div>
                
                <div id="qrContainer" style="display: none;"></div>
                
                <div style="text-align: center; margin-top: 30px;">
                    <a href="pedidos.php" class="btn btn-success">
                        <i class="fas fa-arrow-left"></i> Voltar para Pedidos
                    </a>
                    <button onclick="verificarStatus()" class="btn">
                        <i class="fas fa-sync-alt"></i> Atualizar Status
                    </button>
                </div>
                
                <div class="info-box">
                    <i class="fas fa-info-circle"></i>
                    <strong>Como funciona:</strong><br>
                    1. Se você ainda não conectou, escaneie o QR Code com seu WhatsApp<br>
                    2. Abra o WhatsApp no celular → Configurações → Aparelhos conectados<br>
                    3. Toque em "Conectar um aparelho" e escaneie o código<br>
                    4. Sua sessão ficará ativa e as mensagens serão enviadas automaticamente
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        let verificacaoInterval;
        
        async function verificarStatus() {
            try {
                const formData = new FormData();
                formData.append('action', 'obter_status');
                
                const response = await fetch('whatsapp_connection.php', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                atualizarInterface(data);
                
            } catch (error) {
                console.error('Erro ao verificar status:', error);
            }
        }
        
        function atualizarInterface(data) {
            const statusCard = document.getElementById('statusCard');
            const qrContainer = document.getElementById('qrContainer');
            
            let statusClass = 'status-disconnected';
            let statusText = 'Desconectado';
            let statusIcon = 'fa-times-circle';
            
            if (data.status === 'CONNECTED') {
                statusClass = 'status-connected';
                statusText = 'Conectado';
                statusIcon = 'fa-check-circle';
                qrContainer.style.display = 'none';
                
                // Parar verificação automática se conectado
                if (verificacaoInterval) {
                    clearInterval(verificacaoInterval);
                }
                
            } else if (data.status === 'CONNECTING') {
                statusClass = 'status-connecting';
                statusText = 'Conectando...';
                statusIcon = 'fa-spinner fa-spin';
            }
            
            statusCard.innerHTML = `
                <h2 style="margin-bottom: 15px;">
                    <span class="status-indicator ${statusClass}"></span>
                    Status: ${statusText}
                </h2>
                <p style="color: #666;">
                    <i class="fas ${statusIcon}"></i>
                    ${data.connected ? 'WhatsApp conectado e pronto para uso!' : 'Aguardando conexão do WhatsApp'}
                </p>
            `;
            
            // Mostrar QR Code se não conectado
            if (!data.connected && data.qr_code) {
                qrContainer.style.display = 'block';
                qrContainer.innerHTML = `
                    <div class="qr-container">
                        <h3 style="margin-bottom: 20px; color: #333;">
                            <i class="fas fa-qrcode"></i> Escaneie o QR Code
                        </h3>
                        <img src="${data.qr_code}" alt="QR Code WhatsApp">
                        <p style="margin-top: 15px; color: #666;">
                            O código expira em alguns minutos. Atualize se necessário.
                        </p>
                    </div>
                `;
            }
        }
        
        // Verificar status ao carregar
        verificarStatus();
        
        // Verificar automaticamente a cada 5 segundos até conectar
        verificacaoInterval = setInterval(verificarStatus, 5000);
    </script>
</body>
</html>