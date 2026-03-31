<?php
/**
 * Sistema de Gestão de Projetos - Corte Certo Plus
 * Versão melhorada com estrutura organizada e visual moderno
 */

// Configurações
define('PASTA_PROJETOS', 'C:/corte_certo_plus/PED');
define('TITULO_SISTEMA', 'Corte Certo Plus - Gestão de Projetos');

class ProjetoManager {
    private $pastaProjetos;
    
    public function __construct($pastaProjetos) {
        $this->pastaProjetos = $pastaProjetos;
    }
    
    /**
     * Lista todos os projetos disponíveis
     */
    public function listarProjetos() {
        return glob("{$this->pastaProjetos}/*.INI");
    }
    
    /**
     * Lista todos os marceneiros únicos dos projetos
     */
    public function listarMarceneiros() {
        $projetos = $this->listarProjetos();
        $marceneiros = [];
        
        foreach ($projetos as $projeto) {
            if (file_exists($projeto)) {
                $dadosIni = parse_ini_file($projeto);
                $marceneiro = $this->limparTexto($dadosIni['OBS1'] ?? '');
                
                if (!empty($marceneiro) && $marceneiro !== 'Não informado') {
                    $marceneiros[] = $marceneiro;
                }
            }
        }
        
        $marceneiros = array_unique($marceneiros);
        sort($marceneiros);
        
        return $marceneiros;
    }
    
    /**
     * Filtra projetos baseado nos critérios fornecidos
     */
    public function filtrarProjetos($filtros = []) {
        $projetos = $this->listarProjetos();
        $projetosFiltrados = [];
        
        foreach ($projetos as $projeto) {
            $id = preg_replace('/\D/', '', basename($projeto, ".INI"));
            $detalhes = $this->obterDetalhesBasicosProjeto($projeto);
            
            // Aplicar filtros
            $passouFiltro = true;
            
            // Filtro por número do projeto
            if (!empty($filtros['numero']) && strpos($id, $filtros['numero']) === false) {
                $passouFiltro = false;
            }
            
            // Filtro por marceneiro
            if (!empty($filtros['marceneiro']) && $filtros['marceneiro'] !== 'todos') {
                if (stripos($detalhes['marceneiro'], $filtros['marceneiro']) === false) {
                    $passouFiltro = false;
                }
            }
            
            // Filtro por nome do cliente (usando o campo CAMPO1 que contém o nome do projeto/cliente)
            if (!empty($filtros['cliente'])) {
                if (stripos($detalhes['nome'], $filtros['cliente']) === false) {
                    $passouFiltro = false;
                }
            }
            
            if ($passouFiltro) {
                $projetosFiltrados[] = [
                    'arquivo' => $projeto,
                    'id' => $id,
                    'detalhes' => $detalhes
                ];
            }
        }
        
        return $projetosFiltrados;
    }
    
    /**
     * Obtém detalhes básicos do projeto para filtros
     */
    private function obterDetalhesBasicosProjeto($arquivo) {
        $detalhes = [
            'nome' => 'Projeto Desconhecido',
            'marceneiro' => 'Não informado',
            'status' => '0'
        ];
        
        if (file_exists($arquivo)) {
            $dadosIni = parse_ini_file($arquivo);
            
            $detalhes['nome'] = $this->obterNomeProjeto($arquivo);
            $detalhes['marceneiro'] = $this->limparTexto($dadosIni['OBS1'] ?? 'Não informado');
            $detalhes['status'] = trim($dadosIni['STATUS'] ?? '0');
        }
        
        return $detalhes;
    }
    
    /**
     * Limpa e formata texto removendo caracteres especiais
     */
    public function limparTexto($texto) {
        $replacements = [
            '#|#' => ' ',
            '_' => ' ',
            '(' => ' ',
            ')' => ' ',
            '°' => 'GRAU',
            'º' => 'GRAU'
        ];
        
        $texto = str_replace(array_keys($replacements), array_values($replacements), $texto);
        $texto = preg_replace('/@[A-Za-z0-9]+/', '', $texto);
        
        return trim($texto);
    }
    
    /**
     * Remove acentos e caracteres especiais
     */
    public function removerAcentos($string) {
        $acentos = ['á','ã','â','à','é','ê','í','ó','õ','ô','ú','ç','Á','Ã','Â','À','É','Ê','Í','Ó','Õ','Ô','Ú','Ç','¿'];
        $semAcentos = ['a','a','a','a','e','e','i','o','o','o','u','c','A','A','A','A','E','E','I','O','O','O','U','C','?'];
        
        return str_replace($acentos, $semAcentos, $string);
    }
    
