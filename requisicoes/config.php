<?php
// Configuração do banco de dados
define('DB_HOST', '192.168.0.201');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'onnmoveis');

// Conexão com o banco de dados
function getConnection() {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
    if ($conn->connect_error) {
        die("Erro na conexão: " . $conn->connect_error);
    }
    
    $conn->set_charset("utf8mb4");
    return $conn;
}

// Função para formatar dinheiro
function formatarMoeda($valor) {
    return 'R$ ' . number_format($valor, 2, ',', '.');
}

// Função para formatar data
function formatarData($data) {
    return date('d/m/Y H:i', strtotime($data));
}
?>
