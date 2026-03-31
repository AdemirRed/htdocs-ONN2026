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
        foreach ($lines as $line) {
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

            $retalhos[] = [
                'codigo' => trim($fields[0]),
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
    <title>Buscar Retalhos</title>
 <link rel="stylesheet" href="styles.css">

    
</head>
<body>
    <div class="container">
        <h1>Buscar Retalhos</h1>

        <form method="POST">
           
<div class="form-group">
    <label for="material">Material:</label>
    <select name="material" id="material-select" onchange="toggleEspessuras()">
        <option value="Todos">Todos</option>
        <?php foreach ($materials as $code => $material): ?>
            <option value="<?= htmlspecialchars($code) ?>" <?= isset($_POST['material']) && $_POST['material'] == $code ? 'selected' : '' ?>>
                <?= htmlspecialchars($material['name']) ?>
            </option>
        <?php endforeach; ?>
    </select>
</div>

<div class="form-group">
    <label>Espessuras:</label><br>
    <?php foreach (["3mm", "6mm", "9mm", "15mm", "18mm"] as $esp): ?>
        <label>
            <input type="checkbox" name="espessura[]" id="espessura-<?= $esp ?>" value="<?= $esp ?>" <?= isset($_POST['espessura']) && in_array($esp, $_POST['espessura']) ? 'checked' : '' ?>>
            <?= $esp ?>
        </label><br>
    <?php endforeach; ?>
</div>

            <div class="form-group">
    <label for="altura">Altura:</label>
    <input type="number" name="altura" id="altura" value="<?= isset($_POST['altura']) ? htmlspecialchars($_POST['altura']) : '' ?>">
</div>

<div class="form-group">
    <label for="largura">Largura:</label>
    <input type="number" name="largura" id="largura" value="<?= isset($_POST['largura']) ? htmlspecialchars($_POST['largura']) : '' ?>">
</div>

<div class="form-group">
                <label>
                    <input type="checkbox" name="grain" value="Sim" <?= isset($_POST['grain']) && $_POST['grain'] == 'Sim' ? 'checked' : '' ?>>
                    Respeitar veio
                </label>
            </div>

<button type="button" onclick="trocarValores()">Trocar Altura e Largura</button>

<script>
function trocarValores() {
    const altura = document.getElementById('altura');
    const largura = document.getElementById('largura');

    // Trocar os valores
    const temp = altura.value;
    altura.value = largura.value;
    largura.value = temp;
}
</script>


            

            <button type="submit">Pesquisar</button>
            <a href="http://192.168.0.201/materiais_view.php">
                <button type="button">Voltar</button>
            </a>
        </form>
<div class="table-wrapper">
        <table>
            <thead>
                <tr>
                    <th>Linha</th>
                    <th>Material</th>
                    <th>Ativo</th>
                    <th>Quantidade</th>
                    <th>Altura</th>
                    <th>Largura</th>
                    <th>Descrição</th>
                    <th>Reservado</th>
                    <th>Solicitar</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($retalhos)): ?>
                    <?php foreach ($retalhos as $retalho): ?>
                        <tr>
                            <td><?= htmlspecialchars($retalho['codigo']) ?></td>
                            <td><?= htmlspecialchars($retalho['material']) ?></td>
                            <td><?= htmlspecialchars($retalho['ativo']) ?></td>
                            <td><?= htmlspecialchars($retalho['quantidade']) ?></td>
                            <td><?= htmlspecialchars($retalho['altura']) ?></td>
                            <td><?= htmlspecialchars($retalho['largura']) ?></td>
                            <td><?= htmlspecialchars($retalho['descricao']) ?></td>
			   <td><?= htmlspecialchars($retalho['reservado']) ?></td>
                           <td>
    <form onsubmit="return validarQuantidade(this);">
        <input type="number" name="quantidade" min="1" max="<?= htmlspecialchars($retalho['quantidade']) ?>" placeholder="1" required>
       <!-- <button type="submit" id="meuBotao">Solicitar</button> -->

    </form>
    <script>
    document.addEventListener("DOMContentLoaded", function() {
        // Adiciona a classe 'desativado' ao link para desabilitar o clique
        document.getElementById("meuBotao").classList.add("desativado");
    });
    

        function validarQuantidade(form) {
            const quantidade = form.querySelector('input[name="quantidade"]').value;
            const maxQuantidade = parseInt(form.querySelector('input[name="quantidade"]').max);
            
            if (quantidade > maxQuantidade) {
                alert(`A quantidade máxima disponível é ${maxQuantidade}.`);
                return false;
            }

            // Criar a mensagem para o WhatsApp no formato desejado
            let mensagem = `Olá, gostaria de solicitar o seguinte RETALHO:\n\n`;
            mensagem += `------------------\n`;
            mensagem += `*Material:* ${"<?= urlencode($retalho['material']) ?>"}\n`;
            mensagem += `*Quantidade:* ${quantidade}\n`;
            mensagem += `*Tamanho:* ${"<?= urlencode($retalho['altura']) ?>"}x${"<?= urlencode($retalho['largura']) ?>"}\n`;
            mensagem += `*Descrição:* ${"<?= urlencode($retalho['descricao']) ?>"}\n`;
            mensagem += `------------------\n\n`;
            mensagem += `Poderiam confirmar a disponibilidade?`;

            // Codificar a mensagem para a URL
            mensagem = encodeURIComponent(mensagem);

            // Número do WhatsApp (com DDI e DDD)
            const numeroWhatsApp = "+5551997756708";

            // Abrir o WhatsApp com a mensagem pré-preenchida
            window.open(`https://wa.me/${numeroWhatsApp}?text=${mensagem}`, '_blank');
            return false;
        }
    </script>
</td>




                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="8" class="no-results">Nenhum retalho encontrado com os filtros aplicados.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
</div>
    </div>
<script>
    function toggleEspessuras() {
        const materialSelect = document.getElementById('material-select');
        const isTodos = materialSelect.value === "Todos";

        // Seleciona todos os checkboxes de espessuras
        const espessuraCheckboxes = document.querySelectorAll('input[name="espessura[]"]');
        
        // Habilita ou desabilita os checkboxes
        espessuraCheckboxes.forEach(checkbox => {
            checkbox.disabled = !isTodos;
        });
    }

    // Executa ao carregar a página para ajustar o estado inicial
    document.addEventListener("DOMContentLoaded", toggleEspessuras);
</script>

</body>
</html>