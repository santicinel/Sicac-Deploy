from typing import Any, Dict, List, Optional

from fastapi import APIRouter, HTTPException
from langchain_core.messages import AIMessage, HumanMessage, SystemMessage
from pydantic import BaseModel

from routes.llm_utils import build_llm_clients, invoke_with_fallback

router = APIRouter()


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


llm_clients = build_llm_clients(temperature=0.2)

SUMMARY_PROMPT = """
Sos Adriel, coordinador de reclamos de CEA Insumos.
Tu tarea es generar un resumen ejecutivo claro, facil de escanear y apto para presentacion.

Formato visual exacto:
Panorama general:
- Total filtrado.
- Estado predominante.
- Tipo predominante.
- Tendencia corta (1 linea).

Prioridades de hoy:
- Maximo 5 bullets.
- Formato: [ID] Cliente - motivo - accion sugerida.

Riesgos y alertas:
- Si no hay riesgos, escribir: "- Sin riesgos criticos detectados."

Detalle rapido:
- Maximo 8 bullets.
- Formato: [ID] [estado] [tipo] [fecha] | Asunto: ... | Nota: ...

Reglas de estilo:
- Bullets cortos (max 16 palabras por bullet).
- Evita bloques largos de texto.
- No inventes informacion.

Filtros aplicados: {filters_context}
Reclamos para analizar:
{claims_context}
"""

CHAT_PROMPT = """
Sos Adriel, coordinador de reclamos de CEA Insumos.
Responde preguntas solo con base en el resumen y reclamos disponibles.
Si preguntan por algo fuera de la lista, aclara que no hay datos cargados.

Formato obligatorio:
Respuesta corta:
- 1 a 3 bullets claros.

Accion sugerida:
- 1 o 2 bullets accionables.

IDs citados:
- Lista de IDs usados en tu respuesta (o "Ninguno").

Reglas:
- Maximo 90 palabras.
- No texto en bloque.
- No inventes datos.

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


def build_local_summary(claims: List[ClaimItem], filters_context: str) -> str:
    total = len(claims)
    status_counts: Dict[str, int] = {}
    type_counts: Dict[str, int] = {}
    for item in claims:
        status_counts[item.status] = status_counts.get(item.status, 0) + 1
        type_counts[item.type] = type_counts.get(item.type, 0) + 1

    lines = [
        "Panorama general:",
        f"- Total de reclamos: {total}",
        f"- Filtros: {filters_context}",
        f"- Por estado: {status_counts or {'sin_datos': 0}}",
        f"- Por tipo: {type_counts or {'sin_datos': 0}}",
        "",
        "Prioridades de hoy:",
    ]

    for item in claims[:5]:
        lines.append(
            f"- [{item.id}] {item.customer} - {item.subject} - revisar estado {item.status}"
        )

    lines.extend(
        [
            "",
            "Riesgos y alertas:",
            "- Sin evaluacion avanzada (modo demo sin API key).",
        ]
    )
    return "\n".join(lines)


def build_local_chat_response(summary_context: str, messages: List[Dict[str, Any]]) -> str:
    last_user = ""
    for message in reversed(messages):
        if message.get("role") == "user":
            last_user = str(message.get("content", "")).strip()
            break

    if not last_user:
        return (
            "Respuesta corta:\n"
            "- Modo demo sin API key.\n"
            "- Enviame una pregunta puntual sobre los IDs visibles.\n\n"
            "Accion sugerida:\n"
            "- Configurar GPT_API_KEY/OPENAI_API_KEY para analisis completo.\n\n"
            "IDs citados:\n"
            "- Ninguno"
        )

    preview = summary_context[:700]
    return (
        "Respuesta corta:\n"
        "- Modo demo sin API key.\n"
        "- Te comparto una vista previa del resumen disponible.\n\n"
        "Accion sugerida:\n"
        "- Hace una consulta concreta por ID para avanzar.\n\n"
        "IDs citados:\n"
        "- Ninguno\n\n"
        f"Resumen disponible:\n{preview}"
    )


@router.post("/summary")
def summary_endpoint(request: SummaryRequest):
    try:
        if not request.claims:
            return {"summary": "No hay reclamos para resumir con los filtros seleccionados."}

        claims_context = build_claims_context(request.claims)
        filters_context = build_filters_context(request.filters)

        if not llm_clients:
            return {"summary": build_local_summary(request.claims, filters_context)}

        system_prompt = SUMMARY_PROMPT.format(
            claims_context=claims_context,
            filters_context=filters_context,
        )
        messages = [
            SystemMessage(content=system_prompt),
            HumanMessage(
                content="Genera el resumen ejecutivo con foco en lectura rapida y accion."
            ),
        ]

        response, _ = invoke_with_fallback(llm_clients, messages)
        return {"summary": str(response.content)}

    except Exception as exc:
        print(f"Error in claims summary: {exc}")
        raise HTTPException(status_code=500, detail=str(exc))


@router.post("/chat")
def summary_chat_endpoint(request: SummaryChatRequest):
    try:
        claims_context = build_claims_context(request.claims or [])
        filters_context = build_filters_context(request.filters)
        summary_context = request.summary or "No hay resumen previo."

        if not llm_clients:
            return {
                "response": build_local_chat_response(summary_context, request.messages)
            }

        system_prompt = CHAT_PROMPT.format(
            summary_context=summary_context,
            filters_context=filters_context,
            claims_context=claims_context,
        )

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
        print(f"Error in claims chat: {exc}")
        raise HTTPException(status_code=500, detail=str(exc))
