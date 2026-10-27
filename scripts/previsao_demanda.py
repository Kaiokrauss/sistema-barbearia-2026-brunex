#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
Motor Preditivo de Demanda, Simulador de Metas & Detector Anti-Furo (No-Show) — Barbearia VIP (2026)
Script em Python que analisa o histórico de agendamentos, calcula a probabilidade de falta (No-Show),
simula o atingimento da meta financeira mensal e identifica horários ociosos para promoções Happy Hour.

Exemplos de uso no terminal:
  python scripts/previsao_demanda.py
  python scripts/previsao_demanda.py --meta 6000 --json
"""

import argparse
import json
import sys
from collections import defaultdict
from datetime import datetime
from typing import Dict, Any, List

if hasattr(sys.stdout, "reconfigure"):
    sys.stdout.reconfigure(encoding="utf-8")


HORARIOS_PADRAO = ["09:00", "10:00", "11:00", "13:00", "14:00", "15:00", "16:00", "17:00"]


def dados_exemplo() -> Dict[str, Any]:
    return {
        "meta_mensal": 5000.0,
        "ticket_medio_padrao": 50.0,
        "mrr_assinaturas": 359.60,
        "horarios_expediente": HORARIOS_PADRAO,
        "agendamentos": [
            {"id": 10, "cliente_nome": "Carlos Eduardo", "cliente_telefone": "11988887777", "servico_nome": "Corte + Barba VIP", "servico_preco": 75.0, "barbeiro_nome": "Carlos Navalha", "data_agendada": "2026-10-27", "horario": "10:00", "status": "ativo", "codigo": "VIP101"},
            {"id": 11, "cliente_nome": "Cliente Sem Telefone", "cliente_telefone": "", "servico_nome": "Corte Degradê", "servico_preco": 45.0, "barbeiro_nome": "Lucas Degradê", "data_agendada": "2026-10-28", "horario": "17:00", "status": "ativo", "codigo": "VIP102"},
            {"id": 12, "cliente_nome": "Rafael Costa", "cliente_telefone": "11977776666", "servico_nome": "Barboterapia", "servico_preco": 40.0, "barbeiro_nome": "João Barbeiro", "data_agendada": "2026-10-20", "horario": "16:00", "status": "cancelado", "codigo": "VIP103"},
            {"id": 13, "cliente_nome": "Rafael Costa", "cliente_telefone": "11977776666", "servico_nome": "Corte + Barba VIP", "servico_preco": 75.0, "barbeiro_nome": "João Barbeiro", "data_agendada": "2026-10-29", "horario": "16:00", "status": "ativo", "codigo": "VIP104"},
        ]
    }


def analisar_previsao(payload: Dict[str, Any]) -> Dict[str, Any]:
    agendamentos: List[Dict[str, Any]] = payload.get("agendamentos", [])
    meta_mensal = max(100.0, float(payload.get("meta_mensal", 5000.0)))
    mrr_assinaturas = float(payload.get("mrr_assinaturas", 0.0))
    horarios_exp = payload.get("horarios_expediente") or HORARIOS_PADRAO

    ativos = [a for a in agendamentos if str(a.get("status", "ativo")).lower() in ("ativo", "concluido")]
    cancelados = [a for a in agendamentos if str(a.get("status", "")).lower() == "cancelado"]

    receita_cortes = round(sum(float(a.get("servico_preco") or 0.0) for a in ativos), 2)
    receita_atual = round(receita_cortes + mrr_assinaturas, 2)
    ticket_medio = round(receita_cortes / len(ativos), 2) if ativos else float(payload.get("ticket_medio_padrao", 50.0))
    if ticket_medio <= 0:
        ticket_medio = 50.0

    # 1. Simulador de Meta Financeira & Projeção (Run-rate)
    falta_para_meta = max(0.0, round(meta_mensal - receita_atual, 2))
    progresso_meta_pct = min(100.0, round((receita_atual / meta_mensal) * 100.0, 1))
    cortes_necessarios = int((falta_para_meta / ticket_medio) + 0.999) if falta_para_meta > 0 else 0
    combos_necessarios = int((falta_para_meta / (ticket_medio * 1.6)) + 0.999) if falta_para_meta > 0 else 0

    dia_atual = max(1, datetime.now().day)
    ritmo_diario = round(receita_atual / dia_atual, 2)
    projecao_30_dias = round(ritmo_diario * 30, 2)

    # 2. Histórico por cliente/telefone para cálculo de Score Anti-Furo (No-Show)
    historico_cliente = defaultdict(lambda: {"ativos": 0, "cancelados": 0})
    for a in agendamentos:
        chave = (str(a.get("cliente_telefone") or "") or str(a.get("cliente_nome") or "")).strip().lower()
        st = str(a.get("status", "ativo")).lower()
        if st == "cancelado":
            historico_cliente[chave]["cancelados"] += 1
        else:
            historico_cliente[chave]["ativos"] += 1

    analise_noshow = []
    for a in ativos:
        nome = str(a.get("cliente_nome") or "Cliente")
        tel = str(a.get("cliente_telefone") or "").strip()
        tel_limpo = "".join(ch for ch in tel if ch.isdigit())
        horario = str(a.get("horario") or "00:00")[:5]
        data_ag = str(a.get("data_agendada") or "")
        servico = str(a.get("servico_nome") or "Corte")
        preco = float(a.get("servico_preco") or 0.0)
        chave = (tel or nome).strip().lower()

        hist = historico_cliente[chave]
        score_risco = 12  # Risco base (12%)
        motivos = []

        if hist["cancelados"] > 0:
            score_risco += min(55, hist["cancelados"] * 30)
            motivos.append(f"Possui {hist['cancelados']} cancelamento(s) no histórico")

        if len(tel_limpo) < 10:
            score_risco += 30
            motivos.append("Telefone ausente ou incompleto")

        if horario in ("09:00", "17:00", "18:00"):
            score_risco += 10
            motivos.append(f"Horário de ponta ({horario}) com maior índice de atraso")

        if hist["ativos"] >= 3 and hist["cancelados"] == 0:
            score_risco = max(5, score_risco - 10)
            motivos.append("Cliente fiel recorrente")

        score_risco = min(95, max(5, score_risco))

        if score_risco >= 50:
            nivel = "ALTO"
        elif score_risco >= 28:
            nivel = "MEDIO"
        else:
            nivel = "BAIXO"

        if not motivos:
            motivos.append("Agendamento regular dentro do padrão")

        msg_preventiva = (
            f"Olá *{nome}*! 💈 Passando para confirmar seu horário na *Barbearia VIP* "
            f"para *{data_ag}* às *{horario}* ({servico} - R$ {preco:.2f}). "
            f"Podemos manter sua vaga reservada? Responda SIM para confirmar! ✂️"
        )

        analise_noshow.append({
            "id": a.get("id"),
            "codigo": a.get("codigo", ""),
            "cliente_nome": nome,
            "cliente_telefone": tel,
            "telefone_limpo": f"55{tel_limpo}" if len(tel_limpo) in (10, 11) else tel_limpo,
            "servico_nome": servico,
            "servico_preco": preco,
            "data_agendada": data_ag,
            "horario": horario,
            "score_risco_pct": score_risco,
            "nivel_risco": nivel,
            "motivo_principal": " • ".join(motivos),
            "mensagem_preventiva": msg_preventiva
        })

    analise_noshow.sort(key=lambda x: x["score_risco_pct"], reverse=True)

    # 3. Mapa de Ocupação por Horário & Sugestão de Happy Hour (Precificação Dinâmica)
    contagem_horarios = {h[:5]: 0 for h in horarios_exp}
    for a in ativos:
        h = str(a.get("horario") or "")[:5]
        contagem_horarios[h] = contagem_horarios.get(h, 0) + 1

    max_ocup = max(contagem_horarios.values()) if contagem_horarios else 1
    if max_ocup <= 0:
        max_ocup = 1

    grade_demanda = []
    horarios_ociosos = []
    for h, qtd in sorted(contagem_horarios.items()):
        taxa_relativa = round((qtd / max_ocup) * 100.0, 0)
        if qtd == 0 or taxa_relativa <= 35:
            status_demanda = "BAIXA DEMANDA (Sugerir Happy Hour -15%)"
            horarios_ociosos.append(h)
        elif taxa_relativa >= 75:
            status_demanda = "ALTA PROCURA (Horário Nobre)"
        else:
            status_demanda = "DEMANDA NORMAL"

        grade_demanda.append({
            "horario": h,
            "atendimentos": qtd,
            "intensidade_pct": int(taxa_relativa),
            "classificacao": status_demanda
        })

    return {
        "success": True,
        "engine": "python",
        "versao_python": sys.version.split()[0],
        "gerado_em": datetime.now().strftime("%d/%m/%Y %H:%M:%S"),
        "simulacao_meta": {
            "meta_mensal": meta_mensal,
            "receita_atual": receita_atual,
            "falta_para_meta": falta_para_meta,
            "progresso_pct": progresso_meta_pct,
            "ticket_medio_atual": ticket_medio,
            "cortes_restantes": cortes_necessarios,
            "combos_restantes": combos_necessarios,
            "ritmo_diario": ritmo_diario,
            "projecao_30_dias": projecao_30_dias,
            "meta_atingida": falta_para_meta <= 0
        },
        "analise_noshow": analise_noshow,
        "grade_demanda": grade_demanda,
        "horarios_ociosos": horarios_ociosos,
        "dica_estrategica_python": (
            f"Para atingir a meta de R$ {meta_mensal:.2f}, você precisa de mais {cortes_necessarios} atendimento(s) "
            f"(ou {combos_necessarios} combos Corte + Produto). "
            + (f"Ative um cupom de 15% OFF para os horários ociosos ({', '.join(horarios_ociosos[:3])})!" if horarios_ociosos else "Sua grade está com excelente ocupação!")
        )
    }


def main():
    parser = argparse.ArgumentParser(description="Motor Preditivo de Demanda e Metas em Python")
    parser.add_argument("--meta", type=float, default=5000.0, help="Meta de faturamento mensal (R$)")
    parser.add_argument("--json", action="store_true", help="Saída em JSON")
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
        payload["meta_mensal"] = args.meta

    res = analisar_previsao(payload)
    print(json.dumps(res, ensure_ascii=False, indent=2))


if __name__ == "__main__":
    main()
