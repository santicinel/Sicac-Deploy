<script setup lang="ts">
import {
  ChevronsUpDown,
  LogOut,
  User,
  X,
} from "lucide-vue-next"
import { useRouter } from "vue-router"
import { useAuthStore } from "@/store/authStore"
import { computed, reactive, ref } from "vue"
import { toast } from "vue-sonner"
import { getCurrentUser, updateCurrentUser } from "@/services/authService"

import {
  Avatar,
  AvatarFallback,
  AvatarImage,
} from "@/components/ui/avatar"
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuGroup,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu"
import {
  SidebarMenu,
  SidebarMenuButton,
  SidebarMenuItem,
  useSidebar,
} from "@/components/ui/sidebar"

const props = defineProps<{
  user: {
    name: string
    email: string
    avatar: string
  }
}>()

const { isMobile } = useSidebar()
const router = useRouter()
const authStore = useAuthStore()
const showProfile = ref(false)
const profileSaving = ref(false)
const profileSyncing = ref(false)

const PROFILE_STORAGE_KEY = "sicac_profile"
const DEFAULT_INITIALS = "US"

type ProfileData = {
  name?: string
  email?: string
  phone?: string
  address?: string
  city?: string
}

type ProfileMap = Record<string, ProfileData>

const normalizeEmail = (email: string) => email.trim().toLowerCase()

const resolveCurrentName = () => authStore.user?.name ?? props.user.name ?? ""
const resolveCurrentEmail = () => authStore.user?.email ?? props.user.email ?? ""
const resolveCurrentPhone = () => authStore.user?.phone ?? ""
const resolveCurrentAddress = () => authStore.user?.address ?? ""
const resolveCurrentCity = () => authStore.user?.city ?? ""
const isDemoUser = computed(() => normalizeEmail(resolveCurrentEmail()).endsWith("@demo.com"))

const getInitialsFromName = (name: string) => {
  const parts = name
    .trim()
    .split(/\s+/)
    .filter(Boolean)

  if (!parts.length) return DEFAULT_INITIALS
  if (parts.length === 1) return parts[0]?.slice(0, 2).toUpperCase() ?? DEFAULT_INITIALS

  const first = parts[0]?.charAt(0) ?? ""
  const last = parts[parts.length - 1]?.charAt(0) ?? ""
  return `${first}${last}`.toUpperCase() || DEFAULT_INITIALS
}

const userInitials = computed(() => getInitialsFromName(resolveCurrentName()))

const toProfileData = (value: unknown): ProfileData | null => {
  if (!value || typeof value !== "object" || Array.isArray(value)) return null
  const candidate = value as Record<string, unknown>
  return {
    name: typeof candidate.name === "string" ? candidate.name : undefined,
    email: typeof candidate.email === "string" ? candidate.email : undefined,
    phone: typeof candidate.phone === "string" ? candidate.phone : undefined,
    address: typeof candidate.address === "string" ? candidate.address : undefined,
    city: typeof candidate.city === "string" ? candidate.city : undefined,
  }
}

const loadProfileMap = (): ProfileMap => {
  if (typeof window === "undefined") return {}
  try {
    const raw = window.localStorage.getItem(PROFILE_STORAGE_KEY)
    if (!raw) return {}

    const parsed = JSON.parse(raw) as unknown
    if (!parsed || typeof parsed !== "object" || Array.isArray(parsed)) return {}

    // Compatibilidad con formato viejo: un unico objeto plano.
    const legacy = toProfileData(parsed)
    const isLegacy =
      Boolean(legacy?.name) ||
      Boolean(legacy?.email) ||
      Boolean(legacy?.phone) ||
      Boolean(legacy?.address) ||
      Boolean(legacy?.city)

    if (isLegacy) {
      const key = normalizeEmail(legacy?.email ?? resolveCurrentEmail())
      return key ? { [key]: legacy ?? {} } : {}
    }

    return parsed as ProfileMap
  } catch {
    return {}
  }
}

const profileForm = reactive({
  name: resolveCurrentName(),
  email: resolveCurrentEmail(),
  phone: "",
  address: "",
  city: "",
})

const loadProfileForCurrentUser = () => {
  const key = normalizeEmail(resolveCurrentEmail())
  if (!key) return null
  const profiles = loadProfileMap()
  return profiles[key] ?? null
}

const persistProfileLocally = (
  previousEmail: string,
  nextProfile: {
    name: string
    email: string
    phone: string
    address: string
    city: string
  }
) => {
  if (typeof window === "undefined") return

  const profiles = loadProfileMap()
  const nextEmailKey = normalizeEmail(nextProfile.email)

  if (previousEmail && previousEmail !== nextEmailKey) {
    delete profiles[previousEmail]
  }

  if (nextEmailKey) {
    profiles[nextEmailKey] = {
      name: nextProfile.name,
      email: nextProfile.email,
      phone: nextProfile.phone,
      address: nextProfile.address,
      city: nextProfile.city,
    }
  }

  window.localStorage.setItem(PROFILE_STORAGE_KEY, JSON.stringify(profiles))
}

