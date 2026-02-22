import os
import json
from typing import List, Optional, Dict, Any, Tuple
from fastapi import APIRouter, HTTPException
from pydantic import BaseModel
from langchain_openai import ChatOpenAI
from langchain_core.messages import HumanMessage, SystemMessage, AIMessage
from pypdf import PdfReader

router = APIRouter()

# --- 1. Load Catalog ---
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
        print(f"[INFO] ESTOY RECOMENDADOR: Loaded {len(PRODUCTS)} products from catalog.")
except Exception as e:
    print(f"[ERROR] Error loading catalog in recommender: {e}")

# --- 1b. Load Manuals (PDF) ---
MANUALS_DIR = os.path.join(os.path.dirname(__file__), "..", "kb", "manuales")
MANUAL_CHUNKS: Dict[str, List[Dict[str, str]]] = {}


def chunk_text(text: str, chunk_size: int = 1200, overlap: int = 200) -> List[str]:
    if not text:
        return []
    if chunk_size <= 0:
        return []
    overlap = max(0, min(overlap, chunk_size - 1))

    chunks = []
    start = 0
    length = len(text)
    while start < length:
        end = min(start + chunk_size, length)
        chunks.append(text[start:end].strip())
        if end >= length:
            break
        start = end - overlap
    return [c for c in chunks if c]


def manual_key_from_filename(filename: str) -> Optional[str]:
    name = filename.lower()
    if "cctv" in name:
        return "cctv"
    if "alarmas" in name or "alarma" in name:
        return "alarmas"
    if "acceso" in name:
        return "acceso"
    if "vehiculos" in name or "vehiculo" in name:
        return "vehiculos"
    if "infraestructura" in name or "infra" in name:
        return "infraestructura"
    if "otros" in name:
        return "otros"
    return None


def load_manuals():
    if not os.path.isdir(MANUALS_DIR):
        print(f"[WARN] Manuals directory not found: {MANUALS_DIR}")
        return
    for filename in os.listdir(MANUALS_DIR):
        if not filename.lower().endswith(".pdf"):
            continue
        manual_key = manual_key_from_filename(filename)
        if not manual_key:
            continue
        path = os.path.join(MANUALS_DIR, filename)
        try:
            reader = PdfReader(path)
            text = ""
            for page in reader.pages:
                page_text = page.extract_text() or ""
                if page_text:
                    text += page_text + "\n"
            if not text.strip():
                continue
            chunks = chunk_text(text)
            MANUAL_CHUNKS.setdefault(manual_key, [])
            for chunk in chunks:
                MANUAL_CHUNKS[manual_key].append(
                    {
                        "source": filename,
                        "content": chunk,
                    }
                )
            print(f"[INFO] Loaded manual: {filename}")
        except Exception as e:
            print(f"[ERROR] Error loading manual {filename}: {e}")


load_manuals()

# --- 2. Models ---

class RecommendationRequest(BaseModel):
    category: Optional[str] = None       # e.g., "CCTV", "Alarmas", "Acceso"
    subcategory: Optional[str] = None    # e.g., "Casa", "Auto" (Used for text search filter)
    price_min: Optional[float] = None
    price_max: Optional[float] = None
    attributes: Optional[Dict[str, Any]] = None # e.g., {"pets": True, "wifi": True}

class MultiRecommendationRequest(BaseModel):
    requests: List[RecommendationRequest]
    user_name: Optional[str] = "Cliente"

class ChatRequest(BaseModel):
    messages: list
    # Optional: context specific to the recommendation could be passed here, 
    # but usually the history contains the "System" prompt with the context.

# --- 3. Product Filtering Logic ---


import re
import unicodedata


def normalize_text(value: str) -> str:
    if not value:
        return ""
    text = value.lower()
    text = unicodedata.normalize("NFD", text)
    return "".join(ch for ch in text if unicodedata.category(ch) != "Mn")


