// pedidos.js - Arquivo JavaScript completo

// Permitir colar imagens no campo de upload
document.addEventListener('paste', function(event) {
    const items = (event.clipboardData || event.originalEvent.clipboardData).items;
    const input = document.getElementById('inputImagens');
    let files = input.files ? Array.from(input.files) : [];
    let changed = false;
    for (let item of items) {
        if (item.type.indexOf('image') !== -1) {
            const file = item.getAsFile();
            files.push(file);
            changed = true;
        }
    }
    if (changed) {
        const dataTransfer = new DataTransfer();
        files.forEach(f => dataTransfer.items.add(f));
        input.files = dataTransfer.files;
        mostrarPreviewImagens();
        
        if (typeof notificationSystem !== 'undefined') {
            notificationSystem.mostrarNotificacao('Imagem colada com sucesso!', 'success', 2000);
        }
    }
});

// Mostrar preview das imagens
if (document.getElementById('inputImagens')) {
    document.getElementById('inputImagens').addEventListener('change', mostrarPreviewImagens);
}

function mostrarPreviewImagens() {
    const input = document.getElementById('inputImagens');
    const preview = document.getElementById('previewImagens');
    if (!input || !preview) return;
    
    preview.innerHTML = '';
    Array.from(input.files).forEach(file => {
        const reader = new FileReader();
        reader.onload = e => {
            const img = document.createElement('img');
            img.src = e.target.result;
            img.width = 60;
            img.style.borderRadius = '6px';
            img.style.border = '1px solid #ccc';
            img.title = file.name;
            preview.appendChild(img);
        };
        reader.readAsDataURL(file);
    });
}

// Sistema de notificações
class NotificationSystem {
    constructor() {
        this.container = document.getElementById('notificationContainer');
        this.notificationCount = 0;
        this.ultimoCheckPedidos = Date.now();
        if (this.container) {
            this.init();
        }
    }
    
    init() {
        this.verificarNovosPedidos();
        setInterval(() => this.verificarNovosPedidos(), 10000);
        document.addEventListener('visibilitychange', () => {
            if (!document.hidden) {
                this.verificarNovosPedidos();
            }
        });
    }
    
    mostrarNotificacao(mensagem, tipo = 'info', duracao = 5000) {
        if (!this.container) return;
        
        const notification = document.createElement('div');
        const icons = {
            'success': 'fas fa-check-circle',
            'info': 'fas fa-info-circle',
            'novo-pedido': 'fas fa-plus-circle',
            'error': 'fas fa-exclamation-circle'
        };
        
        notification.className = `notification ${tipo}`;
        notification.innerHTML = `
            <i class="${icons[tipo]} icon"></i>
            <span>${mensagem}</span>
            <button class="close-btn" onclick="notificationSystem.fecharNotificacao(this)">×</button>
        `;
        
        this.container.appendChild(notification);
        this.notificationCount++;
        this.atualizarContador();
        
        setTimeout(() => {
            if (notification.parentNode) {
                this.fecharNotificacao(notification.querySelector('.close-btn'));
            }
        }, duracao);
    }
    
    fecharNotificacao(btn) {
        const notification = btn.closest('.notification');
        if (!notification) return;
        
        notification.style.animation = 'slideOut 0.3s ease-out';
        setTimeout(() => {
            if (notification.parentNode) {
                notification.remove();
                this.notificationCount--;
                this.atualizarContador();
            }
        }, 300);
    }
    
    atualizarContador() {
        const counter = document.getElementById('notificationCount');
        if (!counter) return;
        
        if (this.notificationCount > 0) {
            counter.textContent = this.notificationCount;
            counter.style.display = 'flex';
        } else {
            counter.style.display = 'none';
        }
    }
    
    async verificarNovosPedidos() {
        try {
            const response = await fetch(`check_novos_pedidos.php?ultimo_check=${this.ultimoCheckPedidos}`);
            const data = await response.json();
            
            if (data.novos_pedidos && data.novos_pedidos.length > 0) {
                data.novos_pedidos.forEach(pedido => {
                    this.mostrarNotificacao(
                        `Novo pedido: ${pedido.NomeProduto} (${pedido.Quantidade} ${pedido.Unidade})`,
                        'novo-pedido',
                        8000
                    );
                });
                this.ultimoCheckPedidos = Date.now();
            }
        } catch (error) {
            console.error('Erro ao verificar novos pedidos:', error);
        }
    }
}

// Inicializar sistema de notificações
const notificationSystem = new NotificationSystem();

/**
 * Busca URLs de imagens associadas ao pedido
 */
function buscarImagensPedido(pedidoId) {
    // Por enquanto retorna array vazio
    // Você pode implementar uma busca AJAX aqui se tiver imagens associadas
    return [];
}

/**
 * Envia WhatsApp para o fornecedor via API
 */
