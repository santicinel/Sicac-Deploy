<script setup lang="ts">
import { ref, computed } from 'vue'
import type { HTMLAttributes } from 'vue'
import { cn } from '@/lib/utils'
import { useRouter } from 'vue-router'
import { toast } from 'vue-sonner'
import { useAuthStore } from '@/store/authStore'
import LoginForm from '@/components/auth/LoginForm.vue'
import RegisterForm from '@/components/auth/RegisterForm.vue'
import ForgotPasswordForm from '@/components/auth/ForgotPasswordForm.vue'
import CameraWall from '@/components/login/CameraWall.vue'
import companyLogo from '../../sicac.png'

const props = defineProps<{
  class?: HTMLAttributes['class']
}>()

type AuthMode = 'login' | 'register' | 'recover'
const authMode = ref<AuthMode>('login')
const formState = ref<'idle' | 'tracking' | 'privacy' | 'success' | 'fail'>('idle')
const isPasswordVisible = ref(false)
const router = useRouter()
const authStore = useAuthStore()

const toggleAuth = () => {
  authMode.value = authMode.value === 'login' ? 'register' : 'login'
}

const customToggle = (mode: AuthMode) => {
  authMode.value = mode
}

const currentComponent = computed(() => {
  switch (authMode.value) {
    case 'login':
      return LoginForm
    case 'register':
      return RegisterForm
    case 'recover':
      return ForgotPasswordForm
    default:
      return LoginForm
  }
})

const handleFocusIn = (e: FocusEvent) => {
  if (formState.value === 'success' || formState.value === 'fail') return

  const target = e.target as HTMLInputElement
  if (target.type === 'password') {
    formState.value = isPasswordVisible.value ? 'tracking' : 'privacy'
    return
  }

  formState.value = 'tracking'
}

const handleFocusOut = () => {
  if (formState.value === 'success' || formState.value === 'fail') return

  if (!isPasswordVisible.value) {
    formState.value = 'privacy'
    return
  }

  formState.value = 'idle'
}

const handlePasswordVisibility = (visible: boolean) => {
  isPasswordVisible.value = visible
  formState.value = visible ? 'tracking' : 'privacy'
}

const resolveErrorMessage = (error: unknown) => {
  if (error instanceof Error && error.message) {
    return error.message
  }
  return 'No se pudo iniciar sesión.'
}

const handleLoginSuccess = async (response: any) => {
  formState.value = 'success'
  toast.success('Inicio de sesión correcto.')
  const user = response?.data?.user
  if (user && user.role) {
    authStore.setUser(user)
  }

  if (user?.role === 'admin') {
    await router.push('/admin/claims')
  } else if (user?.role === 'technician') {
    await router.push('/technician/claims')
  } else {
    await router.push('/home')
  }
}

const handleLoginError = (error: unknown) => {
  formState.value = 'fail'
  toast.error(resolveErrorMessage(error))
  setTimeout(() => {
    formState.value = 'idle'
  }, 1000)
}
</script>

<template>
  <div class="flex min-h-screen w-full bg-background">
    <div class="hidden lg:flex w-[40%] relative bg-zinc-950 items-center justify-center overflow-hidden">
      <CameraWall :form-state="formState" />
    </div>

    <div class="w-full lg:w-[60%] flex flex-col items-center justify-center p-8 border-l shadow-2xl z-20 bg-card relative">
      <div class="absolute top-8 left-8 lg:top-12 lg:left-12 z-30 pointer-events-none">
        <div class="flex items-center gap-3">
          <img :src="companyLogo" alt="Logo SICAC" class="h-12 w-12 object-contain" />
          <h2 class="text-3xl font-extrabold tracking-tighter opacity-80 text-foreground">CEA<br />INSUMOS</h2>
        </div>
      </div>

      <div :class="cn('w-full space-y-6 max-w-[450px]', props.class)">
        <div class="space-y-2 text-center">
          <h1 class="text-3xl font-bold tracking-tight">Bienvenido</h1>
        </div>

        <div @focusin="handleFocusIn" @focusout="handleFocusOut">
          <Transition
            enter-active-class="transition duration-300 ease-out"
            enter-from-class="transform translate-y-4 opacity-0"
            enter-to-class="transform translate-y-0 opacity-100"
            leave-active-class="transition duration-200 ease-in"
            leave-from-class="transform translate-y-0 opacity-100"
            leave-to-class="transform translate-y-4 opacity-0"
            mode="out-in"
          >
            <component
              :is="currentComponent"
              @toggle-auth="toggleAuth"
              @forgot-password="customToggle('recover')"
              @to-login="customToggle('login')"
              @login-success="handleLoginSuccess"
              @login-error="handleLoginError"
              @password-visibility-changed="handlePasswordVisibility"
            />
          </Transition>
        </div>

        <div class="text-center text-xs text-muted-foreground space-y-2">
          <p>
            Ayuda rapida:
            <RouterLink to="/faq" class="underline hover:text-primary">Preguntas frecuentes</RouterLink>.
          </p>
          <p>
            Al ingresar aceptas nuestros
            <RouterLink to="/terms" class="underline hover:text-primary">Terminos y condiciones</RouterLink>.
          </p>
        </div>
      </div>
    </div>
  </div>
</template>
