<?php
header('Content-Type: application/json');

// Configurações do banco de dados
$servername = "192.168.0.201";
if (!empty($_SERVER['SERVER_ADDR']) && $_SERVER['SERVER_ADDR'] === $servername) {
    $servername = "127.0.0.1";
}
$username = "root";      // Substitua pelo seu usuário do MySQL
$password = "";        // Substitua pela sua senha do MySQL
$database = "onnmoveis";        // Nome do seu banco de dados

// Conexão com o banco
$conn = new mysqli($servername, $username, $password, $database);

// Verifica conexão
if ($conn->connect_error) {
    echo json_encode(["erro" => "Falha na conexão: " . $conn->connect_error]);
    exit;
}

// Buscar pedidos com status 'Pendente' (ou ajuste para AdicionadoAoEstoque = 0, se desejar)
$sql = "SELECT NomeProduto, NomeFornecedor FROM pedidos WHERE Status = 'Pendente'";
$result = $conn->query($sql);

$pedidos = [];

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $pedidos[] = $row;
    }
}

// Retorna o número e os dados dos pedidos
echo json_encode([
    "total" => count($pedidos),
    "pedidos" => $pedidos
]);

$conn->close();
?>
