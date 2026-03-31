<?php
require_once 'config.php';

$conn = getConnection();

// Criar tabela de requisições
$sql_requisicoes = "CREATE TABLE IF NOT EXISTS requisicoes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cliente VARCHAR(200) NOT NULL,
    marceneiro VARCHAR(200) NOT NULL,
    servico TEXT NOT NULL,
    total DECIMAL(10,2) DEFAULT 0,
    data_requisicao DATETIME DEFAULT CURRENT_TIMESTAMP,
    pdf_gerado VARCHAR(255),
    status ENUM('pendente', 'finalizada') DEFAULT 'pendente',
    observacoes TEXT,
    INDEX idx_data (data_requisicao),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

// Criar tabela de itens da requisição
$sql_itens_requisicao = "CREATE TABLE IF NOT EXISTS itens_requisicao (
    id INT AUTO_INCREMENT PRIMARY KEY,
    requisicao_id INT NOT NULL,
    codigo_item VARCHAR(50) NOT NULL,
    item_nome VARCHAR(200) NOT NULL,
    quantidade INT NOT NULL,
    valor_unitario DECIMAL(10,2),
    valor_total DECIMAL(10,2),
    FOREIGN KEY (requisicao_id) REFERENCES requisicoes(id) ON DELETE CASCADE,
    INDEX idx_requisicao (requisicao_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

try {
    // Criar tabelas
    if ($conn->query($sql_requisicoes) === TRUE) {
        echo "✓ Tabela 'requisicoes' criada com sucesso!<br>";
    } else {
        echo "Erro ao criar tabela 'requisicoes': " . $conn->error . "<br>";
    }
    
    if ($conn->query($sql_itens_requisicao) === TRUE) {
        echo "✓ Tabela 'itens_requisicao' criada com sucesso!<br>";
    } else {
        echo "Erro ao criar tabela 'itens_requisicao': " . $conn->error . "<br>";
    }
    
    echo "<br><strong>Sistema pronto para uso!</strong><br>";
    echo "<a href='index.php' style='display:inline-block;margin-top:20px;padding:10px 20px;background:#4a9eff;color:white;text-decoration:none;border-radius:8px;'>Acessar Sistema</a>";
    
} catch (Exception $e) {
    echo "Erro: " . $e->getMessage();
}

$conn->close();
?>
