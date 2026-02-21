import os
from typing import List, Optional, Dict, Any
from fastapi import APIRouter, HTTPException
from pydantic import BaseModel
from langchain_openai import ChatOpenAI
from langchain_core.messages import HumanMessage, SystemMessage, AIMessage

router = APIRouter()

# --- Models ---
class ClaimItem(BaseModel):
    id: str
    type: str
    status: str
    subject: str
    description: str
    customer: str
    createdAt: str


class SummaryFilters(BaseModel):
    date_from: Optional[str] = None
    date_to: Optional[str] = None
    status: Optional[str] = None
    type: Optional[str] = None


class SummaryRequest(BaseModel):
    claims: List[ClaimItem]
    filters: Optional[SummaryFilters] = None


class SummaryChatRequest(BaseModel):
    messages: List[Dict[str, Any]]
    summary: Optional[str] = None
    claims: Optional[List[ClaimItem]] = None
    filters: Optional[SummaryFilters] = None


# --- LLM Setup ---
api_key = os.getenv("GPT_API_KEY")
llm = ChatOpenAI(
    model="gpt-4o-mini",
    temperature=0.2,
    api_key=api_key
)

SUMMARY_PROMPT = """
Sos el coordinador de reclamos de CEA Insumos, te llamas Adriel.
Tu tarea es resumir y detectar los principales quilombos recientes.

Responde en espanol con este formato:
Inicio: Comentar cuantos reclamos coincidentes hay y una línea de cada uno modo resumen.
1) Resumen general (1 a 3 bullets, claro y concreto)(evitar usar infomación usada en "Inicio").
2) Detalle por reclamo (1 bullet por reclamo con id, cliente, estado, tipo, fecha, asunto y detalle).
3) Riesgos o urgencias (si aplica).

Filtros aplicados: {filters_context}
Reclamos para analizar:
{claims_context}
"""

CHAT_PROMPT = """
Sos el coordinador de reclamos de CEA Insumos.
Responde preguntas sobre el resumen y los reclamos listados.
Si te preguntan por reclamos que no estan en la lista, aclara que no hay datos.
Mantene respuestas claras y utiles, en espanol.

Resumen actual:
{summary_context}

Filtros aplicados: {filters_context}
Reclamos disponibles:
{claims_context}
"""


def build_claims_context(claims: List[ClaimItem]) -> str:
    if not claims:
        return "No hay reclamos para analizar."
    lines = []
    for item in claims:
        lines.append(
            f"- {item.id} | {item.customer} | {item.status} | {item.type} | {item.createdAt}\n"
            f"  Asunto: {item.subject}\n"
            f"  Detalle: {item.description}"
        )
    return "\n".join(lines)


def build_filters_context(filters: Optional[SummaryFilters]) -> str:
    if not filters:
        return "Sin filtros adicionales."
    parts = []
    if filters.date_from:
        parts.append(f"desde {filters.date_from}")
    if filters.date_to:
        parts.append(f"hasta {filters.date_to}")
    if filters.status:
        parts.append(f"estado {filters.status}")
    if filters.type:
        parts.append(f"tipo {filters.type}")
    return ", ".join(parts) if parts else "Sin filtros adicionales."


# --- Endpoints ---
@router.post("/summary")
def summary_endpoint(request: SummaryRequest):
    try:
        if not request.claims:
            return {"summary": "No hay reclamos para resumir con los filtros seleccionados."}

        claims_context = build_claims_context(request.claims)
        filters_context = build_filters_context(request.filters)
        system_prompt = SUMMARY_PROMPT.format(
            claims_context=claims_context,
            filters_context=filters_context
        )

        messages = [
            SystemMessage(content=system_prompt),
            HumanMessage(content="Genera el resumen con mas detalle.")
        ]

        response = llm.invoke(messages)
        return {"summary": response.content}

    except Exception as e:
        print(f"Error in claims summary: {e}")
        raise HTTPException(status_code=500, detail=str(e))


@router.post("/chat")
def summary_chat_endpoint(request: SummaryChatRequest):
    try:
        claims_context = build_claims_context(request.claims or [])
        filters_context = build_filters_context(request.filters)
        summary_context = request.summary or "No hay resumen previo."

        system_prompt = CHAT_PROMPT.format(
            summary_context=summary_context,
            filters_context=filters_context,
            claims_context=claims_context
        )

        lc_messages = [SystemMessage(content=system_prompt)]
        for msg in request.messages:
            if msg.get("role") == "user":
                lc_messages.append(HumanMessage(content=msg.get("content", "")))
            elif msg.get("role") == "assistant":
                lc_messages.append(AIMessage(content=msg.get("content", "")))

        response = llm.invoke(lc_messages)
        return {"response": response.content}

    except Exception as e:
        print(f"Error in claims chat: {e}")
        raise HTTPException(status_code=500, detail=str(e))