def keyword_in_text(term: str, text_normalized: str) -> bool:
    term_norm = normalize_text(term)
    if not term_norm:
        return False
    if " " in term_norm:
        return term_norm in text_normalized
    return re.search(rf"\b{re.escape(term_norm)}\b", text_normalized) is not None


def expand_vehicle_terms(term: str) -> List[str]:
    t = normalize_text(term)
    if t in ("moto", "motos", "motocicleta", "motocicletas"):
        return ["moto", "motos", "motocicleta", "motocicletas"]
    if t in ("auto", "autos", "automotor", "automovil", "vehiculo", "vehiculos", "camioneta", "camionetas"):
        return ["auto", "autos", "automotor", "automovil", "vehiculo", "vehiculos", "camioneta", "camionetas"]
    return [term]


def is_vehicle_alarm_request(criteria: RecommendationRequest, keywords: List[str]) -> bool:
    sub = normalize_text(criteria.subcategory or "")
    if sub in ("moto", "motos", "auto", "autos", "vehiculo", "vehiculos", "automotor", "automovil"):
        return True
    attrs = criteria.attributes or {}
    type_val = normalize_text(str(attrs.get("type", "")))
    if "vehicular" in type_val or "vehiculo" in type_val:
        return True
    for k in keywords:
        k_norm = normalize_text(k)
        if k_norm in ("moto", "motos", "auto", "autos", "vehiculo", "vehiculos", "automotor", "automovil"):
            return True
    return False


def is_vehicle_alarm_product(p: dict) -> bool:
    text = normalize_text(
        f"{p.get('Nombre', '')} {p.get('Texto_RAG', '')} {p.get('subfamilia', '')}"
    )
    if "alarma" not in text:
        return False
    if re.search(r"\bmoto(s)?\b", text):
        return True
    if re.search(r"\bvehiculo(s)?\b", text):
        return True
    if re.search(r"\bauto(s)?\b", text):
        return True
    if "automotor" in text or "automovil" in text:
        return True
    return False


def build_text_content(p: dict) -> str:
    return normalize_text(
        f"{p.get('Nombre', '')} {p.get('Texto_RAG', '')} {p.get('Caracter\u00edsticas (raw)', '')}"
    )


def score_product(p: dict, keywords: List[str], vehicle_alarm_request: bool) -> int:
    text = build_text_content(p)
    score = 0
    for k in keywords:
        if keyword_in_text(k, text):
            score += 1
    if vehicle_alarm_request:
        if "alarma" in text:
            score += 2
        if re.search(r"\bmoto(s)?\b", text):
            score += 2
        if "control remoto" in text or "gabinete" in text or "receptor" in text:
            score -= 2
    return score


def filter_products(criteria: RecommendationRequest, limit: int = 10):
    filtered = []

    # Pre-process keywords for subcategory/attributes
    keywords: List[str] = []
    if criteria.subcategory:
        keywords.extend(expand_vehicle_terms(criteria.subcategory))
    if criteria.attributes:
        for k, v in criteria.attributes.items():
            if isinstance(v, bool) and v:
                keywords.append(k.lower())
            elif isinstance(v, str):
                keywords.extend(expand_vehicle_terms(v))
            elif isinstance(v, list):
                for item in v:
                    if isinstance(item, str):
                        keywords.extend(expand_vehicle_terms(item))

    keywords = [k for k in keywords if k]
    vehicle_alarm_request = is_vehicle_alarm_request(criteria, keywords)

    # Category aliases
    category_aliases = set()
    if criteria.category:
        req_cat = normalize_text(criteria.category)
        if req_cat:
            category_aliases.add(req_cat)
            if req_cat in ("alarma", "alarmas"):
                category_aliases.add("alarmas")
                if vehicle_alarm_request:
                    category_aliases.add("vehiculos")

    for p in PRODUCTS:
        # 1. Category Filter (Family)
        if category_aliases:
            p_fam = normalize_text(p.get('familia') or "")
            p_sub = normalize_text(p.get('subfamilia') or "")
            if not any(alias in p_fam or alias in p_sub for alias in category_aliases):
                continue

        # 2. Price Filter
        try:
            price = p.get('Precio (ARS)')
            if price is not None:
                price = float(price)
                if criteria.price_min is not None and price < criteria.price_min:
                    continue
                if criteria.price_max is not None and criteria.price_max > 0 and price > criteria.price_max:
                    continue
        except (ValueError, TypeError):
            pass

        # 3. Keyword / Text Filter (Subcategory + Attributes)
        if keywords:
            text_content = build_text_content(p)
            match_any = False
            for k in keywords:
                if keyword_in_text(k, text_content):
                    match_any = True
                    break
            if not match_any:
                continue

        filtered.append(p)

    if vehicle_alarm_request:
        preferred = [p for p in filtered if is_vehicle_alarm_product(p)]
        if preferred:
            filtered = preferred

    filtered.sort(key=lambda p: score_product(p, keywords, vehicle_alarm_request), reverse=True)
    return filtered[:limit]


