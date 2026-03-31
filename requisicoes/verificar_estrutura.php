<?php
require_once 'config.php';

$conn = getConnection();

echo "<h2>Estrutura das tabelas:</h2>";

// Verificar tabelas existentes
$tables = ['requisicoes', 'itens_requisicao', 'requisicoes_itens', 'itens'];

foreach ($tables as $table) {
    echo "<h3>Tabela: $table</h3>";
    
    $sql = "SHOW COLUMNS FROM $table";
    $result = $conn->query($sql);
    
    if ($result) {
        echo "<table border='1'><tr><th>Campo</th><th>Tipo</th><th>Null</th><th>Key</th><th>Default</th></tr>";
        while ($row = $result->fetch_assoc()) {
            echo "<tr>";
            echo "<td>" . $row['Field'] . "</td>";
            echo "<td>" . $row['Type'] . "</td>";
            echo "<td>" . $row['Null'] . "</td>";
            echo "<td>" . $row['Key'] . "</td>";
            echo "<td>" . $row['Default'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "❌ Tabela não existe: " . $conn->error . "<br>";
    }
    echo "<br>";
}

$conn->close();
?>