async function enviarWhatsAppFornecedor(botao) {
    const pedidoId = botao.getAttribute('data-pedido-id');
    const telefone = botao.getAttribute('data-telefone');
    const fornecedor = botao.getAttribute('data-fornecedor');
    const status = botao.getAttribute('data-status');
    
    // Se já estiver concluído, não permitir envio
    if (status === 'Concluído') {
        notificationSystem.mostrarNotificacao(
            '✅ Este pedido já foi concluído e a mensagem já foi enviada.',
            'info',
            3000
        );
        return;
    }
    
    // Limpar formatação do telefone
    const telefoneNumeros = telefone.replace(/\D/g, '');
    
    // Confirmar envio
    if (!confirm(`Deseja enviar o pedido via WhatsApp para ${fornecedor}?\nTelefone: ${telefoneNumeros}`)) {
        return;
    }
    
    // Desabilitar botão e mostrar loading
    botao.disabled = true;
    const textoOriginal = botao.innerHTML;
    botao.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Enviando...';
    
    try {
        const formData = new FormData();
        formData.append('enviar_whatsapp_fornecedor', '1');
        formData.append('pedido_id', pedidoId);
        
        const imagensUrls = buscarImagensPedido(pedidoId);
        if (imagensUrls.length > 0) {
            formData.append('imagens_urls', JSON.stringify(imagensUrls));
        }
        
        const response = await fetch('pedidos.php', {
            method: 'POST',
            body: formData
        });
        
        const resultado = await response.json();
        
        if (resultado.success) {
            botao.classList.add('enviado');
            botao.innerHTML = '<i class="fab fa-whatsapp"></i> Enviado';
            botao.style.cursor = 'not-allowed';
            
            if (resultado.already_sent) {
                notificationSystem.mostrarNotificacao(
                    `ℹ️ Pedido já estava concluído`,
                    'info',
                    3000
                );
            } else {
                notificationSystem.mostrarNotificacao(
                    `✅ Mensagem enviada para ${fornecedor}!\n📱 Telefone: ${telefoneNumeros}`,
                    'success',
                    5000
                );
                
                // Atualizar status na tabela
                const row = botao.closest('tr');
                const statusBadge = row.querySelector('.status-badge');
                if (statusBadge) {
                    statusBadge.className = 'status-badge status-concluido';
                    statusBadge.textContent = 'Concluído';
                }
                
                // Remover botão de concluir se existir
                const concluirBtn = row.querySelector('button[type="submit"]');
                if (concluirBtn) {
                    concluirBtn.closest('form').remove();
                }
            }
            
            if (resultado.imagens && resultado.imagens.success) {
                notificationSystem.mostrarNotificacao(
                    `📷 ${resultado.imagens.resultados.length} imagem(ns) enviada(s)`,
                    'info',
                    3000
                );
            }
        } else {
            botao.innerHTML = textoOriginal;
            botao.disabled = false;
            
            if (resultado.redirect) {
                if (confirm('WhatsApp não conectado! Deseja conectar agora?')) {
                    window.open(resultado.redirect, '_blank');
                }
            } else {
                notificationSystem.mostrarNotificacao(
                    `❌ Erro ao enviar: ${resultado.error}`,
                    'error',
                    5000
                );
            }
        }
    } catch (error) {
        console.error('Erro ao enviar WhatsApp:', error);
        botao.innerHTML = textoOriginal;
        botao.disabled = false;
        notificationSystem.mostrarNotificacao(
            '❌ Erro ao enviar mensagem. Tente novamente.',
            'error',
            5000
        );
    }
}

/**
 * Marca WhatsApp como enviado (usado no link direto)
 */
async function marcarWhatsAppEnviado(pedidoId) {
    setTimeout(async () => {
        try {
            const formData = new FormData();
            formData.append('marcar_whatsapp_enviado', '1');
            formData.append('pedido_id', pedidoId);
            
            const response = await fetch('pedidos.php', {
                method: 'POST',
                body: formData
            });
            
            const data = await response.json();
            
            if (data.success) {
                const btn = document.querySelector(`a[data-pedido-id="${pedidoId}"]`);
                if (btn) {
                    btn.classList.add('enviado');
                    btn.innerHTML = '<i class="fab fa-whatsapp"></i> Enviado';
                }
                
                const row = document.querySelector(`tr[data-pedido-id="${pedidoId}"]`);
                if (row) {
                    const statusBadge = row.querySelector('.status-badge');
                    if (statusBadge) {
                        statusBadge.className = 'status-badge status-concluido';
                        statusBadge.textContent = 'Concluído';
                    }
                    
                    const concluirBtn = row.querySelector('button[type="submit"]');
                    if (concluirBtn) {
                        concluirBtn.closest('form').remove();
                    }
                }
                
                notificationSystem.mostrarNotificacao('Pedido marcado como concluído!', 'success');
            }
        } catch (error) {
            console.error('Erro:', error);
            notificationSystem.mostrarNotificacao('Erro ao marcar pedido como concluído', 'error');
        }
    }, 1000);
}

/**
 * Verificar status WhatsApp
 */