const openProfile = async () => {
  const stored = loadProfileForCurrentUser()

  profileForm.name = stored?.name?.trim() || resolveCurrentName()
  profileForm.email = stored?.email?.trim() || resolveCurrentEmail()
  profileForm.phone = stored?.phone?.trim() || resolveCurrentPhone()
  profileForm.address = stored?.address?.trim() || resolveCurrentAddress()
  profileForm.city = stored?.city?.trim() || resolveCurrentCity()
  showProfile.value = true

  if (isDemoUser.value) return

  profileSyncing.value = true
  try {
    const remoteUser = await getCurrentUser()
    if (!remoteUser) return

    const mergedUser = {
      id: remoteUser.id ?? authStore.user?.id,
      name: remoteUser.name ?? resolveCurrentName(),
      email: remoteUser.email ?? resolveCurrentEmail(),
      role: remoteUser.role ?? authStore.user?.role ?? "user",
      dni: remoteUser.dni ?? authStore.user?.dni ?? null,
      phone: remoteUser.phone ?? null,
      address: remoteUser.address ?? null,
      city: remoteUser.city ?? null,
    }
    authStore.setUser(mergedUser)

    profileForm.name = remoteUser.name ?? profileForm.name
    profileForm.email = remoteUser.email ?? profileForm.email
    profileForm.phone = remoteUser.phone ?? profileForm.phone
    profileForm.address = remoteUser.address ?? profileForm.address
    profileForm.city = remoteUser.city ?? profileForm.city

    persistProfileLocally(normalizeEmail(resolveCurrentEmail()), {
      name: profileForm.name.trim(),
      email: profileForm.email.trim(),
      phone: profileForm.phone.trim(),
      address: profileForm.address.trim(),
      city: profileForm.city.trim(),
    })
  } catch (error) {
    console.error(error)
  } finally {
    profileSyncing.value = false
  }
}

const saveProfile = async () => {
  if (profileSaving.value) return

  profileSaving.value = true
  const previousEmail = normalizeEmail(resolveCurrentEmail())
  const nextName = profileForm.name.trim() || resolveCurrentName()
  const nextEmail = profileForm.email.trim() || resolveCurrentEmail()
  const nextPhone = profileForm.phone.trim()
  const nextAddress = profileForm.address.trim()
  const nextCity = profileForm.city.trim()

  persistProfileLocally(previousEmail, {
    name: nextName,
    email: nextEmail,
    phone: nextPhone,
    address: nextAddress,
    city: nextCity,
  })

  try {
    let mergedUser = {
      id: authStore.user?.id,
      name: nextName,
      email: nextEmail,
      role: authStore.user?.role ?? "user",
      dni: authStore.user?.dni ?? null,
      phone: nextPhone || null,
      address: nextAddress || null,
      city: nextCity || null,
    }

    if (!isDemoUser.value) {
      const updatedUser = await updateCurrentUser({
        name: nextName,
        email: nextEmail,
        phone: nextPhone || null,
        address: nextAddress || null,
        city: nextCity || null,
      })

      if (!updatedUser) {
        throw new Error("No se recibieron datos actualizados del usuario.")
      }

      mergedUser = {
        id: updatedUser.id ?? authStore.user?.id,
        name: updatedUser.name ?? nextName,
        email: updatedUser.email ?? nextEmail,
        role: updatedUser.role ?? authStore.user?.role ?? "user",
        dni: updatedUser.dni ?? authStore.user?.dni ?? null,
        phone: updatedUser.phone ?? null,
        address: updatedUser.address ?? null,
        city: updatedUser.city ?? null,
      }
    }

    authStore.setUser(mergedUser)
    toast.success("Datos personales actualizados.")
    showProfile.value = false
  } catch (error) {
    console.error(error)
    toast.error("No se pudieron guardar los datos personales.")
  } finally {
    profileSaving.value = false
  }
}

const handleLogout = async () => {
  authStore.logout()
  await router.push("/login")
}
</script>

