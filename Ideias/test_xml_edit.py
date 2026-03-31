"""
Teste para verificar se as edições no XML estão sendo salvas corretamente
"""
import xml.etree.ElementTree as ET

# Carregar arquivo
xml_file = r'C:\xampp\htdocs\Ideias\PRG_P0001_PECAS_AVULCO_funcional.cutplanning'
tree = ET.parse(xml_file)
root = tree.getroot()

def localname(tag):
    if "}" in tag:
        return tag.split("}", 1)[1]
    elif "." in tag:
        return tag.split(".")[-1]
    return tag

# Encontrar primeiro corte Y
print("=== VALORES ANTES DA EDIÇÃO ===")
for material in root.iter():
    if localname(material.tag).lower() == 'material':
        for program in material:
            if localname(program.tag).lower() == 'program':
                cuts = program.findall('.//*')
                for i, cut in enumerate(cuts[:15]):  # Primeiros 15 elementos
                    if localname(cut.tag).lower() == 'cut':
                        cut_type = cut.get('type')
                        if cut_type in ['P', 'Y']:
                            print(f"Cut {i}: type={cut_type}, value={cut.get('value')}")
                break
        break

# Modificar primeiro Y encontrado
for material in root.iter():
    if localname(material.tag).lower() == 'material':
        for program in material:
            if localname(program.tag).lower() == 'program':
                for i, cut in enumerate(program):
                    if localname(cut.tag).lower() == 'cut' and cut.get('type') == 'Y':
                        old_value = cut.get('value')
                        cut.set('value', '999.99')
                        print(f"\n>>> MODIFICADO: Cut {i} (Y) de {old_value} para 999.99")
                        break
                break
        break

# Verificar se mudança está no root
print("\n=== VALORES APÓS EDIÇÃO (antes de salvar) ===")
for material in root.iter():
    if localname(material.tag).lower() == 'material':
        for program in material:
            if localname(program.tag).lower() == 'program':
                for i, cut in enumerate(program):
                    if localname(cut.tag).lower() == 'cut':
                        cut_type = cut.get('type')
                        if cut_type in ['P', 'Y']:
                            print(f"Cut {i}: type={cut_type}, value={cut.get('value')}")
                break
        break

# Salvar
output_file = r'C:\xampp\htdocs\Ideias\test_output.cutplanning'
xml_str = ET.tostring(root, encoding='unicode', method='xml')

# Adicionar declaração XML
xml_declaration = '<?xml version="1.0" encoding="UTF-8" standalone="no"?>'
final_content = xml_declaration + xml_str

with open(output_file, 'w', encoding='utf-8') as f:
    f.write(final_content)

print(f"\n✅ Arquivo salvo em: {output_file}")

# Recarregar arquivo salvo
print("\n=== VALORES NO ARQUIVO RECARREGADO ===")
tree2 = ET.parse(output_file)
root2 = tree2.getroot()

for material in root2.iter():
    if localname(material.tag).lower() == 'material':
        for program in material:
            if localname(program.tag).lower() == 'program':
                for i, cut in enumerate(program):
                    if localname(cut.tag).lower() == 'cut':
                        cut_type = cut.get('type')
                        if cut_type in ['P', 'Y']:
                            print(f"Cut {i}: type={cut_type}, value={cut.get('value')}")
                break
        break
