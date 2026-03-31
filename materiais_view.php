<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$directory_materials = 'C:/CC_DATA_BASE/MAT';
$directory_retalhos = 'C:/CC_DATA_BASE/CHP';	

$files_materials = glob($directory_materials . '/*.INI');
$materials = [];

// Função para ler espessura do arquivo INI
function getEspessuraFromIni($iniFilePath) {
    $iniData = parse_ini_file($iniFilePath, true);
    if (isset($iniData['PROP_FISIC']['ESPESSURA'])) {
        return floatval($iniData['PROP_FISIC']['ESPESSURA']);
    }
    return null;
}

// Processar materiais
foreach ($files_materials as $file) {
    $filename = basename($file, '.INI');
    $code = ltrim($filename, 'M') ?: 'EMPTY';

    $data = @parse_ini_file($file, true);
    if ($data === false) continue;

    $nome_material = isset($data['DESC']['CAMPO1']) ? str_replace(['(', ')'], '', $data['DESC']['CAMPO1']) : 'EMPTY';
    $veio_vertical = isset($data['PROP_FISIC']['VEIO_VERTICAL']) ? $data['PROP_FISIC']['VEIO_VERTICAL'] : null;
    $giro = isset($data['PROP_FISIC']['GIRO']) ? $data['PROP_FISIC']['GIRO'] : null;
    $veio = (is_numeric($veio_vertical) && is_numeric($giro) && $veio_vertical == 1 && $giro == 0) ? 'Sim' : 'Não';
    $espessura = getEspessuraFromIni($file);

    $materials[] = [
        'code' => $code,
        'name' => $nome_material,
        'grain' => $veio,
        'espessura' => $espessura,
        'file_path' => $file
    ];
}

// Ordenar materiais por nome
usort($materials, function($a, $b) {
    return strcmp($a['name'], $b['name']);
});

