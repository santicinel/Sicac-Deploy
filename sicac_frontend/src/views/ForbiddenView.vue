<script setup lang="ts">
import { Button } from '@/components/ui/button'
import { useRouter } from 'vue-router'

const router = useRouter()

const goHome = () => {
    router.push('/')
}
</script>

<template>
    <div class="h-screen w-full flex flex-col items-center justify-center bg-gray-50">
        <div class="text-center space-y-6 max-w-md px-4">
            <div class="space-y-2">
                <h1 class="text-9xl font-extrabold text-primary">403</h1>
                <h2 class="text-2xl font-bold tracking-tight text-gray-900">Acceso Restringido</h2>
                <p class="text-muted-foreground">
                    Lo sentimos, no tienes los permisos necesarios para acceder a esta página.
                </p>
            </div>
            
            <Button @click="goHome" size="lg" class="w-full sm:w-auto">
                Volver al inicio
            </Button>
        </div>
    </div>
</template>