def extract_request_keywords(criteria: RecommendationRequest) -> List[str]:
    keywords: List[str] = []
    if criteria.category:
        keywords.append(criteria.category)
    if criteria.subcategory:
        keywords.extend(expand_vehicle_terms(criteria.subcategory))
    if criteria.attributes:
        for k, v in criteria.attributes.items():
            if isinstance(v, bool) and v:
                keywords.append(k.lower())
            elif isinstance(v, str):
                keywords.extend(expand_vehicle_terms(v))
            elif isinstance(v, list):
                for item in v:
                    if isinstance(item, str):
                        keywords.extend(expand_vehicle_terms(item))
    return [k for k in keywords if k]


def manual_keys_for_request(criteria: RecommendationRequest) -> List[str]:
    keys = set()
    if criteria.category:
        cat = normalize_text(criteria.category)
        if "cctv" in cat:
            keys.add("cctv")
        if "alarma" in cat or "alarmas" in cat:
            keys.add("alarmas")
        if "acceso" in cat:
            keys.add("acceso")
        if "infra" in cat:
            keys.add("infraestructura")
        if "otros" in cat:
            keys.add("otros")
        if "veh" in cat:
            keys.add("vehiculos")

    keywords = extract_request_keywords(criteria)
    if is_vehicle_alarm_request(criteria, keywords):
        keys.add("vehiculos")

    if criteria.subcategory:
        sub = normalize_text(criteria.subcategory)
        if "infra" in sub:
            keys.add("infraestructura")
        if "cctv" in sub or "camara" in sub:
            keys.add("cctv")
        if "acceso" in sub or "portero" in sub:
            keys.add("acceso")
        if "alarma" in sub or "sensor" in sub:
            keys.add("alarmas")

    return list(keys)


def select_manual_chunks(keys: List[str], keywords: List[str], limit: int = 6) -> List[Dict[str, str]]:
    if not keys:
        return []
    candidates: List[Tuple[int, Dict[str, str]]] = []
    terms = [normalize_text(k) for k in keywords if k]
    for key in keys:
        for chunk in MANUAL_CHUNKS.get(key, []):
            content_norm = normalize_text(chunk["content"])
            score = 0
            for term in terms:
                if term and term in content_norm:
                    score += 1
            if score > 0:
                candidates.append((score, chunk))
    if candidates:
        candidates.sort(key=lambda item: item[0], reverse=True)
        return [item[1] for item in candidates[:limit]]

    fallback: List[Dict[str, str]] = []
    for key in keys:
        fallback.extend(MANUAL_CHUNKS.get(key, [])[:2])
    return fallback[:limit]
