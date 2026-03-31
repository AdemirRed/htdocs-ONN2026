<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$directory_materials = 'C:/CC_DATA_BASE/MAT';
$directory_retalhos = 'C:/CC_DATA_BASE/CHP';

$code = isset($_GET['code']) ? intval($_GET['code']) : 0;
if ($code <= 0) {
    die('Código inválido.');
}

// Função para buscar informações do material
function getMaterialInfo($directory, $code) {
    $files = glob($directory . '/*.INI');
    foreach ($files as $file) {
        $filename = basename($file, '.INI');
        $material_code = ltrim($filename, 'M');
        if (intval($material_code) === $code) {
            $data = @parse_ini_file($file, true);
            if ($data !== false) {
                $name = isset($data['DESC']['CAMPO1']) ? str_replace(['(', ')'], '', $data['DESC']['CAMPO1']) : 'Material não encontrado';
                $espessura = isset($data['PROP_FISIC']['ESPESSURA']) ? floatval($data['PROP_FISIC']['ESPESSURA']) : null;
                $veio_vertical = isset($data['PROP_FISIC']['VEIO_VERTICAL']) ? $data['PROP_FISIC']['VEIO_VERTICAL'] : null;
                $giro = isset($data['PROP_FISIC']['GIRO']) ? $data['PROP_FISIC']['GIRO'] : null;
                $veio = (is_numeric($veio_vertical) && is_numeric($giro) && $veio_vertical == 1 && $giro == 0) ? 'Sim' : 'Não';
                
                return [
                    'name' => $name,
                    'espessura' => $espessura,
                    'veio' => $veio
                ];
            }
        }
    }
    return [
        'name' => 'Material não encontrado',
        'espessura' => null,
        'veio' => 'Não'
    ];
}

$material_info = getMaterialInfo($directory_materials, $code);

$file_path_retalhos = $directory_retalhos . "/RET" . str_pad($code, 5, '0', STR_PAD_LEFT) . ".TAB";
if (!file_exists($file_path_retalhos)) {
    die('Arquivo de retalhos não encontrado.');
}

$retalhos = [];
$total_pecas = 0;
$total_reservado = 0;
$area_total = 0;

$lines = file($file_path_retalhos, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
foreach ($lines as $line) {
    $fields = explode(',', $line);
    if (count($fields) < 6) continue;

    $quantidade = (int)trim($fields[2]);
    $reservado = isset($fields[6]) ? (int)trim($fields[6]) : 0;
    $altura = floatval(trim($fields[3]));
    $largura = floatval(trim($fields[4]));
    $area = ($altura * $largura) / 1000000; // Converter para m²

    $retalhos[] = [
        'codigo' => trim($fields[0]),
        'ativo' => trim($fields[1]),
        'quantidade' => $quantidade,
        'altura' => $altura,
        'largura' => $largura,
        'descricao' => trim($fields[5]),
        'reservado' => $reservado,
        'area' => $area,
        'disponivel' => $quantidade - $reservado
    ];

    $total_pecas += $quantidade;
    $total_reservado += $reservado;
    $area_total += $area * $quantidade;
}

// Ordenar por área (maior para menor)
usort($retalhos, function($a, $b) {
    return $b['area'] <=> $a['area'];
});

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $codigo = intval($_POST['codigo']);
    $quantidadeSolicitada = intval($_POST['quantidade']);

    foreach ($retalhos as &$retalho) {
        if ($retalho['codigo'] == $codigo) {
            if ($retalho['disponivel'] >= $quantidadeSolicitada) {
                $retalho['reservado'] += $quantidadeSolicitada;
                $retalho['disponivel'] -= $quantidadeSolicitada;
            } else {
                die('Quantidade solicitada excede o disponível.');
            }
            break;
        }
    }

    // Atualizar arquivo
    $updated_lines = [];
    foreach ($retalhos as $retalho) {
        $updated_lines[] = implode(',', [
            $retalho['codigo'],
            $retalho['ativo'],
            $retalho['quantidade'],
            $retalho['altura'],
            $retalho['largura'],
            $retalho['descricao'],
            $retalho['reservado']
        ]);
    }

    file_put_contents($file_path_retalhos, implode(PHP_EOL, $updated_lines));
    
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'message' => 'Retalho reservado com sucesso!'
    ]);
    exit;
}