// Obter estatísticas
$total_materials = count($materials);
$materials_with_grain = count(array_filter($materials, function($m) { return $m['grain'] === 'Sim'; }));
$current_datetime = date('d/m/Y H:i:s');
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NEXUS | Catálogo de Materiais MDF</title>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🏗️</text></svg>">
    
    <style>
        /* Tema Nexus - Variáveis CSS */
        :root {
            --nexus-bg-primary: #0d1117;
            --nexus-bg-secondary: #161b22;
            --nexus-bg-tertiary: #21262d;
            --nexus-bg-glass: rgba(33, 38, 45, 0.95);
            --nexus-border: #30363d;
            --nexus-text-primary: #f0f6fc;
            --nexus-text-secondary: #8b949e;
            --nexus-accent-blue: #58a6ff;
            --nexus-accent-green: #3fb950;
            --nexus-accent-purple: #a5a5ff;
            --nexus-accent-orange: #ffa657;
            --nexus-accent-cyan: #39d0d6;
            --nexus-hover-bg: #30363d;
            --nexus-focus-border: #1f6feb;
            --nexus-error: #f85149;
            --nexus-warning: #d29922;
            --nexus-shadow: rgba(0, 0, 0, 0.5);
            --nexus-gradient-primary: linear-gradient(135deg, var(--nexus-accent-blue), var(--nexus-accent-purple));
            --nexus-gradient-secondary: linear-gradient(135deg, var(--nexus-accent-green), var(--nexus-accent-cyan));
            --nexus-gradient-warning: linear-gradient(135deg, var(--nexus-accent-orange), var(--nexus-warning));
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Helvetica, Arial, sans-serif;
            background: linear-gradient(135deg, var(--nexus-bg-primary) 0%, var(--nexus-bg-secondary) 100%);
            color: var(--nexus-text-primary);
            line-height: 1.6;
            min-height: 100vh;
            background-attachment: fixed;
        }

        /* Header Principal */
        .main-header {
            background: var(--nexus-bg-glass);
            backdrop-filter: blur(20px);
            border-bottom: 2px solid var(--nexus-border);
            box-shadow: 0 4px 32px var(--nexus-shadow);
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .header-content {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
        }

        .logo-section {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .logo-icon {
            width: 50px;
            height: 50px;
            background: var(--nexus-gradient-primary);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            box-shadow: 0 4px 20px rgba(88, 166, 255, 0.3);
        }

        .logo-text {
            display: flex;
            flex-direction: column;
        }

        .logo-title {
            font-size: 1.8rem;
            font-weight: 700;
            background: var(--nexus-gradient-primary);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .logo-subtitle {
            font-size: 0.9rem;
            color: var(--nexus-text-secondary);
            font-weight: 400;
        }

        .header-info {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            gap: 8px;
        }

        .user-badge {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 8px 16px;
            background-color: var(--nexus-bg-tertiary);
            border-radius: 25px;
            border: 1px solid var(--nexus-border);
        }

        .user-avatar {
            width: 32px;
            height: 32px;
            background: var(--nexus-gradient-secondary);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 14px;
        }

        .datetime-info {
            font-size: 0.8rem;
            color: var(--nexus-text-secondary);
            text-align: right;
        }

        /* Estatísticas */
        .stats-section {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
        }

        .stat-card {
            background: var(--nexus-bg-secondary);
            border-radius: 12px;
            padding: 20px;
            border: 1px solid var(--nexus-border);
            box-shadow: 0 4px 16px var(--nexus-shadow);
            text-align: center;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px var(--nexus-shadow);
        }

        .stat-value {
            font-size: 2.5rem;
            font-weight: 700;
            background: var(--nexus-gradient-primary);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 8px;
        }

        .stat-label {
            color: var(--nexus-text-secondary);
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* Container Principal */
        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }

        /* Seção de Controles */
        .controls-section {
            background: var(--nexus-bg-secondary);
            border-radius: 16px;
            padding: 25px;
            margin-bottom: 30px;
            border: 1px solid var(--nexus-border);
            box-shadow: 0 8px 32px var(--nexus-shadow);
        }

        .controls-grid {
            display: grid;
            grid-template-columns: 1fr auto;
            gap: 20px;
            align-items: center;
        }

        .search-container {
            position: relative;
        }

        .search-input {
            width: 100%;
            padding: 14px 50px 14px 16px;
            background-color: var(--nexus-bg-tertiary);
            border: 2px solid var(--nexus-border);
            border-radius: 10px;
            color: var(--nexus-text-primary);
            font-size: 16px;
            transition: all 0.3s ease;
            outline: none;
        }

        .search-input:focus {
            border-color: var(--nexus-focus-border);
            box-shadow: 0 0 0 3px rgba(31, 111, 235, 0.2);
        }

        .search-icon {
            position: absolute;
            right: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--nexus-text-secondary);
            font-size: 18px;
        }

        .action-buttons {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }

        .btn {
            padding: 12px 20px;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            white-space: nowrap;
        }

        .btn-primary {
            background: var(--nexus-gradient-primary);
            color: white;
            box-shadow: 0 4px 15px rgba(88, 166, 255, 0.3);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(88, 166, 255, 0.4);
        }

        .btn-secondary {
            background: var(--nexus-gradient-secondary);
            color: white;
            box-shadow: 0 4px 15px rgba(63, 185, 80, 0.3);
        }

        .btn-secondary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(63, 185, 80, 0.4);
        }

        .btn-tertiary {
            background: var(--nexus-gradient-warning);
            color: white;
            box-shadow: 0 4px 15px rgba(255, 166, 87, 0.3);
        }

        .btn-tertiary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(255, 166, 87, 0.4);
        }

        /* Tabela */
        .table-section {
            background: var(--nexus-bg-secondary);
            border-radius: 16px;
            overflow: hidden;
            border: 1px solid var(--nexus-border);
            box-shadow: 0 8px 32px var(--nexus-shadow);
        }

        .table-header {
            background: var(--nexus-gradient-primary);
            padding: 20px 25px;
            color: white;
        }

        .table-title {
            font-size: 1.3rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .table-wrapper {
            overflow-x: auto;
            max-height: 70vh;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            background-color: var(--nexus-bg-tertiary);
        }

        th {
            background: linear-gradient(135deg, var(--nexus-bg-primary), var(--nexus-bg-secondary));
            color: var(--nexus-text-primary);
            padding: 16px 12px;
            text-align: left;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-size: 12px;
            border-bottom: 2px solid var(--nexus-border);
            position: sticky;
            top: 0;
            z-index: 10;
            cursor: pointer;
            transition: background-color 0.2s ease;
        }

        th:hover {
            background: var(--nexus-hover-bg);
        }

        td {
            padding: 14px 12px;
            border-bottom: 1px solid var(--nexus-border);
            color: var(--nexus-text-primary);
            font-size: 14px;
            vertical-align: middle;
        }

        tr:hover {
            background-color: var(--nexus-hover-bg);
            transition: background-color 0.2s ease;
        }

        tr:nth-child(even) {
            background-color: rgba(33, 38, 45, 0.3);
        }

        .material-code {
            font-family: 'JetBrains Mono', monospace;
            font-weight: bold;
            background: var(--nexus-bg-primary);
            padding: 4px 8px;
            border-radius: 6px;
            color: var(--nexus-accent-cyan);
        }

        .grain-badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: bold;
            text-transform: uppercase;
        }

        .grain-yes {
            background-color: var(--nexus-accent-green);
            color: white;
        }

        .grain-no {
            background-color: var(--nexus-text-secondary);
            color: white;
        }

        .action-buttons-cell {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .btn-small {
            padding: 6px 12px;
            font-size: 11px;
            min-width: auto;
        }

        .no-results {
            text-align: center;
            color: var(--nexus-text-secondary);
            font-style: italic;
            padding: 60px 20px;
            background: var(--nexus-bg-primary);
        }

        /* Modal */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(13, 17, 23, 0.9);
            justify-content: center;
            align-items: center;
            backdrop-filter: blur(10px);
        }

        .modal-content {
            background: var(--nexus-bg-secondary);
            border-radius: 16px;
            padding: 30px;
            max-width: 90vw;
            max-height: 80vh;
            overflow-y: auto;
            border: 1px solid var(--nexus-border);
            box-shadow: 0 20px 60px var(--nexus-shadow);
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid var(--nexus-border);
        }

        .modal-title {
            font-size: 1.5rem;
            font-weight: 600;
            background: var(--nexus-gradient-primary);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .close-btn {
            background: var(--nexus-error);
            color: white;
            border: none;
            border-radius: 50%;
            width: 32px;
            height: 32px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
            transition: all 0.3s ease;
        }

        .close-btn:hover {
            transform: scale(1.1);
            box-shadow: 0 4px 12px rgba(248, 81, 73, 0.3);
        }

        /* Responsivo */
        @media (max-width: 768px) {
            .header-content {
                flex-direction: column;
                text-align: center;
            }

            .header-info {
                align-items: center;
            }

            .stats-section {
                grid-template-columns: repeat(2, 1fr);
                padding: 20px 10px;
            }

            .container {
                padding: 20px 10px;
            }

            .controls-grid {
                grid-template-columns: 1fr;
                gap: 15px;
            }

            .action-buttons {
                justify-content: center;
            }

            .btn {
                flex: 1;
                min-width: 120px;
            }

            th, td {
                padding: 8px 6px;
                font-size: 12px;
            }

            .action-buttons-cell {
                flex-direction: column;
            }

            .modal-content {
                padding: 20px;
                margin: 20px;
            }
        }

        /* Animações */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .container > * {
            animation: fadeInUp 0.6s ease-out;
        }

        /* Scrollbar personalizada */
        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }

        ::-webkit-scrollbar-track {
            background: var(--nexus-bg-primary);
            border-radius: 4px;
        }

        ::-webkit-scrollbar-thumb {
            background: var(--nexus-border);
            border-radius: 4px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: var(--nexus-accent-blue);
        }
    </style>
