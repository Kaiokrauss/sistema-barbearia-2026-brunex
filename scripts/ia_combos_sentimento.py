#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
IA 3 & 4 da Barbearia VIP (2026) — Código Didático e Fácil de Entender:
1) IA Calculadora de Tempo & Combo Ideal (calcula minutos na cadeira + desconto automático de pacote)
2) IA Analisadora de Sentimento das Avaliações (lê os comentários dos clientes e identifica elogios/críticas)

Como testar no terminal:
  python scripts/ia_combos_sentimento.py
"""

import argparse
import json
import sys
from typing import Dict, Any, List

if hasattr(sys.stdout, "reconfigure"):
    sys.stdout.reconfigure(encoding="utf-8")


# =====================================================================
# PARTE 1: IA CALCULADORA DE TEMPO NA CADEIRA & COMBO IDEAL
# =====================================================================

CATALOGO_ITENS = {
    "corte": {"nome": "Corte Masculino / Degradê", "preco": 45.00, "minutos": 35, "tipo": "servico"},
    "barba": {"nome": "Barba Completa / Barboterapia", "preco": 35.00, "minutos": 25, "tipo": "servico"},
    "sobrancelha": {"nome": "Alinhamento de Sobrancelha na Navalha", "preco": 15.00, "minutos": 10, "tipo": "servico"},
    "hidratacao": {"nome": "Hidratação & Massagem Capilar", "preco": 30.00, "minutos": 20, "tipo": "servico"},
    "pomada": {"nome": "Pomada Modeladora Efeito Matte (Para Casa)", "preco": 40.00, "minutos": 0, "tipo": "produto"},
    "oleo_barba": {"nome": "Óleo Nobre Hidratante para Barba (Para Casa)", "preco": 35.00, "minutos": 0, "tipo": "produto"},
}


def calcular_combo_ideal(chaves_escolhidas: List[str]) -> Dict[str, Any]:
    """
    Recebe a lista de itens que o cliente quer fazer (ex: ['corte', 'barba', 'sobrancelha']),
    soma os minutos de duração e aplica desconto inteligente de Combo se tiver 2 ou mais itens.
    """
    if not chaves_escolhidas:
        chaves_escolhidas = ["corte", "barba"]

    itens_detalhados = []
    preco_sem_desconto = 0.0
    tempo_total_minutos = 0

    for chave in chaves_escolhidas:
        if chave in CATALOGO_ITENS:
            item = CATALOGO_ITENS[chave]
            itens_detalhados.append(item)
            preco_sem_desconto += item["preco"]
            tempo_total_minutos += item["minutos"]

    qtd_itens = len(itens_detalhados)

    # Regra da IA para Desconto de Combo:
    # - 1 item: 0% de desconto
    # - 2 itens: 10% de desconto (Combo Duplo VIP)
    # - 3 ou mais itens: 15% de desconto (Combo Completo Rei da Barbearia)
    if qtd_itens >= 3:
        percentual_desconto = 15
        nome_combo = "👑 Combo Completo VIP (15% OFF)"
        explicacao_ia = "Como você escolheu 3 ou mais itens, a IA ativou o pacote máximo com 15% de desconto!"
    elif qtd_itens == 2:
        percentual_desconto = 10
        nome_combo = "✂️ Combo Duplo Inteligente (10% OFF)"
        explicacao_ia = "Combinando 2 itens na mesma visita, a IA aplicou 10% de desconto automático!"
    else:
        percentual_desconto = 0
        nome_combo = "Atendimento Individual"
        explicacao_ia = "Dica da IA: Adicione mais 1 serviço ou produto para destravar 10% de desconto no Combo!"

    valor_desconto = round(preco_sem_desconto * (percentual_desconto / 100.0), 2)
    preco_final_combo = round(preco_sem_desconto - valor_desconto, 2)

    # Formata o tempo em horas e minutos para ficar fácil de ler
    horas = tempo_total_minutos // 60
    mins = tempo_total_minutos % 60
    if horas > 0 and mins > 0:
        tempo_formatado = f"{horas}h e {mins} min ({tempo_total_minutos} min)"
    elif horas > 0:
        tempo_formatado = f"{horas}h ({tempo_total_minutos} min)"
    else:
        tempo_formatado = f"{tempo_total_minutos} minutos"

    return {
        "nome_combo": nome_combo,
        "quantidade_itens": qtd_itens,
        "itens": itens_detalhados,
        "tempo_total_minutos": tempo_total_minutos,
        "tempo_formatado": tempo_formatado,
        "preco_normal": round(preco_sem_desconto, 2),
        "percentual_desconto": percentual_desconto,
        "economia_reais": valor_desconto,
        "preco_final_combo": preco_final_combo,
        "explicacao_ia": explicacao_ia
    }


# =====================================================================
# PARTE 2: IA ANALISADORA DE SENTIMENTO DAS AVALIAÇÕES (COMENTÁRIOS)
# =====================================================================

PALAVRAS_POSITIVAS = [
    "otimo", "ótimo", "excelente", "perfeito", "top", "melhor", "bom", "boa",
    "recomendo", "parabens", "parabéns", "incrivel", "incrível", "pontual",
    "limpo", "educado", "mestre", "nota 10", "show", "sensacional", "gostei",
    "amei", "rapido", "rápido", "caprichoso", "capricho", "vip"
]

PALAVRAS_NEGATIVAS = [
    "ruim", "pessimo", "péssimo", "demorou", "atraso", "atrasado", "caro",
    "horrivel", "horrível", "errado", "mal", "feio", "sujo", "triste", "nunca"
]

TEMAS_ELOGIADOS = {
    "Qualidade do Corte & Degradê": ["corte", "degrade", "degradê", "navalha", "régua", "regua", "cabelo", "barba"],
    "Atendimento & Simpatia": ["atendimento", "educado", "atencioso", "conversa", "profissional", "equipe", "barbeiro"],
    "Pontualidade & Rapidez": ["pontual", "horario", "horário", "rapido", "rápido", "sem fila"],
    "Ambiente & Conforto VIP": ["ambiente", "cerveja", "café", "cafe", "limpo", "musica", "música", "ar condicionado"]
}


def analisar_comentario_unico(texto: str, nota: int = 5) -> Dict[str, Any]:
    """Analisa uma frase em português e diz se o sentimento é Positivo, Neutro ou Negativo."""
    texto_limpo = texto.lower()

    pontos_positivos = sum(1 for p in PALAVRAS_POSITIVAS if p in texto_limpo)
    pontos_negativos = sum(1 for p in PALAVRAS_NEGATIVAS if p in texto_limpo)

    # Soma peso da nota em estrelas (1 a 5)
    if nota >= 4:
        pontos_positivos += 1
    elif nota <= 2:
        pontos_negativos += 1

    if pontos_positivos > pontos_negativos:
        sentimento = "POSITIVO"
        icone = "🟢"
    elif pontos_negativos > pontos_positivos:
        sentimento = "NEGATIVO"
        icone = "🔴"
    else:
        sentimento = "NEUTRO"
        icone = "🟡"

    return {
        "sentimento": sentimento,
        "icone": icone,
        "pontos_positivos": pontos_positivos,
        "pontos_negativos": pontos_negativos
    }


def analisar_lista_avaliacoes(avaliacoes: List[Dict[str, Any]], texto_teste: str = "") -> Dict[str, Any]:
    """Analisa todas as avaliações da barbearia e descobre o índice de aprovação e temas mais elogiados."""
    resultados = []
    qtd_positivos = 0
    qtd_neutros = 0
    qtd_negativos = 0
    contagem_temas = {tema: 0 for tema in TEMAS_ELOGIADOS}

    for av in avaliacoes:
        comentario = str(av.get("comentario") or "")
        nota = int(av.get("nota") or 5)
        analise = analisar_comentario_unico(comentario, nota)

        if analise["sentimento"] == "POSITIVO":
            qtd_positivos += 1
        elif analise["sentimento"] == "NEGATIVO":
            qtd_negativos += 1
        else:
            qtd_neutros += 1

        # Conta quais temas foram elogiados no comentário
        comentario_lower = comentario.lower()
        for tema, palavras in TEMAS_ELOGIADOS.items():
            if any(p in comentario_lower for p in palavras):
                contagem_temas[tema] += 1

        resultados.append({
            "cliente_nome": av.get("cliente_nome", "Cliente"),
            "servico_nome": av.get("servico_nome", "Corte"),
            "nota": nota,
            "comentario": comentario,
            "sentimento": analise["sentimento"],
            "icone": analise["icone"]
        })

    total = len(resultados)
    indice_aprovacao = round((qtd_positivos / total) * 100.0, 1) if total > 0 else 100.0

    # Ordena os temas mais elogiados
    ranking_elogios = [
        {"tema": t, "mencoes": qtd}
        for t, qtd in sorted(contagem_temas.items(), key=lambda x: x[1], reverse=True)
    ]

    # Se o usuário digitou uma frase de teste ao vivo
    resultado_teste_ao_vivo = None
    if texto_teste.strip():
        resultado_teste_ao_vivo = analisar_comentario_unico(texto_teste, 3)
        resultado_teste_ao_vivo["frase_digitada"] = texto_teste

    return {
        "total_analisados": total,
        "positivos": qtd_positivos,
        "neutros": qtd_neutros,
        "negativos": qtd_negativos,
        "indice_felicidade_pct": indice_aprovacao,
        "ranking_elogios": ranking_elogios,
        "avaliacoes_classificadas": resultados,
        "teste_ao_vivo": resultado_teste_ao_vivo
    }


def main():
    parser = argparse.ArgumentParser(description="IA de Combos/Tempo e Análise de Sentimento em Python")
    parser.add_argument("--stdin", action="store_true", help="Lê dados via stdin JSON")
    args, _ = parser.parse_known_args()

    payload = {}
    if args.stdin:
        try:
            raw = sys.stdin.read().strip()
            if raw:
                payload = json.loads(raw)
        except Exception:
            payload = {}

    itens_escolhidos = payload.get("itens_combo", ["corte", "barba", "pomada"])
    avaliacoes_banco = payload.get("avaliacoes", [
        {"cliente_nome": "Lucas Almeida", "servico_nome": "Corte Degradê", "nota": 5, "comentario": "Atendimento excelente, o degradê ficou perfeito e o ambiente é muito limpo!"},
        {"cliente_nome": "Marcos Paulo", "servico_nome": "Corte + Barba", "nota": 5, "comentario": "Barbeiro muito pontual e caprichoso, recomendo demais!"},
        {"cliente_nome": "João Pedro", "servico_nome": "Barboterapia", "nota": 4, "comentario": "Muito bom o corte e a cerveja gelada, nota 10."}
    ])
    texto_teste = str(payload.get("texto_teste", ""))

    combo_res = calcular_combo_ideal(itens_escolhidos)
    sentimento_res = analisar_lista_avaliacoes(avaliacoes_banco, texto_teste)

    resposta = {
        "success": True,
        "engine": "python",
        "versao_python": sys.version.split()[0],
        "catalogo_disponivel": CATALOGO_ITENS,
        "combo_calculado": combo_res,
        "sentimento_avaliacoes": sentimento_res
    }

    print(json.dumps(resposta, ensure_ascii=False, indent=2))


if __name__ == "__main__":
    main()