# --- 4. LLM Setup ---
api_key = (os.getenv("GPT_API_KEY") or os.getenv("OPENAI_API_KEY") or "").strip()
llm = (
    ChatOpenAI(
        model="gpt-4o-mini",
        temperature=0.3, # Slightly higher for creative sales pitch
        api_key=api_key
    )
    if api_key
    else None
)


def build_local_recommendation_response(
    products: List[dict],
    user_name: str,
    user_req_desc: str,
) -> str:
    intro = (
        f"Hola {user_name}, estoy en modo demo sin API key "
        "(GPT_API_KEY/OPENAI_API_KEY no configurada)."
    )
    if not products:
        return (
            f"{intro} No encontre coincidencias fuertes en el catalogo con estos filtros: "
            f"{user_req_desc or 'sin detalle'}."
        )

    lines = []
    for p in products[:3]:
        lines.append(
            f"- {p.get('Nombre', 'Producto')} | SKU: {p.get('ID', 'N/D')} | Precio: ${p.get('Precio (ARS)', 'Consultar')}"
        )
    return (
        f"{intro}\n\nOpciones encontradas por filtros locales:\n"
        + "\n".join(lines)
        + "\n\nSi queres recomendaciones conversacionales avanzadas, agrega una API key y reinicia el servicio ai."
    )

SYSTEM_PROMPT_TEMPLATE = """
Sos Filippo, el vendedor experto en Inteligencia Artificial de CEA Insumos.
Tu objetivo es analizar la solicitud del cliente y los productos encontrados para recomendar la mejor opcion.

Perfil:
- Experto tecnico pero con lenguaje claro.
- Vendedor proactivo: Queres cerrar la venta.
- Amable y empatico.

Instrucciones:
1. Analiza los productos disponibles que coinciden con la busqueda:
{products_context}

2. La solicitud del cliente fue: "{user_request_description}"

3. Contexto tecnico (manuales relevantes):
{manuals_context}

4. Genera una respuesta que:
   - Salude cordialmente.
   - Prioriza productos que coincidan exactamente con lo solicitado (ej: alarmas para moto/auto).
   - Evita recomendar controles, gabinetes o accesorios si hay alarmas disponibles.
   - Presenta las 2 o 3 mejores opciones de la lista (no listes 10, elegi las mejores).
   - Explica POR QUE son buenas opciones (costo-beneficio, caracteristicas clave).
   - Si no hay productos exactos, ofrece la alternativa mas cercana o invita a consultar.
   - Menciona los precios en Pesos Argentinos (ARS) si estan disponibles.
   - Cierra con una pregunta o invitacion a comprar ("Te gustaria agregarlo al carrito?", "Tenes alguna duda sobre la instalacion?").

IMPORTANTE:
- Solo recomienda lo que este en la lista. Si la lista esta vacia, pedi disculpas y solicita mas detalles o que contacten a un humano.
- No inventes precios.
"""

# --- 5. Endpoints ---

@router.post("/recommend")
def recommend_endpoint(request: RecommendationRequest):
    # Wrap single request into list for unified logic, or keep specifically for simple calls
    return run_recommendation([request])

@router.post("/recommend_multi")
def recommend_multi_endpoint(request: MultiRecommendationRequest):
    return run_recommendation(request.requests, request.user_name)

