<script setup lang="ts">
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { toast } from 'vue-sonner'
import { loginTechnician } from '@/services/authService'
import { useAuthStore } from '@/store/authStore'
import { Icon } from '@iconify/vue'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card'
import { Field, FieldGroup, FieldLabel } from '@/components/ui/field'
import { Eye, EyeOff } from 'lucide-vue-next'

const email = ref('')
const password = ref('')
const showPassword = ref(false)
const isLoading = ref(false)

const router = useRouter()
const authStore = useAuthStore()

const handleSubmit = async () => {
  if (!email.value || !password.value) return

  isLoading.value = true
  try {
    const response = await loginTechnician({
      email: email.value,
      password: password.value
    })
    
    toast.success('Sesión de técnico iniciada.')
    const user = response?.data?.user
    if (user) {
      authStore.setUser(user)
      if (user.role === 'technician') {
        router.push('/technician/claims')
      } else {
        router.push('/home')
      }
    }
  } catch (error: any) {
    const msg = error.response?.data?.message || error.message || 'Error al iniciar sesión'
    toast.error(msg)
  } finally {
    isLoading.value = false
  }
}
</script>

<template>
  <Card class="border shadow-lg">
    <CardHeader class="text-center pb-2">
      <div class="flex justify-center mb-2">
        <Icon icon="mdi:account-wrench" class="w-12 h-12 text-primary" />
      </div>
      <CardTitle class="text-xl">
        Técnicos
      </CardTitle>
      <CardDescription>
        Acceso para personal técnico
      </CardDescription>
    </CardHeader>
    <CardContent>
      <form @submit.prevent="handleSubmit">
        <FieldGroup>
          <Field>
            <FieldLabel for="email">Email</FieldLabel>
            <Input
              id="email"
              v-model.trim="email"
              type="email"
              placeholder="tecnico@ejemplo.com"
              required
              :disabled="isLoading"
            />
          </Field>
          <Field>
            <div class="flex items-center">
              <FieldLabel for="password">Contraseña</FieldLabel>
            </div>
            <div class="relative">
                <Input 
                    id="password" 
                    v-model="password"
                    :type="showPassword ? 'text' : 'password'" 
                    required 
                    class="pr-10"
                    :disabled="isLoading"
                />
                <button 
                    type="button" 
                    class="absolute text-muted-foreground right-3 top-1/2 -translate-y-1/2 hover:text-foreground"
                    @click="showPassword = !showPassword"
                    :disabled="isLoading"
                >
                    <Eye v-if="!showPassword" class="w-4 h-4" />
                    <EyeOff v-else class="w-4 h-4" />
                </button>
            </div>
          </Field>
          <Field>
            <Button type="submit" class="w-full" :disabled="isLoading">
              {{ isLoading ? 'Ingresando...' : 'Ingresar' }}
            </Button>
          </Field>
        </FieldGroup>
      </form>
    </CardContent>
  </Card>
</template>