async function verificarStatusWhatsApp() {
    try {
        const response = await fetch('whatsapp_connection.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'action=obter_status'
        });
        const data = await response.json();
        
        const btn = document.getElementById('btnWhatsApp');
        const status = document.getElementById('whatsappStatus');
        
        if (!btn || !status) return;
        
        if (data.connected) {
            btn.classList.remove('btn-danger');
            btn.classList.add('btn-success');
            status.innerHTML = ' ✓';
        } else {
            btn.classList.remove('btn-success');
            btn.classList.add('btn-danger');
            status.innerHTML = ' ✗';
        }
        status.style.display = 'inline';
    } catch (error) {
        console.error('Erro ao verificar WhatsApp:', error);
    }
}

/**
 * Verificar status de envio ao carregar página
 */
document.addEventListener('DOMContentLoaded', function() {
    // Verificar status WhatsApp
    if (document.getElementById('btnWhatsApp')) {
        verificarStatusWhatsApp();
        setInterval(verificarStatusWhatsApp, 30000);
    }
    
    // Verificar botões de WhatsApp
    const botoesWhatsApp = document.querySelectorAll('.whatsapp-btn');
    botoesWhatsApp.forEach(botao => {
        const pedidoId = botao.getAttribute('data-pedido-id');
        const status = botao.getAttribute('data-status');
        
        // Se estiver concluído, marcar como enviado
        if (status === 'Concluído') {
            botao.classList.add('enviado');
            botao.innerHTML = '<i class="fab fa-whatsapp"></i> Enviado';
            botao.disabled = true;
            botao.style.cursor = 'not-allowed';
        } else {
            // Verificar se já foi enviado
            fetch(`pedidos.php?verificar_enviado=1&pedido_id=${pedidoId}`)
                .then(res => res.json())
                .then(data => {
                    if (data.enviado) {
                        botao.classList.add('enviado');
                        botao.innerHTML = '<i class="fab fa-whatsapp"></i> Enviado';
                        botao.disabled = true;
                        botao.style.cursor = 'not-allowed';
                    }
                })
                .catch(err => console.error('Erro ao verificar envio:', err));
        }
    });
    
    // Validação do formulário
    const formNovoPedido = document.getElementById('formNovoPedido');
    if (formNovoPedido) {
        formNovoPedido.addEventListener('submit', function(e) {
            const produto = this.querySelector('[name="NomeProduto"]').value.trim();
            const fornecedor = this.querySelector('[name="NomeFornecedor"]').value;
            const quantidade = this.querySelector('[name="Quantidade"]').value;
            
            if (!produto || !fornecedor || !quantidade || quantidade < 1) {
                e.preventDefault();
                notificationSystem.mostrarNotificacao('Por favor, preencha todos os campos obrigatórios', 'error');
                return false;
            }
            
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processando...';
            submitBtn.disabled = true;
            
            setTimeout(() => {
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            }, 3000);
        });
    }
    
    // Auto-hide alerts após 5 segundos
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        setTimeout(() => {
            alert.style.transition = 'opacity 0.5s ease-out';
            alert.style.opacity = '0';
            setTimeout(() => {
                if (alert.parentNode) {
                    alert.remove();
                }
            }, 500);
        }, 5000);
    });
});

// CSS para animações
const style = document.createElement('style');
style.textContent = `
    @keyframes slideOut {
        from { transform: translateX(0); opacity: 1; }
        to { transform: translateX(100%); opacity: 0; }
    }
    
    @keyframes shimmer {
        0% { left: -100%; }
        100% { left: 100%; }
    }
    
    @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }
    
    @keyframes checkPulse {
        0%, 100% { transform: scale(1); }
        50% { transform: scale(1.2); }
    }
    
    .notification {
        position: relative;
        overflow: hidden;
    }
    
    .notification::before {
        content: '';
        position: absolute;
        top: 0;
        left: -100%;
        width: 100%;
        height: 100%;
        background: linear-gradient(90deg, transparent, rgba(255,255,255,0.4), transparent);
        animation: shimmer 2s infinite;
    }
    
    .whatsapp-btn {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 8px 16px;
        background: linear-gradient(135deg, #25D366 0%, #128C7E 100%);
        color: white;
        border: none;
        border-radius: 20px;
        font-size: 0.9em;
        font-weight: 500;
        cursor: pointer;
        transition: all 0.3s ease;
        text-decoration: none;
        box-shadow: 0 2px 5px rgba(37, 211, 102, 0.3);
    }
    
    .whatsapp-btn:hover:not(.enviado):not(:disabled) {
        background: linear-gradient(135deg, #128C7E 0%, #075E54 100%);
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(37, 211, 102, 0.4);
    }
    
    .whatsapp-btn.enviado {
        background: linear-gradient(135deg, #28a745 0%, #20803d 100%);
        cursor: not-allowed !important;
        opacity: 0.8;
        box-shadow: 0 2px 5px rgba(40, 167, 69, 0.3);
    }
    
    .whatsapp-btn.enviado i {
        animation: checkPulse 0.5s ease;
    }
    
    .whatsapp-btn:disabled {
        opacity: 0.7;
        cursor: not-allowed !important;
    }
    
    .whatsapp-btn i {
        font-size: 1.1em;
    }
    
    .whatsapp-btn .fa-spinner {
        animation: spin 1s linear infinite;
    }
`;
document.head.appendChild(style);