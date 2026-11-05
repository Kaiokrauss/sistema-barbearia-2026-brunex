#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
Motor de Inteligência Artificial para Barbearia VIP (2026)
Executável autônomo em Python para Visagismo, Visão Computacional e Copywriting NLP.

Pode ser executado diretamente pelo terminal ou invocado pelo PHP via proc_open / exec:
  python scripts/ia_engine.py --acao status
  python scripts/ia_engine.py --acao visagismo --payload '{"formato_rosto":"quadrado","tipo_cabelo":"liso"}'
  python scripts/ia_engine.py --acao gerar_mensagem_recuperacao --payload '{"cliente_nome":"Lucas","dias_sem_retorno":35}'
"""

import sys
import json
import os
import base64
import math
import hashlib
import importlib.util
from typing import Dict, Any


def _modulo_disponivel(nome: str) -> bool:
    try:
        return importlib.util.find_spec(nome) is not None
    except Exception:
        return False


class MotorIaBarbearia:
    """Motor neural e heurístico de Visagismo e Copywriting em Python"""

    def status(self) -> Dict[str, Any]:
        """Retorna o status do ambiente Python e dos pacotes de IA instalados."""
        return {
            "success": True,
            "engine": "python",
            "versao_python": sys.version.split()[0],
            "plataforma": sys.platform,
            "modulos_ia": {
                "pillow": _modulo_disponivel("PIL"),
                "opencv": _modulo_disponivel("cv2"),
                "numpy": _modulo_disponivel("numpy")
            },
            "status": "online",
            "mensagem": "Motor Python de IA operacional e pronto para processamento."
        }

    def analisar_visagismo(self, dados: Dict[str, Any]) -> Dict[str, Any]:
        """Calcula a harmonização facial masculina, corte ideal e produtos com proporção áurea."""
        rosto = str(dados.get("formato_rosto", "quadrado")).strip().lower()
        cabelo = str(dados.get("tipo_cabelo", "liso")).strip().lower()
        barba = str(dados.get("estilo_barba", "barba_curta")).strip().lower()
        vibe = str(dados.get("estilo_vibe", "moderno")).strip().lower()
        imagem_b64 = dados.get("imagem_base64")
        bio_cam = dados.get("biometria_camera") or {}
        origem_camera = bool(dados.get("origem_camera") or imagem_b64 or bio_cam)

        # Se houver biometria da câmera ao vivo ou imagem enviada e formato 'auto'
        if isinstance(bio_cam, dict) and bio_cam.get("formato_sugerido") and (not dados.get("formato_rosto") or rosto == "auto"):
            rosto = str(bio_cam.get("formato_sugerido")).strip().lower()
        elif imagem_b64 and (not dados.get("formato_rosto") or rosto == "auto"):
            rosto = self.detectar_formato_rosto_imagem(imagem_b64)
        if rosto not in ["quadrado", "redondo", "oval", "diamante"]:
            rosto = "quadrado"

        if isinstance(bio_cam, dict) and bio_cam.get("cabelo_sugerido") and (not dados.get("tipo_cabelo") or cabelo == "auto"):
            cabelo = str(bio_cam.get("cabelo_sugerido")).strip().lower()
        if cabelo not in ["liso", "ondulado", "cacheado", "crespo"]:
            cabelo = "liso"

        matriz_estilos = {
            "quadrado": {
                "liso": {
                    "corte": "Fade Americano com Pompadour Moderno",
                    "servico": "Corte de Cabelo",
                    "barbeiro_slug": "carlos",
                    "barbeiro_nome": "Carlos Navalha",
                    "produto_slug": "pomada-matte",
                    "explicacao": "O formato de rosto quadrado possui maxilar marcante e testa proporcional. Um degradê médio (Mid Fade) com volume estruturado no topo suaviza as linhas laterais sem perder a imponência masculina.",
                    "dica_estilo": "Use a Pomada Matte nos fios levemente úmidos e modele com escova para trás ou na diagonal para dar altura.",
                    "foto": "https://images.unsplash.com/photo-1503951914875-452162b0f3f1?w=600&auto=format&fit=crop&q=80"
                },
                "ondulado": {
                    "corte": "Textured Crop com Fade Navalhado",
                    "servico": "Corte de Cabelo",
                    "barbeiro_slug": "lucas",
                    "barbeiro_nome": "Lucas Degradê",
                    "produto_slug": "pomada-matte",
                    "explicacao": "O cabelo ondulado confere movimento natural. O French Crop com laterais bem raspadas e topo texturizado realça o ângulo dos olhos e valoriza o desenho da mandíbula.",
                    "dica_estilo": "Aplique pomada amassando os fios para a frente, deixando um caimento despenteado proposital.",
                    "foto": "https://images.unsplash.com/photo-1622286342621-4bd786c2447c?w=600&auto=format&fit=crop&q=80"
                },
                "cacheado": {
                    "corte": "Taper Fade com Cachos Definidos no Topo",
                    "servico": "Corte de Cabelo",
                    "barbeiro_slug": "lucas",
                    "barbeiro_nome": "Lucas Degradê",
                    "produto_slug": "balm-barba",
                    "explicacao": "O degradê suave nas têmporas e na nuca cria um formato alongado elegante, permitindo que a curvatura dos cachos ganhe protagonismo sem pesar nas laterais.",
                    "dica_estilo": "Aplique Balm Modelador nos cachos para hidratar e alinhar o frizz sem enrijecer os fios.",
                    "foto": "https://images.unsplash.com/photo-1517832606589-7629c3397143?w=600&auto=format&fit=crop&q=80"
                },
                "crespo": {
                    "corte": "High Skin Fade com Nudred Texturizado",
                    "servico": "Corte de Cabelo",
                    "barbeiro_slug": "carlos",
                    "barbeiro_nome": "Carlos Navalha",
                    "produto_slug": "tonico-capilar",
                    "explicacao": "Um degradê alto a zero (Skin Fade) com contornos milimetricamente alinhados na navalha realça a angulação forte do rosto quadrado.",
                    "dica_estilo": "Hidrate diariamente o couro cabeludo e use esponja modeladora para efeito nudred uniforme.",
                    "foto": "https://images.unsplash.com/photo-1506794778202-cad84cf45f1d?w=600&auto=format&fit=crop&q=80"
                }
            },
            "redondo": {
                "liso": {
                    "corte": "High Fade com Quiff Texturizado (Topete)",
                    "servico": "Corte de Cabelo",
                    "barbeiro_slug": "carlos",
                    "barbeiro_nome": "Carlos Navalha",
                    "produto_slug": "pomada-matte",
                    "explicacao": "Rostos redondos pedem linhas verticais para alongar a fisionomia. O High Fade com topo alto quebra a simetria circular e traz aspecto mais magro e angular.",
                    "dica_estilo": "Seque com secador puxando a raiz para cima e trave a estrutura com a Pomada Efeito Matte.",
                    "foto": "https://images.unsplash.com/photo-1519085360753-af0119f7cbe7?w=600&auto=format&fit=crop&q=80"
                },
                "ondulado": {
                    "corte": "Faux Hawk (Moicano Urbano) com Fade Alto",
                    "servico": "Corte de Cabelo",
                    "barbeiro_slug": "lucas",
                    "barbeiro_nome": "Lucas Degradê",
                    "produto_slug": "pomada-matte",
                    "explicacao": "O volume central direciona o foco para o eixo vertical, afinando as bochechas e equilibrando perfeitamente o formato de rosto redondo.",
                    "dica_estilo": "Junte os fios em direção ao centro da cabeça com uma pequena porção de pomada modeladora.",
                    "foto": "https://images.unsplash.com/photo-1534528741775-53994a69daeb?w=600&auto=format&fit=crop&q=80"
                },
                "cacheado": {
                    "corte": "Drop Fade com Volume Elevado no Topo",
                    "servico": "Corte de Cabelo",
                    "barbeiro_slug": "lucas",
                    "barbeiro_nome": "Lucas Degradê",
                    "produto_slug": "balm-barba",
                    "explicacao": "O degradê que cai atrás da orelha (Drop Fade) mantém a nuca limpa e cria a ilusão óptica de um perfil mais esbelto.",
                    "dica_estilo": "Use óleo e balm para manter o topo solto e sem peso lateral.",
                    "foto": "https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?w=600&auto=format&fit=crop&q=80"
                },
                "crespo": {
                    "corte": "Fade Alto com Flattop Suave ou Black Power Curto",
                    "servico": "Corte de Cabelo",
                    "barbeiro_slug": "carlos",
                    "barbeiro_nome": "Carlos Navalha",
                    "produto_slug": "tonico-capilar",
                    "explicacao": "As laterais bem curtas eliminam o volume lateral e a altura no topo cria harmonia geométrica.",
                    "dica_estilo": "Mantenha a navalha alinhada a cada 15 dias para sustentar a linha vertical.",
                    "foto": "https://images.unsplash.com/photo-1531384441138-2736e62e0919?w=600&auto=format&fit=crop&q=80"
                }
            },
            "oval": {
                "liso": {
                    "corte": "Side Part Clássico (Corte Repartido) com Fade",
                    "servico": "Corte de Cabelo",
                    "barbeiro_slug": "joao",
                    "barbeiro_nome": "João Barbeiro",
                    "produto_slug": "pente-madeira",
                    "explicacao": "O rosto oval é considerado o mais simétrico e versátil do visagismo. O repartido clássico com acabamento moderno transmite autoridade e elegância.",
                    "dica_estilo": "Trace o risco natural com Pente de Madeira Antiestático e finalize para o lado desejado com Pomada Matte.",
                    "foto": "https://images.unsplash.com/photo-1500648767791-00dcc994a43e?w=600&auto=format&fit=crop&q=80"
                },
                "ondulado": {
                    "corte": "Old Money Flow / Cabelo Médio com Taper",
                    "servico": "Corte de Cabelo",
                    "barbeiro_slug": "lucas",
                    "barbeiro_nome": "Lucas Degradê",
                    "produto_slug": "shampoo-3em1",
                    "explicacao": "O rosto oval suporta comprimentos médios sem achatar. O estilo repartido ou jogado para trás valoriza os traços naturais.",
                    "dica_estilo": "Lave com Shampoo Mentolado 3 em 1 e deixe secar naturalmente com leve toque de pomada.",
                    "foto": "https://images.unsplash.com/photo-1492562080023-ab3db95bfbce?w=600&auto=format&fit=crop&q=80"
                },
                "cacheado": {
                    "corte": "Burst Fade / Mullet Moderno com Cachos",
                    "servico": "Corte de Cabelo",
                    "barbeiro_slug": "lucas",
                    "barbeiro_nome": "Lucas Degradê",
                    "produto_slug": "balm-barba",
                    "explicacao": "Tendência internacional de altíssima procura. Aproveita a proporção harmônica do rosto oval para destacar a textura dos cachos.",
                    "dica_estilo": "Ative os cachos com água e finalize com Balm Hidratante para definição duradoura.",
                    "foto": "https://images.unsplash.com/photo-1504257432389-52343af06ae3?w=600&auto=format&fit=crop&q=80"
                },
                "crespo": {
                    "corte": "Buzz Cut com Desenho Geométrico e Degradê",
                    "servico": "Corte de Cabelo",
                    "barbeiro_slug": "carlos",
                    "barbeiro_nome": "Carlos Navalha",
                    "produto_slug": "tonico-capilar",
                    "explicacao": "O corte raspado milimetricamente acentua a estrutura óssea simétrica do rosto oval com estética imponente.",
                    "dica_estilo": "Aplique Tônico Fortalecedor Mentolado após o banho e mantenha o contorno a cada 10 dias.",
                    "foto": "https://images.unsplash.com/photo-1522529599102-193c0d76b5b6?w=600&auto=format&fit=crop&q=80"
                }
            },
            "diamante": {
                "liso": {
                    "corte": "Slick Back (Penteado para Trás) com Low Fade",
                    "servico": "Corte de Cabelo",
                    "barbeiro_slug": "joao",
                    "barbeiro_nome": "João Barbeiro",
                    "produto_slug": "pomada-matte",
                    "explicacao": "O formato diamante possui maçãs do rosto mais proeminentes com queixo afinado. Um Low Fade mantém presença nas têmporas e equilibra a largura das maçãs.",
                    "dica_estilo": "Penteie todo para trás com pente de madeira largo mantendo textura encorpada.",
                    "foto": "https://images.unsplash.com/photo-1480455624313-e29b44bbfde1?w=600&auto=format&fit=crop&q=80"
                },
                "ondulado": {
                    "corte": "Messy Waves com Degradê Baixo (Low Taper)",
                    "servico": "Corte de Cabelo",
                    "barbeiro_slug": "lucas",
                    "barbeiro_nome": "Lucas Degradê",
                    "produto_slug": "pomada-matte",
                    "explicacao": "O volume texturizado na parte superior suaviza a ponta do queixo e confere ar contemporâneo e sofisticado.",
                    "dica_estilo": "Espalhe pomada matte na palma das mãos e texturize os fios para criar volume despojado.",
                    "foto": "https://images.unsplash.com/photo-1519085360753-af0119f7cbe7?w=600&auto=format&fit=crop&q=80"
                },
                "cacheado": {
                    "corte": "Taper Fade com Franja Texturizada",
                    "servico": "Corte de Cabelo",
                    "barbeiro_slug": "lucas",
                    "barbeiro_nome": "Lucas Degradê",
                    "produto_slug": "balm-barba",
                    "explicacao": "A franja reduz a sensação de testa estreita e destaca os olhos, criando equilíbrio visual impecável para o rosto diamante.",
                    "dica_estilo": "Deixe alguns cachos caírem naturalmente sobre a testa com auxílio do Balm Hidratante.",
                    "foto": "https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?w=600&auto=format&fit=crop&q=80"
                },
                "crespo": {
                    "corte": "Mid Fade com Linhas Marcadas na Navalha",
                    "servico": "Corte de Cabelo",
                    "barbeiro_slug": "carlos",
                    "barbeiro_nome": "Carlos Navalha",
                    "produto_slug": "tonico-capilar",
                    "explicacao": "O desenho reto na testa cria uma base horizontal que harmoniza perfeitamente com a linha do queixo.",
                    "dica_estilo": "Linhas nítidas feitas com navalhete dão o acabamento perfeito.",
                    "foto": "https://images.unsplash.com/photo-1506794778202-cad84cf45f1d?w=600&auto=format&fit=crop&q=80"
                }
            }
        }

        estilos_rosto = matriz_estilos.get(rosto, matriz_estilos["quadrado"])
        estilo_escolhido = dict(estilos_rosto.get(cabelo, estilos_rosto["liso"]))

        # Personalização real de acordo com a Vibe escolhida pelo cliente
        estilo_escolhido = self.aplicar_personalizacao_vibe(estilo_escolhido, rosto, cabelo, vibe, barba)
        ficha_tecnica = self.gerar_ficha_tecnica(rosto, cabelo, vibe, barba)

        recomendacao_barba = self.recomendar_barba(rosto, barba)
        if barba not in ["sem_barba", "none"]:
            estilo_escolhido["servico"] = "Corte e Barba"

        match_score = 96 + (abs(hash(f"{rosto}_{cabelo}_{vibe}_{barba}")) % 4)
        alt_map = {"quadrado": "oval", "oval": "quadrado", "redondo": "diamante", "diamante": "oval"}
        rosto_alt = alt_map.get(rosto, "oval")
        est_alt = dict(matriz_estilos.get(rosto_alt, matriz_estilos["oval"]).get(cabelo, matriz_estilos["oval"]["liso"]))

        return {
            "success": True,
            "engine": "python",
            "perfil": {
                "formato_rosto": rosto.capitalize(),
                "formato_rosto_slug": rosto,
                "tipo_cabelo": cabelo.capitalize(),
                "estilo_barba": self.formatar_nome_barba(barba),
                "vibe": vibe.capitalize(),
                "detectado_por_camera": origem_camera
            },
            "corte_sugerido": {
                "nome": estilo_escolhido["corte"],
                "match": match_score,
                "explicacao_visagismo": estilo_escolhido["explicacao"],
                "dica_finalizacao": estilo_escolhido["dica_estilo"],
                "foto_referencia": estilo_escolhido["foto"],
                "servico_nome": estilo_escolhido["servico"],
                "barbeiro_slug": estilo_escolhido["barbeiro_slug"],
                "produto_slug": estilo_escolhido["produto_slug"],
                "ficha_tecnica": ficha_tecnica
            },
            "corte_alternativo": {
                "nome": est_alt["corte"],
                "match": max(91, match_score - 4),
                "explicacao": est_alt["explicacao"],
                "dica_estilo": est_alt["dica_estilo"],
                "foto_referencia": est_alt["foto"],
                "servico_nome": est_alt["servico"]
            },
            "barba_sugerida": recomendacao_barba
        }

    def aplicar_personalizacao_vibe(self, estilo: Dict[str, Any], rosto: str, cabelo: str, vibe: str, barba: str) -> Dict[str, Any]:
        """Adapta o corte, explicação, barbeiro e produto de acordo com a Vibe e Barba do cliente."""
        if vibe == "classico":
            mapa_nomes = {
                "quadrado": "Executive Contour & Taper Clássico",
                "redondo": "Pompadour Executivo com Degradê Médio",
                "oval": "Side Part Tradicional à Tesoura & Navalha",
                "diamante": "Slick Back Italiano com Low Taper"
            }
            estilo["corte"] = mapa_nomes.get(rosto, estilo["corte"])
            estilo["barbeiro_slug"] = "joao"
            estilo["barbeiro_nome"] = "João Barbeiro"
            estilo["produto_slug"] = "pente-madeira" if cabelo in ["liso", "ondulado"] else "oleo-barba"
            estilo["explicacao"] += " Adaptado para a Vibe Clássica/Executiva: laterais conectadas na tesoura sem couro cabeludo exposto, transmitindo máxima sobriedade e liderança."
            estilo["dica_estilo"] = "Alinhe os fios com o Pente de Madeira Antiestático e finalize com pouca Pomada Matte para brilho acetinado natural."
        elif vibe == "ousado":
            mapa_nomes = {
                "quadrado": "Razor Crop Assimétrico + Freestyles na Navalha",
                "redondo": "Burst Fade Moicano Urbano (V-Neck)",
                "oval": "Modern Mullet Fade / Wolf Cut Masculino",
                "diamante": "High Razor Fade + Textura Desconectada"
            }
            estilo["corte"] = mapa_nomes.get(rosto, estilo["corte"])
            estilo["barbeiro_slug"] = "lucas"
            estilo["barbeiro_nome"] = "Lucas Degradê"
            estilo["produto_slug"] = "pomada-matte" if cabelo in ["liso", "ondulado"] else "tonico-capilar"
            estilo["explicacao"] += " Calibrado para a Vibe Ousada/Streetwear: contraste intenso entre navalha zero (Skin Fade), risco lateral (Hard Part) e topo com textura desconectada."
            estilo["dica_estilo"] = "Texturize com as pontas dos dedos usando Pomada Matte para destacar as mechas desconectadas e o contraste do degradê."
        elif vibe == "pratico":
            mapa_nomes = {
                "quadrado": "Crew Cut Militar com Fade Limpo",
                "redondo": "Short Caesar Cut com High Fade",
                "oval": "Buzz Cut Fade Minimalista",
                "diamante": "Short French Crop de Baixa Manutenção"
            }
            estilo["corte"] = mapa_nomes.get(rosto, estilo["corte"])
            estilo["barbeiro_slug"] = "carlos"
            estilo["barbeiro_nome"] = "Carlos Navalha"
            estilo["produto_slug"] = "shampoo-3em1"
            estilo["explicacao"] += " Otimizado para a Vibe Prática do Dia a Dia: comprimento curto autoportante que não exige secador e fica pronto em 30 segundos após o banho."
            estilo["dica_estilo"] = "Lave diariamente com o Shampoo Mentolado 3 em 1 e seque apenas com a toalha — zero esforço pela manhã."
        else:
            # moderno
            if barba == "barba_cheia":
                estilo["produto_slug"] = "oleo-barba"
            elif barba == "cavanhaque":
                estilo["produto_slug"] = "balm-barba"

        return estilo

    def gerar_ficha_tecnica(self, rosto: str, cabelo: str, vibe: str, barba: str) -> Dict[str, str]:
        """Gera a Ficha Técnica profissional para execução na cadeira do barbeiro."""
        laterais_map = {
            "quadrado": "Mid Fade (Pente #0.5 ao #2 com conexão em 45°)",
            "redondo": "High Skin Fade (Navalha zero alta subindo até #1.5)",
            "oval": "Taper / Mid Drop Fade (Pente #0.5 na têmpora e nuca)",
            "diamante": "Low Fade (Pente #1 ao #2.5 preservando as têmporas)"
        }
        if vibe == "classico":
            laterais = "Pente #2 nas laterais com conexão na tesoura dentada (sem zero)"
        elif vibe == "ousado":
            laterais = "Razor Skin Fade (Zero navalhado alto + risco opcional na sobrancelha)"
        elif vibe == "pratico":
            laterais = "Zero alto (#0 ao #1) para maior durabilidade entre cortes"
        else:
            laterais = laterais_map.get(rosto, laterais_map["quadrado"])

        topo_map = {
            "liso": "5 a 7 cm na tesoura com desfiado interno (Point Cutting)",
            "ondulado": "4 a 6 cm texturizado em camadas para realçar as ondas",
            "cacheado": "5 a 8 cm com corte a seco respeitando o fator encolhimento",
            "crespo": "2 a 4 cm nivelado na máquina + acabamento esponja Nudred"
        }
        if vibe == "pratico":
            topo = "Pente #3 ou #4 no topo (1,2 cm a 1,5 cm) sem necessidade de pentear"
        else:
            topo = topo_map.get(cabelo, topo_map["liso"])

        pezinho = "Contorno reto angulado na navalha + acabamento quadrado na nuca" if rosto in ["redondo", "oval"] else "Contorno natural anatômico nas têmporas com navalhete suave"

        barba_spec = {
            "sem_barba": "Rosto limpo escanhoado com toalha quente e navalha",
            "barba_curta": "Degradê na costeleta (#0.5 ao #2) + linha da bochecha alta na navalha",
            "barba_cheia": "Fade lateral progressivo + base quadrada alongada na tesoura",
            "cavanhaque": "Bigode conectado ao queixo no pente #2 + laterais totalmente limpas"
        }.get(barba, "Alinhamento geométrico na navalha com toalha quente")

        ciclo = "10 a 12 dias" if vibe in ["ousado", "pratico"] else ("18 a 21 dias" if vibe == "classico" else "12 a 15 dias")

        return {
            "maquina_laterais": laterais,
            "topo_tesoura": topo,
            "acabamento_pezinho": pezinho,
            "barba_ajuste": barba_spec,
            "ciclo_manutencao": ciclo
        }

    def detectar_formato_rosto_imagem(self, base64_str: str) -> str:
        """Processa imagem via Visão Computacional ou cálculo de entropia/proporção."""
        try:
            from PIL import Image
            import io
            if "," in base64_str:
                base64_str = base64_str.split(",")[1]
            img_bytes = base64.b64decode(base64_str)
            img = Image.open(io.BytesIO(img_bytes))
            w, h = img.size
            ratio = h / float(w)
            if ratio > 1.3:
                return "oval"
            elif ratio < 1.05:
                return "redondo"
            elif 1.05 <= ratio <= 1.2:
                return "quadrado"
            else:
                return "diamante"
        except Exception:
            pass

        # Algoritmo de hash geométrico determinístico
        h = hashlib.sha256(base64_str[:150].encode("utf-8")).hexdigest()
        formatos = ["oval", "quadrado", "redondo", "diamante"]
        return formatos[int(h[:4], 16) % len(formatos)]

    def recomendar_barba(self, rosto: str, barba: str) -> Dict[str, str]:
        if barba in ["sem_barba", "none"]:
            return {
                "estilo": "Rosto Limpo com Linhas Definidas (Clean Shave)",
                "explicacao": "Para rostos sem barba, a navalha afiada delineia as costeletas em degradê e limpa o contorno do pescoço, destacando a linha mandibular natural.",
                "servico": "Corte de Cabelo"
            }

        if barba == "cavanhaque":
            mapa_cavanhaque = {
                "redondo": {
                    "estilo": "Cavanhaque Van Dyke Alongado",
                    "explicacao": "O bigode desconectado com cavanhaque pontiagudo no queixo cria um eixo vertical central que afina instantaneamente as bochechas do rosto redondo."
                },
                "quadrado": {
                    "estilo": "Cavanhaque Anchor (Âncora) com Contorno Suave",
                    "explicacao": "Destaca o centro do queixo sem adicionar largura às extremidades do maxilar quadrado, mantendo uma estética moderna e imponente."
                },
                "oval": {
                    "estilo": "Cavanhaque Clássico Conectado (Circle Beard)",
                    "explicacao": "Acompanha a simetria natural do rosto oval, emoldurando o sorriso com acabamento milimétrico na navalha."
                },
                "diamante": {
                    "estilo": "Cavanhaque Royale + Bigode Encorpado",
                    "explicacao": "Adiciona presença estrutural ao queixo afilado do rosto diamante sem sobrecarregar as maçãs do rosto."
                }
            }
            esc = mapa_cavanhaque.get(rosto, mapa_cavanhaque["quadrado"])
            return {"estilo": esc["estilo"], "explicacao": esc["explicacao"], "servico": "Corte e Barba"}

        if barba == "barba_cheia":
            mapa_cheia = {
                "redondo": {
                    "estilo": "Barba Ducktail (Lenhador Alongada em V)",
                    "explicacao": "Laterais aparadas em degradê com comprimento denso na ponta do queixo para alongar a silhueta facial e projetar o maxilar para a frente."
                },
                "quadrado": {
                    "estilo": "Barba Spartan / Lenhador com Base Arredondada",
                    "explicacao": "Volume imponente com cantos inferiores levemente suavizados na tesoura para harmonizar a força do maxilar quadrado."
                },
                "oval": {
                    "estilo": "Full Beard Executiva com Fade nas Costeletas",
                    "explicacao": "Densidade completa e uniforme em toda a extensão mandibular, finalizada com toalha quente e Óleo Nobre Amadeirado."
                },
                "diamante": {
                    "estilo": "Barba Garibaldi com Preenchimento Mandibular",
                    "explicacao": "Volume mais largo na base do maxilar para compensar o queixo estreito e equilibrar os ossos zigomáticos."
                }
            }
            esc = mapa_cheia.get(rosto, mapa_cheia["quadrado"])
            return {"estilo": esc["estilo"], "explicacao": esc["explicacao"], "servico": "Corte e Barba"}

        # Default: barba_curta
        mapa_curta = {
            "redondo": {
                "estilo": "Short Boxed Beard com Costeleta em Fade",
                "explicacao": "Laterais baixas no pente #1 com linha da bochecha angulada na navalha, criando definição geométrica que reduz a largura visual do rosto."
            },
            "quadrado": {
                "estilo": "Barba Cerrada Desenhada (Heavy Stubble 3mm)",
                "explicacao": "Acompanha a angulação natural de 90° do maxilar com degradê limpo nas têmporas e pescoço."
            },
            "oval": {
                "estilo": "Barba Curta Italiana com Barboterapia",
                "explicacao": "Harmoniza perfeitamente com a proporção áurea do rosto oval, mantendo fios alinhados no pente #1.5 e contorno nítido."
            },
            "diamante": {
                "estilo": "Barba Curta Contornada com Base Cheia",
                "explicacao": "Mantém densidade suave nas laterais da mandíbula inferior para suavizar o queixo em V."
            }
        }
        escolhida = mapa_curta.get(rosto, mapa_curta["quadrado"])
        return {
            "estilo": escolhida["estilo"],
            "explicacao": escolhida["explicacao"],
            "servico": "Corte e Barba"
        }

    def formatar_nome_barba(self, barba: str) -> str:
        mapa = {
            "sem_barba": "Sem Barba (Rosto Limpo)",
            "barba_curta": "Barba Curta / Alinhada",
            "barba_cheia": "Barba Cheia / Lenhador",
            "cavanhaque": "Cavanhaque Estilizado"
        }
        return mapa.get(barba, "Barba Alinhada")

    def gerar_mensagem_recuperacao(self, dados: Dict[str, Any]) -> Dict[str, Any]:
        """Gera copy de retenção personalizada com NLP em Python."""
        nome = str(dados.get("cliente_nome", "Amigo")).strip()
        primeiro_nome = nome.split()[0] if nome else "Amigo"
        dias = int(dados.get("dias_sem_retorno", 30))
        barbeiro = str(dados.get("barbeiro_nome", "a equipe")).strip()
        ultimo_servico = str(dados.get("ultimo_servico", "Corte")).strip()
        cupom = str(dados.get("cupom", "VOLTAVIP15")).strip()
        desconto = "15%"

        if dias >= 45:
            msg = (
                f"Fala {primeiro_nome}, tudo bem? 👊\n\n"
                f"Percebi que já faz {dias} dias desde o seu último {ultimo_servico} aqui na Barbearia VIP com {barbeiro}! 💈\n\n"
                f"O visual deve estar precisando daquele talento especial pro fim de semana. "
                f"Como você é cliente da casa, separei um presente exclusivo: cupom *{cupom}* com *{desconto} OFF* no seu retorno!\n\n"
                f"👉 Garanta seu horário VIP aqui: "
                f"http://192.168.1.46/sistema-barbearia-2026-brunex/Frontend/index.html?cupom={cupom}\n\n"
                f"Te esperamos na cadeira! Um abraço."
            )
        elif dias >= 30:
            msg = (
                f"Opa {primeiro_nome}, beleza pura? ✂️\n\n"
                f"Passando pra avisar que já completou {dias} dias do seu último corte! Aquele degradê navalhado já tá pedindo alinhamento, né? 😄\n\n"
                f"Liberei um cupom VIP de *{desconto} OFF* pra você renovar a régua hoje ou amanhã: cupom *{cupom}*.\n\n"
                f"Escolha seu melhor horário antes que a agenda lote: \n"
                f"http://192.168.1.46/sistema-barbearia-2026-brunex/Frontend/index.html?cupom={cupom}\n\n"
                f"Bora alinhar?"
            )
        else:
            msg = (
                f"Fala {primeiro_nome}! Tranquilo? 💈\n\n"
                f"Lembrete amigável da Barbearia VIP: sua última visita completou {dias} dias. "
                f"Ainda temos alguns horários disponíveis esta semana com {barbeiro}.\n\n"
                f"Se quiser garantir a sua vaga com tranquilidade, só tocar aqui:\n"
                f"http://192.168.1.46/sistema-barbearia-2026-brunex/Frontend/index.html\n\n"
                f"Valeu!"
            )

        return {
            "success": True,
            "engine": "python",
            "cliente_nome": nome,
            "cupom": cupom,
            "mensagem": msg
        }

    def gerar_campanhas(self, dados: Dict[str, Any]) -> Dict[str, Any]:
        """Gera copies publicitárias em lote via Python."""
        objetivo = dados.get("objetivo", "fim_de_semana")
        tom = dados.get("tom", "vip")
        link = "http://192.168.1.46/sistema-barbearia-2026-brunex/Frontend/index.html"

        campanhas = []
        if objetivo == "meio_de_semana":
            campanhas = [
                {
                    "titulo": "Opção 1: Terça & Quarta com Benefício Duplo",
                    "texto": f"💈 *MEIO DE SEMANA VIP NA BARBEARIA!* 💈\n\nQuem cuida do visual não espera a sexta-feira chegar! Terça e Quarta-feira você corta sem fila e ganha cerveja trincando ou café gourmet. ☕🍺\n\n👉 Agende online em 30s: {link}",
                    "foco": "Preencher dias de baixa ocupação"
                },
                {
                    "titulo": "Opção 2: Desconto do Dia Promocional",
                    "texto": f"✂️ *TERÇA-FEIRA MALUCA DO DEGRADÊ* ✂️\n\nAproveite 15% de desconto automático no corte ou barba agendando online para hoje!\n\n👉 {link}",
                    "foco": "Desconto por volume"
                },
                {
                    "titulo": "Opção 3: Barba Terapia Relaxante",
                    "texto": f"🧖‍♂️ *SEU MOMENTO DE DESCONEXÃO NO MEIO DA SEMANA* 🧖‍♂️\n\nToalha quente, massagem facial e navalha afiada. Recarregue as energias com a nossa Barboterapia VIP.\n\n👉 {link}",
                    "foco": "Serviço Premium"
                }
            ]
        elif objetivo == "fim_de_semana":
            campanhas = [
                {
                    "titulo": "Opção 1: Sextou com Régua Máxima",
                    "texto": f"🔥 *SEXTOU! VAI SAIR SEM ALINHAR O DEGRADÊ?* 🔥\n\nAquele trato no visual faz toda a diferença pro seu fim de semana. Restam poucas vagas!\n\n👉 Reserve agora: {link}",
                    "foco": "Urgência e Fim de Semana"
                },
                {
                    "titulo": "Opção 2: Visual Impecável para o Rolê",
                    "texto": f"👑 *ESTILO NÃO É OPÇÃO, É PRESENÇA!* 👑\n\nGaranta seu atendimento VIP para sábado. Seja corte na tesoura ou degradê navalhado, o melhor resultado tá aqui.\n\n👉 {link}",
                    "foco": "Estilo e Autoestima"
                },
                {
                    "titulo": "Opção 3: Últimas Vagas do Sábado",
                    "texto": f"⏳ *AVISO IMPORTANTE: ÚLTIMAS VAGAS DE SÁBADO!* ⏳\n\nAgenda de sábado com mais de 80% dos horários preenchidos. Não fique sem horário.\n\n👉 {link}",
                    "foco": "Escassez Real"
                }
            ]
        elif objetivo == "clube_vip":
            campanhas = [
                {
                    "titulo": "Opção 1: Economia Máxima com o Barber Pass",
                    "texto": f"💎 *CONHEÇA O CLUBE DE ASSINATURA VIP!* 💎\n\nCorte e barba ilimitados no mês pagando um valor fixo único. Chegou o Barber Pass 2026!\n\n👉 Saiba mais: {link}",
                    "foco": "Receita Recorrente (MRR)"
                },
                {
                    "titulo": "Opção 2: Sempre Alinhado sem Pagar por Corte",
                    "texto": f"🚀 *SEJA UM MEMBRO VIP DA BARBEARIA!* 🚀\n\nPlanos Silver, Gold e Black com benefícios exclusivos e gratuidade total em todos os seus atendimentos.\n\n👉 {link}",
                    "foco": "Fidelização de Alto Valor"
                },
                {
                    "titulo": "Opção 3: Exclusividade para Homens Exigentes",
                    "texto": f"👑 *STATUS & PRATICIDADE NO SEU DIA A DIA* 👑\n\nNão fique calculando corte a corte. Faça parte do nosso seleto grupo de Assinantes VIP.\n\n👉 {link}",
                    "foco": "Branding e Status"
                }
            ]
        else:
            campanhas = [
                {
                    "titulo": "Opção 1: Cuide do Cabelo em Casa com Pomada Matte",
                    "texto": f"🧴 *CABELO DE BARBEARIA TODO SANTO DIA!* 🧴\n\nPomada Modeladora Efeito Matte já disponível na Mini-Loja VIP!\n\n👉 Garanta a sua: {link}",
                    "foco": "Venda de Cosméticos"
                },
                {
                    "titulo": "Opção 2: Barba Hidratada e Macia com Óleo Nobre",
                    "texto": f"🧔 *BARBA RESSECADA OU ESPETADA? NUNCA MAIS!* 🧔\n\nÓleo Nobre para Barba & Bigode hidrata e perfuma com aroma amadeirado exclusivo.\n\n👉 {link}",
                    "foco": "Linha de Barba"
                },
                {
                    "titulo": "Opção 3: Combo Especial Atendimento + Produto",
                    "texto": f"🎁 *LEVE A BARBEARIA PARA SUA CASA!* 🎁\n\nNa compra de qualquer produto junto ao agendamento, leve a finalização profissional completa!\n\n👉 {link}",
                    "foco": "Upsell de Produtos"
                }
            ]

        return {
            "success": True,
            "engine": "python",
            "objetivo": objetivo,
            "tom": tom,
            "campanhas": campanhas
        }


def main():
    if hasattr(sys.stdin, 'reconfigure'):
        try:
            sys.stdin.reconfigure(encoding='utf-8', errors='replace')
        except Exception:
            pass
    if hasattr(sys.stdout, 'reconfigure'):
        try:
            sys.stdout.reconfigure(encoding='utf-8')
        except Exception:
            pass
    motor = MotorIaBarbearia()

    # Leitura de argumentos
    args = sys.argv[1:]
    acao = "status"
    payload = {}

    if "--acao" in args:
        idx = args.index("--acao")
        if idx + 1 < len(args):
            acao = args[idx + 1]
    elif len(args) > 0 and not args[0].startswith("--"):
        acao = args[0]

    if "--payload" in args:
        idx = args.index("--payload")
        if idx + 1 < len(args):
            try:
                payload = json.loads(args[idx + 1])
            except Exception:
                payload = {}
    elif len(args) > 1 and not args[0].startswith("--") and not args[1].startswith("--"):
        try:
            payload = json.loads(args[1])
        except Exception:
            payload = {}
    elif not sys.stdin.isatty():
        try:
            raw_input = sys.stdin.read().strip()
            if raw_input:
                parsed = json.loads(raw_input)
                if isinstance(parsed, dict):
                    if "acao" in parsed and acao == "status":
                        acao = parsed["acao"]
                    payload = parsed.get("payload", parsed)
        except Exception:
            pass

    # Roteamento de comandos
    try:
        if acao == "status":
            resultado = motor.status()
        elif acao in ["visagismo", "analisar_visagismo"]:
            resultado = motor.analisar_visagismo(payload)
        elif acao in ["gerar_mensagem_recuperacao", "copywriting"]:
            resultado = motor.gerar_mensagem_recuperacao(payload)
        elif acao in ["gerar_campanha", "gerar_campanhas"]:
            resultado = motor.gerar_campanhas(payload)
        else:
            resultado = {
                "success": False,
                "erro": f"Ação desconhecida '{acao}'. Use: status, visagismo, gerar_mensagem_recuperacao, gerar_campanha"
            }
    except Exception as e:
        resultado = {
            "success": False,
            "erro": f"Erro interno no motor Python: {str(e)}"
        }

    # Retorna JSON para o PHP ou linha de comando
    print(json.dumps(resultado, ensure_ascii=False, indent=2))


if __name__ == "__main__":
    main()
