#!/usr/bin/env python3
"""
Testes para o Sistema Drywall Performance v1.0
Executa validações de lógica, cálculos e integridade de dados.
Baseado em padrões de mercado para sistemas de gestão de obras/quotas.
"""

import json
import os
from datetime import datetime, timedelta

# Caminhos dos arquivos
BASE_DIR = r"d:\projetos\sistema_drywall_v1\sistema\dados"
CLIENTES_FILE = os.path.join(BASE_DIR, "clientes.json")
OS_FILE = os.path.join(BASE_DIR, "os.json")
PRECOS_FILE = os.path.join(BASE_DIR, "precos.json")

def load_json(file_path):
    """Carrega JSON com tratamento de erro."""
    try:
        with open(file_path, 'r', encoding='utf-8') as f:
            return json.load(f)
    except (FileNotFoundError, json.JSONDecodeError) as e:
        print(f"Erro ao carregar {file_path}: {e}")
        return []

def save_json(file_path, data):
    """Salva JSON."""
    with open(file_path, 'w', encoding='utf-8') as f:
        json.dump(data, f, indent=2, ensure_ascii=False)

# Funções simuladas do sistema (baseadas no código PHP)
def proximo_id_cliente(clientes):
    """Simula proximo_id_cliente."""
    if not clientes:
        return 1  # Corrigido: deveria ser 1, não 23
    ids = [c.get('id', 0) for c in clientes]
    return max(ids) + 1

def gerar_codigo_os(cliente_id, segmento, ano):
    """Simula gerar_codigo_os."""
    os_lista = load_json(OS_FILE)
    ano_str = str(ano)[-2:]
    prefixo = f"{cliente_id:03d}{segmento[0].upper()}{ano_str}"
    seq = sum(1 for os in os_lista if os.get('codigo', '').startswith(prefixo))
    return f"{prefixo}{seq+1:02d}"

def calcular_os(itens, desconto=0):
    """Calcula subtotal e total de OS."""
    subtotal = sum(float(i.get('total', 0)) for i in itens)
    total_geral = max(0, subtotal - desconto)
    return round(subtotal, 2), round(total_geral, 2)

def validar_cliente(cliente):
    """Valida dados de cliente."""
    required = ['nome', 'id']
    for r in required:
        if not cliente.get(r):
            return False, f"Campo obrigatório faltando: {r}"
    if cliente.get('tipo') not in ['PF', 'PJ']:
        return False, "Tipo inválido"
    return True, "OK"

def validar_os(os):
    """Valida dados de OS."""
    if not os.get('cliente_id') or not os.get('cliente_nome'):
        return False, "Cliente obrigatório"
    subtotal_calc, total_calc = calcular_os(os.get('itens', []), os.get('desconto', 0))
    if subtotal_calc != os.get('subtotal', 0) or total_calc != os.get('total_geral', 0):
        return False, f"Cálculo incorreto: esperado subtotal {subtotal_calc}, total {total_calc}"
    return True, "OK"

# Testes
def test_clientes():
    print("=== TESTE: Clientes ===")
    clientes = load_json(CLIENTES_FILE)
    erros = 0
    for c in clientes:
        ok, msg = validar_cliente(c)
        if not ok:
            print(f"Cliente {c.get('id')}: {msg}")
            erros += 1
    next_id = proximo_id_cliente(clientes)
    print(f"Próximo ID: {next_id}")
    print(f"Erros: {erros}")

def test_os():
    print("=== TESTE: OS ===")
    os_lista = load_json(OS_FILE)
    clientes = load_json(CLIENTES_FILE)
    cliente_ids = {c['id'] for c in clientes}
    erros = 0
    for os in os_lista:
        ok, msg = validar_os(os)
        if not ok:
            print(f"OS {os.get('codigo')}: {msg}")
            erros += 1
        if os.get('cliente_id') not in cliente_ids:
            print(f"OS {os.get('codigo')}: Cliente ID {os.get('cliente_id')} não existe")
            erros += 1
    print(f"Erros: {erros}")

def test_precos():
    print("=== TESTE: Preços ===")
    precos = load_json(PRECOS_FILE)
    erros = 0
    for p in precos:
        if not p.get('id') or not p.get('produto'):
            print(f"Preço ID {p.get('id')}: Dados incompletos")
            erros += 1
        if p.get('area') and p.get('preco'):
            custo_m2 = p['preco'] / p['area']
            custo_perda = custo_m2 * (1 + p.get('perda', 0)/100)
            print(f"Preço {p['produto']}: Custo/m² {custo_m2:.2f}, com perda {custo_perda:.2f}")
    print(f"Erros: {erros}")

def test_integridade():
    print("=== TESTE: Integridade Geral ===")
    clientes = load_json(CLIENTES_FILE)
    os_lista = load_json(OS_FILE)
    # Verificar referências
    cliente_ids = {c['id'] for c in clientes}
    os_cliente_ids = {os['cliente_id'] for os in os_lista if 'cliente_id' in os}
    if not os_cliente_ids.issubset(cliente_ids):
        print("OS com clientes inexistentes:", os_cliente_ids - cliente_ids)
    # Verificar códigos únicos
    codigos = [os.get('codigo') for os in os_lista]
    if len(codigos) != len(set(codigos)):
        print("Códigos duplicados:", [c for c in codigos if codigos.count(c) > 1])

def test_performance():
    print("=== TESTE: Performance ===")
    # Simular carga
    clientes = load_json(CLIENTES_FILE) * 100  # Simular 300 clientes
    os_lista = load_json(OS_FILE) * 100  # Simular 300 OS
    start = datetime.now()
    # Simular cálculo de dashboard
    total_os = len(os_lista)
    aprovadas = [o for o in os_lista if o.get('status') == 'aprovado']
    total_aprovado = sum(float(o.get('total_geral', 0)) for o in aprovadas)
    end = datetime.now()
    print(f"Tempo para 300 registros: {(end - start).total_seconds()}s")

# Sugestões de otimização baseadas em padrões de mercado
def sugestoes_otimizacao():
    print("=== SUGESTÕES DE OTIMIZAÇÃO (Padrões de Mercado) ===")
    print("1. **Banco de Dados**: Migrar de JSON para MySQL/PostgreSQL para escalabilidade e ACID.")
    print("2. **Autenticação**: Adicionar login/logout com sessões, além de .htaccess.")
    print("3. **API RESTful**: Padronizar todas operações via API (JSON API ou GraphQL).")
    print("4. **Relatórios**: Adicionar geração de PDFs (TCPDF), Excel (PHPExcel), dashboards com gráficos (Chart.js).")
    print("5. **Financeiro**: Módulo para contas a receber/pagar, fluxo de caixa, integrações com bancos.")
    print("6. **Notificações**: E-mails automáticos (PHPMailer), WhatsApp/SMS para status de OS.")
    print("7. **Mobile**: Responsividade com Bootstrap/Tailwind, PWA para acesso móvel.")
    print("8. **Backup**: Automação diária, armazenamento em nuvem (AWS S3).")
    print("9. **Testes**: Framework PHPUnit para testes unitários/integração.")
    print("10. **Segurança**: Sanitização de inputs, CSRF protection, logs de auditoria.")

if __name__ == "__main__":
    print("Iniciando testes do Sistema Drywall Performance...")
    test_clientes()
    test_os()
    test_precos()
    test_integridade()
    test_performance()
    sugestoes_otimizacao()
    print("Testes concluídos.")