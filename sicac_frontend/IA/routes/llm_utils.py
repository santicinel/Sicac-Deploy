import os
from typing import Any, List, Optional, Sequence, Tuple

from langchain_openai import ChatOpenAI


DEFAULT_PRIMARY_MODEL = "gpt-5.2"
DEFAULT_FALLBACK_MODELS = ["gpt-5", "gpt-4.1", "gpt-4o-mini"]


def _api_key() -> str:
    return (os.getenv("GPT_API_KEY") or os.getenv("OPENAI_API_KEY") or "").strip()


def _split_models(raw_value: str) -> List[str]:
    if not raw_value:
        return []
    return [item.strip() for item in raw_value.split(",") if item.strip()]


def configured_models() -> List[str]:
    primary = (
        os.getenv("GPT_MODEL")
        or os.getenv("OPENAI_MODEL")
        or DEFAULT_PRIMARY_MODEL
    ).strip()

    env_fallbacks = (
        os.getenv("GPT_MODEL_FALLBACKS")
        or os.getenv("OPENAI_MODEL_FALLBACKS")
        or ",".join(DEFAULT_FALLBACK_MODELS)
    )

    ordered = [primary, *_split_models(env_fallbacks)]
    seen = set()
    deduped: List[str] = []
    for model in ordered:
        if model and model not in seen:
            deduped.append(model)
            seen.add(model)
    return deduped


def build_llm_clients(temperature: float) -> List[ChatOpenAI]:
    api_key = _api_key()
    if not api_key:
        return []
    return [
        ChatOpenAI(
            model=model_name,
            temperature=temperature,
            api_key=api_key,
        )
        for model_name in configured_models()
    ]


def _client_model_name(client: ChatOpenAI) -> str:
    return (
        getattr(client, "model_name", None)
        or getattr(client, "model", None)
        or "unknown-model"
    )


def invoke_with_fallback(
    llm_clients: List[ChatOpenAI],
    messages: Sequence[Any],
) -> Tuple[Any, str]:
    if not llm_clients:
        raise RuntimeError("No LLM clients available.")

    last_error: Optional[Exception] = None
    for index, client in enumerate(list(llm_clients)):
        model_name = _client_model_name(client)
        try:
            response = client.invoke(messages)
            used = (
                response.response_metadata.get("model_name")
                if hasattr(response, "response_metadata") and isinstance(response.response_metadata, dict)
                else None
            )
            if index > 0:
                # Keep the last working model first to avoid repeated failed attempts.
                llm_clients.insert(0, llm_clients.pop(index))
            return response, used or model_name
        except Exception as exc:
            print(f"[WARN] LLM invoke failed with {model_name}: {exc}")
            last_error = exc

    if last_error:
        raise last_error
    raise RuntimeError("LLM invocation failed without specific error.")
