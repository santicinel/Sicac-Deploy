<script setup lang="ts">
import { Button } from '@/components/ui/button'
import { useRouter } from 'vue-router'

const router = useRouter()
const lastUpdate = '16 de febrero de 2026'
</script>

<template>
  <div class="relative flex min-h-screen flex-col items-center p-4 bg-background">
    <div class="w-full max-w-5xl space-y-6 py-8">
      <div class="flex items-center justify-between gap-4">
        <h1 class="text-2xl md:text-3xl font-bold tracking-tight">Terminos y condiciones</h1>
        <Button variant="outline" @click="router.push('/login')">Volver al login</Button>
      </div>

      <div class="rounded-xl border bg-card p-4 md:p-6 space-y-4">
        <p class="text-sm text-muted-foreground">
          Version vigente al {{ lastUpdate }}.
        </p>

        <h2 class="font-semibold">1. Identificacion del prestador</h2>
        <p class="text-sm text-muted-foreground">
          CEA Insumos opera esta plataforma para gestion comercial y tecnica. Debe exhibirse razon social, CUIT, domicilio, correo y canal de contacto para reclamos de consumo.
        </p>

        <h2 class="font-semibold">2. Aceptacion de terminos</h2>
        <p class="text-sm text-muted-foreground">
          Al registrarte o iniciar sesion aceptas estos terminos, la politica de privacidad y el tratamiento de datos segun la normativa argentina aplicable.
        </p>

        <h2 class="font-semibold">3. Cuentas y uso permitido</h2>
        <p class="text-sm text-muted-foreground">
          Debes brindar datos veraces y resguardar tus credenciales. No se permite uso fraudulento, acceso no autorizado, ni acciones que afecten la seguridad o disponibilidad del sistema.
        </p>

        <h2 class="font-semibold">4. Contratacion a distancia y consumidor</h2>
        <p class="text-sm text-muted-foreground">
          Si se realizan operaciones online con consumidores finales, deben informarse condiciones esenciales, precio final, costos, plazos y canales de atencion. En ventas a distancia corresponde derecho de revocacion dentro de 10 dias corridos desde la entrega o contratacion, segun Ley 24.240 y Codigo Civil y Comercial.
        </p>

        <h2 class="font-semibold">5. Datos personales y privacidad</h2>
        <p class="text-sm text-muted-foreground">
          Los datos personales se tratan conforme Ley 25.326 y su decreto reglamentario 1558/2001. El titular puede ejercer derechos de acceso, rectificacion, actualizacion y supresion por los canales de contacto publicados.
        </p>

        <h2 class="font-semibold">6. Seguridad y conservacion</h2>
        <p class="text-sm text-muted-foreground">
          Se aplican medidas tecnicas y organizativas razonables para proteger la informacion. Los datos se conservan solo por el tiempo necesario para cumplir finalidades operativas, legales y de auditoria.
        </p>

        <h2 class="font-semibold">7. Propiedad intelectual</h2>
        <p class="text-sm text-muted-foreground">
          El software, marcas, logos y contenidos pertenecen a sus titulares. Queda prohibida su reproduccion o uso no autorizado.
        </p>

        <h2 class="font-semibold">8. Limitacion de responsabilidad</h2>
        <p class="text-sm text-muted-foreground">
          La plataforma se brinda con esfuerzos razonables de continuidad y seguridad. No se garantiza ausencia total de errores, interrupciones o eventos de terceros fuera de control.
        </p>

        <h2 class="font-semibold">9. Jurisdiccion y ley aplicable</h2>
        <p class="text-sm text-muted-foreground">
          Rige la ley argentina. Para conflictos con consumidores se respetan foros y procedimientos de defensa del consumidor que resulten aplicables.
        </p>

        <h2 class="font-semibold">10. Marco normativo de referencia</h2>
        <ul class="text-sm text-muted-foreground list-disc pl-5 space-y-1">
          <li>Ley 25.326 de Proteccion de Datos Personales.</li>
          <li>Decreto 1558/2001 reglamentario de la Ley 25.326.</li>
          <li>Ley 24.240 de Defensa del Consumidor.</li>
          <li>Codigo Civil y Comercial (contratos de consumo y celebrados a distancia).</li>
        </ul>

      </div>
    </div>
  </div>
</template>
