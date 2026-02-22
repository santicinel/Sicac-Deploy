import os
from typing import List, Dict
from fastapi import APIRouter, HTTPException
from pydantic import BaseModel
from langchain_openai import ChatOpenAI
from langchain_core.messages import HumanMessage, SystemMessage, AIMessage
from pypdf import PdfReader

router = APIRouter()

MANUAL_CHUNKS: List[Dict[str, str]] = []


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


def load_manuals():
    manuals_dir = os.path.join(os.path.dirname(__file__), "..", "kb", "manuales")
    if not os.path.isdir(manuals_dir):
        print(f"[WARN] Manuals directory not found: {manuals_dir}")
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
            print(f"[INFO] Loaded manual: {filename}")
        except Exception as e:
            print(f"[ERROR] Error loading manual {filename}: {e}")


load_manuals()


def find_relevant_manual_chunks(query: str, limit: int = 3):
    query = query.lower()
    words = [w for w in query.split() if len(w) > 2]
    if not words:
        return []
    matches = []
    for chunk in MANUAL_CHUNKS:
        text = chunk["content"].lower()
        score = 0
        for w in words:
            if w in text:
                score += 1
        if score > 0:
            matches.append((score, chunk))
    matches.sort(key=lambda x: x[0], reverse=True)
    return [m[1] for m in matches[:limit]]


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


def build_fallback_response(user_query: str, relevant_manuals: List[Dict[str, str]]) -> str:
    intro = (
        "Asistente tecnico en modo demo: falta configurar "
        "GPT_API_KEY/OPENAI_API_KEY en el servicio de IA."
    )
    if not relevant_manuals:
        return (
            f"{intro} No encontre fragmentos directos en los manuales para: '{user_query}'. "
            "Enviame modelo exacto, sintomas y pruebas ya realizadas."
        )

    lines = []
    for item in relevant_manuals[:2]:
        excerpt = item.get("content", "").replace("\n", " ").strip()[:260]
        lines.append(f"- {item.get('source', 'manual')}: {excerpt}")

    return (
        f"{intro}\n\nFragmentos tecnicos relacionados:\n"
        + "\n".join(lines)
        + "\n\nPara diagnostico conversacional completo, agrega una API key y reinicia el servicio ai."
    )


class TechnicianChatRequest(BaseModel):
    messages: list


@router.post("/ai/technician-chat")
def technician_chat_endpoint(request: TechnicianChatRequest):
    try:
        user_query = ""
        for msg in reversed(request.messages):
            if msg.get("role") == "user":
                user_query = msg.get("content", "")
                break

        relevant_manuals = find_relevant_manual_chunks(user_query)
        manuals_context = ""
        if relevant_manuals:
            manuals_context = "\nMANUALES RELEVANTES:\n"
            for item in relevant_manuals:
                manuals_context += f"[{item['source']}]\n{item['content'][:1200]}\n\n"

        if llm is None:
            return {"response": build_fallback_response(user_query, relevant_manuals)}

        system_prompt = (
            "Tu nombre es Raul y puedes decir tu nombre si te lo preguntan. "
            "Sos un asistente tecnico para instaladores de sistemas de seguridad. "
            "Respondes dudas sobre instalacion, pruebas, cableado, configuracion basica, "
            "diagnostico de fallas y buenas practicas. "
            "Tono claro, breve y profesional. "
            "Si falta informacion, pedi una sola aclaracion puntual. "
            "No inventes datos ni garantias. "
            "No hables de informacion fuera de los manuales. "
            "Si te dicen que tienen problemas con algo, deciles que te aclaren el modelo o alguna descripcion para ayudar a diagnosticar. "
            f"Contexto disponible: {manuals_context}"
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
        print(f"Error: {e}")
        raise HTTPException(status_code=500, detail=str(e))
