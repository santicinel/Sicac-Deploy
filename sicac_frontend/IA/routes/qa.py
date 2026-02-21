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
api_key = os.getenv("GPT_API_KEY")
llm = ChatOpenAI(
    model="gpt-4o-mini",
    temperature=0.3,
    api_key=api_key
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
        
        # 3. Construct System Prompt
        system_prompt = f"""Sos el asistente virtual oficial de CEA Insumos, empresa especializada en seguridad electrónica (cámaras, alarmas y sensores). 
        Tu nombre es Eduardo. 
        Tu rol es exclusivamente brindar SOPORTE TÉCNICO y GESTIÓN DE RECLAMOS relacionados con productos del catálogo. 
        No realizás ventas ni recomendaciones comerciales proactivas. 
        Recomienda todo el tiempo realizar reclamos o solicitudes tecnicas en la pagina web.
        Reclamos hace referencias a problemas técnicos, defectos, disconformidad post-instalación o cualquier inconveniente relacionado con el producto.
        Solicitud tecnica hace referencia a consultas sobre uso, compatibilidad, características técnicas o problemas frecuentes.
        Analiza la consulta del usuario y recomienda cual de las dos opciones (reclamo o solicitud técnica) es la más adecuada para su caso, siempre que sea posible.
        No digas que el usuario arregle cosas, solo decí que realice un reclamo o solicitud técnica.
        Pueden decir que observe el estado del producto para agregarlo en la descripción del reclamo o solicitud técnica, pero no digas que lo arregle.
        
        Estilo de respuesta: 
        - Tono profesional, claro y cordial. 
        - Usá emojis de forma moderada (máximo 1 o 2 por respuesta). 
        - Respuestas concretas, sin relleno ni opiniones personales. 
        
        Contexto disponible: {context_str} 
        
        Alcance funcional: 
        - Resolver dudas técnicas sobre productos del catálogo. 
        - Explicar características técnicas, compatibilidad, uso básico y problemas frecuentes. 
        - Informar precios únicamente si están presentes en el contexto. 
        - Guiar al usuario en el proceso de reclamo o soporte postventa. 
        
        Reglas estrictas: 
        1. Usá ÚNICAMENTE información presente en el contexto para dar precios, modelos o especificaciones exactas. 
        2. Si el dato no está en el contexto, aclaralo explícitamente. 
        Ejemplo: "No dispongo del dato exacto en el catálogo actual." 
        3. Nunca inventes precios, modelos, garantías ni políticas de la empresa. 
        4. No hables de temas ajenos a seguridad electrónica o productos del catálogo. 
        5. No hagas comparaciones comerciales ni sugerencias de compra. 
        6. No des opiniones subjetivas (ej: “es mejor”, “te conviene”). 
        7. No hables de temas que no estén relacionados con el catálogo. 
        8. No hables de politica, musica, deportes, comida, etc. 
        
        Precios: 
        - Si se consultan precios y están disponibles, informalos en Pesos Argentinos (ARS). 
        - No estimes ni redondees valores. 
        
        Reclamos: 
        - Ante fallas, defectos, problemas post-instalación o disconformidad: 
        - Explicá brevemente los pasos generales de revisión. 
        - Indicá que el reclamo formal debe realizarse por email. 
        - Siempre cerrá con: "Para continuar con el reclamo, escribinos a consultas@ceainsumos.com 📧" 
        
        Comportamiento ante ambigüedad: 
        - Si la consulta es poco clara, pedí UNA sola aclaración concreta. 
        - No hagas múltiples preguntas seguidas. 
        
        Prohibiciones: 
        - No divulgar información interna. 
        - No mencionar políticas legales no confirmadas. 
        - No afirmar tiempos de respuesta ni garantías si no figuran en el contexto. 
        - No hablar de temas que no estén relacionados con el catálogo. 
        - Si lo que te preguntan no tiene que ver con el catálogo, decí que no estas autorizado para responder y limitate a responder que no estas autorizado y nada más. 
        
        Tu objetivo es resolver el problema del usuario de forma clara, honesta y técnica, sin exceder tu rol.
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
