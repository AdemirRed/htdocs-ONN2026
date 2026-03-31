<?php
/**
 * Script para criar tabelas necessárias do sistema
 * Execute este arquivo UMA VEZ antes de usar o sistema
 */

session_start();

$conn = new mysqli("192.168.0.201", "root", "", "onnmoveis");
$conn->set_charset("utf8");

if ($conn->connect_error) {
    die("Erro na conexão: " . $conn->connect_error);
}

echo "<h2>Criando tabelas necessárias...</h2>";

// 1. Tabela whatsapp_sessions
$sql1 = "CREATE TABLE IF NOT EXISTS whatsapp_sessions (
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

if ($conn->query($sql1)) {
    echo "✅ Tabela whatsapp_sessions criada/verificada<br>";
} else {
    echo "❌ Erro ao criar whatsapp_sessions: " . $conn->error . "<br>";
}

// 2. Tabela whatsapp_envios
$sql2 = "CREATE TABLE IF NOT EXISTS whatsapp_envios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pedido_id INT NOT NULL,
    telefone VARCHAR(20) NOT NULL,
    usuario_envio VARCHAR(100) NOT NULL,
    data_envio TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_pedido (pedido_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

if ($conn->query($sql2)) {
    echo "✅ Tabela whatsapp_envios criada/verificada<br>";
} else {
    echo "❌ Erro ao criar whatsapp_envios: " . $conn->error . "<br>";
}

// 3. Tabela pedido_imagens
$sql3 = "CREATE TABLE IF NOT EXISTS pedido_imagens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pedido_id INT NOT NULL,
    url_imagem TEXT NOT NULL,
    data_upload TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_pedido (pedido_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

if ($conn->query($sql3)) {
    echo "✅ Tabela pedido_imagens criada/verificada<br>";
} else {
    echo "❌ Erro ao criar pedido_imagens: " . $conn->error . "<br>";
}

// 4. Verificar se tabela notificacoes existe
$sql4 = "CREATE TABLE IF NOT EXISTS notificacoes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tipo VARCHAR(50) NOT NULL,
    pedido_id INT NULL,
    mensagem TEXT NOT NULL,
    lida TINYINT(1) DEFAULT 0,
    data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_pedido (pedido_id),
    INDEX idx_lida (lida)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

if ($conn->query($sql4)) {
    echo "✅ Tabela notificacoes criada/verificada<br>";
} else {
    echo "❌ Erro ao criar notificacoes: " . $conn->error . "<br>";
}

echo "<br><h3>✅ Processo concluído!</h3>";
echo "<p><a href='pedidos.php'>Ir para o sistema de pedidos</a></p>";

$conn->close();
?>