def run_recommendation(requests: List[RecommendationRequest], user_name: str = "Cliente"):
    try:
        # 1. Filter Products for ALL requests
        all_results = []
        seen_ids = set()

        descriptions = []
        manual_keys = set()
        manual_keywords: List[str] = []

        for req in requests:
            results = filter_products(req)
            # Add to aggregate list if unique
            for p in results:
                pid = p.get('ID')
                if pid and pid not in seen_ids:
                    all_results.append(p)
                    seen_ids.add(pid)
            
            # Create description part
            desc_parts = []
            if req.category: desc_parts.append(f"Categoría: {req.category}")
            if req.subcategory: desc_parts.append(f"Subtipo: {req.subcategory}")
            if req.price_min: desc_parts.append(f"Min: ${req.price_min}")
            if req.price_max: desc_parts.append(f"Max: ${req.price_max}")
            if req.attributes: desc_parts.append(f"Extras: {req.attributes}")
            if desc_parts:
                descriptions.append(f"[{', '.join(desc_parts)}]")

            manual_keys.update(manual_keys_for_request(req))
            manual_keywords.extend(extract_request_keywords(req))

        user_req_desc = " + ".join(descriptions)
        
        # 2. Prepare Context for LLM
        products_context = ""
        if not all_results:
            products_context = "No se encontraron productos que coincidan exactamente con los criterios en el catálogo actual."
        else:
            for p in all_results:
                price = f"${p.get('Precio (ARS)', 'Consultar')}"
                products_context += f"- {p.get('Nombre')} | SKU: {p.get('ID')} | Precio: {price}\n  Desc: {p.get('Texto_RAG', '')[:300]}...\n\n"

        selected_chunks = select_manual_chunks(list(manual_keys), manual_keywords)
        if selected_chunks:
            manuals_context = ""
            for chunk in selected_chunks:
                manuals_context += f"[{chunk['source']}]\n{chunk['content'][:1000]}\n\n"
        else:
            manuals_context = "No hay manuales relevantes para los filtros seleccionados."

        # 4. Invoke LLM
        formatted_system_prompt = SYSTEM_PROMPT_TEMPLATE.format(
            products_context=products_context,
            user_request_description=user_req_desc,
            manuals_context=manuals_context
        )
        if llm is None:
            return {
                "response": build_local_recommendation_response(all_results, user_name, user_req_desc),
                "products": all_results,
                "system_prompt_used": "demo_no_llm",
            }

        messages = [
            SystemMessage(content=formatted_system_prompt),
            HumanMessage(content=f"Hola, soy {user_name}, ayudame a elegir según mis requerimientos.")
        ]
        
        response = llm.invoke(messages)
        
        return {
            "response": response.content,
            "products": all_results, # Return raw products too in case frontend wants to show cards
            "system_prompt_used": formatted_system_prompt
        }

    except Exception as e:
        print(f"Error in recommendation: {e}")
        raise HTTPException(status_code=500, detail=str(e))

@router.post("/chat")
def chat_sales_endpoint(request: ChatRequest):
    try:
        # Reconstruct standard chat interaction
        # The frontend should ideally send the FULL history, including the initial System Message 
        # (or we need to re-inject a generic one if missing, but maintaining context is harder).
        # Strategy: The frontend should append the previous Assistant response to the history.
        # But we need a System Prompt to define the Persona "Gustavo".
        if llm is None:
            return {
                "response": (
                    "Asistente comercial en modo demo: falta GPT_API_KEY/OPENAI_API_KEY. "
                    "Puedo listar productos filtrados, pero no mantener chat avanzado en esta instancia."
                )
            }
        lc_messages = []
        
        has_system = False
        for msg in request.messages:
            if msg['role'] == 'system':
                lc_messages.append(SystemMessage(content=msg['content']))
                has_system = True
            elif msg['role'] == 'user':
                lc_messages.append(HumanMessage(content=msg['content']))
            elif msg['role'] == 'assistant':
                lc_messages.append(AIMessage(content=msg['content']))
        
        if not has_system:
            # Fallback system prompt if the specialized one from /recommend isn't preserved
            fallback_prompt = """Sos Gustavo, experto vendedor de seguridad electrónica de CEA Insumos.
            Continuá la conversación con el cliente ayudándolo a decidir.
            Usa el contexto previo de la conversación para saber qué productos se le recomendaron.
            Se amable, breve y orientado a cerrar la venta.
            """
            lc_messages.insert(0, SystemMessage(content=fallback_prompt))

        response = llm.invoke(lc_messages)
        return {"response": response.content}

    except Exception as e:
        print(f"Error in chat: {e}")
        raise HTTPException(status_code=500, detail=str(e))


