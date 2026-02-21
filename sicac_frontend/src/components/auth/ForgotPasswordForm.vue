<script setup lang="ts">
import { Button } from '@/components/ui/button'
import {
  Card,
  CardContent,
  CardHeader,
  CardTitle,
  CardDescription,
} from '@/components/ui/card'
import {
  Field,
  FieldGroup,
  FieldLabel,
} from '@/components/ui/field'
import { Input } from '@/components/ui/input'
import { ref } from 'vue'

const emit = defineEmits<{
  (e: 'toggle-auth'): void
}>()

const email = ref('')

const handleRecover = () => {
    // Simulate recovery logic
    console.log('Instructions sent to', email.value)
    alert(`Se han enviado las instrucciones de recuperación a ${email.value}`)
    emit('toggle-auth')
}
</script>

<template>
  <Card class="border shadow-lg">
    <CardHeader class="text-center pb-2">
      <CardTitle class="text-xl">
        Recuperar Contraseña
      </CardTitle>
      <CardDescription>
        Ingresá tu email y te enviaremos las instrucciones
      </CardDescription>
    </CardHeader>
    <CardContent>
      <form @submit.prevent="handleRecover">
        <FieldGroup>
          <Field>
            <FieldLabel for="email">Email</FieldLabel>
            <Input
              id="email"
              type="email"
              v-model="email"
              placeholder="usuario@ejemplo.com"
              required
            />
          </Field>
          
          <Field>
            <Button type="submit" class="w-full">
              Enviar instrucciones
            </Button>
            <div class="text-center mt-4">
               <a href="#" class="text-sm font-medium text-muted-foreground hover:text-primary hover:underline" @click.prevent="emit('toggle-auth')">
                Volver al login
              </a>
            </div>
          </Field>
        </FieldGroup>
      </form>
    </CardContent>
  </Card>
</template>