$current_datetime = date('d/m/Y H:i:s');
$total_disponivel = $total_pecas - $total_reservado;
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NEXUS | Retalhos do Material <?= htmlspecialchars($code) ?></title>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>📦</text></svg>">
    
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
            --nexus-accent-red: #f85149;
            --nexus-hover-bg: #30363d;
            --nexus-focus-border: #1f6feb;
            --nexus-shadow: rgba(0, 0, 0, 0.5);
            --nexus-gradient-primary: linear-gradient(135deg, var(--nexus-accent-blue), var(--nexus-accent-purple));
            --nexus-gradient-secondary: linear-gradient(135deg, var(--nexus-accent-green), var(--nexus-accent-cyan));
            --nexus-gradient-warning: linear-gradient(135deg, var(--nexus-accent-orange), #d29922);
            --nexus-gradient-danger: linear-gradient(135deg, var(--nexus-accent-red), #da3633);
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

        /* Container Principal */
        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 30px 20px;
        }

        /* Info do Material */
        .material-header {
            background: var(--nexus-bg-secondary);
            border-radius: 16px;
            padding: 30px;
            margin-bottom: 30px;
            border: 1px solid var(--nexus-border);
            box-shadow: 0 8px 32px var(--nexus-shadow);
            position: relative;
            overflow: hidden;
        }

        .material-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: var(--nexus-gradient-primary);
        }

        .material-title {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 20px;
            background: var(--nexus-gradient-primary);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .material-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 25px;
        }

        .detail-card {
            background: var(--nexus-bg-tertiary);
            padding: 15px;
            border-radius: 10px;
            border: 1px solid var(--nexus-border);
            text-align: center;
        }

        .detail-label {
            font-size: 0.9rem;
            color: var(--nexus-text-secondary);
            margin-bottom: 5px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .detail-value {
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--nexus-text-primary);
        }

        .material-code {
            font-family: 'JetBrains Mono', monospace;
            background: var(--nexus-bg-primary);
            padding: 4px 8px;
            border-radius: 6px;
            color: var(--nexus-accent-cyan);
        }

        /* Estatísticas */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: var(--nexus-bg-secondary);
            border-radius: 12px;
            padding: 20px;
            border: 1px solid var(--nexus-border);
            box-shadow: 0 4px 16px var(--nexus-shadow);
            text-align: center;
            transition: transform 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-2px);
        }

        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 8px;
        }

        .stat-label {
            color: var(--nexus-text-secondary);
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .stat-total .stat-value { color: var(--nexus-accent-blue); }
        .stat-disponivel .stat-value { color: var(--nexus-accent-green); }
        .stat-reservado .stat-value { color: var(--nexus-accent-orange); }
        .stat-area .stat-value { color: var(--nexus-accent-purple); }

        /* Controles */
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

        .btn-small {
            padding: 8px 12px;
            font-size: 12px;
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
            background-color: var(--nexus-accent-red);
            color: white;
        }

        .no-results {
            text-align: center;
            color: var(--nexus-text-secondary);
            font-style: italic;
            padding: 60px 20px;
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
        }

        .modal-header {
            text-align: center;
            margin-bottom: 25px;
        }

        .modal-title {
            font-size: 1.5rem;
            font-weight: 600;
            background: var(--nexus-gradient-primary);
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

        .modal-buttons {
            display: flex;
            gap: 15px;
            justify-content: center;
            margin-top: 25px;
        }

        /* Responsivo Melhorado - Mobile First */
@media (max-width: 768px) {
    .header-content {
        flex-direction: column;
        text-align: center;
        padding: 15px;
    }

    .container {
        padding: 15px 8px;
    }

    .material-header {
        padding: 20px 15px;
    }

    .material-title {
        font-size: 1.4rem;
    }

    .material-details {
        grid-template-columns: 1fr;
        gap: 12px;
    }

    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
        gap: 12px;
    }

    .stat-value {
        font-size: 1.5rem;
    }

    .controls-section {
        padding: 15px;
    }

    .controls-grid {
        grid-template-columns: 1fr;
        gap: 15px;
    }

    .action-buttons {
        justify-content: center;
        flex-direction: column;
    }

    .btn {
        width: 100%;
        justify-content: center;
    }

    /* TABELA MOBILE - MELHORADA */
    .table-wrapper {
        overflow-x: auto;
        max-height: 60vh;
        -webkit-overflow-scrolling: touch;
    }

    table {
        min-width: 100%;
        font-size: 13px;
    }

    th {
        padding: 12px 8px;
        font-size: 11px;
        white-space: nowrap;
        text-align: center;
        border-right: 1px solid var(--nexus-border);
    }

    td {
        padding: 12px 8px;
        text-align: center;
        vertical-align: middle;
        border-right: 1px solid var(--nexus-border);
        white-space: nowrap;
    }

    /* DESTAQUE ESPECIAL PARA ALTURA E LARGURA */
    td:nth-child(6), /* Altura */
    td:nth-child(7), /* Largura */
    th:nth-child(6),
    th:nth-child(7) {
        background: rgba(88, 166, 255, 0.1);
        font-weight: bold;
        color: var(--nexus-accent-blue);
        font-size: 14px;
        border: 2px solid var(--nexus-accent-blue);
        min-width: 85px;
    }

    /* Melhor visualização dos códigos */
    .material-code {
        font-size: 12px;
        padding: 6px 8px;
        display: inline-block;
        min-width: 60px;
        text-align: center;
    }

    /* Status badges menores */
    .status-badge {
        font-size: 10px;
        padding: 4px 6px;
    }

    /* Botões de ação menores */
    .btn-small {
        padding: 6px 10px;
        font-size: 11px;
        min-width: 80px;
    }

    .modal-content {
        margin: 15px;
        padding: 20px;
        max-width: calc(100vw - 30px);
    }

    .modal-title {
        font-size: 1.2rem;
    }

    .form-input {
        font-size: 16px; /* Evita zoom no iOS */
    }
}

/* Mobile Pequeno - Ajustes extras */
@media (max-width: 480px) {
    .material-title {
        font-size: 1.2rem;
    }

    .stats-grid {
        grid-template-columns: 1fr;
    }

    .stat-value {
        font-size: 1.3rem;
    }

    /* TABELA COMPACTA PARA TELAS PEQUENAS */
    table {
        font-size: 12px;
    }

    th {
        padding: 10px 6px;
        font-size: 10px;
    }

    td {
        padding: 10px 6px;
    }

    /* ALTURA E LARGURA AINDA MAIS DESTACADAS */
    td:nth-child(6), /* Altura */
    td:nth-child(7), /* Largura */
    th:nth-child(6),
    th:nth-child(7) {
        font-size: 13px;
        font-weight: 900;
        background: rgba(88, 166, 255, 0.2);
        color: #fff;
        text-shadow: 0 1px 2px rgba(0,0,0,0.5);
        min-width: 75px;
    }

    /* Descrição mais compacta */
    td:nth-child(8) {
        max-width: 100px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .btn-small {
        padding: 5px 8px;
        font-size: 10px;
        min-width: 70px;
    }
}

/* Landscape Mobile */
@media (max-width: 768px) and (orientation: landscape) {
    .table-wrapper {
        max-height: 45vh;
    }

    .stats-grid {
        grid-template-columns: repeat(4, 1fr);
    }
}

/* Tabela com scroll horizontal mais suave */
.table-wrapper {
    position: relative;
}

.table-wrapper::after {
    content: '👈 Deslize para ver mais';
    position: absolute;
    right: 10px;
    top: 50%;
    transform: translateY(-50%);
    background: rgba(88, 166, 255, 0.9);
    color: white;
    padding: 5px 10px;
    border-radius: 15px;
    font-size: 11px;
    pointer-events: none;
    opacity: 0;
    transition: opacity 0.3s;
}

@media (max-width: 768px) {
    .table-wrapper::after {
        opacity: 1;
    }
}

/* Destaque visual para scroll */
.table-wrapper::-webkit-scrollbar {
    height: 12px;
}

.table-wrapper::-webkit-scrollbar-track {
    background: var(--nexus-bg-primary);
    border-radius: 6px;
}

.table-wrapper::-webkit-scrollbar-thumb {
    background: var(--nexus-accent-blue);
    border-radius: 6px;
    border: 2px solid var(--nexus-bg-primary);
}

.table-wrapper::-webkit-scrollbar-thumb:hover {
    background: var(--nexus-accent-cyan);
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
                <div class="logo-icon">📦</div>
                <div class="logo-text">
                    <div class="logo-title">NEXUS</div>
                    <div class="logo-subtitle">Gestão de Retalhos</div>
                </div>
            </div>
            <div class="header-info">
                <div class="user-badge">
                <div class="datetime-info">
                    <div>📅 <?= date('d/m/Y') ?></div>
                    <div>⏰ <span id="current-time"><?= date('H:i:s') ?></span></div>
                </div>
            </div>
        </div>
    </header>

    <div class="container">
        <!-- Cabeçalho do Material -->
        <div class="material-header">
            <div class="material-title">
                📦 Retalhos do Material: <?= htmlspecialchars($material_info['name']) ?>
            </div>
            <div class="material-details">
                <div class="detail-card">
                    <div class="detail-label">🏷️ Código</div>
                    <div class="detail-value">
                        <span class="material-code"><?= htmlspecialchars($code) ?></span>
                    </div>
                </div>
                <div class="detail-card">
                    <div class="detail-label">📏 Espessura</div>
                    <div class="detail-value">
                        <?= $material_info['espessura'] ? $material_info['espessura'] . 'mm' : '➖' ?>
                    </div>
                </div>
                <div class="detail-card">
                    <div class="detail-label">🌾 Veio</div>
                    <div class="detail-value">
                        <span class="status-badge <?= $material_info['veio'] === 'Sim' ? 'status-active' : 'status-inactive' ?>">
                            <?= $material_info['veio'] === 'Sim' ? '✅ Sim' : '❌ Não' ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Estatísticas -->
        <div class="stats-grid">
            <div class="stat-card stat-total">
                <div class="stat-value"><?= $total_pecas ?></div>
                <div class="stat-label">📦 Total de Peças</div>
            </div>
            <div class="stat-card stat-disponivel">
                <div class="stat-value"><?= $total_disponivel ?></div>
                <div class="stat-label">✅ Disponíveis</div>
            </div>
            <div class="stat-card stat-reservado">
                <div class="stat-value"><?= $total_reservado ?></div>
                <div class="stat-label">🔒 Reservadas</div>
            </div>
            <div class="stat-card stat-area">
                <div class="stat-value"><?= number_format($area_total, 2) ?>m²</div>
                <div class="stat-label">📐 Área Total</div>
            </div>
        </div>

        <!-- Controles -->
        <div class="controls-section">
            <div class="controls-grid">
                <div class="search-container">
                    <input 
                        type="text" 
                        id="searchInput" 
                        class="search-input"
                        placeholder="🔍 Pesquisar por código, descrição ou dimensões..."
                        oninput="filterTable()"
                    />
                    <span class="search-icon">🔍</span>
                </div>
                <div class="action-buttons">
                    <button class="btn btn-primary" onclick="sortTable(1)">
                        🔢 Ordenar Código
                    </button>
                    <button class="btn btn-primary" onclick="sortTable(9)">
                        📐 Ordenar Área
                    </button>
                    <button class="btn btn-secondary" onclick="window.location.href='materiais_view.php';">
                        ← Voltar aos Materiais
                    </button>
                </div>
            </div>
        </div>

        <!-- Tabela de Retalhos -->
        <div class="table-section">
            <div class="table-header">
                <div class="table-title">
                    📋 Lista de Retalhos <span id="results-count">(<?= count($retalhos) ?> itens)</span>
                </div>
            </div>
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th onclick="sortTable(1)">🏷️ Código</th>
                            <th onclick="sortTable(2)">📊 Status</th>
                            <th onclick="sortTable(3)">📦 Total</th>
                            <th onclick="sortTable(4)">✅ Disponível</th>
                            <th onclick="sortTable(5)">🔒 Reservado</th>
                            <th onclick="sortTable(6)">📏 Altura (mm)</th>
                            <th onclick="sortTable(7)">📐 Largura (mm)</th>
                            <th onclick="sortTable(8)">📝 Descrição</th>
                            <th onclick="sortTable(9)">📐 Área (m²)</th>
                            <th>⚡ Ações</th>
                        </tr>
                    </thead>
                    <tbody id="retalhosTable">
                        <?php if (empty($retalhos)): ?>
                            <tr>
                                <td colspan="10" class="no-results">
                                    📦 Nenhum retalho encontrado para este material.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($retalhos as $retalho): ?>
                                <tr>
                                    <td>
                                        <span class="material-code"><?= htmlspecialchars($retalho['codigo']) ?></span>
                                    </td>
                                    <td>
                                        <span class="status-badge <?= $retalho['ativo'] === '+' ? 'status-active' : 'status-inactive' ?>">
                                            <?= $retalho['ativo'] === '+' ? '✅ Ativo' : '❌ Inativo' ?>
                                        </span>
                                    </td>
                                    <td><strong><?= $retalho['quantidade'] ?></strong></td>
                                    <td>
                                        <span style="color: <?= $retalho['disponivel'] > 0 ? 'var(--nexus-accent-green)' : 'var(--nexus-accent-red)' ?>;">
                                            <strong><?= $retalho['disponivel'] ?></strong>
                                        </span>
                                    </td>
                                    <td><?= $retalho['reservado'] ?></td>
                                    <td><?= number_format($retalho['altura'], 1) ?></td>
                                    <td><?= number_format($retalho['largura'], 1) ?></td>
                                    <td><?= htmlspecialchars($retalho['descricao']) ?></td>
                                    <td><?= number_format($retalho['area'], 3) ?></td>
                                    <td>
                                        <?php if ($retalho['disponivel'] > 0): ?>
                                            <button class="btn btn-secondary btn-small" 
                                                    onclick="openSolicitarModal(<?= $retalho['codigo'] ?>, '<?= htmlspecialchars($material_info['name']) ?>', <?= $retalho['disponivel'] ?>, <?= $retalho['altura'] ?>, <?= $retalho['largura'] ?>, '<?= htmlspecialchars($retalho['descricao']) ?>')">
                                                📲 Solicitar
                                            </button>
                                        <?php else: ?>
                                            <span style="color: var(--nexus-text-secondary); font-style: italic;">
                                                🚫 Indisponível
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
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
                    Informe a quantidade desejada
                </div>
            </div>
            <form class="modal-form" id="solicitarForm">
                <div id="retalhoInfo"></div>
                <div class="form-group">
                    <label class="form-label" for="quantidade">📦 Quantidade:</label>
                    <input type="number" id="quantidade" name="quantidade" class="form-input" min="1" required>
                </div>
                <div class="modal-buttons">
                    <button type="button" class="btn btn-tertiary" onclick="closeSolicitarModal()">
                        ❌ Cancelar
                    </button>
                    <button type="submit" class="btn btn-secondary">
                        ✅ Confirmar Solicitação
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        let currentRetalho = null;
        let sortDirection = {};

        function filterTable() {
            const searchInput = document.getElementById('searchInput').value.toLowerCase();
            const rows = document.querySelectorAll('#retalhosTable tr');
            let visibleCount = 0;

            rows.forEach(row => {
                if (row.querySelector('td')) {
                    const cells = row.querySelectorAll('td');
                    const searchText = Array.from(cells).map(cell => cell.textContent.toLowerCase()).join(' ');
                    
                    if (searchText.includes(searchInput)) {
                        row.style.display = '';
                        visibleCount++;
                    } else {
                        row.style.display = 'none';
                    }
                }
            });

            document.getElementById('results-count').textContent = `(${visibleCount} de <?= count($retalhos) ?> itens)`;
        }

        function sortTable(columnIndex) {
            const table = document.getElementById('retalhosTable');
            const rows = Array.from(table.querySelectorAll('tr')).filter(row => row.querySelector('td'));
            
            const currentDirection = sortDirection[columnIndex] || 'asc';
            const newDirection = currentDirection === 'asc' ? 'desc' : 'asc';
            sortDirection[columnIndex] = newDirection;

            const sortedRows = rows.sort((a, b) => {
                let valueA = a.querySelector(`td:nth-child(${columnIndex})`).textContent.trim();
                let valueB = b.querySelector(`td:nth-child(${columnIndex})`).textContent.trim();

                // Tratamento para diferentes tipos de dados
                if ([1, 3, 4, 5, 6, 7, 9].includes(columnIndex)) {
                    valueA = parseFloat(valueA.replace(/[^\d.-]/g, '')) || 0;
                    valueB = parseFloat(valueB.replace(/[^\d.-]/g, '')) || 0;
                }

                let comparison = 0;
                if (valueA > valueB) comparison = 1;
                if (valueA < valueB) comparison = -1;
                
                return newDirection === 'desc' ? comparison * -1 : comparison;
            });

            table.innerHTML = '';
            sortedRows.forEach(row => table.appendChild(row));

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

        function openSolicitarModal(codigo, material, maxQuantidade, altura, largura, descricao) {
            currentRetalho = {
                codigo: codigo,
                material: material,
                maxQuantidade: maxQuantidade,
                altura: altura,
                largura: largura,
                descricao: descricao
            };

            document.getElementById('retalhoInfo').innerHTML = `
                <div style="background: var(--nexus-bg-tertiary); padding: 15px; border-radius: 8px; margin-bottom: 15px;">
                    <h4 style="color: var(--nexus-accent-blue); margin-bottom: 10px;">📋 Detalhes do Retalho</h4>
                    <p><strong>🏷️ Código:</strong> <span class="material-code">${codigo}</span></p>
                    <p><strong>🏗️ Material:</strong> ${material}</p>
                    <p><strong>📏 Dimensões:</strong> ${altura} x ${largura} mm</p>
                    <p><strong>📝 Descrição:</strong> ${descricao}</p>
                    <p><strong>📦 Disponível:</strong> <span style="color: var(--nexus-accent-green);">${maxQuantidade} peça(s)</span></p>
                </div>
            `;

            const quantidadeInput = document.getElementById('quantidade');
            quantidadeInput.max = maxQuantidade;
            quantidadeInput.value = 1;
            quantidadeInput.focus();

            document.getElementById('solicitarModal').style.display = 'flex';
        }

        function closeSolicitarModal() {
            document.getElementById('solicitarModal').style.display = 'none';
            currentRetalho = null;
        }

        document.getElementById('solicitarForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const quantidade = parseInt(document.getElementById('quantidade').value);
            
            if (quantidade > currentRetalho.maxQuantidade) {
                alert(`❌ Quantidade solicitada (${quantidade}) excede o disponível (${currentRetalho.maxQuantidade}).`);
                return;
            }

            const formData = new FormData();
            formData.append('codigo', currentRetalho.codigo);
            formData.append('quantidade', quantidade);

            try {
                const response = await fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();
                
                if (result.success) {
                    // Criar mensagem do WhatsApp
                    const mensagem = `🔥 *SOLICITAÇÃO DE RETALHO* 🔥\n\n` +
                        `━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n` +
                        `👤 *Solicitante:* AdemirRed\n` +
                        `📅 *Data:* ${new Date().toLocaleDateString('pt-BR')}\n` +
                        `🕐 *Hora:* ${new Date().toLocaleTimeString('pt-BR')}\n` +
                        `━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n` +
                        `🏷️ *Código:* ${currentRetalho.codigo}\n` +
                        `🏗️ *Material:* ${currentRetalho.material}\n` +
                        `📦 *Quantidade:* ${quantidade} peça(s)\n` +
                        `📏 *Dimensões:* ${currentRetalho.altura} x ${currentRetalho.largura} mm\n` +
                        `📝 *Descrição:* ${currentRetalho.descricao}\n` +
                        `━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n` +
                        `✅ *RETALHO RESERVADO COM SUCESSO!*\n` +
                        `🙏 Obrigado pela solicitação!`;

                    const numeroWhatsApp = "+5551997756708";
                    window.open(`https://wa.me/${numeroWhatsApp}?text=${encodeURIComponent(mensagem)}`, '_blank');
                    
                    alert('✅ ' + result.message);
                    closeSolicitarModal();
                    location.reload();
                } else {
                    alert('❌ Erro ao processar solicitação.');
                }
            } catch (error) {
                console.error('Erro:', error);
                alert('❌ Erro de conexão. Tente novamente.');
            }
        });

        // Fechar modal clicando fora
        document.getElementById('solicitarModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeSolicitarModal();
            }
        });

        // Atalhos de teclado
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeSolicitarModal();
            }
            if (e.ctrlKey && e.key === 'f') {
                e.preventDefault();
                document.getElementById('searchInput').focus();
            }
        });

        // Atualizar hora em tempo real
        function updateTime() {
            const now = new Date();
            document.getElementById('current-time').textContent = now.toLocaleTimeString('pt-BR');
        }
        setInterval(updateTime, 1000);

        // Inicialização
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('searchInput').focus();
            
            // Animação das linhas da tabela
            const rows = document.querySelectorAll('#retalhosTable tr');
            rows.forEach((row, index) => {
                if (row.querySelector('td')) {
                    row.style.animationDelay = `${index * 0.05}s`;
                    row.style.animation = 'fadeInUp 0.6s ease-out forwards';
                }
            });
        });
    </script>
</body>
</html>