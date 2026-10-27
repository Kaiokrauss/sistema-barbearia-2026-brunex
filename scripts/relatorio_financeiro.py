#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
Gerador de Relatório Financeiro Executivo — Barbearia VIP (2026)
Motor analítico em Python para fechamento financeiro, ranking de barbeiros,
ranking de serviços, margem de lucro líquido, exportação CSV/Excel e resumo WhatsApp.

Exemplos de uso no terminal:
  python scripts/relatorio_financeiro.py
  python scripts/relatorio_financeiro.py --json
  python scripts/relatorio_financeiro.py --comissao 45 --custo-fixo 1200
"""

import argparse
import csv
import io
import json
import sys
from collections import defaultdict
from datetime import datetime
from typing import Dict, Any, List

if hasattr(sys.stdout, "reconfigure"):
    sys.stdout.reconfigure(encoding="utf-8")


def dados_exemplo() -> Dict[str, Any]:
    """Retorna dados de demonstração caso executado diretamente no terminal sem banco."""
    return {
        "periodo": datetime.now().strftime("%Y-%m"),
        "taxa_comissao": 50.0,
        "custo_fixo": 800.0,
        "mrr_assinaturas": 359.60,
        "total_assinantes_ativos": 4,
        "valor_estoque_produtos": 1450.00,
        "agendamentos": [
            {"id": 1, "cliente_nome": "Marcos Silva", "servico_nome": "Corte + Barba VIP", "servico_preco": 75.0, "barbeiro_nome": "Carlos Navalha", "data_agendada": "2026-10-25", "horario": "10:00", "status": "ativo"},
            {"id": 2, "cliente_nome": "Pedro Rocha", "servico_nome": "Corte Degradê", "servico_preco": 45.0, "barbeiro_nome": "Lucas Degradê", "data_agendada": "2026-10-25", "horario": "14:00", "status": "ativo"},
            {"id": 3, "cliente_nome": "Lucas Mendes", "servico_nome": "Corte + Barba VIP", "servico_preco": 75.0, "barbeiro_nome": "Carlos Navalha", "data_agendada": "2026-10-26", "horario": "15:00", "status": "ativo"},
            {"id": 4, "cliente_nome": "Rafael Costa", "servico_nome": "Barboterapia", "servico_preco": 40.0, "barbeiro_nome": "João Barbeiro", "data_agendada": "2026-10-26", "horario": "16:00", "status": "cancelado"},
            {"id": 5, "cliente_nome": "Gabriel Souza", "servico_nome": "Corte Degradê", "servico_preco": 45.0, "barbeiro_nome": "Lucas Degradê", "data_agendada": "2026-10-27", "horario": "10:00", "status": "ativo"},
        ]
    }


def processar_relatorio(payload: Dict[str, Any]) -> Dict[str, Any]:
    """Processa todos os indicadores financeiros, rankings e exportação CSV em Python."""
    agendamentos: List[Dict[str, Any]] = payload.get("agendamentos", [])
    taxa_comissao = float(payload.get("taxa_comissao", 50.0))
    custo_fixo = float(payload.get("custo_fixo", 0.0))
    mrr_assinaturas = float(payload.get("mrr_assinaturas", 0.0))
    total_assinantes = int(payload.get("total_assinantes_ativos", 0))
    valor_estoque = float(payload.get("valor_estoque_produtos", 0.0))
    periodo = str(payload.get("periodo", "Geral"))

    total_registros = len(agendamentos)
    ativos = [a for a in agendamentos if str(a.get("status", "ativo")).lower() in ("ativo", "concluido")]
    cancelados = [a for a in agendamentos if str(a.get("status", "")).lower() == "cancelado"]

    receita_servicos_confirmada = round(sum(float(a.get("servico_preco") or 0.0) for a in ativos), 2)
    receita_perdida_cancelados = round(sum(float(a.get("servico_preco") or 0.0) for a in cancelados), 2)
    faturamento_bruto_agendado = round(receita_servicos_confirmada + receita_perdida_cancelados, 2)

    # Receita total (Cortes Ativos + Assinaturas VIP MRR)
    receita_operacional_total = round(receita_servicos_confirmada + mrr_assinaturas, 2)

    # Comissões dos barbeiros (incide sobre os serviços confirmados)
    total_comissoes = round(receita_servicos_confirmada * (taxa_comissao / 100.0), 2)
    lucro_liquido = round(receita_operacional_total - total_comissoes - custo_fixo, 2)
    margem_lucro = round((lucro_liquido / receita_operacional_total) * 100.0, 1) if receita_operacional_total > 0 else 0.0

    ticket_medio = round(receita_servicos_confirmada / len(ativos), 2) if len(ativos) > 0 else 0.0
    taxa_cancelamento = round((len(cancelados) / total_registros) * 100.0, 1) if total_registros > 0 else 0.0
    taxa_conversao = round(100.0 - taxa_cancelamento, 1) if total_registros > 0 else 100.0

    # 1. Ranking de Barbeiros
    barbeiros_map: Dict[str, Dict[str, Any]] = defaultdict(lambda: {
        "barbeiro": "",
        "atendimentos": 0,
        "cancelados": 0,
        "receita_gerada": 0.0,
        "comissao_receber": 0.0,
        "lucro_casa": 0.0,
        "ticket_medio": 0.0,
        "participacao_pct": 0.0
    })

    for a in agendamentos:
        nome_b = str(a.get("barbeiro_nome") or "Profissional Geral").strip()
        preco = float(a.get("servico_preco") or 0.0)
        status = str(a.get("status", "ativo")).lower()

        item = barbeiros_map[nome_b]
        item["barbeiro"] = nome_b
        if status in ("ativo", "concluido"):
            item["atendimentos"] += 1
            item["receita_gerada"] = round(item["receita_gerada"] + preco, 2)
        elif status == "cancelado":
            item["cancelados"] += 1

    ranking_barbeiros = []
    for nome_b, dados_b in barbeiros_map.items():
        rec = dados_b["receita_gerada"]
        qtd = dados_b["atendimentos"]
        com = round(rec * (taxa_comissao / 100.0), 2)
        lucro_b = round(rec - com, 2)
        tkt = round(rec / qtd, 2) if qtd > 0 else 0.0
        part = round((rec / receita_servicos_confirmada) * 100.0, 1) if receita_servicos_confirmada > 0 else 0.0

        dados_b.update({
            "comissao_receber": com,
            "lucro_casa": lucro_b,
            "ticket_medio": tkt,
            "participacao_pct": part
        })
        ranking_barbeiros.append(dados_b)

    ranking_barbeiros.sort(key=lambda x: (x["receita_gerada"], x["atendimentos"]), reverse=True)

    # 2. Ranking de Serviços
    servicos_map: Dict[str, Dict[str, Any]] = defaultdict(lambda: {
        "servico": "",
        "quantidade": 0,
        "receita": 0.0,
        "participacao_pct": 0.0
    })

    for a in ativos:
        nome_s = str(a.get("servico_nome") or "Corte Masculino").strip()
        preco = float(a.get("servico_preco") or 0.0)
        s_item = servicos_map[nome_s]
        s_item["servico"] = nome_s
        s_item["quantidade"] += 1
        s_item["receita"] = round(s_item["receita"] + preco, 2)

    ranking_servicos = []
    for nome_s, dados_s in servicos_map.items():
        part = round((dados_s["receita"] / receita_servicos_confirmada) * 100.0, 1) if receita_servicos_confirmada > 0 else 0.0
        dados_s["participacao_pct"] = part
        ranking_servicos.append(dados_s)

    ranking_servicos.sort(key=lambda x: (x["receita"], x["quantidade"]), reverse=True)

    # 3. Horários de Pico
    horarios_map: Dict[str, int] = defaultdict(int)
    for a in ativos:
        h = str(a.get("horario") or "00:00")[:5]
        horarios_map[h] += 1

    horarios_pico = [
        {"horario": h, "atendimentos": qtd}
        for h, qtd in sorted(horarios_map.items(), key=lambda item: item[1], reverse=True)
    ]

    # 4. Geração de CSV (compatível com Microsoft Excel)
    csv_buffer = io.StringIO()
    writer = csv.writer(csv_buffer, delimiter=";")
    writer.writerow(["ID", "Data", "Horario", "Cliente", "Barbeiro", "Servico", "Valor (R$)", "Comissao Barbeiro (R$)", "Lucro Barbearia (R$)", "Status"])
    for a in agendamentos:
        preco = float(a.get("servico_preco") or 0.0)
        status = str(a.get("status", "ativo")).lower()
        com_item = round(preco * (taxa_comissao / 100.0), 2) if status in ("ativo", "concluido") else 0.0
        lucro_item = round(preco - com_item, 2) if status in ("ativo", "concluido") else 0.0
        writer.writerow([
            a.get("id", ""),
            a.get("data_agendada", ""),
            str(a.get("horario", ""))[:5],
            a.get("cliente_nome", ""),
            a.get("barbeiro_nome") or "Profissional Geral",
            a.get("servico_nome") or "Serviço",
            f"{preco:.2f}".replace(".", ","),
            f"{com_item:.2f}".replace(".", ","),
            f"{lucro_item:.2f}".replace(".", ","),
            status.upper()
        ])
    csv_conteudo = csv_buffer.getvalue()

    # 5. Resumo Executivo para WhatsApp
    top_barbeiro = ranking_barbeiros[0]["barbeiro"] if ranking_barbeiros else "N/A"
    top_servico = ranking_servicos[0]["servico"] if ranking_servicos else "N/A"
    resumo_whatsapp = (
        f"📊 *RELATÓRIO FINANCEIRO EXECUTIVO (PYTHON) - BARBEARIA VIP*\n"
        f"📅 *Período:* {periodo}\n\n"
        f"💰 *Receita Confirmada (Cortes):* R$ {receita_servicos_confirmada:.2f}\n"
        f"👑 *Receita Clube VIP (MRR):* R$ {mrr_assinaturas:.2f} ({total_assinantes} assinantes)\n"
        f"📈 *Receita Operacional Total:* R$ {receita_operacional_total:.2f}\n"
        f"✂️ *Comissões ({taxa_comissao:.0f}%):* R$ {total_comissoes:.2f}\n"
        f"🏦 *Lucro Líquido Barbearia:* R$ {lucro_liquido:.2f} (Margem: {margem_lucro:.1f}%)\n\n"
        f"🏆 *Top Barbeiro:* {top_barbeiro}\n"
        f"⭐ *Serviço Campeão:* {top_servico}\n"
        f"🎫 *Ticket Médio:* R$ {ticket_medio:.2f} ({len(ativos)} atendimentos ativos)"
    )

    return {
        "success": True,
        "engine": "python",
        "versao_python": sys.version.split()[0],
        "gerado_em": datetime.now().strftime("%d/%m/%Y %H:%M:%S"),
        "periodo": periodo,
        "metricas": {
            "total_agendamentos": total_registros,
            "atendimentos_ativos": len(ativos),
            "atendimentos_cancelados": len(cancelados),
            "taxa_conversao_pct": taxa_conversao,
            "taxa_cancelamento_pct": taxa_cancelamento,
            "faturamento_bruto_agendado": faturamento_bruto_agendado,
            "receita_servicos_confirmada": receita_servicos_confirmada,
            "receita_perdida_cancelados": receita_perdida_cancelados,
            "mrr_assinaturas": mrr_assinaturas,
            "total_assinantes_ativos": total_assinantes,
            "receita_operacional_total": receita_operacional_total,
            "taxa_comissao_pct": taxa_comissao,
            "total_comissoes_barbeiros": total_comissoes,
            "custo_fixo": custo_fixo,
            "lucro_liquido_barbearia": lucro_liquido,
            "margem_lucro_pct": margem_lucro,
            "ticket_medio": ticket_medio,
            "valor_estoque_produtos": valor_estoque
        },
        "ranking_barbeiros": ranking_barbeiros,
        "ranking_servicos": ranking_servicos,
        "horarios_pico": horarios_pico[:5],
        "resumo_whatsapp": resumo_whatsapp,
        "csv_conteudo": csv_conteudo
    }


def imprimir_relatorio_terminal(rel: Dict[str, Any]) -> None:
    m = rel["metricas"]
    print("=" * 64)
    print("🐍 RELATÓRIO FINANCEIRO EXECUTIVO (PYTHON) — BARBEARIA VIP 2026")
    print("=" * 64)
    print(f"Gerado em                 : {rel['gerado_em']} (Python {rel['versao_python']})")
    print(f"Período Analisado         : {rel['periodo']}")
    print(f"Atendimentos Confirmados  : {m['atendimentos_ativos']} (Cancelados: {m['atendimentos_cancelados']})")
    print(f"Ticket Médio              : R$ {m['ticket_medio']:.2f}")
    print("-" * 64)
    print(f"Receita de Cortes Ativos  : R$ {m['receita_servicos_confirmada']:.2f}")
    print(f"Receita Clube VIP (MRR)   : R$ {m['mrr_assinaturas']:.2f} ({m['total_assinantes_ativos']} assinantes)")
    print(f"RECEITA OPERACIONAL TOTAL : R$ {m['receita_operacional_total']:.2f}")
    print(f"(-) Comissões ({m['taxa_comissao_pct']:.0f}%)       : R$ {m['total_comissoes_barbeiros']:.2f}")
    print(f"(-) Custos Fixos          : R$ {m['custo_fixo']:.2f}")
    print("-" * 64)
    print(f"🏦 LUCRO LÍQUIDO REAL     : R$ {m['lucro_liquido_barbearia']:.2f} (Margem: {m['margem_lucro_pct']:.1f}%)")
    print("=" * 64)
    if rel["ranking_barbeiros"]:
        print("\n🏆 RANKING DE BARBEIROS:")
        for idx, b in enumerate(rel["ranking_barbeiros"], 1):
            print(f"  {idx}. {b['barbeiro']} — {b['atendimentos']} corte(s) | Gerou: R$ {b['receita_gerada']:.2f} | Comissão: R$ {b['comissao_receber']:.2f} ({b['participacao_pct']}%)")
    print("=" * 64)


def main():
    parser = argparse.ArgumentParser(description="Gerador de Relatório Financeiro Executivo da Barbearia VIP")
    parser.add_argument("--comissao", type=float, default=50.0, help="Taxa de comissão dos barbeiros (%)")
    parser.add_argument("--custo-fixo", type=float, default=0.0, help="Custos fixos mensais/período (R$)")
    parser.add_argument("--json", action="store_true", help="Retorna saída estritamente em JSON")
    parser.add_argument("--stdin", action="store_true", help="Lê payload JSON via stdin")
    args, _ = parser.parse_known_args()

    payload = None
    if args.stdin:
        try:
            raw = sys.stdin.read().strip()
            if raw:
                payload = json.loads(raw)
        except Exception:
            payload = None

    if not isinstance(payload, dict):
        payload = dados_exemplo()
        payload["taxa_comissao"] = args.comissao
        payload["custo_fixo"] = args.custo_fixo

    resultado = processar_relatorio(payload)

    if args.json or args.stdin:
        print(json.dumps(resultado, ensure_ascii=False, indent=2))
    else:
        imprimir_relatorio_terminal(resultado)


if __name__ == "__main__":
    main()

