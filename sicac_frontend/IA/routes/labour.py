import json
import os
import re
from typing import Dict, List, Optional

from fastapi import APIRouter, HTTPException
from langchain_core.messages import HumanMessage, SystemMessage
from langchain_openai import ChatOpenAI
from pydantic import BaseModel
from pypdf import PdfReader

router = APIRouter(prefix="/labour")

MANUAL_CHUNKS: List[Dict[str, str]] = []


def chunk_text(text: str, chunk_size: int = 1200, overlap: int = 200) -> List[str]:
    if not text or chunk_size <= 0:
        return []

    overlap = max(0, min(overlap, chunk_size - 1))
    chunks: List[str] = []
    start = 0
    length = len(text)

    while start < length:
        end = min(start + chunk_size, length)
        chunks.append(text[start:end].strip())
        if end >= length:
            break
        start = end - overlap

    return [chunk for chunk in chunks if chunk]


def load_manuals() -> None:
    manuals_dir = os.path.join(os.path.dirname(__file__), "..", "kb", "manuales")
    if not os.path.isdir(manuals_dir):
        print(f"[WARN] Labour manuals directory not found: {manuals_dir}")
        return

    for filename in os.listdir(manuals_dir):
        if not filename.lower().endswith(".pdf"):
            continue

        path = os.path.join(manuals_dir, filename)
        try:
            reader = PdfReader(path)
            text = ""
            for page in reader.pages:
                page_text = page.extract_text() or ""
                if page_text:
                    text += page_text + "\n"

            if not text.strip():
                continue

            for chunk in chunk_text(text):
                MANUAL_CHUNKS.append(
                    {
                        "source": filename,
                        "content": chunk,
                    }
                )
            print(f"[INFO] Labour loaded manual: {filename}")
        except Exception as exc:
            print(f"[ERROR] Labour manual load error ({filename}): {exc}")


load_manuals()


def find_relevant_manual_chunks(query: str, limit: int = 5) -> List[Dict[str, str]]:
    query_lower = (query or "").lower()
    words = [word for word in query_lower.split() if len(word) > 2]
    if not words:
        return []

    matches = []
    for chunk in MANUAL_CHUNKS:
        chunk_text_lower = chunk["content"].lower()
        score = 0
        for word in words:
            if word in chunk_text_lower:
                score += 1
        if score > 0:
            matches.append((score, chunk))

    matches.sort(key=lambda item: item[0], reverse=True)
    return [item[1] for item in matches[:limit]]


def extract_json_object(text: str) -> Optional[dict]:
    if not text:
        return None

    cleaned = text.strip()
    if cleaned.startswith("```"):
        cleaned = re.sub(r"^```(?:json)?\s*", "", cleaned)
        cleaned = re.sub(r"\s*```$", "", cleaned)

    try:
        parsed = json.loads(cleaned)
        if isinstance(parsed, dict):
            return parsed
    except Exception:
        pass

    first = cleaned.find("{")
    last = cleaned.rfind("}")
    if first == -1 or last == -1 or last <= first:
        return None

    try:
        parsed = json.loads(cleaned[first : last + 1])
        if isinstance(parsed, dict):
            return parsed
    except Exception:
        return None
    return None


class LabourItem(BaseModel):
    id: Optional[str] = None
    name: str
    description: Optional[str] = None
    category: Optional[str] = None
    quantity: int = 1


class LabourEstimateRequest(BaseModel):
    items: List[LabourItem]
    labor_request: Optional[str] = None


def clamp_hours(hours: float) -> float:
    return round(max(0.5, min(hours, 120.0)), 1)


def heuristic_hours(items: List[LabourItem], labor_request: str) -> float:
    total = 0.6
    category_base = {
        "camera": 1.8,
        "alarm": 1.6,
        "sensor": 0.8,
    }

    for item in items:
        quantity = max(1, int(item.quantity or 1))
        category = (item.category or "").lower()
        base = category_base.get(category, 1.2)
        total += base * quantity

        text = f"{item.name} {item.description or ''}".lower()
        if any(keyword in text for keyword in ("cable", "cableado", "exterior", "altura", "canalizacion")):
            total += 0.4 * quantity

    request_text = (labor_request or "").lower()
    if any(keyword in request_text for keyword in ("exterior", "intemperie", "altura")):
        total *= 1.15
    if any(keyword in request_text for keyword in ("urgente", "complejo", "domotica", "integracion")):
        total *= 1.2

    return clamp_hours(total)


