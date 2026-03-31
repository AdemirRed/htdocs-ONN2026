<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$directory_materials = 'C:/CC_DATA_BASE/MAT';
$directory_retalhos = 'C:/CC_DATA_BASE/CHP';

$files_materials = glob($directory_materials . '/*.INI');
$files_retalhos = glob($directory_retalhos . '/*.TAB');

$materials = [];

// Processar materiais
foreach ($files_materials as $file) {
    $filename = basename($file, '.INI');
    $code = ltrim($filename, 'M') ?: 'EMPTY';

    $data = @parse_ini_file($file, true);
    if ($data === false) {
        continue;
    }

    $nome_material = isset($data['DESC']['CAMPO1']) ? str_replace(['(', ')'], '', $data['DESC']['CAMPO1']) : 'EMPTY';
    $veio_vertical = isset($data['PROP_FISIC']['VEIO_VERTICAL']) ? $data['PROP_FISIC']['VEIO_VERTICAL'] : null;
    $giro = isset($data['PROP_FISIC']['GIRO']) ? $data['PROP_FISIC']['GIRO'] : null;

    $veio = (is_numeric($veio_vertical) && is_numeric($giro) && $veio_vertical == 1 && $giro == 0) ? 'Sim' : 'Não';

    $materials[] = [
        'code' => $code,
        'name' => $nome_material,
        'grain' => $veio,
    ];
}

// Função para carregar retalhos
function loadRetalhos($code, $directory_retalhos) {
    $file_path = $directory_retalhos . "/RET" . str_pad($code, 5, '0', STR_PAD_LEFT) . ".TAB";
    if (!file_exists($file_path)) {
        return [];
    }

    $retalhos = [];
    $lines = file($file_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $fields = explode(',', $line);
        if (count($fields) < 6) continue;

        $retalhos[] = [
            'codigo' => trim($fields[0]),
            'ativo' => trim($fields[1]),
            'quantidade' => trim($fields[2]),
            'altura' => trim($fields[3]),
            'largura' => trim($fields[4]),
            'descricao' => trim($fields[5]),
            'reservado' => isset($fields[6]) ? trim($fields[6]) : '',
        ];
    }
    return $retalhos;
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Materiais MDF</title>
    <link rel="stylesheet" href="styles.css">
    <script>
        function filterTable() {
            const searchInput = document.getElementById('searchInput').value.toLowerCase();
            const rows = document.querySelectorAll('#materialTable tr');

            rows.forEach(row => {
                const code = row.querySelector('td:nth-child(1)').textContent.toLowerCase();
                const name = row.querySelector('td:nth-child(2)').textContent.toLowerCase();

                if (code.includes(searchInput) || name.includes(searchInput)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        }

     function sortTable(columnIndex) {
    const table = document.getElementById('materialTable');
    const rows = Array.from(table.querySelectorAll('tr'));

    const isNumericColumn = columnIndex === 1; // Coluna "Código" é numérica
    const sortedRows = rows.sort((a, b) => {
        const valueA = a.querySelector(`td:nth-child(${columnIndex})`).textContent.trim();
        const valueB = b.querySelector(`td:nth-child(${columnIndex})`).textContent.trim();

        if (isNumericColumn) {
            // Ordenação numérica para "Código"
            return parseInt(valueA, 10) - parseInt(valueB, 10);
        }

        // Ordenação alfabética para "Nome do Material"
        return valueA.localeCompare(valueB, 'pt-BR', { numeric: true });
    });

    // Remove todas as linhas da tabela e reinsere na nova ordem
    table.innerHTML = '';
    sortedRows.forEach(row => table.appendChild(row));
}





        async function showRetalhos(code) {
            const response = await fetch(`retalhos.php?code=${code}`);
            const data = await response.json();
            const modal = document.getElementById('modal');
            const modalContent = document.getElementById('modal-content');
            modalContent.innerHTML = `
                <h2>Retalhos do Material ${code}</h2>
                <table>
                    <thead>
                        <tr>
                            <th>Código</th>
                            <th>Ativo</th>
                            <th>Quantidade</th>
                            <th>Altura</th>
                            <th>Largura</th>
                            <th>Descrição</th>
                            <th>Reservado</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${data.map(retalho => `
                            <tr>
                                <td>${retalho.codigo}</td>
                                <td>${retalho.ativo}</td>
                                <td>${retalho.quantidade}</td>
                                <td>${retalho.altura}</td>
                                <td>${retalho.largura}</td>
                                <td>${retalho.descricao}</td>
                                <td>${retalho.reservado}</td>
                            </tr>
                        `).join('')}
                    </tbody>
                </table>
            `;
            modal.style.display = 'block';
        }

        function closeModal() {
            document.getElementById('modal').style.display = 'none';
        }

        function openRetalhosPage(code) {
    window.location.href = 'retalhos_view.php?code=' + code;
}

function searchImage(materialName) {
    const query = encodeURIComponent(materialName + " MDF");
    const url = `https://www.google.com/search?tbm=isch&q=${query}`;
    window.open(url, '_blank');
}

    </script>
</head>
<body>
    <div class="container">
        <header>
            <h1>Materiais MDF</h1>
        </header>

        <div class="actions">
            <input 
                type="text" 
                id="searchInput" 
                placeholder="Pesquise por código ou nome" 
                oninput="filterTable()"
            />
            <button onclick="sortTable(1)">Ordenar por Código</button>
            <button onclick="sortTable(2)">Ordenar por Nome</button>
		<button onclick="window.location.href='retalhos_filtro.php';">Pesquisar por TAMANHO</button>
<button onclick="window.location.href='materiais.php';">Home</button>
        </div>
<div class="table-wrapper">
        <table>
            <thead>
                <tr>
                    <th>Código</th>
                    <th>Nome do Material</th>
                    <th>Veio</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody id="materialTable">
                <?php foreach ($materials as $material): ?>
                    <tr>
                        <td><?= htmlspecialchars($material['code']) ?></td>
                        <td><?= htmlspecialchars($material['name']) ?></td>
                        <td><?= htmlspecialchars($material['grain']) ?></td>
                        <td>
    <button onclick="openRetalhosPage('<?= htmlspecialchars($material['code']) ?>')">Ver Retalhos</button>
    <button onclick="searchImage('<?= htmlspecialchars($material['name']) ?>')">Imagem</button>
</td>

                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

    <div id="modal" style="display:none;">
        <div id="modal-content"></div>
        <button onclick="closeModal()">Fechar</button>
    </div>
</body>
</html>

