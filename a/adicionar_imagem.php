<?php
$dbHost = "192.168.0.201";
if (!empty($_SERVER['SERVER_ADDR']) && $_SERVER['SERVER_ADDR'] === $dbHost) {
    $dbHost = "127.0.0.1";
}
$conn = new mysqli($dbHost, "root", "", "onnmoveis");

if ($conn->connect_error) {
    die("Erro: " . $conn->connect_error);
}

// Adicionar coluna de imagem se não existir
$sql = "ALTER TABLE itens ADD COLUMN imagem VARCHAR(255) NULL AFTER Descricao";
$result = $conn->query($sql);

if ($result) {
    echo "✓ Coluna 'imagem' adicionada com sucesso!<br>";
} else {
    if (strpos($conn->error, 'Duplicate column') !== false) {
        echo "✓ Coluna 'imagem' já existe!<br>";
    } else {
        echo "Erro: " . $conn->error . "<br>";
    }
}

// Criar pasta de uploads se não existir
$upload_dir = __DIR__ . '/uploads/materiais';
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0777, true);
    echo "✓ Pasta de uploads criada!<br>";
} else {
    echo "✓ Pasta de uploads já existe!<br>";
}

echo "<br><strong>Sistema de imagens configurado!</strong><br>";
echo "<a href='estoque.php' style='display:inline-block;margin-top:20px;padding:10px 20px;background:#4a9eff;color:white;text-decoration:none;border-radius:8px;'>Voltar ao Estoque</a>";

$conn->close();
?>