def build_items_context(items: List[LabourItem]) -> str:
    if not items:
        return "No hay items."
    lines = []
    for item in items:
        quantity = max(1, int(item.quantity or 1))
        lines.append(
            f"- {item.name} | Cantidad: {quantity} | Categoria: {item.category or 'sin categoria'}\n"
            f"  Descripcion: {item.description or 'sin descripcion'}"
        )
    return "\n".join(lines)


api_key = os.getenv("GPT_API_KEY")
llm = (
    ChatOpenAI(
        model="gpt-4o-mini",
        temperature=0.1,
        api_key=api_key,
    )
    if api_key
    else None
)


@router.post("/estimate")
def estimate_labour(request: LabourEstimateRequest):
    if not request.items:
        return {
            "estimated_hours": 0.0,
            "summary": "No hay productos en el presupuesto para estimar mano de obra.",
            "assumptions": [],
            "sources": [],
            "model_used": "none",
        }

    user_text = (request.labor_request or "").strip()
    items_context = build_items_context(request.items)
    query_for_context = f"{user_text}\n{items_context}".strip()

    relevant_manuals = find_relevant_manual_chunks(query_for_context, limit=5)
    manuals_context = ""
    sources: List[str] = []
    for chunk in relevant_manuals:
        source = chunk["source"]
        if source not in sources:
            sources.append(source)
        manuals_context += f"[{source}]\n{chunk['content'][:1200]}\n\n"

    if not manuals_context:
        manuals_context = "No se encontraron fragmentos directos en manuales para esta solicitud."

    if llm is None:
        fallback = heuristic_hours(request.items, user_text)
        return {
            "estimated_hours": fallback,
            "summary": "Estimacion local aplicada por falta de configuracion del modelo IA.",
            "assumptions": [
                "Se considero instalacion, configuracion inicial y pruebas basicas.",
                "Puede variar segun obra civil, distancia de cableado y accesibilidad.",
            ],
            "sources": sources,
            "model_used": "heuristic",
        }

    try:
        system_prompt = (
            "Sos un estimador tecnico de mano de obra para instalaciones de seguridad electronica. "
            "Debes estimar SOLO horas de trabajo tecnico para instalacion y puesta en marcha. "
            "Incluye montaje, cableado basico, configuracion inicial y pruebas funcionales. "
            "No incluyas tiempo de compra, entrega, administracion o traslados largos. "
            "Si faltan datos, usa supuestos conservadores y listalos. "
            "Se fino a la hora de calcular, cada cambio en cantidad o tipo de producto puede afectar significativamente el tiempo. "
            "Presta mucha atención a lo aclarado por el cliente en detalles adicionales si hay. "
            "Responde SOLO en JSON valido con este formato exacto: "
            "{\"estimated_hours\": number, \"summary\": string, \"assumptions\": string[]}. "
            f"Contexto tecnico de manuales:\n{manuals_context}"
        )

        human_prompt = (
            f"Solicitud de mano de obra del cliente:\n{user_text or 'Sin detalle adicional.'}\n\n"
            f"Productos y alcance:\n{items_context}\n\n"
            "Genera la estimacion de horas totales."
        )

        response = llm.invoke(
            [
                SystemMessage(content=system_prompt),
                HumanMessage(content=human_prompt),
            ]
        )
        payload = extract_json_object(str(response.content))
        if payload is None:
            raise ValueError("IA response is not valid JSON.")

        raw_hours = payload.get("estimated_hours")
        hours = float(raw_hours)
        summary = str(payload.get("summary") or "").strip()
        assumptions_raw = payload.get("assumptions") or []
        assumptions = [str(item).strip() for item in assumptions_raw if str(item).strip()]

        if not summary:
            summary = "Estimacion calculada segun alcance tecnico informado."
        if not assumptions:
            assumptions = [
                "Se considero instalacion, configuracion inicial y pruebas basicas.",
            ]

        return {
            "estimated_hours": clamp_hours(hours),
            "summary": summary,
            "assumptions": assumptions[:5],
            "sources": sources,
            "model_used": "gpt-4o-mini",
        }

    except Exception as exc:
        try:
            fallback = heuristic_hours(request.items, user_text)
            return {
                "estimated_hours": fallback,
                "summary": "Se aplico una estimacion tecnica local por error temporal del modelo IA.",
                "assumptions": [
                    "Se considero instalacion, configuracion inicial y pruebas basicas.",
                    "La estimacion puede variar segun condiciones de obra y acceso.",
                ],
                "sources": sources,
                "model_used": "heuristic_fallback",
            }
        except Exception as inner_exc:
            print(f"[ERROR] Labour estimation error: {exc} | fallback error: {inner_exc}")
            raise HTTPException(status_code=500, detail="No se pudo estimar la mano de obra.")
