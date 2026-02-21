<script setup lang="ts">
import { computed } from "vue"
import type { Product } from "@/services/productsService"
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from "@/components/ui/card"

const props = defineProps<{
  item: Product
  onDelete?: (item: Product) => Promise<void> | void
  deletingId?: number | string | null
}>()

const brandLabel = computed(() =>
  props.item.brand?.name ?? props.item.brand?.label ?? props.item.brand?.nombre ?? props.item.brand_id
)
const categoryLabel = computed(() =>
  props.item.category?.name ?? props.item.category?.label ?? props.item.category?.nombre ?? props.item.category_id
)
const subfamilyLabel = computed(() =>
  props.item.subfamily?.name ?? props.item.subfamily?.label ?? props.item.subfamily?.nombre ?? props.item.subfamily_id
)

const priceLabel = computed(() => {
  const price = props.item.price_ars
  if (price === null || price === undefined) return "Sin precio"
  const numeric = typeof price === "string" ? Number(price) : price
  if (Number.isNaN(numeric)) return String(price)
  return new Intl.NumberFormat("es-AR", {
    style: "currency",
    currency: "ARS",
    maximumFractionDigits: 0,
  }).format(numeric)
})

const isDeleting = computed(() => props.deletingId === props.item.id)
const canDelete = computed(() => typeof props.onDelete === "function")

const handleDelete = async () => {
  if (!props.onDelete || isDeleting.value) return
  await props.onDelete(props.item)
}
</script>

<template>
  <Card>
    <CardHeader>
      <CardTitle class="text-base">{{ item.name }}</CardTitle>
      <CardDescription>{{ item.external_id }}</CardDescription>
    </CardHeader>
    <CardContent class="space-y-2 text-sm">
      <div class="flex items-center justify-between">
        <span class="text-muted-foreground">Marca</span>
        <span class="font-medium">{{ brandLabel }}</span>
      </div>
      <div class="flex items-center justify-between">
        <span class="text-muted-foreground">Categoria</span>
        <span class="font-medium">{{ categoryLabel }}</span>
      </div>
      <div class="flex items-center justify-between">
        <span class="text-muted-foreground">Subfamilia</span>
        <span class="font-medium">{{ subfamilyLabel }}</span>
      </div>
      <div class="flex items-center justify-between">
        <span class="text-muted-foreground">Precio</span>
        <span class="font-semibold">{{ priceLabel }}</span>
      </div>
      <button
        v-if="canDelete"
        type="button"
        class="w-full rounded-md border px-3 py-2 text-xs font-semibold text-destructive hover:bg-destructive/10 disabled:cursor-not-allowed disabled:opacity-60"
        :disabled="isDeleting"
        @click="handleDelete"
      >
        {{ isDeleting ? "Eliminando..." : "Eliminar" }}
      </button>
    </CardContent>
  </Card>
</template>