    /**
     * Obtém o nome do projeto do arquivo INI
     */
    public function obterNomeProjeto($arquivo) {
        if (!file_exists($arquivo)) {
            return "Projeto Desconhecido";
        }
        
        $linhas = file($arquivo, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        
        foreach ($linhas as $linha) {
            if (stripos($linha, 'CAMPO1=') === 0) {
                $nomeProjeto = trim(substr($linha, 7));
                return $this->removerAcentos($this->limparTexto($nomeProjeto));
            }
        }
        
        return "Projeto Desconhecido";
    }
    
    /**
     * Obtém detalhes completos do projeto
     */
    public function obterDetalhesProjeto($idProjeto) {
        $arquivoIni = "{$this->pastaProjetos}/DSC{$idProjeto}.INI";
        $arquivoTab = "{$this->pastaProjetos}/PED{$idProjeto}.TAB";
        
        $resultado = [
            'existe' => false,
            'dados' => [],
            'itens' => []
        ];
        
        if (!file_exists($arquivoIni)) {
            return $resultado;
        }
        
        $dadosIni = parse_ini_file($arquivoIni);
        $status = trim($dadosIni['STATUS'] ?? '0');
        
        $resultado['existe'] = true;
        $resultado['dados'] = [
            'id' => $idProjeto,
            'nome' => $this->obterNomeProjeto($arquivoIni),
            'status' => $this->obterStatusTexto($status),
            'statusCor' => $this->obterStatusCor($status),
            'marceneiro' => $this->limparTexto($dadosIni['OBS1'] ?? 'Não informado'),
            'editadoPor' => $this->limparTexto($dadosIni['CAMPO10'] ?? 'Não informado'),
            'cortadoPor' => $this->limparTexto($dadosIni['CAMPO2'] ?? 'Não informado'),
            'estoqueAtualizado' => ($dadosIni['ESTOQUE _ATUALIZADO'] ?? 0) == 1 ? 'Sim' : 'Não',
            'dataCriacao' => $this->formatarData($dadosIni['CRIACAO'] ?? ''),
            'ultimaModificacao' => $this->formatarData($dadosIni['ULT_MODIF'] ?? '')
        ];
        
        // Processar itens do arquivo TAB
        if (file_exists($arquivoTab)) {
            $resultado['itens'] = $this->processarItensTab($arquivoTab);
        }
        
        return $resultado;
    }
    
    /**
     * Converte código de status em texto legível
     */
    private function obterStatusTexto($status) {
        return match ($status) {
            '7' => 'Desconhecido',
            '6' => 'Material Indefinido!',
            '5' => 'Em Análise',
            '4' => 'Esperando Chapas',
            '3' => 'Cancelado',
            '2' => 'Concluído',
            '1' => 'Cortando',
            '0' => 'Aberto',
            default => 'Desconhecido'
        };
    }
    
    /**
     * Retorna cor para o status
     */
    private function obterStatusCor($status) {
        return match ($status) {
            '2' => 'success',    // Concluído
            '1' => 'warning',    // Cortando
            '3' => 'danger',     // Cancelado
            '6' => 'danger',     // Material Indefinido
            '5' => 'info',       // Em Análise
            '4' => 'secondary',  // Esperando Chapas
            '0' => 'primary',    // Aberto
            default => 'secondary'
        };
    }
    
    /**
     * Formata data para exibição
     */
    private function formatarData($data) {
        if (empty($data) || $data === 'Desconhecido') {
            return 'Não informado';
        }
        
        // Assumindo formato de data do sistema (adapte conforme necessário)
        return $data;
    }
    
    /**
     * Processa itens do arquivo TAB
     */
    private function processarItensTab($arquivoTab) {
        $itens = [];
        $linhas = file($arquivoTab, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        
        foreach ($linhas as $linha) {
            $dados = preg_split('/\s+/', $linha);
            
            if (count($dados) >= 9) {
                $itens[] = [
                    'codigo' => $dados[0],
                    'quantidade' => $dados[2],
                    'altura' => $dados[3],
                    'largura' => $dados[4],
                    'material' => $this->removerAcentos($this->limparTexto($dados[5])),
                    'descricao' => $this->removerAcentos($this->limparTexto($dados[6])),
                    'ambiente' => $this->removerAcentos($this->limparTexto(end($dados)))
                ];
            }
        }
        
        return $itens;
    }
}

// Inicialização

$projetoManager = new ProjetoManager(PASTA_PROJETOS);
$projetoSelecionado = $_GET['projeto'] ?? null;

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= TITULO_SISTEMA ?></title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #2c3e50;
            --secondary-color: #3498db;
            --success-color: #27ae60;
            --warning-color: #f39c12;
            --danger-color: #e74c3c;
            --dark-color: #34495e;
            --light-color: #ecf0f1;
        }

        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .main-container {
            padding: 2rem 0;
        }

