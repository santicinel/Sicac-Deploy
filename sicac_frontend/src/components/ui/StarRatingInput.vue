<script setup lang="ts">
import { computed } from "vue";
import { Icon } from "@iconify/vue";

const props = withDefaults(
  defineProps<{
    modelValue: number | null;
    max?: number;
    disabled?: boolean;
    iconClass?: string;
  }>(),
  {
    max: 5,
    disabled: false,
    iconClass: "h-5 w-5",
  }
);

const emit = defineEmits<{
  (e: "update:modelValue", value: number | null): void;
}>();

const currentValue = computed(() => {
  const numeric = Number(props.modelValue ?? 0);
  if (!Number.isFinite(numeric) || numeric <= 0) return 0;
  return Math.min(props.max, Math.max(0, Math.trunc(numeric)));
});

const setRating = (value: number) => {
  if (props.disabled) return;
  emit("update:modelValue", currentValue.value === value ? null : value);
};
</script>

<template>
  <div class="flex items-center gap-1">
    <button
      v-for="star in max"
      :key="star"
      type="button"
      class="rounded-sm p-0.5 transition-colors disabled:cursor-not-allowed"
      :disabled="disabled"
      :aria-label="`Puntuar ${star} de ${max}`"
      @click="setRating(star)"
    >
      <Icon
        :icon="star <= currentValue ? 'mdi:star' : 'mdi:star-outline'"
        :class="[iconClass, star <= currentValue ? 'text-amber-500' : 'text-muted-foreground']"
      />
    </button>
    <span class="ml-2 text-xs text-muted-foreground">
      {{ currentValue > 0 ? `${currentValue}/${max}` : "Sin puntuar" }}
    </span>
  </div>
</template>
