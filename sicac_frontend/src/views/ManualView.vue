<script setup lang="ts">
import { computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { Button } from '@/components/ui/button'

type ManualRole = 'usuario' | 'tecnico' | 'admin'

const route = useRoute()
const router = useRouter()

interface ManualSection {
  title: string
  points: string[]
}

interface ManualContent {
  title: string
  subtitle: string
  sections: ManualSection[]
}

const manuals: Record<ManualRole, ManualContent> = {
  usuario: {
    title: 'Manual de Usuario (Cliente)',
    subtitle: 'Procedimiento operativo para gestionar catalogo, soporte y presupuesto.',
    sections: [
      {
        title: 'Paso 1: Ingreso y validacion de cuenta',
        points: [
          'Ingresa con email y contrasena registrados.',
          'Si olvidaste tu contrasena, utiliza la opcion de recuperacion desde login.',
          'Verifica tus datos personales desde el menu de usuario antes de operar.',
        ],
      },
      {
        title: 'Paso 2: Consulta de productos',
        points: [
          'Ingresa a Productos y utiliza filtros por categoria, familia, subfamilia o texto.',
          'Revisa cada ficha para confirmar descripcion tecnica y precio.',
          'Agrega al presupuesto unicamente los items que forman parte de la solucion.',
        ],
      },
      {
        title: 'Paso 3: Generacion de presupuesto',
        points: [
          'Abre Presupuesto para revisar cantidades, subtotal y mano de obra estimada.',
          'Genera el PDF y valida que el detalle coincida con lo solicitado.',
          'Consulta el historial de presupuestos para descargar documentos anteriores.',
        ],
      },
      {
        title: 'Paso 4: Solicitudes tecnicas y reclamos',
        points: [
          'En Soporte y reclamos selecciona tipo de caso, categoria, asunto y descripcion.',
          'Completa rango de fechas y turno requerido para la visita.',
          'Monitorea el estado del caso desde el historial hasta su cierre.',
        ],
      },
      {
        title: 'Paso 5: Seguimiento de cierre',
        points: [
          'Al completarse el caso, revisa la solucion informada por el tecnico.',
          'Verifica la fecha de visita programada y el producto reparado.',
          'Califica la atencion tecnica para cerrar la retroalimentacion del servicio.',
        ],
      },
    ],
  },
  tecnico: {
    title: 'Manual de Tecnico',
    subtitle: 'Procedimiento operativo para atencion tecnica y cierre de casos.',
    sections: [
      {
        title: 'Paso 1: Acceso al panel tecnico',
        points: [
          'Inicia sesion con tus credenciales institucionales.',
          'Revisa tus datos personales y confirma canales de contacto actualizados.',
        ],
      },
      {
        title: 'Paso 2: Revision de bandejas de trabajo',
        points: [
          'En Asignadas visualiza casos activos bajo tu responsabilidad.',
          'En Sin asignar revisa solicitudes disponibles para tomar.',
          'En Historial consulta intervenciones finalizadas o canceladas.',
        ],
      },
      {
        title: 'Paso 3: Toma y planificacion del caso',
        points: [
          'Abre el detalle y valida descripcion, rango de fechas y direccion del cliente.',
          'Define la fecha de visita dentro del rango solicitado.',
          'Actualiza estado y comunica avances de forma consistente.',
        ],
      },
      {
        title: 'Paso 4: Ejecucion y cierre tecnico',
        points: [
          'Al finalizar, registra una descripcion clara de la solucion aplicada.',
          'Selecciona el producto reparado para trazabilidad del caso.',
          'Completa la tarea y, si corresponde, califica la atencion del cliente.',
        ],
      },
      {
        title: 'Paso 5: Coordinacion y escalamiento',
        points: [
          'Utiliza Chat tecnico para consultas operativas y apoyo de diagnostico.',
          'Escala bloqueos a administracion cuando existan impedimentos de resolucion.',
          'Mantiene historial completo para auditoria y seguimiento de calidad.',
        ],
      },
    ],
  },
  admin: {
    title: 'Manual de Administrador',
    subtitle: 'Procedimiento operativo para gestion integral del sistema SICAC.',
    sections: [
      {
        title: 'Paso 1: Acceso y control inicial',
        points: [
          'Ingresa al panel de administracion con credenciales autorizadas.',
          'Verifica estado general de solicitudes, reclamos y carga operativa.',
        ],
      },
      {
        title: 'Paso 2: Gestion de solicitudes y reclamos',
        points: [
          'Filtra por estado, tipo y cliente para priorizar atencion.',
          'Asigna tecnicos, actualiza estado y monitorea el avance de cada caso.',
          'Asegura coherencia entre asignacion, visitas y estado final.',
        ],
      },
      {
        title: 'Paso 3: Administracion de tecnicos',
        points: [
          'Registra altas, modificaciones y bajas de tecnicos.',
          'Valida datos de contacto y trazabilidad de cada perfil.',
          'Controla asignaciones para balancear carga operativa.',
        ],
      },
      {
        title: 'Paso 4: Catalogo y parametros',
        points: [
          'Administra productos, categorias y precios con criterios consistentes.',
          'Define parametros de mano de obra para presupuestos.',
          'Revisa impacto de cambios antes de su aplicacion operativa.',
        ],
      },
      {
        title: 'Paso 5: Calidad y auditoria',
        points: [
          'Supervisa puntajes de tecnicos y clientes en casos cerrados.',
          'Analiza comentarios para identificar desvios de servicio.',
          'Mantiene criterios de seguridad, consistencia y trazabilidad de datos.',
        ],
      },
    ],
  },
}

const manual = computed(() => {
  const role = String(route.params.role || '').toLowerCase() as ManualRole
  return manuals[role] ?? null
})
</script>

<template>
  <div class="relative flex min-h-screen flex-col items-center p-4 bg-background">
    <div class="w-full max-w-4xl space-y-6 py-8">
      <div class="flex items-center justify-between gap-4">
        <h1 class="text-2xl md:text-3xl font-bold tracking-tight">
          {{ manual?.title || 'Manual no encontrado' }}
        </h1>
        <Button variant="outline" @click="router.push('/faq')">Volver a FAQ</Button>
      </div>

      <div v-if="manual" class="rounded-xl border bg-card p-4 md:p-6 space-y-5">
        <p class="text-sm text-muted-foreground">{{ manual.subtitle }}</p>

        <div class="grid gap-4">
          <section
            v-for="section in manual.sections"
            :key="section.title"
            class="rounded-lg border p-4"
          >
            <h2 class="text-base font-semibold">{{ section.title }}</h2>
            <ul class="mt-2 list-disc pl-5 text-sm text-muted-foreground space-y-1">
              <li v-for="point in section.points" :key="point">{{ point }}</li>
            </ul>
          </section>
        </div>
      </div>

      <div v-else class="rounded-xl border bg-card p-6 text-sm text-muted-foreground">
        El rol solicitado no existe.
      </div>
    </div>
  </div>
</template>
