# 📸 Sistema de Imagens - Guia Rápido

## ✅ O que foi implementado:

### 1. **Sistema Híbrido de Imagens**
   - 🌐 **Imagens da Internet**: Automaticamente busca imagens do Unsplash baseado no nome do item
   - 📤 **Upload Manual**: Permite enviar imagem personalizada ao cadastrar/editar item

### 2. **Reconhecimento Automático por Palavras-chave**

O sistema detecta automaticamente e usa imagens apropriadas para:
- ✅ **MDF** (15mm, 18mm, 9mm, 6mm, 25mm)
- ✅ **Dobradiças**
- ✅ **Corrediças**
- ✅ **Parafusos**
- ✅ **Pregos**
- ✅ **Cola**
- ✅ **Fita de Borda**
- ✅ **Verniz/Selador**
- ✅ **Lixa**
- ✅ **Puxadores/Maçanetas**
- ✅ **Compensado**
- ✅ **Madeira/Tábuas**
- 📦 **Imagem Padrão**: Para itens não reconhecidos

## 🚀 Como Usar:

### **PASSO 1: Adicionar a coluna de imagem no banco**
Acesse no navegador:
```
http://localhost/a/adicionar_imagem.php
```

### **PASSO 2: Usar o sistema de requisições**
```
http://localhost/requisicoes/
```

### **PASSO 3: Adicionar/Editar itens no estoque**
```
http://localhost/a/estoque.php
```

## 📤 Como fazer Upload de Imagem:

### Ao **ADICIONAR** novo item:
1. Clique em **"+ Novo Item"**
2. Preencha os dados normalmente
3. No campo **"Imagem do Material"**, clique e selecione uma foto
4. Você verá um preview da imagem
5. Clique em **"Adicionar Item"**

### Ao **EDITAR** item existente:
1. Clique no botão **"Editar"** do item
2. Role até o campo **"Alterar Imagem"**
3. Selecione uma nova foto
4. Preview aparecerá automaticamente
5. Clique em **"Salvar Alterações"**

## 🎯 Prioridade de Imagens:

O sistema segue esta ordem:
1. **Upload do usuário** (se existir no banco de dados)
2. **Imagem da internet** (se o nome combinar com palavras-chave)
3. **Ícone padrão** (se nenhuma imagem for encontrada)

## 📁 Onde as imagens são salvas:

```
c:\xampp\htdocs\a\uploads\materiais\
```

As imagens são salvas com nome único: `uniqid_timestamp.extensao`

## 🎨 Formatos Aceitos:

- ✅ JPG / JPEG
- ✅ PNG
- ✅ GIF
- ✅ WEBP

## 💡 Dicas:

1. **Fotos tiradas no celular funcionam perfeitamente!**
2. O sistema redimensiona automaticamente para 400x400px
3. Imagens grandes são otimizadas para carregamento rápido
4. Se a imagem não carregar, mostra ícone automaticamente
5. Use fotos reais dos materiais para melhor identificação visual

## 🔧 Manutenção:

### Limpar imagens antigas não usadas:
As imagens ficam salvas mesmo após excluir o item. Para limpar:
1. Acesse `c:\xampp\htdocs\a\uploads\materiais\`
2. Delete manualmente as imagens não utilizadas

## 📱 No Sistema de Requisições:

- As imagens aparecem automaticamente nos cards
- Clique nos botões **+** e **-** para selecionar quantidades
- Imagens tornam mais fácil identificar materiais rapidamente
- Sistema funciona perfeitamente no celular!

---

**Tudo pronto! Sistema 100% funcional** ✅
