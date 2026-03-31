<?php
$whatsapp_number = '+5551997756708'; // Substitua pelo seu número de WhatsApp

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$directory_materials = 'C:/CC_DATA_BASE/MAT';
$directory_retalhos = 'C:/CC_DATA_BASE/CHP';

// Função para ler espessura de um arquivo .INI
function getEspessuraFromIni($iniFilePath) {
    $iniData = parse_ini_file($iniFilePath, true); // Lê o arquivo .INI
    if (isset($iniData['PROP_FISIC']['ESPESSURA'])) {
        return floatval($iniData['PROP_FISIC']['ESPESSURA']);
    }
    return null; // Retorna null se não encontrar a espessura
}

// Processar materiais
function loadMaterials($directory_materials) {
    $files_materials = glob($directory_materials . '/*.INI');
    $materials = [];

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

        $materials[$code] = [
            'name' => $nome_material,
            'grain' => $veio,
            'espessura' => getEspessuraFromIni($file), // Adiciona espessura do arquivo INI
        ];
    }

    // Ordenar os materiais por nome
    uasort($materials, function($a, $b) {
        return strcmp($a['name'], $b['name']);
    });

    return $materials;
}

// Alteração na função loadRetalhos
function loadRetalhos($directory_retalhos, $materials, $material_code = null, $filters = [], $espessuras = [], $considerar_veio = false) {
    $files_retalhos = glob($directory_retalhos . '/*.TAB');
    $retalhos = [];

    foreach ($files_retalhos as $file) {
        $filename = basename($file, '.TAB');
        $code = intval(ltrim($filename, 'RET'));

        if ($material_code && $code != $material_code) {
            continue;
        }

        $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $lineIndex => $line) {
            $fields = explode(',', $line);
            if (count($fields) < 6) continue;

            $altura = floatval(trim($fields[3]));
            $largura = floatval(trim($fields[4]));

            // Obter espessura do material do arquivo INI
            $material_name = isset($materials[$code]) ? $materials[$code]['name'] : 'Desconhecido';
            $material_grain = isset($materials[$code]) ? $materials[$code]['grain'] : 'Não';
            $material_espessura = isset($materials[$code]) ? $materials[$code]['espessura'] : null;

            // Aplicar filtro de espessuras (comparar com a espessura exata)
            if ($espessuras && !in_array($material_espessura . "mm", $espessuras)) {
                continue;
            }

            // Se não respeitar o veio, ignorar qual é a altura ou largura na busca
            if (!$considerar_veio) {
                if (isset($filters['altura']) && isset($filters['largura'])) {
                    // Flexibilidade nas dimensões, permitindo trocar altura por largura
                    if (!(
                        ($altura >= $filters['altura'] && $largura >= $filters['largura']) || 
                        ($altura >= $filters['largura'] && $largura >= $filters['altura'])
                    )) {
                        continue;
                    }
                }
            } else {
                // Se respeitar o veio, usa a altura e largura como estão
                if (isset($filters['altura']) && $altura < $filters['altura']) {
                    continue;
                }
                if (isset($filters['largura']) && $largura < $filters['largura']) {
                    continue;
                }
            }

            // Gerar código único: usa o campo[0] se existir, senão cria um baseado no material e linha
            $codigo_retalho = trim($fields[0]);
            if (empty($codigo_retalho)) {
                $codigo_retalho = "RET{$code}-" . ($lineIndex + 1);
            }

            $retalhos[] = [
                'codigo' => $codigo_retalho,
                'codigo_material' => $code,
                'material' => $material_name,
                'ativo' => trim($fields[1]),
                'quantidade' => trim($fields[2]),
                'altura' => $altura,
                'largura' => $largura,
                'espessura' => $material_espessura, // Adiciona a espessura no retalho
                'descricao' => trim($fields[5]),
                'reservado' => isset($fields[6]) ? trim($fields[6]) : '',
            ];
        }
    }

    // Ordena os retalhos pela área (altura * largura)
    usort($retalhos, function($a, $b) {
        $areaA = $a['altura'] * $a['largura'];
        $areaB = $b['altura'] * $b['largura'];
        
        if ($areaA == $areaB) {
            return 0;
        }
        
        return ($areaA < $areaB) ? -1 : 1; // Ordem crescente
    });

    return $retalhos;
}

// Capturar os dados do formulário
$material_code = isset($_POST['material']) && $_POST['material'] !== 'Todos' ? $_POST['material'] : null;
$altura_minima = isset($_POST['altura']) ? floatval($_POST['altura']) : null;
$largura_minima = isset($_POST['largura']) ? floatval($_POST['largura']) : null;
$espessuras = isset($_POST['espessura']) ? $_POST['espessura'] : [];
$considerar_veio = isset($_POST['grain']) ? boolval($_POST['grain']) : false;

$materials = loadMaterials($directory_materials);
$filters = [
    'altura' => $altura_minima,
    'largura' => $largura_minima,
];

