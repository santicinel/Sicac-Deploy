<script setup lang="ts">
import { Icon } from "@iconify/vue"

import {
  SidebarMenu,
  SidebarMenuButton,
  SidebarMenuItem,
} from "@/components/ui/sidebar"

defineProps<{
  items: {
    title: string
    url: string
    icon: string
    isActive?: boolean
  }[]
}>()
</script>

<template>
  <SidebarMenu>
      <SidebarMenuItem v-for="item in items" :key="item.title">
      <SidebarMenuButton as-child :is-active="item.isActive">
        <router-link :to="item.url">
          <Icon :icon="item.icon" class="size-4 shrink-0" aria-hidden="true" />
          <span>{{ item.title }}</span>
        </router-link>
      </SidebarMenuButton>
    </SidebarMenuItem>
  </SidebarMenu>
</template>
