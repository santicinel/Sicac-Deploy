<script setup lang="ts">
import { computed, ref, watch } from "vue";
import { Icon } from "@iconify/vue";
import { config_app } from "@/config/app";

type ChatRole = "user" | "assistant";

interface ChatMessage {
  role: ChatRole;
  content: string;
}

const aiBaseUrl = config_app.ai_url;
const chatMessages = ref<ChatMessage[]>([
  {
    role: "assistant",
    content:
      "Hola, soy Raúl, tu asistente técnico. Podés consultarme sobre instalaciones, pruebas y diagnósticos.",
  },
]);
const chatInput = ref("");
const isSending = ref(false);
const errorMessage = ref("");
const chatContainer = ref<HTMLDivElement | null>(null);

const canSend = computed(() => chatInput.value.trim().length > 0 && !isSending.value);

const beautifyAssistantText = (text: string) => {
  let cleaned = text.replace(/\*\*/g, "").replace(/\r/g, "").trim();
  cleaned = cleaned.replace(/(\d+\.)\s*/g, "\n$1 ");
  cleaned = cleaned.replace(/\s+-\s+/g, "\n- ");
  cleaned = cleaned.replace(/\. (?=\d+\.)/g, ".\n");
  cleaned = cleaned.replace(/\n{3,}/g, "\n\n");
  return cleaned;
};

const getMessageText = (msg: ChatMessage) =>
  msg.role === "assistant" ? beautifyAssistantText(msg.content) : msg.content;

const scrollToBottom = () => {
  if (!chatContainer.value) return;
  chatContainer.value.scrollTop = chatContainer.value.scrollHeight;
};

watch(chatMessages, () => {
  requestAnimationFrame(scrollToBottom);
});

const sendMessage = async () => {
  if (!canSend.value) return;
  const content = chatInput.value.trim();
  chatMessages.value.push({ role: "user", content });
  chatInput.value = "";
  isSending.value = true;
  errorMessage.value = "";

  try {
    const response = await fetch(`${aiBaseUrl}/ai/technician-chat`, {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
      },
      body: JSON.stringify({
        model: "gpt-4o-mini",
        messages: chatMessages.value,
      }),
    });

    if (!response.ok) {
      throw new Error("API Error");
    }

    const data = await response.json();
    chatMessages.value.push({
      role: "assistant",
      content: data.response ?? "No pude responder en este momento.",
    });
  } catch (error) {
    console.error(error);
    errorMessage.value =
      "No pude conectar con el servidor de IA. Verifica la API.";
    chatMessages.value.push({
      role: "assistant",
      content: "No pude responder por ahora. Intentalo nuevamente.",
    });
  } finally {
    isSending.value = false;
  }
};
</script>

<template>
  <div class="grid gap-6 p-6 lg:grid-cols-[1.1fr_0.9fr]">
    <section class="rounded-lg border bg-card text-card-foreground shadow-sm">
      <div class="border-b px-6 py-4">
        <h1 class="text-2xl font-semibold">Chat técnico</h1>
        <p class="text-sm text-muted-foreground">
          Asistente de IA para dudas de instalaciones, pruebas y diagnósticos.
        </p>
      </div>
      <div class="flex h-[60vh] flex-col">
        <div ref="chatContainer" class="flex-1 space-y-3 overflow-y-auto px-6 py-4">
          <div
            v-for="(msg, idx) in chatMessages"
            :key="idx"
            :class="[
              'max-w-[90%] rounded-lg px-3 py-2 text-sm shadow-sm',
              msg.role === 'assistant'
                ? 'bg-muted/40 text-foreground'
                : 'ml-auto bg-primary text-primary-foreground',
            ]"
          >
            <p class="whitespace-pre-line break-words leading-relaxed">
              {{ getMessageText(msg) }}
            </p>
          </div>
          <div v-if="isSending" class="max-w-[85%] rounded-lg px-3 py-2 text-sm shadow-sm bg-muted/40 text-muted-foreground">
            Escribiendo...
          </div>
        </div>
        <div class="border-t px-6 py-4">
          <form class="flex gap-2" @submit.prevent="sendMessage">
            <input
              v-model="chatInput"
              placeholder="Escribí tu consulta técnica..."
              class="flex-1 rounded-md border border-input bg-background px-3 py-2 text-sm shadow-sm focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
              :disabled="isSending"
            />
            <button
              type="submit"
              class="inline-flex items-center gap-2 rounded-md bg-primary px-4 py-2 text-sm font-semibold text-primary-foreground shadow-sm transition hover:bg-primary/90 disabled:cursor-not-allowed disabled:bg-muted"
              :disabled="!canSend"
            >
              <Icon icon="mdi:send-outline" class="h-4 w-4" />
              Enviar
            </button>
          </form>
          <p v-if="errorMessage" class="mt-2 text-xs text-destructive">{{ errorMessage }}</p>
        </div>
      </div>
    </section>

    <section class="rounded-lg border bg-card text-card-foreground shadow-sm">
      <div class="border-b px-6 py-4">
        <h2 class="text-lg font-semibold">Guía rápida</h2>
        <p class="text-sm text-muted-foreground">
          Atajos y recordatorios mientras llega el documento técnico.
        </p>
      </div>
      <div class="space-y-4 px-6 py-4 text-sm">
        <div class="rounded-md border bg-background p-4">
          <p class="text-xs font-semibold uppercase text-muted-foreground">Check de instalación</p>
          <ul class="mt-2 space-y-2 text-sm text-muted-foreground">
            <li>Confirmar energía y voltaje estables en el punto.</li>
            <li>Revisar conexión a red y direccionamiento IP.</li>
            <li>Validar señal y respuesta del equipo en app.</li>
          </ul>
        </div>
        <div class="rounded-md border bg-background p-4">
          <p class="text-xs font-semibold uppercase text-muted-foreground">Diagnóstico rápido</p>
          <ul class="mt-2 space-y-2 text-sm text-muted-foreground">
            <li>Reiniciar equipo y verificar logs locales.</li>
            <li>Probar cableado/antena en puntos alternos.</li>
            <li>Registrar evidencia antes de reemplazar piezas.</li>
          </ul>
        </div>
        <div class="rounded-md border bg-background p-4">
          <p class="text-xs font-semibold uppercase text-muted-foreground">Consulta sugerida</p>
          <p class="mt-2 text-muted-foreground">
            “Necesito los pasos para recalibrar un sensor PIR en exteriores con falsa
            deteccion.” 
          </p>
        </div>
      </div>
    </section>
  </div>
</template>