        .project-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .project-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 40px rgba(0, 0, 0, 0.15);
        }

        .project-header {
            background: linear-gradient(135deg, var(--primary-color), var(--dark-color));
            color: white;
            padding: 1.5rem;
            border-radius: 15px 15px 0 0;
            margin: -1px -1px 0 -1px;
        }

        .project-list-item {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(5px);
            border-radius: 10px;
            margin-bottom: 1rem;
            transition: all 0.3s ease;
            border: 1px solid rgba(255, 255, 255, 0.3);
        }

        .project-list-item:hover {
            background: rgba(255, 255, 255, 1);
            transform: translateX(10px);
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
        }

        .status-badge {
            font-size: 0.875rem;
            padding: 0.5rem 1rem;
            border-radius: 50px;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .info-item {
            background: rgba(255, 255, 255, 0.1);
            padding: 1rem;
            border-radius: 10px;
            border-left: 4px solid var(--secondary-color);
        }

        .table-modern {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
        }

        .table-modern thead {
            background: var(--primary-color);
            color: white;
        }

        .table-modern tbody tr:hover {
            background-color: rgba(52, 152, 219, 0.1);
        }

        .btn-modern {
            border-radius: 50px;
            padding: 0.75rem 2rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            transition: all 0.3s ease;
            border: none;
        }

        .btn-modern:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }

        .header-title {
            text-align: center;
            color: white;
            margin-bottom: 2rem;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.3);
        }

        .fade-in {
            animation: fadeIn 0.8s ease-in;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .project-stats {
            display: flex;
            justify-content: space-around;
            margin-top: 1rem;
        }

        .stat-item {
            text-align: center;
            color: rgba(255, 255, 255, 0.9);
        }

        .stat-number {
            font-size: 1.5rem;
            font-weight: bold;
            display: block;
        }

        .back-button {
            position: fixed;
            top: 20px;
            left: 20px;
            z-index: 1000;
        }
    </style>
</head>
<body>

    <?php if ($projetoSelecionado): ?>
        <a href="projetos.php" class="btn btn-light btn-modern back-button">
            <i class="fas fa-arrow-left"></i> Voltar
        </a>
    <?php endif; ?>

    <div class="container main-container">
        <h1 class="header-title fade-in">
            <i class="fas fa-tools"></i>
            <?= TITULO_SISTEMA ?>
        </h1>

        <?php if ($projetoSelecionado): ?>
            <?php
            $idProjeto = preg_replace('/\D/', '', $projetoSelecionado);
            $detalhes = $projetoManager->obterDetalhesProjeto($idProjeto);
            ?>

            <?php if ($detalhes['existe']): ?>
                <div class="project-card fade-in">
                    <div class="project-header">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h2 class="mb-1">
                                    <i class="fas fa-project-diagram"></i>
                                    Projeto #<?= $detalhes['dados']['id'] ?>
                                </h2>
                                <p class="mb-0 opacity-75"><?= $detalhes['dados']['nome'] ?></p>
                            </div>
                            <span class="badge status-badge bg-<?= $detalhes['dados']['statusCor'] ?>">
                                <?= $detalhes['dados']['status'] ?>
                            </span>
                        </div>
                    </div>

                    <div class="card-body p-4">
                        <div class="info-grid">
                            <div class="info-item">
                                <h6><i class="fas fa-user-tie text-primary"></i> Marceneiro</h6>
                                <p class="mb-0"><?= $detalhes['dados']['marceneiro'] ?></p>
                            </div>
                            <div class="info-item">
                                <h6><i class="fas fa-user-edit text-success"></i> Editado por</h6>
                                <p class="mb-0"><?= $detalhes['dados']['editadoPor'] ?></p>
                            </div>
                            <div class="info-item">
                                <h6><i class="fas fa-cut text-warning"></i> Cortado por</h6>
                                <p class="mb-0"><?= $detalhes['dados']['cortadoPor'] ?></p>
                            </div>
                            <div class="info-item">
                                <h6><i class="fas fa-warehouse text-info"></i> Estoque Atualizado</h6>
                                <p class="mb-0"><?= $detalhes['dados']['estoqueAtualizado'] ?></p>
                            </div>
                            <div class="info-item">
                                <h6><i class="fas fa-calendar-plus text-secondary"></i> Data de Criação</h6>
                                <p class="mb-0"><?= $detalhes['dados']['dataCriacao'] ?></p>
                            </div>
                            <div class="info-item">
                                <h6><i class="fas fa-calendar-edit text-secondary"></i> Última Modificação</h6>
                                <p class="mb-0"><?= $detalhes['dados']['ultimaModificacao'] ?></p>
                            </div>
                        </div>

                        <?php if (!empty($detalhes['itens'])): ?>
                            <h4 class="mt-4 mb-3">
                                <i class="fas fa-list"></i>
                                Itens do Projeto
                                <span class="badge bg-primary"><?= count($detalhes['itens']) ?> itens</span>
                            </h4>

                            <div class="table-responsive">
                                <table class="table table-modern mb-0">
                                    <thead>
                                        <tr>
                                            <th><i class="fas fa-barcode"></i> Código</th>
                                            <th><i class="fas fa-sort-numeric-up"></i> Qtd</th>
                                            <th><i class="fas fa-arrows-alt-v"></i> Altura</th>
                                            <th><i class="fas fa-arrows-alt-h"></i> Largura</th>
                                            <th><i class="fas fa-cube"></i> Material</th>
                                            <th><i class="fas fa-info-circle"></i> Descrição</th>
                                            <th><i class="fas fa-home"></i> Ambiente</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($detalhes['itens'] as $item): ?>
                                            <tr>
                                                <td><code><?= $item['codigo'] ?></code></td>
                                                <td><span class="badge bg-secondary"><?= $item['quantidade'] ?></span></td>
                                                <td><?= $item['altura'] ?></td>
                                                <td><?= $item['largura'] ?></td>
                                                <td><?= $item['material'] ?></td>
                                                <td><?= $item['descricao'] ?></td>
                                                <td><?= $item['ambiente'] ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-info mt-4">
                                <i class="fas fa-info-circle"></i>
                                Nenhum item encontrado para este projeto.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php else: ?>
                <div class="alert alert-danger fade-in">
                    <i class="fas fa-exclamation-triangle"></i>
                    Projeto não encontrado.
                </div>
            <?php endif; ?>

        <?php else: ?>
            <!-- Lista de Projetos -->
            <?php
            $projetos = $projetoManager->listarProjetos();
            ?>


            <div class="row">
                <div class="col-lg-8 mx-auto">
                    <div class="project-card fade-in">
                        <div class="project-header">
                            <h2 class="mb-0">
                                <i class="fas fa-folder-open"></i>
                                Projetos Disponíveis
                            </h2>
                            <div class="project-stats">
                                <div class="stat-item">
                                    <span class="stat-number"><?= count($projetos) ?></span>
                                    <small>Total de Projetos</small>
                                </div>
                            </div>
                        </div>

                        <div class="card-body p-4">
                            <?php if (empty($projetos)): ?>
                                <div class="alert alert-warning text-center">
                                    <i class="fas fa-folder-open"></i>
                                    Nenhum projeto encontrado.
                                </div>
                            <?php else: ?>
                                <?php foreach ($projetos as $projeto): ?>
                                    <?php
                                    $id = preg_replace('/\D/', '', basename($projeto, ".INI"));
                                    $nomeProjeto = $projetoManager->obterNomeProjeto($projeto);
                                    ?>
                                    <div class="project-list-item">
                                        <a href="projetos.php?projeto=<?= $id ?>" class="text-decoration-none">
                                            <div class="p-4 d-flex justify-content-between align-items-center">
                                                <div>
                                                    <h5 class="mb-1 text-dark">
                                                        <i class="fas fa-file-alt text-primary"></i>
                                                        Projeto #<?= $id ?>
                                                    </h5>
                                                    <p class="mb-0 text-muted"><?= $nomeProjeto ?></p>
                                                </div>
                                                <i class="fas fa-chevron-right text-muted"></i>
                                            </div>
                                        </a>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>


    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
</body>
</html>