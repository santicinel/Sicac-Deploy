<script setup lang="ts">
import { Button } from '@/components/ui/button'
import { useRouter } from 'vue-router'

const router = useRouter()

const faqs = [
  {
    question: 'No puedo iniciar sesion, que debo verificar?',
    answer:
      'Verifica que el email y la contrasena sean correctos. Confirma que no haya espacios adicionales y que tu cuenta se encuentre activa. Si el inconveniente persiste, intenta nuevamente luego de unos minutos o contacta al canal de soporte.',
  },
  {
    question: 'Como consulto productos y genero un presupuesto?',
    answer:
      'Desde Catalogo puedes filtrar por familia, subfamilia y criterios tecnicos para identificar el producto adecuado. Luego agrega los items al presupuesto y genera el documento PDF con el detalle de materiales y valores.',
  },
  {
    question: 'Como se gestiona una solicitud tecnica o un reclamo?',
    answer:
      'El cliente crea la solicitud desde Soporte y reclamos, indicando descripcion, rango de fechas y turno. El equipo tecnico y administracion visualizan la solicitud, gestionan la asignacion y actualizan el estado hasta su cierre.',
  },
  {
    question: 'Que alcance operativo tiene cada rol?',
    answer:
      'Cliente: consulta catalogo, genera presupuestos y registra solicitudes. Tecnico: toma casos, informa solucion y completa tareas asignadas. Administracion: supervisa solicitudes, administra tecnicos/productos y controla indicadores de servicio.',
  },
  {
    question: 'Los estados de las solicitudes se actualizan en tiempo real?',
    answer:
      'La plataforma refleja la evolucion del caso en el historial de solicitudes del cliente y en los paneles de gestion de tecnico y administracion. Cada cambio de estado queda registrado para seguimiento y auditoria operativa.',
  },
  {
    question: 'Donde encuentro los manuales por rol?',
    answer:
      'En esta misma seccion se encuentran los manuales de Usuario (Cliente), Tecnico y Administracion, con procedimientos y lineamientos de uso para cada perfil.',
  },
]
</script>

<template>
  <div class="relative flex min-h-screen flex-col items-center p-4 bg-background">
    <div class="w-full max-w-4xl space-y-6 py-8">
      <div class="flex items-center justify-between gap-4">
        <h1 class="text-2xl md:text-3xl font-bold tracking-tight">Preguntas frecuentes</h1>
        <Button variant="outline" @click="router.push('/login')">Volver al login</Button>
      </div>

      <div class="rounded-xl border bg-card p-4 md:p-6 space-y-4">
        <h2 class="text-lg font-semibold">Consultas frecuentes</h2>
        <div class="space-y-3">
          <div v-for="item in faqs" :key="item.question" class="rounded-lg border p-4">
            <h3 class="text-sm md:text-base font-semibold">{{ item.question }}</h3>
            <p class="text-sm text-muted-foreground mt-1">{{ item.answer }}</p>
          </div>
        </div>
      </div>

      <div class="rounded-xl border bg-card p-4 md:p-6 space-y-3">
        <h2 class="text-lg font-semibold">Manuales de usuario por rol</h2>
        <div class="grid gap-2 md:grid-cols-3">
          <router-link class="rounded-md border p-3 text-sm hover:bg-muted/40" to="/manual/usuario">
            Manual Usuario (Cliente)
          </router-link>
          <router-link class="rounded-md border p-3 text-sm hover:bg-muted/40" to="/manual/tecnico">
            Manual Tecnico
          </router-link>
          <router-link class="rounded-md border p-3 text-sm hover:bg-muted/40" to="/manual/admin">
            Manual Admin
          </router-link>
        </div>
      </div>
    </div>
  </div>
</template>
