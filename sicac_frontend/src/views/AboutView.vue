<script setup lang="ts">
import { Icon } from "@iconify/vue";
import companyLogo from "../../sicac.png";
</script>

<template>
    <div class="max-w-4xl mx-auto p-6 space-y-12 pb-24">
        <!-- Hero Section -->
        <div class="text-center space-y-4">
            <div class="inline-flex items-center justify-center mb-4">
                <img :src="companyLogo" alt="Logo SICAC" class="h-16 w-16 object-contain" />
            </div>
            <h1 class="text-4xl font-bold tracking-tight text-primary">Sobre Nosotros</h1>
            <p class="text-xl text-muted-foreground max-w-2xl mx-auto">
                Somos CEA Insumos, tu socio estratégico en seguridad electrónica integral.
            </p>
        </div>

        <!-- Mission, Vision, Values -->
        <div class="grid md:grid-cols-3 gap-6">
            <div class="bg-card border rounded-lg p-6 space-y-4 text-center hover:shadow-lg transition-shadow">
                <div class="bg-blue-100 text-blue-700 w-12 h-12 rounded-full flex items-center justify-center mx-auto">
                    <Icon icon="mdi:target" class="h-6 w-6" />
                </div>
                <h3 class="font-semibold text-lg">Nuestra Misión</h3>
                <p class="text-sm text-muted-foreground leading-relaxed">
                    Proveer soluciones de seguridad innovadoras y accesibles que brinden tranquilidad real a hogares y empresas en toda la región.
                </p>
            </div>
            <div class="bg-card border rounded-lg p-6 space-y-4 text-center hover:shadow-lg transition-shadow">
                <div class="bg-emerald-100 text-emerald-700 w-12 h-12 rounded-full flex items-center justify-center mx-auto">
                    <Icon icon="mdi:eye-outline" class="h-6 w-6" />
                </div>
                <h3 class="font-semibold text-lg">Nuestra Visión</h3>
                <p class="text-sm text-muted-foreground leading-relaxed">
                    Ser referentes regionales en tecnología de seguridad, reconocidos por nuestra integridad, calidad de servicio y soporte técnico experto.
                </p>
            </div>
            <div class="bg-card border rounded-lg p-6 space-y-4 text-center hover:shadow-lg transition-shadow">
                <div class="bg-orange-100 text-orange-700 w-12 h-12 rounded-full flex items-center justify-center mx-auto">
                    <Icon icon="mdi:handshake-outline" class="h-6 w-6" />
                </div>
                <h3 class="font-semibold text-lg">Nuestros Valores</h3>
                <ul class="text-sm text-muted-foreground leading-relaxed space-y-1">
                    <li>Integridad y Honestidad</li>
                    <li>Innovación Constante</li>
                    <li>Compromiso con el Cliente</li>
                    <li>Calidad Certificada</li>
                </ul>
            </div>
        </div>

        <!-- Location & Map -->
        <div class="space-y-6">
            <h2 class="text-2xl font-bold flex items-center gap-2">
                <Icon icon="mdi:map-marker-radius" class="text-primary" />
                Nuestra Ubicación
            </h2>
            <div class="rounded-xl overflow-hidden border shadow-sm h-[400px] bg-muted relative group">
                <iframe 
                    src="https://maps.google.com/maps?q=-33.4646614,-61.4875761&t=&z=18&ie=UTF8&iwloc=&output=embed"
                    width="100%" 
                    height="100%" 
                    style="border:0;" 
                    allowfullscreen="true" 
                    loading="lazy" 
                    referrerpolicy="no-referrer-when-downgrade"
                    class="grayscale-[30%] group-hover:grayscale-0 transition-all duration-500"
                >
                </iframe>
                
                <div class="absolute top-4 left-4 bg-background/95 backdrop-blur-sm p-4 rounded-lg shadow-lg border max-w-xs animate-in slide-in-from-left-2 fade-in duration-500">
                    <div class="flex items-start gap-3">
                        <div class="bg-primary/10 p-2 rounded-full">
                            <Icon icon="mdi:store-marker-outline" class="text-primary h-5 w-5" />
                        </div>
                        <div>
                            <p class="font-semibold text-sm">Casa Central</p>
                            <p class="text-sm text-muted-foreground mt-0.5">Buenos Aires 432<br>Firmat</p>
                            <a 
                                href="https://www.google.com/maps/place/Buenos+Aires+432,+S2630BNJ+Firmat,+Santa+Fe/@-33.4646614,-61.490151,17z/data=!3m1!4b1!4m6!3m5!1s0x95c823475b9817fd:0x621a30cd8a2a98d0!8m2!3d-33.4646614!4d-61.4875761!16s%2Fg%2F11vt79z61c?entry=ttu&g_ep=EgoyMDI1MTIwOS4wIKXMDSoASAFQAw%3D%3D" 
                                target="_blank" 
                                class="text-xs text-primary font-medium hover:underline mt-2 inline-flex items-center gap-1"
                            >
                                <span>Cómo llegar</span>
                                <Icon icon="mdi:open-in-new" class="h-3 w-3" />
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Legal Info -->
        <div class="bg-muted/30 rounded-xl p-8 space-y-4 border">
            <h2 class="text-xl font-bold flex items-center gap-2">
                <Icon icon="mdi:gavel" class="text-foreground" />
                Información Legal
            </h2>
            <div class="text-sm text-muted-foreground space-y-3">
                <p>
                    <strong>Razón Social:</strong> CEA Insumos S.R.L.<br>
                    <strong>CUIT:</strong> 20-25459992-2<br>
                    <strong>Domicilio Legal:</strong> Buenos Aires 432, Firmat (2630), Santa Fe, Argentina.
                </p>
                <p>
                    CEA Insumos cumple con todas las normativas vigentes en materia de protección de datos personales (Ley 25.326). 
                    Todas las instalaciones son realizadas por técnicos matriculados bajo estrictos estándares de seguridad eléctrica y normativa local.
                </p>
                <p class="text-xs pt-2 border-t border-border/50">
                    © {{ new Date().getFullYear() }} CEA Insumos. Todos los derechos reservados. Las marcas mencionadas en este sitio son propiedad de sus respectivos dueños.
                </p>
            </div>
        </div>
    </div>
</template>
