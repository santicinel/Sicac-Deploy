<script setup lang="ts">


defineProps<{
  isOpen: boolean;
  title: string;
  description: string;
  confirmText?: string;
  cancelText?: string;
}>();

const emit = defineEmits<{
  (e: 'confirm'): void;
  (e: 'cancel'): void;
}>();
</script>

<template>
  <div v-if="isOpen" class="fixed inset-0 z-50 flex items-center justify-center">
    <!-- Backdrop -->
    <div class="fixed inset-0 bg-black/50 backdrop-blur-sm transition-opacity" @click="emit('cancel')"></div>
    
    <!-- Dialog Content -->
    <div class="z-50 w-full max-w-md rounded-lg bg-background p-6 shadow-lg border border-border sm:rounded-xl">
      <div class="flex flex-col space-y-2 text-center sm:text-left">
        <h3 class="text-lg font-semibold leading-none tracking-tight">
          {{ title }}
        </h3>
        <p class="text-sm text-muted-foreground">
          {{ description }}
        </p>
      </div>

      <div class="mt-6 flex flex-col-reverse sm:flex-row sm:justify-end sm:space-x-2 gap-2 sm:gap-0">
        <button 
          @click="emit('cancel')"
          class="inline-flex items-center justify-center rounded-md border border-input bg-background px-4 py-2 text-sm font-medium shadow-sm transition-colors hover:bg-accent hover:text-accent-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
        >
          {{ cancelText || 'Cancelar' }}
        </button>
        <button 
          @click="emit('confirm')"
          class="inline-flex items-center justify-center rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground shadow transition-colors hover:bg-primary/90 focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
        >
          {{ confirmText || 'Confirmar' }}
        </button>
      </div>
    </div>
  </div>
</template>