<template>
  <SidebarMenu>
    <SidebarMenuItem>
      <DropdownMenu>
        <DropdownMenuTrigger as-child>
          <SidebarMenuButton
            size="lg"
            class="data-[state=open]:bg-sidebar-accent data-[state=open]:text-sidebar-accent-foreground"
          >
            <Avatar class="h-8 w-8 rounded-lg">
              <AvatarImage :src="user.avatar" :alt="user.name" />
              <AvatarFallback class="rounded-lg">
                {{ userInitials }}
              </AvatarFallback>
            </Avatar>
            <div class="grid flex-1 text-left text-sm leading-tight">
              <span class="truncate font-medium">{{ user.name }}</span>
              <span class="truncate text-xs">{{ user.email }}</span>
            </div>
            <ChevronsUpDown class="ml-auto size-4" />
          </SidebarMenuButton>
        </DropdownMenuTrigger>
        <DropdownMenuContent
          class="w-[--reka-dropdown-menu-trigger-width] min-w-56 rounded-lg"
          :side="isMobile ? 'bottom' : 'right'"
          align="end"
          :side-offset="4"
        >
          <DropdownMenuLabel class="p-0 font-normal">
            <div class="flex items-center gap-2 px-1 py-1.5 text-left text-sm">
              <Avatar class="h-8 w-8 rounded-lg">
                <AvatarImage :src="user.avatar" :alt="user.name" />
                <AvatarFallback class="rounded-lg">
                  {{ userInitials }}
                </AvatarFallback>
              </Avatar>
              <div class="grid flex-1 text-left text-sm leading-tight">
                <span class="truncate font-semibold">{{ user.name }}</span>
                <span class="truncate text-xs">{{ user.email }}</span>
              </div>
            </div>
          </DropdownMenuLabel>
          <DropdownMenuSeparator />

          <DropdownMenuGroup>
            <DropdownMenuItem @click="openProfile">
              <User />
              Datos personales
            </DropdownMenuItem>
          </DropdownMenuGroup>

          <DropdownMenuSeparator />
          <DropdownMenuItem @click="handleLogout">
            <LogOut />
            Cerrar sesion
          </DropdownMenuItem>
        </DropdownMenuContent>
      </DropdownMenu>
    </SidebarMenuItem>
  </SidebarMenu>

  <div
    v-if="showProfile"
    class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
  >
    <div class="w-full max-w-lg rounded-lg border bg-card text-card-foreground shadow-lg">
      <div class="flex items-start justify-between border-b px-6 py-4">
        <div>
          <h2 class="text-lg font-semibold">Datos personales</h2>
          <p class="text-sm text-muted-foreground">
            Actualiza tus datos de contacto si lo necesitas.
          </p>
        </div>
        <button
          class="text-muted-foreground hover:text-foreground"
          @click="showProfile = false"
        >
          <X class="h-5 w-5" />
        </button>
      </div>
      <form class="grid gap-4 px-6 py-4 text-sm" @submit.prevent="saveProfile">
        <p v-if="profileSyncing" class="text-xs text-muted-foreground">
          Cargando datos guardados...
        </p>
        <label class="grid gap-2 text-sm">
          <span class="text-xs font-semibold uppercase text-muted-foreground">Nombre</span>
          <input
            v-model="profileForm.name"
            type="text"
            class="w-full rounded-md border border-input px-3 py-2 text-sm"
            :disabled="profileSaving || profileSyncing"
          />
        </label>
        <label class="grid gap-2 text-sm">
          <span class="text-xs font-semibold uppercase text-muted-foreground">Email</span>
          <input
            v-model="profileForm.email"
            type="email"
            class="w-full rounded-md border border-input px-3 py-2 text-sm"
            :disabled="profileSaving || profileSyncing"
          />
        </label>
        <label class="grid gap-2 text-sm">
          <span class="text-xs font-semibold uppercase text-muted-foreground">Telefono</span>
          <input
            v-model="profileForm.phone"
            type="text"
            class="w-full rounded-md border border-input px-3 py-2 text-sm"
            :disabled="profileSaving || profileSyncing"
          />
        </label>
        <label class="grid gap-2 text-sm">
          <span class="text-xs font-semibold uppercase text-muted-foreground">Ciudad</span>
          <input
            v-model="profileForm.city"
            type="text"
            class="w-full rounded-md border border-input px-3 py-2 text-sm"
            :disabled="profileSaving || profileSyncing"
          />
        </label>
        <label class="grid gap-2 text-sm">
          <span class="text-xs font-semibold uppercase text-muted-foreground">Direccion</span>
          <input
            v-model="profileForm.address"
            type="text"
            class="w-full rounded-md border border-input px-3 py-2 text-sm"
            :disabled="profileSaving || profileSyncing"
          />
        </label>
        <div class="flex justify-end gap-2 border-t pt-4">
          <button
            type="button"
            class="rounded-md border px-4 py-2 text-sm"
            @click="showProfile = false"
            :disabled="profileSaving"
          >
            Cancelar
          </button>
          <button
            type="submit"
            class="rounded-md bg-primary px-4 py-2 text-sm font-semibold text-primary-foreground hover:bg-primary/90"
            :disabled="profileSaving || profileSyncing"
          >
            {{ profileSaving ? "Guardando..." : "Guardar" }}
          </button>
        </div>
      </form>
    </div>
  </div>
</template>
