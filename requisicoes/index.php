<?php
require_once 'config.php';
require_once 'get_image.php';

$conn = getConnection();

// Buscar todos os materiais do estoque
$sql = "SELECT Codigo, Nome, EstoqueAtual, (Valor/100) as Valor, Unidade, imagem FROM itens ORDER BY Nome ASC";
$result = $conn->query($sql);

$materiais = [];
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        // Se tem imagem no banco, usar ela. Senão, buscar da internet
        if (!empty($row['imagem']) && file_exists('../a/' . $row['imagem'])) {
            $row['imagem_url'] = '../a/' . $row['imagem'];
        } else {
            $row['imagem_url'] = getImagemPorNome($row['Nome']);
        }
        $materiais[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Requisição de Materiais - ONN Móveis</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="header">
        <div class="logo-section">
            <div class="logo-icon">
                <span class="logo-text">ONN</span>
                <span class="logo-accent">MÓVEIS</span>
            </div>
            <div class="header-divider"></div>
        </div>
        <div class="header-content">
            <i class="fas fa-clipboard-list"></i>
            <h1>Requisição de Materiais</h1>
            <p class="header-subtitle">Sistema Profissional de Gestão</p>
        </div>
        
        <div class="dashboard-nav" style="margin-top: 20px;">
            <a href="dashboard.php" class="nav-btn">
                <i class="fas fa-chart-bar"></i>Dashboard
            </a>
            <a href="index.php" class="nav-btn active">
                <i class="fas fa-clipboard-list"></i>Nova Requisição
            </a>
            <a href="../a/estoque.php" class="nav-btn">
                <i class="fas fa-boxes"></i>Estoque
            </a>
        </div>
    </div>

    <div class="container">
        <!-- Formulário de Informações -->
        <div class="info-section" id="infoSection">
            <h2><i class="fas fa-info-circle"></i> Informações da Requisição</h2>
            
            <div class="form-group">
                <label for="cliente">
                    <i class="fas fa-user"></i> Nome do Cliente *
                </label>
                <input type="text" id="cliente" placeholder="Digite o nome do cliente" required>
            </div>

            <div class="form-group">
                <label for="marceneiro">
                    <i class="fas fa-hard-hat"></i> Marceneiro Responsável *
                </label>
                <input type="text" id="marceneiro" placeholder="Digite o nome do marceneiro" required>
            </div>

            <div class="form-group">
                <label for="servico">
                    <i class="fas fa-tools"></i> Informações do Serviço *
                </label>
                <textarea id="servico" rows="3" placeholder="Descreva o serviço a ser realizado"></textarea>
            </div>

            <button class="btn-primary" onclick="iniciarSelecao()">
                <i class="fas fa-arrow-right"></i> Selecionar Materiais
            </button>
        </div>

        <!-- Seleção de Materiais -->
        <div class="materiais-section" id="materiaisSection" style="display: none;">
            <div class="section-header">
                <button class="btn-back" onclick="voltarInfo()">
                    <i class="fas fa-arrow-left"></i> Voltar
                </button>
                <h2>Selecione os Materiais</h2>
            </div>

            <div class="search-box">
                <i class="fas fa-search"></i>
                <input type="text" id="searchInput" placeholder="Buscar material..." onkeyup="filtrarMateriais()">
            </div>

            <div class="materiais-list" id="materiaisGrid">
                <?php foreach ($materiais as $material): ?>
                <div class="material-item" data-nome="<?= strtolower($material['Nome']) ?>" data-codigo="<?= $material['Codigo'] ?>">
                    <div class="material-image-small">
                        <img src="<?= htmlspecialchars($material['imagem_url']) ?>" 
                             alt="<?= htmlspecialchars($material['Nome']) ?>"
                             loading="lazy"
                             onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                        <i class="fas fa-cube" style="display:none;"></i>
                    </div>
                    
                    <div class="material-details">
                        <h3 class="material-name"><?= htmlspecialchars($material['Nome']) ?></h3>
                        <div class="material-code">Código: <?= htmlspecialchars($material['Codigo']) ?></div>
                        <div class="material-value">R$ <?= number_format($material['Valor'], 2, ',', '.') ?></div>
                        
                        <div class="estoque-info <?= $material['EstoqueAtual'] <= 0 ? 'estoque-zero' : ($material['EstoqueAtual'] <= 5 ? 'estoque-baixo' : '') ?>">
                            <i class="fas fa-warehouse"></i>
                            <span>Estoque: <?= $material['EstoqueAtual'] ?> <?= htmlspecialchars($material['Unidade']) ?></span>
                            <?php if ($material['EstoqueAtual'] <= 0): ?>
                                <span class="aviso-estoque">⚠️ SEM ESTOQUE</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="material-actions">
                        <button class="btn-details" onclick="verDetalhes('<?= $material['Codigo'] ?>')">
                            <i class="fas fa-eye"></i> Detalhes
                        </button>
                        
                        <div class="quantidade-control">
                            <button class="btn-menos" onclick="alterarQuantidade('<?= $material['Codigo'] ?>', -1)" type="button">
                                <i class="fas fa-minus"></i>
                            </button>
                            <input type="number" 
                                   id="qtd-<?= $material['Codigo'] ?>" 
                                   value="0" 
                                   min="0" 
                                   readonly
                                   class="quantidade-input">
                            <button class="btn-mais" onclick="alterarQuantidade('<?= $material['Codigo'] ?>', 1)" type="button">
                                <i class="fas fa-plus"></i>
                            </button>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <div class="resumo-flutuante" id="resumoFlutuante" style="display: none;">
                <div class="resumo-content">
                    <span id="totalItens">0 itens selecionados</span>
                    <button class="btn-finalizar" onclick="finalizarRequisicao()">
                        <i class="fas fa-file-pdf"></i> Gerar PDF
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Loading Overlay -->
    <div class="loading-overlay" id="loadingOverlay" style="display: none;">
        <div class="loading-spinner">
            <i class="fas fa-spinner fa-spin"></i>
            <p>Gerando PDF...</p>
        </div>
    </div>

    <script>
        const materiais = <?= json_encode($materiais) ?>;
        const itensSelecionados = new Map();

        function iniciarSelecao() {
            const cliente = document.getElementById('cliente').value.trim();
            const marceneiro = document.getElementById('marceneiro').value.trim();
            const servico = document.getElementById('servico').value.trim();

            if (!cliente || !marceneiro || !servico) {
                alert('⚠️ Por favor, preencha todos os campos obrigatórios!');
                return;
            }

            document.getElementById('infoSection').style.display = 'none';
            document.getElementById('materiaisSection').style.display = 'block';
        }

        function verDetalhes(codigo) {
            const material = materiais.find(m => m.Codigo === codigo);
            if (!material) return;
            
            const detalhes = `
MATERIAL: ${material.Nome}
CÓDIGO: ${material.Codigo}
VALOR UNITÁRIO: R$ ${parseFloat(material.Valor).toLocaleString('pt-BR', {minimumFractionDigits: 2})}
ESTOQUE: ${material.EstoqueAtual} ${material.Unidade}
UNIDADE: ${material.Unidade}
            `.trim();
            
            alert(detalhes);
        }

        function voltarInfo() {
            document.getElementById('materiaisSection').style.display = 'none';
            document.getElementById('infoSection').style.display = 'block';
        }

        function verDetalhes(codigo) {
            const material = materiais.find(m => m.Codigo === codigo);
            if (!material) return;
            
            const detalhes = `
MATERIAL: ${material.Nome}
CÓDIGO: ${material.Codigo}
VALOR UNITÁRIO: R$ ${parseFloat(material.Valor).toLocaleString('pt-BR', {minimumFractionDigits: 2})}
ESTOQUE: ${material.EstoqueAtual} ${material.Unidade}
UNIDADE: ${material.Unidade}
            `.trim();
            
            alert(detalhes);
        }

        function alterarQuantidade(codigo, delta) {
            console.log('Alterando quantidade:', codigo, delta); // Debug
            
            const input = document.getElementById('qtd-' + codigo);
            if (!input) {
                console.error('Input não encontrado para código:', codigo);
                return;
            }
            
            let quantidade = parseInt(input.value) + delta;
            
            if (quantidade < 0) quantidade = 0;
            
            input.value = quantidade;
            
            // Feedback visual
            input.style.transform = 'scale(1.1)';
            setTimeout(() => {
                input.style.transform = 'scale(1)';
            }, 200);

            if (quantidade > 0) {
                const material = materiais.find(m => m.Codigo === codigo);
                if (material) {
                    itensSelecionados.set(codigo, {
                        codigo: codigo,
                        nome: material.Nome,
                        quantidade: quantidade,
                        valor_unitario: material.Valor,
                        unidade: material.Unidade
                    });
                }
            } else {
                itensSelecionados.delete(codigo);
            }

            atualizarResumo();
        }

        function atualizarResumo() {
            const totalItens = Array.from(itensSelecionados.values())
                .reduce((sum, item) => sum + item.quantidade, 0);
            
            const resumo = document.getElementById('resumoFlutuante');
            const totalItensSpan = document.getElementById('totalItens');

            if (totalItens > 0) {
                resumo.style.display = 'block';
                totalItensSpan.textContent = `${totalItens} ${totalItens === 1 ? 'item selecionado' : 'itens selecionados'}`;
            } else {
                resumo.style.display = 'none';
            }
        }

        function filtrarMateriais() {
            const busca = document.getElementById('searchInput').value.toLowerCase();
            const cards = document.querySelectorAll('.material-card');

            cards.forEach(card => {
                const nome = card.getAttribute('data-nome');
                const codigo = card.getAttribute('data-codigo').toLowerCase();
                
                if (nome.includes(busca) || codigo.includes(busca)) {
                    card.style.display = 'block';
                } else {
                    card.style.display = 'none';
                }
            });
        }

        async function finalizarRequisicao() {
            if (itensSelecionados.size === 0) {
                alert('⚠️ Selecione pelo menos um material!');
                return;
            }

            const cliente = document.getElementById('cliente').value.trim();
            const marceneiro = document.getElementById('marceneiro').value.trim();
            const servico = document.getElementById('servico').value.trim();

            const dados = {
                cliente: cliente,
                marceneiro: marceneiro,
                servico: servico,
                itens: Array.from(itensSelecionados.values())
            };

            document.getElementById('loadingOverlay').style.display = 'flex';

            try {
                const response = await fetch('processar_requisicao.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(dados)
                });

                const result = await response.json();

                document.getElementById('loadingOverlay').style.display = 'none';

                if (result.success) {
                    alert('✅ Requisição gerada com sucesso!\n\nPDF: ' + result.pdf);
                    
                    // Abrir PDF
                    window.open(result.pdf, '_blank');
                    
                    // Resetar formulário
                    setTimeout(() => {
                        location.reload();
                    }, 1000);
                } else {
                    alert('❌ Erro: ' + result.message);
                }
            } catch (error) {
                document.getElementById('loadingOverlay').style.display = 'none';
                alert('❌ Erro ao processar requisição: ' + error.message);
            }
        }
    </script>
</body>
</html>
