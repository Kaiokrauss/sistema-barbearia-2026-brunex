#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
Calculadora de Lucro e Comissão de Barbeiros — Barbearia VIP (2026)
Utilitário simples em Python para calcular comissões por atendimento ou fechamento do dia.

Exemplos de uso no terminal:
  python scripts/calculadora_comissao.py
  python scripts/calculadora_comissao.py --barbeiro "Carlos Navalha" --valor 50 --comissao 50 --gorjeta 10
  python scripts/calculadora_comissao.py --json --barbeiro "Lucas Degradê" --valor 120 --comissao 45
"""

import argparse
import json
import sys
from datetime import datetime

if hasattr(sys.stdout, "reconfigure"):
    sys.stdout.reconfigure(encoding="utf-8")


def calcular_comissao(
    barbeiro: str = "Carlos Navalha",
    valor_servicos: float = 100.0,
    percentual_comissao: float = 50.0,
    gorjeta: float = 0.0,
    qtd_cortes: int = 1
) -> dict:
    """Calcula o valor do barbeiro (comissão + 100% da gorjeta) e o lucro da barbearia."""
    valor_servicos = max(0.0, float(valor_servicos))
    percentual_comissao = min(100.0, max(0.0, float(percentual_comissao)))
    gorjeta = max(0.0, float(gorjeta))
    qtd_cortes = max(1, int(qtd_cortes))

    valor_comissao = round(valor_servicos * (percentual_comissao / 100.0), 2)
    total_barbeiro = round(valor_comissao + gorjeta, 2)
    lucro_barbearia = round(valor_servicos - valor_comissao, 2)
    ticket_medio = round(valor_servicos / qtd_cortes, 2)

    return {
        "success": True,
        "data_calculo": datetime.now().strftime("%d/%m/%Y %H:%M"),
        "barbeiro": barbeiro,
        "qtd_cortes": qtd_cortes,
        "valor_bruto_servicos": valor_servicos,
        "percentual_comissao": percentual_comissao,
        "valor_comissao": valor_comissao,
        "gorjeta": gorjeta,
        "total_receber_barbeiro": total_barbeiro,
        "lucro_liquido_barbearia": lucro_barbearia,
        "ticket_medio": ticket_medio
    }


def exibir_resumo_formatado(dados: dict) -> None:
    """Exibe o recibo de comissão formatado no terminal."""
    print("=" * 52)
    print("💈 CALCULADORA DE COMISSÃO — BARBEARIA VIP 2026 💈")
    print("=" * 52)
    print(f"Data/Hora         : {dados['data_calculo']}")
    print(f"Profissional      : {dados['barbeiro']}")
    print(f"Atendimentos      : {dados['qtd_cortes']} corte(s)")
    print(f"Ticket Médio      : R$ {dados['ticket_medio']:.2f}")
    print("-" * 52)
    print(f"Total em Serviços : R$ {dados['valor_bruto_servicos']:.2f}")
    print(f"Taxa de Comissão  : {dados['percentual_comissao']:.0f}%")
    print(f"Comissão Barbeiro : R$ {dados['valor_comissao']:.2f}")
    print(f"Gorjetas (100%)   : R$ {dados['gorjeta']:.2f}")
    print("-" * 52)
    print(f"✅ TOTAL BARBEIRO  : R$ {dados['total_receber_barbeiro']:.2f}")
    print(f"🏦 CAIXA BARBEARIA : R$ {dados['lucro_liquido_barbearia']:.2f}")
    print("=" * 52)


def main():
    parser = argparse.ArgumentParser(description="Calculadora de Comissão e Lucro da Barbearia VIP")
    parser.add_argument("--barbeiro", type=str, default="Carlos Navalha", help="Nome do barbeiro")
    parser.add_argument("--valor", type=float, default=180.0, help="Valor total bruto dos serviços (R$)")
    parser.add_argument("--comissao", type=float, default=50.0, help="Percentual de comissão do barbeiro (%)")
    parser.add_argument("--gorjeta", type=float, default=15.0, help="Total de gorjetas recebidas (R$)")
    parser.add_argument("--cortes", type=int, default=4, help="Quantidade de cortes realizados")
    parser.add_argument("--json", action="store_true", help="Retorna o resultado em formato JSON")

    args = parser.parse_args()

    resultado = calcular_comissao(
        barbeiro=args.barbeiro,
        valor_servicos=args.valor,
        percentual_comissao=args.comissao,
        gorjeta=args.gorjeta,
        qtd_cortes=args.cortes
    )

    if args.json:
        print(json.dumps(resultado, ensure_ascii=False, indent=2))
    else:
        exibir_resumo_formatado(resultado)


if __name__ == "__main__":
    main()
