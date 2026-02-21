<script setup lang="ts" generic="TData, TValue">
import type { ColumnDef } from "@tanstack/vue-table"
import { useWindowSize } from "@vueuse/core"
import type { Component } from "vue"
import { computed } from "vue"

import type { PaginationData } from "@/interfaces"
import Datatable from "@/components/ui/datatable/Datatable.vue"
import {
  Pagination,
  PaginationContent,
  PaginationEllipsis,
  PaginationItem,
  PaginationNext,
  PaginationPrevious,
} from "@/components/ui/pagination"

const props = defineProps<{
  columns: ColumnDef<TData, TValue>[]
  data: TData[]
  cardComponent: Component
  cardProps?: Record<string, unknown>
  getItemKey?: (item: TData, index: number) => string | number
  forceCardMode?: boolean
  pagination?: PaginationData | null
}>()

const emit = defineEmits<{
  (event: "page-change", page: number): void
}>()

const { width } = useWindowSize()
const isMdOrAbove = computed(() => width.value >= 1024)
const showTable = computed(() => !props.forceCardMode && isMdOrAbove.value)
const resolvedCardProps = computed(() => props.cardProps ?? {})
const showPagination = computed(() => Boolean(props.pagination))

const getKey = (item: TData, index: number) =>
  props.getItemKey ? props.getItemKey(item, index) : index

const handlePageChange = (page: number) => {
  if (!props.pagination) return
  if (page === props.pagination.meta.current_page) return
  emit("page-change", page)
}
</script>

<template>
  <div class="w-full m-auto space-y-4">
    <Datatable
      v-if="showTable"
      :columns="columns"
      :data="data"
    />

    <div v-else class="space-y-3">
      <div
        v-if="!data.length"
        class="flex min-h-[160px] items-center justify-center rounded-lg border border-dashed border-muted-foreground/30 bg-muted/30 px-4 text-sm text-muted-foreground"
      >
        No hay resultados para mostrar.
      </div>

      <div v-else class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <component
          :is="cardComponent"
          v-for="(item, index) in data"
          :key="getKey(item, index)"
          :item="item"
          v-bind="resolvedCardProps"
        />
      </div>
    </div>

    <Pagination
      v-if="showPagination"
      :page="pagination?.meta.current_page"
      :total="pagination?.meta.total"
      :items-per-page="pagination?.meta.per_page ?? 1"
      @update:page="handlePageChange"
    >
      <PaginationContent v-slot="{ items }">
        <PaginationPrevious />
        <template v-for="(item, index) in items" :key="item.type === 'page' ? item.value : `ellipsis-${index}`">
          <PaginationItem
            v-if="item.type === 'page'"
            :value="item.value"
            :is-active="item.value === pagination?.meta.current_page"
          >
            {{ item.value }}
          </PaginationItem>
          <PaginationEllipsis v-else />
        </template>
        <PaginationNext />
      </PaginationContent>
    </Pagination>
  </div>
</template>