// Filtrar os retalhos
$retalhos = loadRetalhos($directory_retalhos, $materials, $material_code, $filters, $espessuras, $considerar_veio);
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nexus | Buscar Retalhos</title>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>▲</text></svg>">
    
    <style>
        /* Tema Escuro Nexus - Integrado */
        :root {
            --nexus-bg-primary: #0d1117;
            --nexus-bg-secondary: #161b22;
            --nexus-bg-tertiary: #21262d;
            --nexus-border: #30363d;
            --nexus-text-primary: #f0f6fc;
            --nexus-text-secondary: #8b949e;
            --nexus-accent-blue: #58a6ff;
            --nexus-accent-green: #3fb950;
            --nexus-accent-purple: #a5a5ff;
            --nexus-accent-orange: #ffa657;
            --nexus-hover-bg: #30363d;
            --nexus-focus-border: #1f6feb;
            --nexus-error: #f85149;
            --nexus-warning: #d29922;
            --nexus-shadow: rgba(0, 0, 0, 0.5);
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

        .header {
            background: linear-gradient(135deg, var(--nexus-bg-secondary), var(--nexus-bg-tertiary));
            padding: 20px 0;
            border-bottom: 2px solid var(--nexus-border);
            box-shadow: 0 4px 20px var(--nexus-shadow);
            position: sticky;
            top: 0;
            z-index: 100;
            backdrop-filter: blur(10px);
        }

        .header-content {
            max-width: 1400px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0 20px;
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .logo-icon {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, var(--nexus-accent-blue), var(--nexus-accent-purple));
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
        }

        .logo-text {
            font-size: 1.8rem;
            font-weight: 700;
            background: linear-gradient(135deg, var(--nexus-accent-blue), var(--nexus-accent-purple));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 8px 16px;
            background-color: var(--nexus-bg-tertiary);
            border-radius: 20px;
            border: 1px solid var(--nexus-border);
        }

        .user-avatar {
            width: 32px;
            height: 32px;
            background: linear-gradient(135deg, var(--nexus-accent-green), #2ea043);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 14px;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 30px 20px;
        }

        .page-title {
            text-align: center;
            margin-bottom: 40px;
            color: var(--nexus-text-primary);
            font-size: 2.5rem;
            font-weight: 700;
            background: linear-gradient(135deg, var(--nexus-accent-blue), var(--nexus-accent-purple));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            text-shadow: 0 0 20px rgba(88, 166, 255, 0.3);
        }

        .search-card {
            background-color: var(--nexus-bg-secondary);
            border-radius: 16px;
            padding: 30px;
            margin-bottom: 30px;
            border: 1px solid var(--nexus-border);
            box-shadow: 0 8px 32px var(--nexus-shadow);
            position: relative;
            overflow: hidden;
        }

        .search-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, var(--nexus-accent-blue), var(--nexus-accent-purple), var(--nexus-accent-green));
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 25px;
            margin-bottom: 30px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-group label {
            margin-bottom: 8px;
            color: var(--nexus-text-primary);
            font-weight: 600;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .form-group label::before {
            content: '▶';
            color: var(--nexus-accent-blue);
            font-size: 10px;
        }

        select, input[type="number"] {
            padding: 14px 16px;
            background-color: var(--nexus-bg-tertiary);
            border: 2px solid var(--nexus-border);
            border-radius: 10px;
            color: var(--nexus-text-primary);
            font-size: 14px;
            transition: all 0.3s ease;
            outline: none;
        }

        select:focus, input[type="number"]:focus {
            border-color: var(--nexus-focus-border);
            box-shadow: 0 0 0 3px rgba(31, 111, 235, 0.2);
            transform: translateY(-2px);
        }

        select:hover, input[type="number"]:hover {
            border-color: var(--nexus-accent-blue);
        }

        .checkbox-group {
            background-color: var(--nexus-bg-tertiary);
            padding: 20px;
            border-radius: 10px;
            border: 1px solid var(--nexus-border);
        }

        .checkbox-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(80px, 1fr));
            gap: 15px;
            margin-top: 10px;
        }

        .checkbox-item {
            display: flex;
            align-items: center;
            cursor: pointer;
            padding: 8px;
            border-radius: 6px;
            transition: all 0.2s ease;
        }

        .checkbox-item:hover {
            background-color: var(--nexus-hover-bg);
        }

        input[type="checkbox"] {
            appearance: none;
            width: 18px;
            height: 18px;
            border: 2px solid var(--nexus-border);
            border-radius: 4px;
            background-color: var(--nexus-bg-primary);
            margin-right: 10px;
            position: relative;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        input[type="checkbox"]:checked {
            background-color: var(--nexus-accent-blue);
            border-color: var(--nexus-accent-blue);
        }

        input[type="checkbox"]:checked::before {
            content: '✓';
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            color: white;
            font-weight: bold;
            font-size: 12px;
        }

        input[type="checkbox"]:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .action-buttons {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            justify-content: center;
            margin-top: 30px;
        }

        .btn {
            padding: 14px 28px;
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
            position: relative;
            overflow: hidden;
        }

        .btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: left 0.5s;
        }

        .btn:hover::before {
            left: 100%;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--nexus-accent-blue), var(--nexus-accent-purple));
            color: white;
            box-shadow: 0 4px 15px rgba(88, 166, 255, 0.3);
        }

        .btn-primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(88, 166, 255, 0.4);
        }

        .btn-secondary {
            background: linear-gradient(135deg, var(--nexus-accent-orange), var(--nexus-warning));
            color: white;
            box-shadow: 0 4px 15px rgba(255, 166, 87, 0.3);
        }

        .btn-secondary:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(255, 166, 87, 0.4);
        }

        .btn-tertiary {
            background: linear-gradient(135deg, var(--nexus-text-secondary), var(--nexus-border));
            color: white;
            box-shadow: 0 4px 15px rgba(139, 148, 158, 0.2);
        }

        .btn-tertiary:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(139, 148, 158, 0.3);
        }

        .results-section {
            background-color: var(--nexus-bg-secondary);
            border-radius: 16px;
            overflow: hidden;
            border: 1px solid var(--nexus-border);
            box-shadow: 0 8px 32px var(--nexus-shadow);
        }

        .results-header {
            background: linear-gradient(135deg, var(--nexus-bg-primary), var(--nexus-bg-secondary));
            padding: 20px 30px;
            border-bottom: 2px solid var(--nexus-border);
        }

        .results-title {
            font-size: 1.3rem;
            font-weight: 600;
            color: var(--nexus-text-primary);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .results-count {
            background-color: var(--nexus-accent-blue);
            color: white;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
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

        .no-results {
            text-align: center;
            color: var(--nexus-text-secondary);
            font-style: italic;
            padding: 60px 20px;
            background: linear-gradient(135deg, var(--nexus-bg-primary), var(--nexus-bg-secondary));
        }

        .request-form {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .request-form input {
            width: 70px;
            padding: 6px 8px;
            font-size: 12px;
            border-radius: 6px;
        }

        .request-form .btn {
            padding: 8px 12px;
            font-size: 11px;
            background: linear-gradient(135deg, var(--nexus-accent-green), #2ea043);
            box-shadow: 0 2px 8px rgba(63, 185, 80, 0.3);
        }

        .request-form .btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(63, 185, 80, 0.4);
        }

        .status-badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: bold;
            text-transform: uppercase;
        }

        .status-active {
            background-color: var(--nexus-accent-green);
            color: white;
        }

        .status-inactive {
            background-color: var(--nexus-error);
            color: white;
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

        @keyframes pulse {
            0%, 100% {
                transform: scale(1);
                box-shadow: 0 0 0 0 rgba(248, 81, 73, 0.7);
            }
            50% {
                transform: scale(1.05);
                box-shadow: 0 0 0 10px rgba(248, 81, 73, 0);
            }
        }

        .container > * {
            animation: fadeInUp 0.6s ease-out;
        }

        /* Estilo do link WhatsApp Config */
        .whatsapp-config-link {
            text-decoration: none;
            color: inherit;
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 8px 12px;
            background-color: var(--nexus-bg-secondary);
            border-radius: 8px;
            transition: all 0.3s;
            border: 1px solid var(--nexus-border);
        }

        .whatsapp-config-link:hover {
            background: linear-gradient(135deg, #25D366, #128C7E);
            border-color: #25D366;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(37, 211, 102, 0.3);
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

        /* Banner de Status WhatsApp */
        .whatsapp-status-banner {
            background: linear-gradient(135deg, #f85149, #da3633);
            color: white;
            padding: 15px 20px;
            text-align: center;
            font-weight: 600;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 15px;
            box-shadow: 0 4px 15px rgba(248, 81, 73, 0.4);
            animation: slideDown 0.5s ease-out;
        }

        .whatsapp-status-banner a {
            color: white;
            text-decoration: underline;
            font-weight: bold;
            margin-left: 5px;
        }

        .whatsapp-status-banner a:hover {
            text-decoration: none;
        }

        @keyframes slideDown {
            from {
                transform: translateY(-100%);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        /* Modal de Solicitação */
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
            max-width: 500px;
            width: 90%;
            border: 1px solid var(--nexus-border);
            box-shadow: 0 20px 60px var(--nexus-shadow);
            animation: fadeInUp 0.3s ease-out;
        }

        .modal-header {
            text-align: center;
            margin-bottom: 25px;
        }

        .modal-title {
            font-size: 1.5rem;
            font-weight: 600;
            background: linear-gradient(135deg, var(--nexus-accent-blue), var(--nexus-accent-purple));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 10px;
        }

        .modal-form {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .form-label {
            font-weight: 600;
            color: var(--nexus-text-primary);
        }

        .form-input {
            padding: 12px 16px;
            background-color: var(--nexus-bg-tertiary);
            border: 2px solid var(--nexus-border);
            border-radius: 8px;
            color: var(--nexus-text-primary);
            font-size: 16px;
            outline: none;
            transition: border-color 0.3s ease;
        }

        .form-input:focus {
            border-color: var(--nexus-focus-border);
            box-shadow: 0 0 0 3px rgba(31, 111, 235, 0.2);
        }

        .form-select {
            padding: 12px 16px;
            background-color: var(--nexus-bg-tertiary);
            border: 2px solid var(--nexus-border);
            border-radius: 8px;
            color: var(--nexus-text-primary);
            font-size: 16px;
            outline: none;
            transition: border-color 0.3s ease;
            cursor: pointer;
        }

        .form-select:focus {
            border-color: var(--nexus-focus-border);
            box-shadow: 0 0 0 3px rgba(31, 111, 235, 0.2);
        }

        .modal-buttons {
            display: flex;
            gap: 15px;
            justify-content: center;
            margin-top: 25px;
        }

        /* Notificações Flutuantes */
        .notificacao-flutuante {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 20px 30px;
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
            color: white;
            font-weight: 600;
            font-size: 15px;
            z-index: 10000;
            opacity: 0;
            transform: translateX(400px);
            transition: all 0.3s cubic-bezier(0.68, -0.55, 0.265, 1.55);
            max-width: 400px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .notificacao-flutuante.show {
            opacity: 1;
            transform: translateX(0);
        }

        .notificacao-success {
            background: linear-gradient(135deg, var(--nexus-accent-green), #2ea043);
            border-left: 5px solid #1a7f37;
        }

        .notificacao-error {
            background: linear-gradient(135deg, var(--nexus-error), #da3633);
            border-left: 5px solid #a40e26;
        }

        .notificacao-info {
            background: linear-gradient(135deg, var(--nexus-accent-blue), #1f6feb);
            border-left: 5px solid #0969da;
        }

        /* Responsivo */
        @media (max-width: 768px) {
            .header-content {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }
            
            .container {
                padding: 20px 10px;
            }
            
            .page-title {
                font-size: 2rem;
            }
            
            .search-card {
                padding: 20px;
            }
            
            .form-grid {
                grid-template-columns: 1fr;
                gap: 20px;
            }
            
            .action-buttons {
                flex-direction: column;
            }
            
            .btn {
                width: 100%;
                justify-content: center;
            }
            
            th, td {
                padding: 8px 6px;
                font-size: 12px;
            }
            
            .request-form {
                flex-direction: column;
                gap: 4px;
            }
            
            .request-form input {
                width: 50px;
            }

            .notificacao-flutuante {
                top: 10px;
                right: 10px;
                left: 10px;
                max-width: none;
                font-size: 13px;
                padding: 15px 20px;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header class="header">
        <div class="header-content">
            <div class="logo">
                <div class="logo-icon">▲</div>
                <div class="logo-text">NEXUS</div>
            </div>
            <div class="user-info">
                <a href="config_whatsapp_retalhos.php" class="whatsapp-config-link" title="Configurar conexão WhatsApp">
                    <span style="font-size: 1.2em;">📱</span>
                    <span>WhatsApp Config</span>
                </a>
            </div>
           </div>
    </header>

    <!-- Banner de Status WhatsApp -->
    <div id="whatsapp-status-banner" style="display: none;"></div>

    <div class="container">
        <h1 class="page-title">Sistema de Busca de Retalhos</h1>

        <!-- Formulário de Busca -->
        <div class="search-card">
            <form method="POST">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="material">Material</label>
                        <select name="material" id="material-select" onchange="toggleEspessuras()">
                            <option value="Todos">🔍 Todos os Materiais</option>
                            <?php foreach ($materials as $code => $material): ?>
                                <option value="<?= htmlspecialchars($code) ?>" <?= isset($_POST['material']) && $_POST['material'] == $code ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($material['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Espessuras Disponíveis</label>
                        <div class="checkbox-group">
                            <div class="checkbox-grid">
                                <?php foreach (["3mm", "6mm", "9mm", "15mm", "18mm"] as $esp): ?>
                                    <div class="checkbox-item">
                                        <input type="checkbox" name="espessura[]" id="espessura-<?= $esp ?>" value="<?= $esp ?>" <?= isset($_POST['espessura']) && in_array($esp, $_POST['espessura']) ? 'checked' : '' ?>>
                                        <label for="espessura-<?= $esp ?>"><?= $esp ?></label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="altura">📏 Altura Mínima (mm)</label>
                        <input type="number" name="altura" id="altura" placeholder="Ex: 300" value="<?= isset($_POST['altura']) ? htmlspecialchars($_POST['altura']) : '' ?>">
                    </div>

                    <div class="form-group">
                        <label for="largura">📐 Largura Mínima (mm)</label>
                        <input type="number" name="largura" id="largura" placeholder="Ex: 200" value="<?= isset($_POST['largura']) ? htmlspecialchars($_POST['largura']) : '' ?>">
                    </div>
                </div>

                <div class="form-group">
                    <div class="checkbox-item">
                        <input type="checkbox" name="grain" id="grain" value="Sim" <?= isset($_POST['grain']) && $_POST['grain'] == 'Sim' ? 'checked' : '' ?>>
                        <label for="grain">🌾 Respeitar direção do veio da madeira</label>
                    </div>
                </div>

                <div class="action-buttons">
                    <button type="button" class="btn btn-secondary" onclick="trocarValores()">
                        🔄 Trocar Altura ↔ Largura
                    </button>
                    <button type="submit" class="btn btn-primary">
                        🔍 Pesquisar Retalhos
                    </button>
                    <a href="http://192.168.0.201/materiais_view.php" class="btn btn-tertiary">
                        ← Voltar ao Menu
                    </a>
                </div>
            </form>
        </div>

        <!-- Resultados -->
        <div class="results-section">
            <div class="results-header">
                <div class="results-title">
                    📋 Resultados da Busca
                    <span class="results-count"><?= count($retalhos) ?> encontrado<?= count($retalhos) != 1 ? 's' : '' ?></span>
                </div>
            </div>
            
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>🔢 Cód. Mat.</th>
                            <th>🏗️ Material</th>
                            <th>🏷️ Cód. Retalho</th>
                            <th>📊 Status</th>
                            <th>📦 Qtd</th>
                            <th>📏 Altura</th>
                            <th>📐 Largura</th>
                            <th>📝 Descrição</th>
                            <th>🔒 Reservado</th>
                            <th>📲 Solicitar</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($retalhos)): ?>
                            <?php foreach ($retalhos as $index => $retalho): ?>
                                <tr>
                                    <td><strong>M<?= htmlspecialchars($retalho['codigo_material']) ?></strong></td>
                                    <td><?= htmlspecialchars($retalho['material']) ?></td>
                                    <td><?= htmlspecialchars($retalho['codigo']) ?: '➖' ?></td>
                                    <td>
                                        <span class="status-badge <?= $retalho['ativo'] == 'S' ? 'status-active' : 'status-inactive' ?>">
                                            <?= $retalho['ativo'] == 'S' ? 'Ativo' : 'Inativo' ?>
                                        </span>
                                    </td>
                                    <td><strong><?= htmlspecialchars($retalho['quantidade']) ?></strong></td>
                                    <td><?= htmlspecialchars($retalho['altura']) ?>mm</td>
                                    <td><?= htmlspecialchars($retalho['largura']) ?>mm</td>
                                    <td><?= htmlspecialchars($retalho['descricao']) ?></td>
                                    <td><?= htmlspecialchars($retalho['reservado']) ?: '➖' ?></td>
                                    <td>
                                        <button class="btn btn-secondary" style="padding: 8px 16px; font-size: 12px;" 
                                                onclick='openSolicitarModal(<?= json_encode($retalho) ?>, <?= $index ?>)'>
                                            📲 Solicitar
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="10" class="no-results">
                                    🔍 Nenhum retalho encontrado com os filtros aplicados.<br>
                                    <small>Tente ajustar os critérios de busca.</small>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Modal de Solicitação -->
    <div id="solicitarModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <div class="modal-title">📲 Solicitar Retalho</div>
                <div style="color: var(--nexus-text-secondary); font-size: 0.9rem;">
                    Informe os dados da solicitação
                </div>
            </div>
            <form class="modal-form" id="solicitarForm">
                <div id="retalhoInfo"></div>
                
                <div class="form-group">
                    <label class="form-label" for="nome">👤 Seu Nome:</label>
                    <input type="text" id="nome" name="nome" class="form-input" placeholder="Digite seu nome" required>
                </div>

                <div class="form-group">
                    <label class="form-label" for="destinatario">📱 Enviar para:</label>
                    <select id="destinatario" name="destinatario" class="form-select" required>
                        <option value="1" selected>1️⃣ Ademir</option>
                        <option value="2">2️⃣ Pedro</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label" for="quantidade">📦 Quantidade:</label>
                    <input type="number" id="quantidade" name="quantidade" class="form-input" min="1" required>
                </div>

                <div class="modal-buttons">
                    <button type="button" class="btn btn-tertiary" onclick="closeSolicitarModal()">
                        ❌ Cancelar
                    </button>
                    <button type="submit" class="btn btn-primary">
                        ✅ Confirmar Solicitação
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function trocarValores() {
            const altura = document.getElementById('altura');
            const largura = document.getElementById('largura');
            
            // Adicionar efeito visual
            altura.style.transform = 'scale(1.1)';
            largura.style.transform = 'scale(1.1)';
            
            setTimeout(() => {
                const temp = altura.value;
                altura.value = largura.value;
                largura.value = temp;
                
                altura.style.transform = 'scale(1)';
                largura.style.transform = 'scale(1)';
            }, 150);
        }

        function toggleEspessuras() {
            const materialSelect = document.getElementById('material-select');
            const isTodos = materialSelect.value === "Todos";
            const espessuraCheckboxes = document.querySelectorAll('input[name="espessura[]"]');
            
            espessuraCheckboxes.forEach(checkbox => {
                checkbox.disabled = !isTodos;
                if (!isTodos) {
                    checkbox.checked = false;
                }
            });
        }

        // Variável global para armazenar dados do retalho atual
        let currentRetalho = null;
        let currentRetalhoIndex = null;

        function openSolicitarModal(retalho, index) {
            currentRetalho = retalho;
            currentRetalhoIndex = index;

            // Preencher informações do retalho
            document.getElementById('retalhoInfo').innerHTML = `
                <div style="background: var(--nexus-bg-tertiary); padding: 15px; border-radius: 8px; margin-bottom: 15px;">
                    <h4 style="color: var(--nexus-accent-blue); margin-bottom: 10px;">📋 Detalhes do Retalho</h4>
                    <p><strong>🔢 Cód. Material:</strong> M${retalho.codigo_material}</p>
                    <p><strong>🏗️ Material:</strong> ${retalho.material}</p>
                    <p><strong>🏷️ Cód. Retalho:</strong> ${retalho.codigo || '➖'}</p>
                    <p><strong>📏 Dimensões:</strong> ${retalho.altura} x ${retalho.largura} mm</p>
                    <p><strong>📝 Descrição:</strong> ${retalho.descricao}</p>
                    <p><strong>📦 Disponível:</strong> <span style="color: var(--nexus-accent-green);">${retalho.quantidade} peça(s)</span></p>
                </div>
            `;

            // Configurar campo de quantidade
            const quantidadeInput = document.getElementById('quantidade');
            quantidadeInput.max = retalho.quantidade;
            quantidadeInput.value = 1;

            // Limpar nome
            document.getElementById('nome').value = '';

            // Resetar destinatário
            document.getElementById('destinatario').value = '1';

            // Mostrar modal
            document.getElementById('solicitarModal').style.display = 'flex';
            
            // Focar no campo nome
            setTimeout(() => document.getElementById('nome').focus(), 100);
        }

        function closeSolicitarModal() {
            document.getElementById('solicitarModal').style.display = 'none';
            currentRetalho = null;
            currentRetalhoIndex = null;
        }

        // Processar envio do formulário
        document.getElementById('solicitarForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            if (!currentRetalho) {
                alert('⚠️ Erro: Nenhum retalho selecionado.');
                return;
            }

            const nome = document.getElementById('nome').value.trim();
            const destinatario = document.getElementById('destinatario').value;
            const quantidade = parseInt(document.getElementById('quantidade').value);

            if (quantidade > currentRetalho.quantidade) {
                alert(`⚠️ A quantidade máxima disponível é ${currentRetalho.quantidade} unidades.`);
                return;
            }

            // Desabilitar botão
            const submitBtn = e.target.querySelector('button[type="submit"]');
            const btnOriginalText = submitBtn.innerHTML;
            submitBtn.disabled = true;
            submitBtn.innerHTML = '⏳ Enviando...';

            // Enviar via API WhatsApp
            const formData = new FormData();
            formData.append('codigo', currentRetalho.codigo || '');
            formData.append('codigo_material', currentRetalho.codigo_material || '');
            formData.append('material', currentRetalho.material || '');
            formData.append('quantidade', quantidade || '1');
            formData.append('altura', currentRetalho.altura || '0');
            formData.append('largura', currentRetalho.largura || '0');
            formData.append('descricao', currentRetalho.descricao || '');
            formData.append('espessura', currentRetalho.espessura || '');
            formData.append('nome', nome);
            formData.append('destinatario', destinatario);

            // Debug
            console.log('Dados enviados:', {
                codigo: currentRetalho.codigo,
                codigo_material: currentRetalho.codigo_material,
                material: currentRetalho.material,
                quantidade: quantidade,
                nome: nome,
                destinatario: destinatario === '2' ? 'Pedro' : 'Ademir'
            });

            fetch('enviar_whatsapp_retalho.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                submitBtn.disabled = false;
                submitBtn.innerHTML = btnOriginalText;
                
                if (data.success) {
                    // Exibir notificação de sucesso
                    const msgSucesso = data.message || '✅ Solicitação enviada com sucesso!';
                    mostrarNotificacao(msgSucesso, 'success');
                    
                    // Fechar modal
                    closeSolicitarModal();
                    
                    // Tocar som de sucesso
                    playSound('success');
                } else {
                    // Tratar diferentes tipos de erro
                    let mensagemErro = data.message || data.error || 'Erro desconhecido';
                    
                    // Se for erro de sessão WhatsApp desconectada
                    if (data.details && data.details.http_code === 404) {
                        mostrarNotificacao('⚠️ WhatsApp Desconectado! Clique em "📱 WhatsApp Config" no topo para conectar.', 'error');
                        
                        // Destacar botão de configuração
                        const configBtn = document.querySelector('a[href="config_whatsapp_retalhos.php"]');
                        if (configBtn) {
                            configBtn.style.animation = 'pulse 1s ease-in-out 3';
                            configBtn.style.background = 'linear-gradient(135deg, #f85149, #da3633)';
                        }
                    } else if (data.details) {
                        console.log('Validação dos campos:', data.details);
                        mensagemErro += ' (verifique o console)';
                        mostrarNotificacao('❌ Erro ao enviar: ' + mensagemErro, 'error');
                    } else {
                        mostrarNotificacao('❌ Erro ao enviar: ' + mensagemErro, 'error');
                    }
                    
                    console.error('Erro detalhado:', data);
                }
            })
            .catch(error => {
                submitBtn.disabled = false;
                submitBtn.innerHTML = btnOriginalText;
                mostrarNotificacao('❌ Erro de conexão: ' + error.message, 'error');
                console.error('Erro:', error);
            });

            return false;
        });

        // Fechar modal clicando fora
        document.getElementById('solicitarModal').addEventListener('click', function(e) {
            if (e.target.id === 'solicitarModal') {
                closeSolicitarModal();
            }
        });

        // Fechar modal com ESC
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && document.getElementById('solicitarModal').style.display === 'flex') {
                closeSolicitarModal();
            }
        });

        // Função para exibir notificações
        function mostrarNotificacao(mensagem, tipo = 'info') {
            // Remover notificação anterior se existir
            const notifAnterior = document.querySelector('.notificacao-flutuante');
            if (notifAnterior) {
                notifAnterior.remove();
            }

            // Criar elemento de notificação
            const notificacao = document.createElement('div');
            notificacao.className = 'notificacao-flutuante notificacao-' + tipo;
            notificacao.innerHTML = mensagem;
            
            document.body.appendChild(notificacao);

            // Animar entrada
            setTimeout(() => {
                notificacao.classList.add('show');
            }, 100);

            // Remover após 5 segundos
            setTimeout(() => {
                notificacao.classList.remove('show');
                setTimeout(() => {
                    notificacao.remove();
                }, 300);
            }, 5000);
        }

        // Executar ao carregar a página
        document.addEventListener("DOMContentLoaded", function() {
            toggleEspessuras();
            
            // Adicionar animação aos elementos da tabela
            const rows = document.querySelectorAll('tbody tr');
            rows.forEach((row, index) => {
                row.style.animationDelay = `${index * 0.05}s`;
                row.style.animation = 'fadeInUp 0.6s ease-out forwards';
            });

            // Verificar status do WhatsApp
            verificarStatusWhatsApp();
        });

        // Função para verificar status do WhatsApp
        function verificarStatusWhatsApp() {
            fetch('verificar_whatsapp_status.php')
                .then(response => response.json())
                .then(data => {
                    if (!data.connected) {
                        // Exibir banner de aviso
                        const banner = document.getElementById('whatsapp-status-banner');
                        banner.className = 'whatsapp-status-banner';
                        banner.innerHTML = `
                            ⚠️ <strong>WhatsApp Desconectado!</strong>
                            Para enviar solicitações, você precisa conectar o WhatsApp.
                            <a href="config_whatsapp_retalhos.php">Clique aqui para conectar</a>
                        `;
                        banner.style.display = 'flex';
                    }
                })
                .catch(error => {
                    console.warn('Não foi possível verificar o status do WhatsApp:', error);
                });
        }

        // Adicionar efeitos sonoros (opcional)
        function playSound(type) {
            const audio = new Audio();
            switch(type) {
                case 'click':
                    audio.src = 'data:audio/wav;base64,UklGRnoGAABXQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YQoGAACBhYqFbF1fdJivrJBhNjVgodDbq2EcBj+a2/LDciUFLIHO8tiJNwgZaLvt559NEAxQp+PwtmMcBjiR1/LMeSwFJHfH8N2QQAoUXrTp66hVFApGn+PwtmMcBjiR1/LNeSsFJHfH8N2QQAoUXrTp66hVFApGn+PxtmQcBjiR1/LNeSsFJHfH8N2QQAoUXrTp66hVFApGn+PxtmQcBjiR1/LNeSsFJHfH8N2QQAoUXrTp66hVFApGn+PxtmQcBjiR1/LNeSsFJHfH8N2QQAoUXrTp66hVFApGn+PxtmQcBjiR1/LNeSsFJHfH8N2QQAoUXrTp66hVFApGn+PxtmQcBjiR1/LNeSsFJHfH8N2QQAoUXrTp66hVFApGn+PxtmQcBjiR1/LNeSsFJHfH8N2QQAoUXrTp66hVFApGn+PxtmQcBjiR1/LNeSsFJHfH8N2QQAoUXrTp66hVFApGn+PxtmQcBjiR1/LNeSsFJHfH8N2QQAoUXrTp66hVFApGn+PxtmQcBjiR1/LNeSsFJHfH8N2QQAoUXrTp66hVFApGn+PxtmQcBjiR1/LNeSsFJHfH8N2QQAoUXrTp66hVFApGn+PxtmQcBjiR1/LNeSsFJHfH8N2QQAoUXrTp66hVFApGn+PxtmQcBjiR1/LNeSsFJHfH8N2QQAoUXrTp66hVFApGn+PxtmQcBjiR1/LNeSsFJHfH8N2QQAoUXrTp66hVFApGn+PxtmQcBjiR1/LNeSsFJHfH8N2QQAoUXrTp66hVFApGn+PxtmQcBjiR1/LNeSsFJHfH8N2QQAoUXrTp66hVFApGn+PxtmQcBjiR1/LNeSsFJHfH8N2QQAoUXrTp66hVFApGn+PxtmQcBjiR1/LNeSsFJHfH8N2QQAoUXrTp66hVFApGn+PxtmQcBjiR1/LNeSsFJHfH8N2QQAoUXrTp66hVFApGn+PxtmQcBjiR1/LNeSsFJHfH8N2QQAoUXrTp66hVFApGn+PxtmQcBjiR1/LNeSsFJHfH8N2QQAoUXrTp66hVFApGn+PxtmQcBjiR1/LNeSsFJHfH8N2QQAoUXrTp66hVFApGn+PxtmQcBjiR1/LNeSsFJHfH8N2QQAoUXrTp66hVFApGn+PxtmQcBjiR1/LNeSsFJHfH8N2QQAoUXrTp66hVFApGn+PxtmQcBjiR1/LNeSsFJHfH8N2QQAoUXrTp66hVFApGn+PxtmQcBjiR1/LNeSsFJHfH8N2QQAoUXrTp66hVFApGn+PxtmQcBjiR1/LNeSsFJHfH8N2QQAoUXrTp66hVFApGn+PxtmQcBjiR1/LNeSsFJHfH8N2QQAoUXrTp66hVFApGn+PxtmQcBjiR1/LNeSsFJHfH8N2QQAoUXrTp66hVFApGn+PxtmQcBjiR1/LNeSsFJHfH8N2QQAoUXrTp66hVFApGn+PxtmQcBjiR1/LNeSsFJHfH8N2QQAoUXrTp66hVFApGn+PxtmQcBjiR1/LNeSsFJHfH8N2QQAoUXrTp66hVFApGn+PxtmQcBjiR1/LNeSsFJHfH8N2QQAoUXrTp66hVFApGn+PxtmQcBjiR1/LNeSsFJHfH8N2QQAoUXrTp66hVFApGn+PxtmQcBjiR1/LNeSsFJHfH8N2QQAoUXrTp66hVFApGn+PxtmQcBjiR1/LNeSsFJHfH8N2QQAoUXrTp66hVFApGn+PxtmQcBjiR1/LNeSsFJHfH8N2QQAoUXrTp66hVFApGn+PxtmQcBjiR1/LNeSsFJHfH8N2QQAoUXrTp66hVFA==';
                    break;
                case 'success':
                    audio.src = 'data:audio/wav;base64,UklGRnoGAABXQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YQoGAACBhYqFbF1fdJivrJBhNjVgodDbq2EcBj+a2/LDciUFLIHO8tiJNwgZaLvt559NEAxQp+PwtmMcBjiR1/LMeSwFJHfH8N2QQAoUXrTp66hVFApGn+PwtmMcBjiR1/LNeSsFJHfH8N2QQAoUXrTp66hVFApGn+PxtmQcBjiR1/LNeSsFJHfH8N2QQAoUXrTp66hVFApGn+PxtmQcBjiR1/LNeSsFJHfH8N2QQAoUXrTp66hVFApGn+PxtmQcBjiR1/LNeSsFJHfH8N2QQAoUXrTp66hVFApGn+PxtmQcBjiR1/LNeSsFJHfH8N2QQAoUXrTp66hVFApGn+PxtmQcBjiR1/LNeSsFJHfH8N2QQAoUXrTp66hVFApGn+PxtmQcBjiR1/LNeSsFJHfH8N2QQAoUXrTp66hVFApGn+PxtmQcBjiR1/LNeSsFJHfH8N2QQAoUXrTp66hVFApGn+PxtmQcBjiR1/LNeSsFJHfH8N2QQAoUXrTp66hVFApGn+PxtmQcBjiR1/LNeSsFJHfH8N2QQAoUXrTp66hVFApGn+PxtmQcBjiR1/LNeSsFJHfH8N2QQAoUXrTp66hVFApGn+PxtmQcBjiR1/LNeSsFJHfH8N2QQAoUXrTp66hVFApGn+PxtmQcBjiR1/LNeSsFJHfH8N2QQAoUXrTp66hVFApGn+PxtmQcBjiR1/LNeSsFJHfH8N2QQAoUXrTp66hVFApGn+PxtmQcBjiR1/LNeSsFJHfH8N2QQAoUXrTp66hVFApGn+PxtmQcBjiR1/LNeSsFJHfH8N2QQAoUXrTp66hVFApGn+PxtmQcBjiR1/LNeSsFJHfH8N2QQAoUXrTp66hVFApGn+PxtmQcBjiR1/LNeSsFJHfH8N2QQAoUXrTp66hVFApGn+PxtmQcBjiR1/LNeSsFJHfH8N2QQAoUXrTp66hVFApGn+PxtmQcBjiR1/LNeSsFJHfH8N2QQAoUXrTp66hVFApGn+PxtmQcBjiR1/LNeSsFJHfH8N2QQAoUXrTp66hVFApGn+PxtmQcBjiR1/LNeSsFJHfH8N2QQAoUXrTp66hVFApGn+PxtmQcBjiR1/LNeSsFJHfH8N2QQAoUXrTp66hVFApGn+PxtmQcBjiR1/LNeSsFJHfH8N2QQAoUXrTp66hVFApGn+PxtmQcBjiR1/LNeSsFJHfH8N2QQAoUXrTp66hVFApGn+PxtmQcBjiR1/LNeSsFJHfH8N2QQAoUXrTp66hVFApGn+PxtmQcBjiR1/LNeSsFJHfH8N2QQAoUXrTp66hVFApGn+PxtmQcBjiR1/LNeSsFJHfH8N2QQAoUXrTp66hVFApGn+PxtmQcBjiR1/LNeSsFJHfH8N2QQAoUXrTp66hVFApGn+PxtmQcBjiR1/LNeSsFJHfH8N2QQAoUXrTp66hVFApGn+PxtmQcBjiR1/LNeSsFJHfH8N2QQAoUXrTp66hVFApGn+PxtmQcBjiR1/LNeSsFJHfH8N2QQAoUXrTp66hVFApGn+PxtmQcBjiR1/LNeSsFJHfH8N2QQAoUXrTp66hVFApGn+PxtmQcBjiR1/LNeSsFJHfH8N2QQAoUXrTp66hVFApGn+PxtmQcBjiR1/LNeSsFJHfH8N2QQAoUXrTp66hVFApGn+PxtmQcBjiR1/LNeSsFJHfH8N2QQAoUXrTp66hVFApGn+PxtmQcBjiR1/LNeSsFJHfH8N2QQAoUXrTp66hVFApGn+PxtmQcBjiR1/LNeSsFJHfH8N2QQAoUXrTp66hVFA==';
                    break;
            }
            audio.play().catch(() => {}); // Ignorar erros de áudio
        }

        // Adicionar sons aos botões
        document.querySelectorAll('.btn').forEach(btn => {
            btn.addEventListener('click', () => playSound('click'));
        });
    </script>
</body>
</html>