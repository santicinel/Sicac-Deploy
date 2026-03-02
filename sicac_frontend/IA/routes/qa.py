import json
import os
from typing import List

from fastapi import APIRouter, HTTPException
from langchain_core.messages import AIMessage, HumanMessage, SystemMessage
from pydantic import BaseModel

from routes.llm_utils import build_llm_clients, invoke_with_fallback

router = APIRouter()

CATALOG_PATH = os.path.join(
    os.path.dirname(__file__), "..", "kb", "catalogo_sielse_normalizado.json"
)
PRODUCTS = []

try:
    with open(CATALOG_PATH, "r", encoding="utf-8") as file_handle:
        data = json.load(file_handle)
        if isinstance(data, list):
            PRODUCTS = data
        elif isinstance(data, dict):
            PRODUCTS = data.get("products", []) or data.get("Productos", [])
        else:
            PRODUCTS = []
        print(f"[INFO] Loaded {len(PRODUCTS)} products from catalog.")
except Exception as exc:
    print(f"[ERROR] Error loading catalog: {exc}")


def find_relevant_products(query: str, limit: int = 5):
    query_text = (query or "").lower()
    matches = []

    for product in PRODUCTS:
        text = (
            f"{product.get('Nombre', '')} "
            f"{product.get('ID', '')} "
            f"{product.get('familia', '')} "
            f"{product.get('subfamilia', '')}"
        ).lower()

        score = 0
        for word in query_text.split():
            if word in text:
                score += 1

        if score > 0:
            matches.append((score, product))

    matches.sort(key=lambda item: item[0], reverse=True)
    return [item[1] for item in matches[:limit]]


llm_clients = build_llm_clients(temperature=0.25)


def build_fallback_response(relevant_products: List[dict]) -> str:
    intro = (
        "Asistente en modo demo: falta configurar GPT_API_KEY/OPENAI_API_KEY."
    )
    if not relevant_products:
        return (
            f"{intro}\n\n"
            "Resumen rapido: no encontre productos claros en el catalogo.\n"
            "Para avanzar necesito:\n"
            "- Marca y modelo del equipo\n"
            "- Sintoma principal y desde cuando ocurre"
        )

    lines = []
    for product in relevant_products[:3]:
        lines.append(
            f"- {product.get('Nombre', 'Producto')} "
            f"(SKU: {product.get('ID', 'N/D')}) "
            f"| Precio: ${product.get('Precio (ARS)', 'Consultar')}"
        )
    return (
        f"{intro}\n\n"
        "Resumen rapido: encontre productos relacionados.\n"
        "Referencias utiles:\n"
        + "\n".join(lines)
        + "\n\nCuando quieras, te ayudo a armar el asunto y la descripcion del ticket."
    )


SUPPORT_SYSTEM_PROMPT_TEMPLATE = """
Sos Gustavo, asistente de CEA Insumos para Soporte y Reclamos.
Tu trabajo es ayudar a cargar tickets sin abrumar.
No vendas productos ni inventes politicas.

Contexto del catalogo:
{context_str}

Reglas:
1. Usa solo el contexto para modelos, precios y datos tecnicos.
2. Si falta informacion, decilo con claridad y pedi solo lo minimo.
3. Nunca pidas contrasenas, codigos 2FA ni datos bancarios.
4. Si hay riesgo electrico, indica cortar energia y contactar tecnico.
5. Si reporta falla/problema -> sugeri "Iniciar reclamo".
6. Si pide ayuda, configuracion o visita sin falla confirmada -> sugeri "Solicitud tecnica".

Formato visual obligatorio en cada respuesta:
- "Resumen rapido:" una sola linea (max 18 palabras).
- "Siguiente paso:" con 1 o 2 bullets cortos.
- Si faltan datos: "Para avanzar necesito:" con maximo 2 preguntas.
- Cuando ya alcance la info, agrega:
  Tipo de caso:
  Categoria sugerida:
  Asunto sugerido:
  Descripcion sugerida:
  - Producto:
  - Problema:
  - Desde:
  - Instalacion/Conexion:
  - Ya probado:
  - Necesito:

Limites de estilo:
- Maximo 110 palabras.
- Evita parrafos largos; usa saltos de linea y bullets.
- Nada de texto redundante.
- Cierra con: "Cuando quieras, lo enviamos."
"""


class QuestionRequest(BaseModel):
    messages: list


@router.post("/chat")
def chat_endpoint(request: QuestionRequest):
    try:
        user_query = ""
        for message in reversed(request.messages):
            if message.get("role") == "user":
                user_query = str(message.get("content", ""))
                break

        relevant_products = find_relevant_products(user_query)
        context_str = "Sin productos relevantes del catalogo para esta consulta."
        if relevant_products:
            lines = []
            for product in relevant_products:
                price = f"${product.get('Precio (ARS)', 'Consultar')}"
                lines.append(
                    f"- {product.get('Nombre')} (SKU: {product.get('ID')}) | Precio: {price}\n"
                    f"  Categoria: {product.get('familia')} > {product.get('subfamilia')}\n"
                    f"  Detalle: {product.get('Texto_RAG', '')[:180]}..."
                )
            context_str = "\n".join(lines)

        if not llm_clients:
            return {"response": build_fallback_response(relevant_products)}

        system_prompt = SUPPORT_SYSTEM_PROMPT_TEMPLATE.format(context_str=context_str)
        lc_messages = [SystemMessage(content=system_prompt)]

        for message in request.messages:
            role = message.get("role")
            content = str(message.get("content", ""))
            if role == "user":
                lc_messages.append(HumanMessage(content=content))
            elif role == "assistant":
                lc_messages.append(AIMessage(content=content))

        response, _ = invoke_with_fallback(llm_clients, lc_messages)
        return {"response": str(response.content)}

    except Exception as exc:
        print(f"Error in qa chat: {exc}")
        raise HTTPException(status_code=500, detail=str(exc))
