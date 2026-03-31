<?php
// Função para obter URL de imagem baseada no nome do item
function getImagemPorNome($nome) {
    $nome_lower = strtolower($nome);
    
    // MDF - diferentes espessuras
    if (preg_match('/mdf\s*(15|9|18|6|25)\s*mm/i', $nome_lower, $matches)) {
        $espessura = $matches[1];
        return "https://images.unsplash.com/photo-1616486338812-3dadae4b4ace?w=400&h=400&fit=crop"; // MDF genérico
    }
    
    // Dobradiças
    if (preg_match('/dobra[dç]i[çc]a/i', $nome_lower)) {
        return "https://images.unsplash.com/photo-1590736969955-71cc94901144?w=400&h=400&fit=crop";
    }
    
    // Parafusos
    if (preg_match('/parafuso/i', $nome_lower)) {
        return "https://images.unsplash.com/photo-1565193566173-7a0ee3dbe261?w=400&h=400&fit=crop";
    }
    
    // Corrediças
    if (preg_match('/corredi[çc]a/i', $nome_lower)) {
        return "https://images.unsplash.com/photo-1618220179428-22790b461013?w=400&h=400&fit=crop";
    }
    
    // Cola
    if (preg_match('/cola/i', $nome_lower)) {
        return "https://images.unsplash.com/photo-1589939705384-5185137a7f0f?w=400&h=400&fit=crop";
    }
    
    // Fita de borda
    if (preg_match('/fita.*borda/i', $nome_lower)) {
        return "https://images.unsplash.com/photo-1572981779307-38b8cabb2407?w=400&h=400&fit=crop";
    }
    
    // Verniz / Selador
    if (preg_match('/verniz|selador|finish/i', $nome_lower)) {
        return "https://images.unsplash.com/photo-1562259949-e8e7689d7828?w=400&h=400&fit=crop";
    }
    
    // Lixa
    if (preg_match('/lixa/i', $nome_lower)) {
        return "https://images.unsplash.com/photo-1581092918056-0c4c3acd3789?w=400&h=400&fit=crop";
    }
    
    // Puxadores
    if (preg_match('/puxador|ma[çc]aneta/i', $nome_lower)) {
        return "https://images.unsplash.com/photo-1558618666-fcd25c85cd64?w=400&h=400&fit=crop";
    }
    
    // Compensado
    if (preg_match('/compensado/i', $nome_lower)) {
        return "https://images.unsplash.com/photo-1611768675137-f4faa2c000fc?w=400&h=400&fit=crop";
    }
    
    // Madeira / Tábua
    if (preg_match('/madeira|t[áa]bua|pinus|eucalipto/i', $nome_lower)) {
        return "https://images.unsplash.com/photo-1587293852726-70cdb56c2866?w=400&h=400&fit=crop";
    }
    
    // Pregos
    if (preg_match('/prego/i', $nome_lower)) {
        return "https://images.unsplash.com/photo-1589903308904-1010c2294adc?w=400&h=400&fit=crop";
    }
    
    // Imagem padrão
    return "https://images.unsplash.com/photo-1586023492125-27b2c045efd7?w=400&h=400&fit=crop";
}

// Se chamado diretamente via GET, retornar imagem
if (isset($_GET['nome'])) {
    header('Content-Type: application/json');
    echo json_encode(['url' => getImagemPorNome($_GET['nome'])]);
    exit;
}
?>
