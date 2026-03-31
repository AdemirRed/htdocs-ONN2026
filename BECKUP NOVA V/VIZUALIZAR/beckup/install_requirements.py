import subprocess
import sys

def install_package(package):
    """Instala um pacote Python"""
    try:
        subprocess.check_call([sys.executable, "-m", "pip", "install", package])
        print(f"✅ {package} instalado com sucesso!")
    except subprocess.CalledProcessError:
        print(f"❌ Erro ao instalar {package}")

def main():
    """Instala as dependências necessárias"""
    packages = [
        "Pillow",  # Para manipulação de imagens
    ]
    
    print("🔧 Instalando dependências para a interface do WhatsApp...")
    
    for package in packages:
        install_package(package)
    
    print("\n✅ Instalação concluída!")
    print("Agora você pode executar: python whatsapp_interface.py")

if __name__ == "__main__":
    main()