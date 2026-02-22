import os
import json
from typing import List
from fastapi import APIRouter, HTTPException
from pydantic import BaseModel
from langchain_openai import ChatOpenAI
from langchain_core.messages import HumanMessage, SystemMessage, AIMessage

router = APIRouter()

# Load Product Catalog
# Adjust path: routes/../kb/catalogo.json -> ../kb/catalogo.json
CATALOG_PATH = os.path.join(os.path.dirname(__file__), "..", "kb", "catalogo_sielse_normalizado.json")
PRODUCTS = []

try:
    with open(CATALOG_PATH, "r", encoding="utf-8") as f:
        data = json.load(f)
        if isinstance(data, list):
            PRODUCTS = data
        elif isinstance(data, dict):
            PRODUCTS = data.get("products", []) or data.get("Productos", [])
        else:
            PRODUCTS = []
        print(f"[INFO] Loaded {len(PRODUCTS)} products from catalog.")
except Exception as e:
        print(f"[ERROR] Error loading catalog: {e}")

# Helper: Simple Search (Keywords)
def find_relevant_products(query: str, limit: int = 5):
    query = query.lower()
    matches = []
    
    for p in PRODUCTS:
        # Search in Name, ID, Family, Subfamily
        text = f"{p.get('Nombre', '')} {p.get('ID', '')} {p.get('familia', '')} {p.get('subfamilia', '')}".lower()
        
        # Scoring: Count how many query words appear in text
        score = 0
        words = query.split()
        for w in words:
            if w in text:
                score += 1
        
        if score > 0:
            matches.append((score, p))
    
    # Sort by score desc
    matches.sort(key=lambda x: x[0], reverse=True)
    return [m[1] for m in matches[:limit]]


# Initialize LLM
api_key = (os.getenv("GPT_API_KEY") or os.getenv("OPENAI_API_KEY") or "").strip()
llm = (
    ChatOpenAI(
        model="gpt-4o-mini",
        temperature=0.3,
        api_key=api_key
    )
    if api_key
    else None
)


def build_fallback_response(relevant_products: List[dict]) -> str:
    intro = (
        "La IA avanzada esta en modo demo porque falta configurar "
        "GPT_API_KEY/OPENAI_API_KEY en el servicio de IA."
    )
    if not relevant_products:
        return (
            f"{intro} No encontre productos claramente relacionados en el catalogo local. "
            "Si queres, contame modelo, familia y falla para orientarte mejor."
        )

    lines = []
    for p in relevant_products[:3]:
        lines.append(
            f"- {p.get('Nombre', 'Producto')} (SKU: {p.get('ID', 'N/D')}) | Precio: ${p.get('Precio (ARS)', 'Consultar')}"
        )
    return (
        f"{intro}\n\nProductos que podrian servirte como referencia:\n"
        + "\n".join(lines)
        + "\n\nPara respuestas conversacionales completas, agrega una API key y reinicia el servicio ai."
    )

class QuestionRequest(BaseModel):
    messages: list

@router.post("/chat")
def chat_endpoint(request: QuestionRequest):
    try:
        # 1. Identify User Query
        user_query = ""
        for msg in reversed(request.messages):
            if msg['role'] == 'user':
                user_query = msg['content']
                break
        
        # 2. Retrieve Context (RAG)
        relevant_products = find_relevant_products(user_query)
        context_str = ""
        if relevant_products:
            context_str = "\nPRODUCTOS RELEVANTES ENCONTRADOS EN EL CATÁLOGO:\n"
            for p in relevant_products:
                price = f"${p.get('Precio (ARS)', 'Consultar')}"
                context_str += f"- {p.get('Nombre')} (SKU: {p.get('ID')}) - Precio: {price}\n  Categoría: {p.get('familia')} > {p.get('subfamilia')}\n  Detalles: {p.get('Texto_RAG', '')[:200]}...\n"
        if llm is None:
            return {"response": build_fallback_response(relevant_products)}


        system_prompt = f"""Sos el asistente virtual oficial de CEA Insumos, empresa de seguridad electronica.
        Tu nombre es Eduardo.
        Tu rol es brindar soporte tecnico y gestion de reclamos sobre productos del catalogo.
        No hagas ventas proactivas ni recomendaciones comerciales.

        Contexto disponible:
        {context_str}

        Reglas:
        1. Usa solo datos del contexto para precios, modelos y especificaciones.
        2. Si falta informacion, decilo explicitamente.
        3. No inventes precios, garantias ni politicas.
        4. Si la consulta no es del catalogo, responde que no estas autorizado.
        5. Ante fallas, sugiere abrir reclamo o solicitud tecnica.

        Estilo:
        - Profesional, claro y breve.
        - Una sola pregunta de aclaracion si hace falta.
        """

        # 4. Build Message History
        lc_messages = [SystemMessage(content=system_prompt)]
        
        for msg in request.messages:
            if msg['role'] == 'user':
                lc_messages.append(HumanMessage(content=msg['content']))
            elif msg['role'] == 'assistant':
                lc_messages.append(AIMessage(content=msg['content']))

        # 5. Invoke LLM
        response = llm.invoke(lc_messages)
        
        return {"response": response.content}

    except Exception as e:
        print(f"Error: {e}")
        raise HTTPException(status_code=500, detail=str(e))

