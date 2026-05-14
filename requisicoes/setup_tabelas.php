<?php
/**
 * Script para criar tabelas de requisições
 */

require_once 'config.php';
$conn = getConnection();

echo "<h2>Criando tabelas de requisições...</h2>";

// 1. Verificar e atualizar tabela requisicoes
$sql_check_columns = "SHOW COLUMNS FROM requisicoes";
$result_check = $conn->query($sql_check_columns);
$existing_columns = [];

if ($result_check) {
    while ($row = $result_check->fetch_assoc()) {
        $existing_columns[] = $row['Field'];
    }
}

// Adicionar colunas que faltam
if (!in_array('usuario', $existing_columns)) {
    $sql_add_usuario = "ALTER TABLE requisicoes ADD COLUMN usuario VARCHAR(100) NOT NULL DEFAULT 'Sistema'";
    if ($conn->query($sql_add_usuario)) {
        echo "✅ Coluna 'usuario' adicionada à tabela requisicoes<br>";
    } else {
        echo "❌ Erro ao adicionar coluna usuario: " . $conn->error . "<br>";
    }
}

if (!in_array('total', $existing_columns)) {
    $sql_add_total = "ALTER TABLE requisicoes ADD COLUMN total DECIMAL(10,2) DEFAULT 0";
    if ($conn->query($sql_add_total)) {
        echo "✅ Coluna 'total' adicionada à tabela requisicoes<br>";
    } else {
        echo "❌ Erro ao adicionar coluna total: " . $conn->error . "<br>";
    }
}

if (!in_array('status', $existing_columns)) {
    $sql_add_status = "ALTER TABLE requisicoes ADD COLUMN status VARCHAR(50) DEFAULT 'pendente'";
    if ($conn->query($sql_add_status)) {
        echo "✅ Coluna 'status' adicionada à tabela requisicoes<br>";
    } else {
        echo "❌ Erro ao adicionar coluna status: " . $conn->error . "<br>";
    }
}

if (!in_array('observacoes', $existing_columns)) {
    $sql_add_obs = "ALTER TABLE requisicoes ADD COLUMN observacoes TEXT";
    if ($conn->query($sql_add_obs)) {
        echo "✅ Coluna 'observacoes' adicionada à tabela requisicoes<br>";
    } else {
        echo "❌ Erro ao adicionar coluna observacoes: " . $conn->error . "<br>";
    }
}

echo "✅ Tabela requisicoes verificada/atualizada<br>";

// 2. Tabela requisicoes_itens
$sql2 = "CREATE TABLE IF NOT EXISTS requisicoes_itens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    requisicao_id INT NOT NULL,
    codigo_material VARCHAR(20) NOT NULL,
    quantidade INT NOT NULL,
    valor_unitario DECIMAL(10,2) NOT NULL,
    valor_total DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (requisicao_id) REFERENCES requisicoes(id) ON DELETE CASCADE,
    INDEX idx_requisicao (requisicao_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

if ($conn->query($sql2)) {
    echo "✅ Tabela requisicoes_itens criada/verificada<br>";
} else {
    echo "❌ Erro ao criar requisicoes_itens: " . $conn->error . "<br>";
}

// 3. Adicionar algumas requisições de exemplo se não existirem
$sql_check = "SELECT COUNT(*) as count FROM requisicoes";
$result = $conn->query($sql_check);
$count = $result->fetch_assoc()['count'];

if ($count == 0) {
    echo "<br><h3>Adicionando dados de exemplo...</h3>";
    
    // Inserir requisições de exemplo
    $sql_exemplo1 = "INSERT INTO requisicoes (usuario, status, total, observacoes) VALUES 
        ('João Silva', 'aprovada', 1250.50, 'Materiais para reforma sala'),
        ('Maria Santos', 'pendente', 890.75, 'Materiais para cozinha'),
        ('Pedro Costa', 'finalizada', 2340.00, 'Materiais para banheiro')";
    
    if ($conn->query($sql_exemplo1)) {
        echo "✅ Requisições de exemplo criadas<br>";
    } else {
        echo "❌ Erro ao criar exemplos: " . $conn->error . "<br>";
    }
}

echo "<br><h3>✅ Processo concluído!</h3>";
echo "<p><a href='dashboard.php'>Testar Dashboard</a> | <a href='index.php'>Voltar ao Sistema</a></p>";

$conn->close();
?>