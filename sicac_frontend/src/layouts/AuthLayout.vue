<script lang="ts">
export const description = "A two column login page with a cover image."
</script>

<script setup lang="ts">
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import { GalleryVerticalEnd } from "lucide-vue-next"
import {
  Carousel,
  CarouselContent,
  CarouselItem,
} from '@/components/ui/carousel'
import Autoplay from 'embla-carousel-autoplay'

const route = useRoute()

const defaultImages = [
    'https://images.unsplash.com/photo-1557597774-9d273605dfa9?q=80&w=2070&auto=format&fit=crop',
]

const carouselImages = computed(() => {
    return (route.meta.carouselImages as string[]) || defaultImages
})

const plugin = Autoplay({
  delay: 4000,
  stopOnInteraction: false,
})
</script>

<template>
  <div class="grid min-h-svh lg:grid-cols-2">
    <div class="flex flex-col gap-4 p-6 md:p-10">
      <div class="flex justify-center gap-2 md:justify-start">
        <a href="#" class="flex items-center gap-2 font-medium">
          <div class="bg-primary text-primary-foreground flex size-6 items-center justify-center rounded-md">
            <GalleryVerticalEnd class="size-4" />
          </div>
          CEA Insumos
        </a>
      </div>
      <div class="flex flex-1 items-center justify-center">
        <div class="w-full max-w-xs">
          <RouterView/>
        </div>
      </div>
    </div>
    <div class="bg-muted relative hidden lg:block overflow-hidden">
      <Carousel
        :plugins="[plugin]"
        class="h-full w-full"
        :opts="{
            loop: true
        }"
      >
        <CarouselContent class="h-full">
            <CarouselItem v-for="(image, index) in carouselImages" :key="index" class="h-full w-full">
                 <img
                    :src="image"
                    alt="Image"
                    class="h-full w-full object-cover dark:brightness-[0.2] dark:grayscale"
                >
            </CarouselItem>
        </CarouselContent>
      </Carousel>
    </div>
  </div>
</template>
