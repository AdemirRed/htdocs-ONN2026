# 📱 Sistema de Solicitação de Retalhos via WhatsApp API

## 🎯 Visão Geral

Sistema integrado para buscar e solicitar retalhos de materiais, com envio automático via WhatsApp API (ZDG/Evolution API).

## 📁 Arquivos Criados/Modificados

### 1. **retalhos_filtro.php** (Modificado)
- Interface principal de busca de retalhos
- Formulário AJAX para envio de solicitações
- Sistema de notificações flutuantes
- Link para configuração do WhatsApp

### 2. **enviar_whatsapp_retalho.php** (Novo)
- Endpoint API para processar envios
- Integração com WhatsApp API
- Formatação de mensagens
- Log de erros

### 3. **config_whatsapp_retalhos.php** (Novo)
- Página de configuração da conexão WhatsApp
- Geração e exibição de QR Code
- Verificação de status da sessão
- Interface amigável para conexão

## 🔧 Configuração

### Pré-requisitos

1. **API WhatsApp** (ZDG/Evolution API) rodando em:
   - URL: `http://192.168.0.201:200`
   - API Key: `redblack`
   - Sessão: `retalhos_sistema`

2. **Servidor Web** (Apache/XAMPP)
   - PHP 7.4+
   - Extensão cURL habilitada

### Instalação

1. **Copie os arquivos para o servidor:**
   ```
   c:\xampp\htdocs\
   ├── retalhos_filtro.php
   ├── enviar_whatsapp_retalho.php
   └── config_whatsapp_retalhos.php
   ```

2. **Configure a API WhatsApp:**
   - Edite `enviar_whatsapp_retalho.php` e `config_whatsapp_retalhos.php`
   - Atualize as variáveis:
     ```php
     $whatsapp_api_url = 'http://192.168.0.201:200';
     $whatsapp_api_key = 'redblack';
     $session_id = 'retalhos_sistema';
     $numero_destino = '5551997756708';
     ```

3. **Conecte o WhatsApp:**
   - Acesse: `http://192.168.0.201/config_whatsapp_retalhos.php`
   - Clique em "Iniciar Conexão"
   - Escaneie o QR Code com o WhatsApp
   - Aguarde a confirmação de conexão

## 🚀 Como Usar

### 1. Buscar Retalhos

1. Acesse `retalhos_filtro.php`
2. Selecione os filtros desejados:
   - Material
   - Espessura
   - Dimensões mínimas
   - Respeitar veio da madeira

3. Clique em "Pesquisar Retalhos"

### 2. Solicitar Retalho

1. Na tabela de resultados, localize o retalho desejado
2. Digite a quantidade no campo "Solicitar"
3. Clique no botão "📲 Solicitar"
4. O sistema enviará automaticamente via WhatsApp
5. Aguarde a notificação de confirmação

### 3. Verificar Conexão WhatsApp

- Clique no botão "📱 WhatsApp Config" no cabeçalho
- Visualize o status da conexão
- Reconecte se necessário

## 📨 Formato da Mensagem

```
🔥 *SOLICITAÇÃO DE RETALHO* 🔥

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
📅 *Data:* 14/01/2026
🕐 *Hora:* 10:30:45
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

🏷️ *Código:* RET12345
🏗️ *Material:* MDF Branco
📦 *Quantidade Solicitada:* 2 unidade(s)
📏 *Dimensões:* 500mm x 300mm
📐 *Espessura:* 18mm
📝 *Descrição:* Retalho disponível
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

✅ *Poderiam confirmar a disponibilidade?*
🙏 Obrigado!
```

## 🔍 Endpoints da API

### enviar_whatsapp_retalho.php

**Método:** POST  
**Parâmetros:**
- `codigo` (string) - Código do retalho
- `material` (string) - Nome do material
- `quantidade` (int) - Quantidade solicitada
- `altura` (float) - Altura em mm
- `largura` (float) - Largura em mm
- `descricao` (string) - Descrição do retalho
- `espessura` (float, opcional) - Espessura em mm

**Resposta de Sucesso:**
```json
{
  "success": true,
  "message": "Solicitação enviada com sucesso!",
  "response": { ... }
}
```

**Resposta de Erro:**
```json
{
  "success": false,
  "error": "Mensagem de erro",
  "details": { ... }
}
```

### config_whatsapp_retalhos.php

**Ações AJAX:**

1. **verificar_status**
   ```
   POST: action=verificar_status
   Retorna: { "success": true, "status": {...} }
   ```

2. **iniciar_sessao**
   ```
   POST: action=iniciar_sessao
   Retorna: { "success": true, "message": "..." }
   ```

3. **obter_qr**
   ```
   POST: action=obter_qr
   Retorna: { "success": true, "qr_image": "data:image/png;base64,..." }
   ```

## ⚙️ Características Técnicas

### Segurança
- Validação de dados no servidor
- Sanitização de entrada
- Timeout de requisições (30s)
- Log de erros

### Performance
- Requisições AJAX assíncronas
- Cache de sessão
- Timeout configurável

### UX/UI
- Notificações flutuantes
- Feedback visual imediato
- Loading states
- Design responsivo
- Tema escuro (Nexus)

## 🐛 Troubleshooting

### Erro: "Erro ao enviar mensagem"

**Possíveis causas:**
1. API WhatsApp offline
2. Sessão desconectada
3. Número de destino inválido

**Solução:**
- Verifique se a API está rodando: `http://192.168.0.201:200/session/status/retalhos_sistema`
- Reconecte o WhatsApp em `config_whatsapp_retalhos.php`
- Verifique o formato do número: `5551997756708`

### Erro: "Erro de conexão"

**Possíveis causas:**
1. Servidor web offline
2. Timeout de rede
3. Permissões de arquivo

**Solução:**
- Verifique se o Apache está rodando
- Aumente o timeout: `curl_setopt($ch, CURLOPT_TIMEOUT, 60);`
- Verifique permissões: `chmod 644 *.php`

### QR Code não aparece

**Solução:**
1. Aguarde 2-3 segundos após clicar em "Iniciar Conexão"
2. Atualize a página
3. Verifique logs do servidor: `tail -f /xampp/apache/logs/error.log`

## 📊 Logs

Os logs são gravados em:
- **PHP Error Log:** `c:\xampp\apache\logs\error.log`
- **Formato:** `[timestamp] WhatsApp enviado com sucesso - Código: RET12345, Quantidade: 2`

## 🔄 Atualizações Futuras

- [ ] Dashboard de estatísticas de envio
- [ ] Histórico de solicitações
- [ ] Múltiplos destinatários
- [ ] Envio de imagens dos retalhos
- [ ] Confirmação automática via webhook
- [ ] Integração com banco de dados

## 📞 Suporte

Para problemas ou dúvidas:
- Verifique a documentação da API WhatsApp
- Consulte os logs de erro
- Teste os endpoints manualmente com cURL

## 📝 Notas

- A sessão `retalhos_sistema` é dedicada e não deve ser usada para outros fins
- Mantenha a API WhatsApp sempre atualizada
- Faça backup regular das configurações
- Monitore o status da conexão periodicamente

---

**Versão:** 1.0  
**Data:** 14/01/2026  
**Autor:** Sistema Nexus
