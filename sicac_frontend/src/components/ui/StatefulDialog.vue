<script setup lang="ts">
import { type Ref } from "vue";
import { Loader2, CheckCircle2, XCircle } from "lucide-vue-next";
import { Button } from "@/components/ui/button";

interface Props {
  state: {
    loading: boolean | Ref<boolean>;
    error: any | Ref<any>;
    success: boolean | Ref<boolean>;
    reset: () => void;
  };
}

defineProps<Props>();
</script>

<template>
  <div class="h-full w-full">
    <!-- Loading State -->
    <div
      v-if="state.loading"
      class="flex flex-col items-center justify-center py-4 space-y-4"
    >
      <slot name="loading">
        <Loader2 class="h-12 w-12 animate-spin text-primary" />
        <div class="text-muted-foreground text-center">
             <slot name="title">Cargando...</slot>
        </div>
      </slot>
    </div>

    <!-- Success State -->
    <div
      v-else-if="state.success"
      class="flex flex-col h-full w-full items-center justify-center"
    >
      <slot name="success">
        <CheckCircle2 class="h-12 w-12 text-green-500" />
        <div class="text-green-600 font-medium text-center">
             <slot name="title">¡Operación exitosa!</slot>
        </div>
        <div class="text-muted-foreground text-sm text-center">
             <slot name="subtitle"></slot>
        </div>
        <div class="flex gap-2">
           <slot name="actions">
                <Button @click="state.reset()">Volver</Button>
           </slot>
        </div>
      </slot>
    </div>

    <!-- Error State -->
    <div
      v-else-if="state.error"
      class="flex flex-col items-center justify-center py-10 space-y-4"
    >
      <slot name="error">
        <XCircle class="h-12 w-12 text-destructive" />
        <div class="text-destructive font-medium text-center">
             <slot name="title">Error en la operación</slot>
        </div>
        <p class="text-sm text-center text-muted-foreground">
             <slot name="subtitle">{{ state.error?.message || "Ocurrió un error inesperado." }}</slot>
        </p>
         <slot name="actions">
            <Button variant="secondary" @click="state.reset()">Intentar de nuevo</Button>
         </slot>
      </slot>
    </div>

    <!-- Default/Idle State -->
    <slot v-else></slot>
  </div>
</template>
