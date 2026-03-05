<script setup lang="ts">
import { ref, computed } from 'vue'
import type { SidebarProps } from '@/components/ui/sidebar'
import { useAuthStore } from '@/store/authStore'

import { Icon } from "@iconify/vue"

import NavMain from '@/components/NavMain.vue'
import NavUser from '@/components/NavUser.vue'
import TechnicianDailyItinerary from '@/components/TechnicianDailyItinerary.vue'
import {
  Sidebar,
  SidebarContent,
  SidebarFooter,
  SidebarHeader,
  SidebarMenu,
  SidebarMenuButton,
  SidebarMenuItem,
} from '@/components/ui/sidebar'

import { useRoute } from 'vue-router'
import companyLogo from '../../sicac.png'

const props = withDefaults(defineProps<SidebarProps>(), {
  variant: "inset",
})

const showInfo = ref(false)
const authStore = useAuthStore()
const route = useRoute()

const user = computed(() => {
  return {
    name: authStore.user?.name ?? "Usuario",
    email: authStore.user?.email ?? "usuario@ceainsumos.com",
    avatar: "/avatars/shadcn.jpg",
  }
})

const isTechnician = computed(() => authStore.role === "technician")

const isActive = (url: string) => {
    return route.path === url || route.path.startsWith(url + '/')
}

const navMain = computed(() => {
  if (authStore.role === "admin") {
    return [
      {
        title: "Reclamos",
        url: "/admin/claims",
        icon: "mdi:clipboard-text-outline",
        isActive: isActive("/admin/claims"),
      },
      {
        title: "Técnicos",
        url: "/admin/technicians",
        icon: "mdi:account-wrench-outline",
        isActive: isActive("/admin/technicians"),
      },
      {
        title: "Productos",
        url: "/admin/products",
        icon: "mdi:package-variant-closed",
        isActive: isActive("/admin/products"),
      },
      {
        title: "Puntajes",
        url: "/admin/ratings",
        icon: "mdi:star-outline",
        isActive: isActive("/admin/ratings"),
      },
      {
        title: "Analíticas BI",
        url: "/admin/dashboard-bi",
        icon: "mdi:chart-bar",
        isActive: isActive("/admin/dashboard-bi"),
      },
      {
        title: "Mano de obra",
        url: "/admin/labor-rate",
        icon: "mdi:hammer-wrench",
        isActive: isActive("/admin/labor-rate"),
      },
      {
        title: "Vista Usuario",
        url: "/home",
        icon: "mdi:account-circle-outline",
        isActive: isActive("/home"),
      },
    ]
  }

  if (authStore.role === "technician") {
    return [
      {
        title: "Mis reclamos",
        url: "/technician/claims",
        icon: "mdi:clipboard-account-outline",
        isActive: isActive("/technician/claims"),
      },
      {
        title: "Chat técnico",
        url: "/technician/chat",
        icon: "mdi:message-text-outline",
        isActive: isActive("/technician/chat"),
      },
    ]
  }

  return [
    {
      title: "Productos",
      url: "/home",
      icon: "mdi:camera-outline",
      isActive: isActive("/home"), // Assuming /home is the products page for users
    },
    {
      title: "Recomendaciones de IA",
      url: "/ai-recommendation",
      icon: "mdi:robot-outline",
      isActive: isActive("/ai-recommendation"),
    },
    {
      title: "Soporte y reclamos",
      url: "/support",
      icon: "mdi:lifebuoy",
      isActive: isActive("/support"),
    },
    {
      title: "Presupuesto",
      url: "/budget",
      icon: "mdi:cart-outline",
      isActive: isActive("/budget"),
    },
  ]
})
</script>

<template>
  <Sidebar v-bind="props">
    <SidebarHeader>
      <SidebarMenu>
        <SidebarMenuItem>
          <SidebarMenuButton size="lg" as-child>
            <a href="#">
              <div class="flex aspect-square size-14 items-center justify-center">
                <img :src="companyLogo" alt="Logo SICAC" class="size-12 object-contain" />
              </div>
              <div class="grid flex-1 text-left text-sm leading-tight">
                <span class="truncate font-medium">CEA Insumos</span>
                <span class="truncate text-xs">Sistemas de seguridad</span>
              </div>
            </a>
          </SidebarMenuButton>
        </SidebarMenuItem>
      </SidebarMenu>
    </SidebarHeader>
    <SidebarContent>
      <NavMain :items="navMain" />
      <TechnicianDailyItinerary v-if="isTechnician" />
    </SidebarContent>
    <SidebarFooter>
      <SidebarMenu>
        <SidebarMenuItem>
          <SidebarMenuButton @click="showInfo = !showInfo" class="w-full justify-between group">
             <span class="flex items-center gap-2">
                <Icon icon="mdi:information-outline" />
                <span>Sobre Nosotros</span>
             </span>
             <Icon :icon="showInfo ? 'mdi:chevron-down' : 'mdi:chevron-up'" class="text-muted-foreground/50 group-hover:text-muted-foreground transition-colors" />
          </SidebarMenuButton>
          <div v-show="showInfo" class="px-2 py-2 text-xs text-muted-foreground space-y-2 border-l-2 border-sidebar-border ml-3 my-1 animate-in slide-in-from-left-1 fade-in">
              <div>
                <strong class="text-foreground block mb-0.5">CEA Insumos</strong>
                <span>Buenos Aires 432<br>Firmat</span>
              </div>
              <div class="flex items-center gap-2">
                <Icon icon="mdi:clock-outline" class="h-3 w-3" />
                <span>L-V 08-18 hs</span>
              </div>
              <div class="flex items-center gap-2">
                <Icon icon="mdi:phone" class="h-3 w-3" />
                <span>+54 (3465) 665656</span>
              </div>
              <a href="mailto:contacto@ceainsumos.com" class="flex items-center gap-2 hover:text-primary transition-colors">
                <Icon icon="mdi:email-outline" class="h-3 w-3" />
                <span>contacto@ceainsumos.com</span>
              </a>
              <div class="pt-2 border-t border-border/50">
                  <router-link to="/about" class="flex items-center gap-2 text-primary hover:underline font-medium">
                      <Icon icon="mdi:arrow-right-thin" class="h-4 w-4" />
                      <span>Ver más detalles</span>
                  </router-link>
              </div>
          </div>
        </SidebarMenuItem>
      </SidebarMenu>
      <NavUser :user="user" />
    </SidebarFooter>
  </Sidebar>
</template>

