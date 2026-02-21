<script setup lang="ts">
import { ref, onMounted, onUnmounted, reactive, watch, nextTick } from 'vue';
import CameraUnit from './CameraUnit.vue';

// Props
const props = defineProps<{
    formState: 'idle' | 'tracking' | 'privacy' | 'success' | 'fail';
}>();

// Configuration
const GRID_ROWS = 2;
const GRID_COLS = 3;
const INTERPOLATION_FACTOR = 0.3; // Much faster for "al milimetro" feel

// Types
interface Camera {
    id: number;
    x: number; // Screen X center
    y: number; // Screen Y center
    angle: number; // Current interpolated angle
    targetAngle: number; // Goal angle
}

// State
const containerRef = ref<HTMLElement | null>(null);
const cameras = reactive<Camera[]>([]);
const mousePos = ref({ x: 0, y: 0 });

// Initialize Cameras
const initCameras = () => {
    cameras.splice(0); // Clear
    if (!containerRef.value) return;

    // We just create dummy data first, positions will be calculated on resize/mounted
    for (let i = 0; i < GRID_ROWS * GRID_COLS; i++) {
        cameras.push({ id: i, x: 0, y: 0, angle: 0, targetAngle: 0 });
    }
    nextTick(() => {
        updateCameraPositions();
    });
};

const updateCameraPositions = () => {
    if (!containerRef.value) return;
    const gridItems = containerRef.value.querySelectorAll('.camera-slot');
    
    gridItems.forEach((item, index) => {
        const rect = item.getBoundingClientRect();
        if (cameras[index]) {
            cameras[index].x = rect.left + rect.width / 2;
            cameras[index].y = rect.top + rect.height / 2;
        }
    });
};

// Mouse Handling
const handleMouseMove = (e: MouseEvent) => {
    mousePos.value = { x: e.clientX, y: e.clientY };
};

// Animation Loop
let animationFrameId: number;

const loop = () => {
    cameras.forEach(cam => {
        // Determine Target Angle
        if (props.formState === 'privacy') {
            // Privacy Mode: Look away or center? User said "center slowly or look away".
            // Let's make them maintain 0 (center) or look down (-90). Let's go with 0 (center/idle).
            // Or maybe look DOWN to show "I'm not looking".
            cam.targetAngle = 90; // Look down
        } else if (props.formState === 'success' || props.formState === 'fail') {
             // Keep looking at last point or center? 
             // Center is cleaner for the "nod" or "shake" effect.
             cam.targetAngle = 0;
        } else {
            // Tracking Mode (Make it ALWAYS track unless specific state overrides)
            const dx = mousePos.value.x - cam.x;
            const dy = mousePos.value.y - cam.y;
            let rad = Math.atan2(dy, dx);
            let deg = rad * (180 / Math.PI);
            
            // New CameraUnit 0deg = Right. atan2 0 = Right.
            // Screen Layout: Cams Left, Mouse Right. dx is (+). deg is 0.
            // We want to face Right (0).
            cam.targetAngle = deg;
        }

        // Interpolation (Lerp)
        // Handle wrapping -180/180 check if needed, but for simple limited clamp simple lerp is ok.
        // But plain lerp on angles can spin 350->10 the wrong way. 
        // For this simple 2D restricted wall, clamp is safer.
        // Clamp Angle so heads don't spin 360 like Exorcist if mouse goes around.
        // But clamp limits range. User requested "clamp between -60 and 60".
        // wait, atan2 returns -180 to 180. 
        // Let's unwrap diff.
        
        let diff = cam.targetAngle - cam.angle;
        // Normalize diff to -180...180
        while (diff < -180) diff += 360;
        while (diff > 180) diff -= 360;

        cam.angle += diff * INTERPOLATION_FACTOR;
    });

    animationFrameId = requestAnimationFrame(loop);
};

onMounted(() => {
    initCameras();
    window.addEventListener('resize', updateCameraPositions);
    window.addEventListener('mousemove', handleMouseMove);
    loop();
});

onUnmounted(() => {
    window.removeEventListener('resize', updateCameraPositions);
    window.removeEventListener('mousemove', handleMouseMove);
    cancelAnimationFrame(animationFrameId);
});

// Watch for form state changes to trigger one-off logic if needed
watch(() => props.formState, (val) => {
    if (val === 'fail') {
        // Trigger shake via CSS class in unit
    }
});

</script>

<template>
    <div 
        ref="containerRef" 
        class="w-full h-full relative bg-zinc-900 overflow-hidden flex flex-col items-center justify-center"
    >
        <!-- Background Image (CCTV Wall vibe) -->
        <div class="absolute inset-0 opacity-20 pointer-events-none">
            <div class="absolute inset-0 bg-[radial-gradient(circle_at_center,_var(--tw-gradient-stops))] from-zinc-800 to-black"></div>
            <!-- Optional Grid Lines -->
            <svg class="w-full h-full opacity-10" width="100%" height="100%">
                <defs>
                    <pattern id="grid" width="40" height="40" patternUnits="userSpaceOnUse">
                        <path d="M 40 0 L 0 0 0 40" fill="none" stroke="white" stroke-width="0.5"/>
                    </pattern>
                </defs>
                <rect width="100%" height="100%" fill="url(#grid)" />
            </svg>
        </div>

        <!-- "TE VIGILAMOS" Header -->
        <div class="absolute top-10 md:top-24 z-0 pointer-events-none w-full text-center">
            <h2 class="text-5xl md:text-6xl font-black tracking-widest text-amber-50 uppercase select-none drop-shadow-sm">
                TE VIGILAMOS
            </h2>
        </div>
        
        <!-- Camera Grid -->
        <div class="relative z-10 grid grid-cols-3 gap-8 md:gap-16 p-8">
            <div 
                v-for="cam in cameras" 
                :key="cam.id" 
                class="camera-slot flex items-center justify-center p-4"
            >
                <CameraUnit 
                    :angle="cam.angle" 
                    :state="formState" 
                />
            </div>
        </div>

        <!-- Overlay Text (Optional flavor) -->
        <div class="absolute bottom-6 right-6 text-zinc-600 font-mono text-xs tracking-widest pointer-events-none select-none">
             SYSTEM_STATUS: {{ formState.toUpperCase() }}<br>
             CAM_ACTIVE: {{ cameras.length }}
        </div>
    </div>
</template>
