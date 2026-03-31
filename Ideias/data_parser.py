"""
Parser de dados XML para arquivos .cutplanning
Extrai materiais, chapas e peças do XML
"""
import xml.etree.ElementTree as ET


class CutPlanningParser:
    """Parser para arquivos .cutplanning"""
    
    def __init__(self):
        self.xml_tree = None
        self.xml_root = None
        self.label_templates = {}
    
    @staticmethod
    def _localname(tag):
        """
        Extrai o nome local de uma tag XML
        "{ns}name" -> "name" ou "com.pacote.Class" -> "Class"
        """
        if "}" in tag:
            return tag.split("}", 1)[1]
        elif "." in tag:
            return tag.split(".")[-1]
        return tag
    
    def load_file(self, file_path):
        """Carrega arquivo XML"""
        self.xml_tree = ET.parse(file_path)
        self.xml_root = self.xml_tree.getroot()
        # Templates de etiqueta (layout/metadata dos fields)
        self.label_templates = self.parse_label_templates()
        return self.xml_tree, self.xml_root

    def parse_label_templates(self):
        """Extrai templates de etiqueta (cutplan.label + label.field).

        Retorna um dict no formato:
        {
            '1': {
                'id': '1',
                'description': '...',
                'type': 'part',
                'fields': {
                    '180': {'name': '180', 'type': 'text', 'expression': 'Cliente:', ...},
                    ...
                }
            },
            ...
        }
        """
        templates = {}
        if not self.xml_root:
            return templates

        for elem in self.xml_root.iter():
            if self._localname(elem.tag).lower() != "label":
                continue

            label_id = elem.get("id")
            if not label_id:
                continue

            template = {
                "id": label_id,
                "description": elem.get("description", ""),
                "type": elem.get("type", ""),
                "width": elem.get("width", ""),
                "height": elem.get("height", ""),
                "fields": {},
            }

            for child in list(elem):
                if self._localname(child.tag).lower() != "field":
                    continue

                field_name = child.get("name")
                if not field_name:
                    continue

                template["fields"][field_name] = {
                    "name": field_name,
                    "type": child.get("type", ""),
                    "expression": child.get("expression", ""),
                    "fontName": child.get("fontName", ""),
                    "fontSize": child.get("fontSize", ""),
                    "fontStyle": child.get("fontStyle", ""),
                    "direction": child.get("direction", ""),
                    "alignment": child.get("alignment", ""),
                }

            templates[label_id] = template

        return templates
    
    def parse_all_materials(self):
        """
        Extrai todos os materiais e seus programas (chapas)
        Retorna: lista de dicts com estrutura:
        [
            {
                'material': element,
                'description': str,
                'color': str,
                'code': str,
                'width': str,
                'length': str,
                'thickness': str,
                'programs': [
                    {
                        'program': element,
                        'number': str,
                        'quantity': str,
                        'pieces': [...],
                        'cuts': [...]
                    }
                ]
            }
        ]
        """
        if not self.xml_root:
            return []
        
        materials_data = []
        
        # Iterar todos os materiais
        for material_elem in self.xml_root.iter():
            if self._localname(material_elem.tag).lower() != "material":
                continue
            
            material_info = {
                'material': material_elem,
                'description': material_elem.get('description', 'Material sem nome'),
                'color': material_elem.get('color', 'SEM COR'),
                'code': material_elem.get('code', ''),
                'width': material_elem.get('width', '0'),
                'length': material_elem.get('lenght', '0'),  # Note: lenght (typo no XML)
                'thickness': material_elem.get('thickness', '0'),
                'programs': []
            }
            
            # Iterar programas (chapas) dentro do material
            for program_elem in material_elem:
                if self._localname(program_elem.tag).lower() != "program":
                    continue
                
                program_info = {
                    'program': program_elem,
                    'number': program_elem.get('number', '0'),
                    'quantity': program_elem.get('quantity', '1'),
                    'width': program_elem.get('width', material_info['width']),
                    'length': program_elem.get('lenght', material_info['length']),
                    'cuts': [],
                    'pieces': []
                }
                
                # Extrair cuts e peças
                self._parse_program_cuts(program_elem, program_info)
                
                material_info['programs'].append(program_info)
            
            # Só adiciona material se tiver programas
            if material_info['programs']:
                materials_data.append(material_info)
        
        return materials_data
    
    def _parse_program_cuts(self, program_elem, program_info):
        """Extrai os cuts de um programa e identifica peças"""
        cuts_with_pieces = []
        all_cuts = []
        
        for cut_elem in program_elem:
            if self._localname(cut_elem.tag).lower() != "cut":
                continue
            
            cut_info = {
                'cut': cut_elem,
                'id': cut_elem.get('id', ''),
                'type': cut_elem.get('type', ''),
                'value': cut_elem.get('value', '0'),
                'quantity': '0',
                'fields': {},
                'data_element': None,
                'template': None,
            }
            
            # Procurar data dentro do cut
            for data_elem in cut_elem:
                if self._localname(data_elem.tag).lower() == "data":
                    cut_info['quantity'] = data_elem.get('quantity', '0')
                    cut_info['data_element'] = data_elem
                    cut_info['template'] = data_elem.get('template')
                    
                    # Extrair fields
                    for field_elem in data_elem:
                        if self._localname(field_elem.tag).lower() == "field":
                            field_name = field_elem.get('name', '')
                            field_value = field_elem.get('value', '')
                            if field_name:
                                cut_info['fields'][field_name] = {
                                    'element': field_elem,
                                    'value': field_value
                                }
            
            all_cuts.append(cut_info)
            
            # Se tem fields, é uma peça
            if cut_info['fields']:
                piece_info = {
                    'cut_id': cut_info['id'],
                    'description': cut_info['fields'].get('185', {}).get('value', 'Peça sem nome'),
                    'width': cut_info['fields'].get('187', {}).get('value', '0'),
                    'height': cut_info['fields'].get('189', {}).get('value', '0'),
                    'environment': cut_info['fields'].get('193', {}).get('value', ''),
                    'quantity': cut_info['quantity'],
                    'cut': cut_elem,
                    'cut_element': cut_elem,  # Referência para edição
                    'fields': cut_info['fields'],
                    'data_element': cut_info['data_element'],
                    'template': cut_info['template'],
                }
                cuts_with_pieces.append(cut_info)
                program_info['pieces'].append(piece_info)
        
        program_info['cuts'] = all_cuts
        return cuts_with_pieces
    
    def get_material_summary(self, material_data):
        """Retorna resumo de um material"""
        total_sheets = len(material_data['programs'])
        total_pieces = sum(len(p['pieces']) for p in material_data['programs'])
        return {
            'total_sheets': total_sheets,
            'total_pieces': total_pieces,
            'description': material_data['description'],
            'color': material_data['color']
        }
    
    def get_program_summary(self, program_data):
        """Retorna resumo de um programa/chapa"""
        return {
            'number': program_data['number'],
            'quantity': program_data['quantity'],
            'pieces_count': len(program_data['pieces']),
            'total_cuts': len(program_data['cuts'])
        }