</head>
<body>
    <!-- Header Principal -->
    <header class="main-header">
        <div class="header-content">
            <div class="logo-section">
                <div class="logo-icon">🏗️</div>
                <div class="logo-text">
                    <div class="logo-title">NEXUS</div>
                    <div class="logo-subtitle">Sistema de Gestão de Materiais</div>
                </div>
            </div>
            <div class="header-info">
                <div class="datetime-info">
                    <div>📅 <?= date('d/m/Y') ?></div>
                    <div>⏰ <?= date('H:i:s') ?></div>
                </div>
            </div>
        </div>
    </header>

    <!-- Estatísticas -->
    <section class="stats-section">
        <div class="stat-card">
            <div class="stat-value"><?= $total_materials ?></div>
            <div class="stat-label">📦 Total de Materiais</div>
        </div>
        <div class="stat-card">
            <div class="stat-value"><?= $materials_with_grain ?></div>
            <div class="stat-label">🌾 Com Veio</div>
        </div>
        <div class="stat-card">
            <div class="stat-value"><?= ($total_materials - $materials_with_grain) ?></div>
            <div class="stat-label">🚫 Sem Veio</div>
        </div>
        <div class="stat-card">
            <div class="stat-value"><?= round(($materials_with_grain / $total_materials) * 100, 1) ?>%</div>
            <div class="stat-label">📊 Taxa de Veio</div>
        </div>
    </section>

    <div class="container">
        <!-- Controles -->
        <div class="controls-section">
            <div class="controls-grid">
                <div class="search-container">
                    <input 
                        type="text" 
                        id="searchInput" 
                        class="search-input"
                        placeholder="🔍 Pesquisar por código ou nome do material..."
                        oninput="filterTable()"
                        autocomplete="off"
                    />
                    <span class="search-icon">🔍</span>
                </div>
                <div class="action-buttons">
                    <button class="btn btn-primary" onclick="sortTable(1)">
                        🔢 Ordenar Código
                    </button>
                    <button class="btn btn-primary" onclick="sortTable(2)">
                        📝 Ordenar Nome
                    </button>
                    <button class="btn btn-secondary" onclick="window.location.href='retalhos_filtro.php';">
                        📏 Buscar por Tamanho
                    </button>
                    <button class="btn btn-tertiary" onclick="window.location.href='materiais.php';">
                        🏠 Home
                    </button>
                </div>
            </div>
        </div>

        <!-- Tabela de Materiais -->
        <div class="table-section">
            <div class="table-header">
                <div class="table-title">
                    📋 Catálogo de Materiais MDF
                </div>
            </div>
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th onclick="sortTable(1)">🏷️ Código</th>
                            <th onclick="sortTable(2)">📝 Nome do Material</th>
                            <th onclick="sortTable(3)">🌾 Veio</th>
                            <th onclick="sortTable(4)">📏 Espessura</th>
                            <th>⚡ Ações</th>
                        </tr>
                    </thead>
                    <tbody id="materialTable">
                        <?php if (empty($materials)): ?>
                            <tr>
                                <td colspan="5" class="no-results">
                                    🔍 Nenhum material encontrado.<br>
                                    <small>Verifique se os arquivos estão no diretório correto.</small>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($materials as $material): ?>
                                <tr>
                                    <td>
                                        <span class="material-code"><?= htmlspecialchars($material['code']) ?></span>
                                    </td>
                                    <td><?= htmlspecialchars($material['name']) ?></td>
                                    <td>
                                        <span class="grain-badge <?= $material['grain'] === 'Sim' ? 'grain-yes' : 'grain-no' ?>">
                                            <?= $material['grain'] === 'Sim' ? '✅ Sim' : '❌ Não' ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?= $material['espessura'] ? $material['espessura'] . 'mm' : '➖' ?>
                                    </td>
                                    <td>
                                        <div class="action-buttons-cell">
                                            <button class="btn btn-primary btn-small" onclick="openRetalhosPage('<?= htmlspecialchars($material['code']) ?>')">
                                                📦 Ver Retalhos
                                            </button>
                                            <button class="btn btn-secondary btn-small" onclick="searchImage('<?= htmlspecialchars($material['name']) ?>')">
                                                🖼️ Imagem
                                            </button>
                                            <button class="btn btn-tertiary btn-small" onclick="showMaterialDetails('<?= htmlspecialchars($material['code']) ?>', '<?= htmlspecialchars($material['name']) ?>', '<?= htmlspecialchars($material['grain']) ?>', '<?= htmlspecialchars($material['espessura']) ?>')">
                                                ℹ️ Detalhes
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Modal -->
    <div id="modal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <div class="modal-title" id="modal-title">Detalhes do Material</div>
                <button class="close-btn" onclick="closeModal()">✕</button>
            </div>
            <div id="modal-body"></div>
        </div>
    </div>

    <script>
        let sortDirection = {};

        function filterTable() {
            const searchInput = document.getElementById('searchInput').value.toLowerCase();
            const rows = document.querySelectorAll('#materialTable tr');
            let visibleCount = 0;

            rows.forEach(row => {
                if (row.querySelector('td')) {
                    const code = row.querySelector('td:nth-child(1)').textContent.toLowerCase();
                    const name = row.querySelector('td:nth-child(2)').textContent.toLowerCase();
                    
                    if (code.includes(searchInput) || name.includes(searchInput)) {
                        row.style.display = '';
                        visibleCount++;
                    } else {
                        row.style.display = 'none';
                    }
                }
            });

            // Atualizar contador de resultados
            updateResultsCount(visibleCount);
        }

        function updateResultsCount(count) {
            const title = document.querySelector('.table-title');
            const total = <?= $total_materials ?>;
            title.innerHTML = `📋 Catálogo de Materiais MDF <span style="opacity: 0.7; font-size: 0.9em;">(${count} de ${total})</span>`;
        }

        function sortTable(columnIndex) {
            const table = document.getElementById('materialTable');
            const rows = Array.from(table.querySelectorAll('tr')).filter(row => row.querySelector('td'));
            
            // Determinar direção da ordenação
            const currentDirection = sortDirection[columnIndex] || 'asc';
            const newDirection = currentDirection === 'asc' ? 'desc' : 'asc';
            sortDirection[columnIndex] = newDirection;

            const sortedRows = rows.sort((a, b) => {
                let valueA = a.querySelector(`td:nth-child(${columnIndex})`).textContent.trim();
                let valueB = b.querySelector(`td:nth-child(${columnIndex})`).textContent.trim();

                // Tratamento especial para diferentes tipos de dados
                if (columnIndex === 1) { // Código
                    valueA = parseInt(valueA) || 0;
                    valueB = parseInt(valueB) || 0;
                } else if (columnIndex === 4) { // Espessura
                    valueA = parseFloat(valueA.replace('mm', '')) || 0;
                    valueB = parseFloat(valueB.replace('mm', '')) || 0;
                }

                let comparison = 0;
                if (valueA > valueB) comparison = 1;
                if (valueA < valueB) comparison = -1;
                
                return newDirection === 'desc' ? comparison * -1 : comparison;
            });

            // Limpar tabela e adicionar linhas ordenadas
            table.innerHTML = '';
            sortedRows.forEach(row => table.appendChild(row));

            // Atualizar indicadores visuais de ordenação
            updateSortIndicators(columnIndex, newDirection);
        }

        function updateSortIndicators(activeColumn, direction) {
            const headers = document.querySelectorAll('th');
            headers.forEach((header, index) => {
                header.innerHTML = header.innerHTML.replace(/ [↑↓]/g, '');
                if (index + 1 === activeColumn) {
                    header.innerHTML += direction === 'asc' ? ' ↑' : ' ↓';
                }
            });
        }

        async function showRetalhos(code) {
            try {
                const response = await fetch(`retalhos.php?code=${code}`);
                const data = await response.json();
                
                document.getElementById('modal-title').textContent = `Retalhos do Material ${code}`;
                document.getElementById('modal-body').innerHTML = `
                    <div style="margin-bottom: 15px;">
                        <strong>Total de retalhos encontrados: ${data.length}</strong>
                    </div>
                    <div class="table-wrapper">
                        <table>
                            <thead>
                                <tr>
                                    <th>Código</th>
                                    <th>Status</th>
                                    <th>Quantidade</th>
                                    <th>Altura</th>
                                    <th>Largura</th>
                                    <th>Descrição</th>
                                    <th>Reservado</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${data.length > 0 ? data.map(retalho => `
                                    <tr>
                                        <td>${retalho.codigo}</td>
                                        <td><span class="grain-badge ${retalho.ativo === 'X' ? 'grain-yes' : 'grain-no'}">${retalho.ativo === 'X' ? 'Ativo' : 'Inativo'}</span></td>
                                        <td>${retalho.quantidade}</td>
                                        <td>${retalho.altura}mm</td>
                                        <td>${retalho.largura}mm</td>
                                        <td>${retalho.descricao}</td>
                                        <td>${retalho.reservado || '➖'}</td>
                                    </tr>
                                `).join('') : '<tr><td colspan="7" class="no-results">Nenhum retalho encontrado</td></tr>'}
                            </tbody>
                        </table>
                    </div>
                `;
                
                document.getElementById('modal').style.display = 'flex';
            } catch (error) {
                console.error('Erro ao carregar retalhos:', error);
                alert('❌ Erro ao carregar retalhos. Verifique se o arquivo retalhos.php existe.');
            }
        }

        function showMaterialDetails(code, name, grain, espessura) {
            document.getElementById('modal-title').textContent = `Detalhes do Material ${code}`;
            document.getElementById('modal-body').innerHTML = `
                <div style="display: grid; gap: 15px;">
                    <div style="padding: 15px; background: var(--nexus-bg-tertiary); border-radius: 8px;">
                        <h3 style="color: var(--nexus-accent-blue); margin-bottom: 10px;">📝 Informações Básicas</h3>
                        <p><strong>🏷️ Código:</strong> <span class="material-code">${code}</span></p>
                        <p><strong>📄 Nome:</strong> ${name}</p>
                        <p><strong>🌾 Veio:</strong> <span class="grain-badge ${grain === 'Sim' ? 'grain-yes' : 'grain-no'}">${grain === 'Sim' ? '✅ Sim' : '❌ Não'}</span></p>
                        <p><strong>📏 Espessura:</strong> ${espessura ? espessura + 'mm' : '➖ Não informado'}</p>
                    </div>
                    <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                        <button class="btn btn-primary" onclick="openRetalhosPage('${code}')">
                            📦 Ver Todos os Retalhos
                        </button>
                        <button class="btn btn-secondary" onclick="searchImage('${name}')">
                            🖼️ Buscar Imagens
                        </button>
                        <button class="btn btn-tertiary" onclick="window.open('retalhos_filtro.php?material=${code}', '_blank')">
                            📏 Buscar por Tamanho
                        </button>
                    </div>
                </div>
            `;
            document.getElementById('modal').style.display = 'flex';
        }

        function closeModal() {
            document.getElementById('modal').style.display = 'none';
        }

        function openRetalhosPage(code) {
            window.location.href = 'retalhos_view.php?code=' + code;
        }

        function searchImage(materialName) {
            const query = encodeURIComponent(materialName + " MDF textura");
            const url = `https://www.google.com/search?tbm=isch&q=${query}`;
            window.open(url, '_blank');
        }

        // Fechar modal clicando fora dele
        document.getElementById('modal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeModal();
            }
        });

        // Atalhos de teclado
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeModal();
            }
            if (e.ctrlKey && e.key === 'f') {
                e.preventDefault();
                document.getElementById('searchInput').focus();
            }
        });

        // Inicialização
        document.addEventListener('DOMContentLoaded', function() {
            // Focar no campo de pesquisa
            document.getElementById('searchInput').focus();
            
            // Atualizar contador inicial
            updateResultsCount(<?= $total_materials ?>);
            
            // Animação das linhas da tabela
            const rows = document.querySelectorAll('#materialTable tr');
            rows.forEach((row, index) => {
                if (row.querySelector('td')) {
                    row.style.animationDelay = `${index * 0.05}s`;
                    row.style.animation = 'fadeInUp 0.6s ease-out forwards';
                }
            });
        });

        // Atualizar hora em tempo real
        function updateTime() {
            const now = new Date();
            const timeElement = document.querySelector('.datetime-info div:last-child');
            if (timeElement) {
                timeElement.textContent = `⏰ ${now.toLocaleTimeString('pt-BR')}`;
            }
        }

        setInterval(updateTime, 1000);
    </script>
</body>
</html>