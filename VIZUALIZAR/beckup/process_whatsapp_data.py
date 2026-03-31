import json
import base64
import os
from datetime import datetime
from collections import defaultdict

def process_whatsapp_data(json_file_path):
    """
    Processa dados do WhatsApp separando mensagens e imagens por sessionId
    """
    
    # Carregar dados do JSON
    with open(json_file_path, 'r', encoding='utf-8') as file:
        data = json.load(file)
    
    # Dicionários para organizar por sessionId
    sessions_data = defaultdict(lambda: {
        'messages': [],
        'images': [],
        'media_files': []
    })
    
    # Processar cada item
    for item in data:
        try:
            session_id = item.get('conteudo', {}).get('sessionId', 'unknown')
            data_type = item.get('conteudo', {}).get('dataType', 'unknown')
            data_recebimento = item.get('data_recebimento', '')
            
            if data_type == 'message_create' or data_type == 'message':
                # Processar mensagem
                message_data = item.get('conteudo', {}).get('data', {})
                message = message_data.get('message', {})
                
                message_info = {
                    'id': message.get('id', {}).get('_serialized', ''),
                    'timestamp': datetime.fromtimestamp(message.get('timestamp', 0)).strftime('%Y-%m-%d %H:%M:%S'),
                    'from': message.get('from', ''),
                    'to': message.get('to', ''),
                    'body': message.get('body', ''),
                    'type': message.get('type', ''),
                    'hasMedia': message.get('hasMedia', False),
                    'data_recebimento': data_recebimento
                }
                
                sessions_data[session_id]['messages'].append(message_info)
                
                # Se a mensagem contém imagem em base64 no body
                if message_info['type'] == 'image' and message_info['body'].startswith('/9j/'):
                    image_info = {
                        'message_id': message_info['id'],
                        'timestamp': message_info['timestamp'],
                        'base64_data': message_info['body'],
                        'from': message_info['from'],
                        'to': message_info['to'],
                        'data_recebimento': data_recebimento
                    }
                    sessions_data[session_id]['images'].append(image_info)
            
            elif data_type == 'media':
                # Processar dados de mídia
                media_data = item.get('conteudo', {}).get('data', {})
                message_media = media_data.get('messageMedia', {})
                message = media_data.get('message', {})
                
                if message_media.get('data'):
                    media_info = {
                        'message_id': message.get('id', {}).get('_serialized', ''),
                        'timestamp': datetime.fromtimestamp(message.get('timestamp', 0)).strftime('%Y-%m-%d %H:%M:%S'),
                        'mimetype': message_media.get('mimetype', ''),
                        'filesize': message_media.get('filesize', 0),
                        'base64_data': message_media.get('data', ''),
                        'from': message.get('from', ''),
                        'to': message.get('to', ''),
                        'data_recebimento': data_recebimento
                    }
                    sessions_data[session_id]['media_files'].append(media_info)
                    
                    # Se for imagem, adicionar também à lista de imagens
                    if message_media.get('mimetype', '').startswith('image/'):
                        sessions_data[session_id]['images'].append(media_info)
            
        except Exception as e:
            print(f"Erro ao processar item: {e}")
            continue
    
    return sessions_data

def save_images_to_files(sessions_data, output_dir='whatsapp_images'):
    """
    Salva as imagens base64 como arquivos
    """
    if not os.path.exists(output_dir):
        os.makedirs(output_dir)
    
    for session_id, session_data in sessions_data.items():
        session_dir = os.path.join(output_dir, session_id)
        if not os.path.exists(session_dir):
            os.makedirs(session_dir)
        
        for i, image in enumerate(session_data['images']):
            try:
                # Determinar extensão do arquivo
                mimetype = image.get('mimetype', 'image/jpeg')
                if 'jpeg' in mimetype:
                    ext = '.jpg'
                elif 'png' in mimetype:
                    ext = '.png'
                else:
                    ext = '.jpg'  # padrão
                
                # Nome do arquivo
                timestamp = image.get('timestamp', '').replace(':', '-').replace(' ', '_')
                filename = f"image_{i+1}_{timestamp}{ext}"
                filepath = os.path.join(session_dir, filename)
                
                # Decodificar e salvar
                image_data = base64.b64decode(image['base64_data'])
                with open(filepath, 'wb') as f:
                    f.write(image_data)
                
                print(f"Imagem salva: {filepath}")
                
            except Exception as e:
                print(f"Erro ao salvar imagem {i+1} da sessão {session_id}: {e}")

def print_session_summary(sessions_data):
    """
    Exibe um resumo dos dados por sessão
    """
    print("\n" + "="*60)
    print("RESUMO DOS DADOS POR SESSÃO")
    print("="*60)
    
    for session_id, session_data in sessions_data.items():
        print(f"\nSESSÃO: {session_id}")
        print("-" * 40)
        print(f"Mensagens: {len(session_data['messages'])}")
        print(f"Imagens: {len(session_data['images'])}")
        print(f"Arquivos de mídia: {len(session_data['media_files'])}")
        
        # Mostrar algumas mensagens
        if session_data['messages']:
            print("\nÚltimas mensagens:")
            for msg in session_data['messages'][-3:]:  # últimas 3
                print(f"  [{msg['timestamp']}] {msg['from']}: {msg['body'][:50]}...")
        
        # Mostrar informações das imagens
        if session_data['images']:
            print("\nImagens encontradas:")
            for i, img in enumerate(session_data['images'], 1):
                size_kb = len(img['base64_data']) * 3 / 4 / 1024  # estimativa do tamanho
                print(f"  {i}. [{img['timestamp']}] Tamanho: ~{size_kb:.1f}KB")

def save_session_data_to_json(sessions_data, output_file='sessions_organized.json'):
    """
    Salva os dados organizados em um novo arquivo JSON
    """
    # Criar uma versão sem os dados base64 para visualização
    summary_data = {}
    for session_id, session_data in sessions_data.items():
        summary_data[session_id] = {
            'messages': session_data['messages'],
            'images_count': len(session_data['images']),
            'media_files_count': len(session_data['media_files']),
            'images_info': [
                {
                    'message_id': img['message_id'],
                    'timestamp': img['timestamp'],
                    'from': img['from'],
                    'to': img['to'],
                    'size_kb': len(img['base64_data']) * 3 / 4 / 1024 if 'base64_data' in img else 0
                }
                for img in session_data['images']
            ]
        }
    
    with open(output_file, 'w', encoding='utf-8') as f:
        json.dump(summary_data, f, ensure_ascii=False, indent=2)
    
    print(f"\nDados organizados salvos em: {output_file}")

def main():
    json_file = 'chat_data.json'  # Substitua pelo caminho do seu arquivo
    
    print("Processando dados do WhatsApp...")
    sessions_data = process_whatsapp_data(json_file)
    
    print_session_summary(sessions_data)
    
    # Salvar imagens como arquivos
    save_images_to_files(sessions_data)
    
    # Salvar dados organizados em JSON
    save_session_data_to_json(sessions_data)
    
    print(f"\nProcessamento concluído!")
    print(f"Total de sessões encontradas: {len(sessions_data)}")

if __name__ == "__main__":
    main()