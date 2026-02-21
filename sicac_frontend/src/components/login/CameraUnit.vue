<script setup lang="ts">
import { computed } from 'vue';

const props = defineProps<{
    angle: number; // Rotation angle in degrees
    state: 'idle' | 'tracking' | 'privacy' | 'success' | 'fail';
}>();

// LED Color based on state
const ledColor = computed(() => {
    switch (props.state) {
        case 'success': return '#4ade80'; // Green
        case 'fail': return '#ef4444';    // Red
        case 'privacy': return '#60a5fa'; // Blue/Neutral
        default: return '#ef4444';        // Red
    }
});

const isShutterClosed = computed(() => props.state === 'privacy');
</script>

<template>
    <!-- Increased size for visibility -->
    <div class="relative w-64 h-64 flex items-center justify-center">
        <!-- ViewBox 100x100. Center 50,50. Overflow visible for beam -->
        <svg viewBox="0 0 100 100" class="w-full h-full drop-shadow-2xl overflow-visible" :class="{ 'animate-shake': state === 'fail', 'animate-nod': state === 'success' }">
            <defs>
                <linearGradient id="beamGradient" x1="0%" y1="0%" x2="100%" y2="0%">
                    <stop offset="0%" stop-color="rgba(70, 200, 255, 0.4)" />
                    <stop offset="100%" stop-color="rgba(70, 200, 255, 0)" />
                </linearGradient>
            </defs>

            <!-- Mounting Bracket (Static base) -->
            <circle cx="50" cy="50" r="8" fill="#1c1917" stroke="#333" stroke-width="2" />
            
            <!-- Rotating Body Group 
                 REMOVED transition-transform to allow instant JS updates without lag 
            -->
            <g 
                class="will-change-transform origin-center"
                :style="{ transform: `rotate(${angle}deg)` }"
            >
                <!-- Tracking Beam (Light Cone) - VISIBLE DIRECTION INDICATOR -->
                <path 
                    v-if="state === 'tracking' || state === 'idle'"
                    d="M100,50 L300,0 L300,100 Z" 
                    fill="url(#beamGradient)" 
                    pointer-events="none"
                    class="opacity-0 transition-opacity duration-300"
                    :class="{ 'opacity-50': state === 'tracking' }"
                />

                <!-- Arm -->
                <rect x="40" y="45" width="20" height="10" fill="#262626" rx="2" />

                <!-- Cable at Back (Visual cue for 'Rear') -->
                <path d="M55,50 C45,50 40,60 40,80" fill="none" stroke="#000" stroke-width="2" opacity="0.5" />

                <!-- Camera Main Body -->
                <rect x="55" y="32" width="45" height="36" rx="6" fill="#202020" stroke="#404040" stroke-width="1.5" />
                
                <!-- Front Cap -->
                <rect x="95" y="32" width="5" height="36" rx="1" fill="#101010" />
                 
                <!-- Lens -->
                <ellipse cx="100" cy="50" rx="3" ry="12" fill="#38bdf8" class="animate-pulse opacity-80" /> <!-- Blue tint for lens -->
                
                <!-- Shutter -->
                <rect 
                    x="96" y="32" width="5" height="36" fill="#334155" 
                    class="transition-all duration-300"
                    :style="{ 
                        opacity: isShutterClosed ? 1 : 0,
                        transform: isShutterClosed ? 'scaleY(1)' : 'scaleY(0)',
                        transformOrigin: 'center'
                    }"
                />

                <!-- Status LED -->
                <circle cx="90" cy="40" r="2.5" :fill="ledColor" class="transition-colors duration-300 shadow-[0_0_8px_currentColor]" />
                
                <!-- Stripes/Vents -->
                <rect x="65" y="32" width="2" height="36" fill="#000" opacity="0.3" />
                <rect x="75" y="32" width="2" height="36" fill="#000" opacity="0.3" />
            </g>
        </svg>
    </div>
</template>

<style scoped>
.animate-shake {
    animation: shake 0.5s cubic-bezier(.36,.07,.19,.97) both;
}

@keyframes shake {
  10%, 90% { transform: translate3d(-1px, 0, 0); }
  20%, 80% { transform: translate3d(2px, 0, 0); }
  30%, 50%, 70% { transform: translate3d(-4px, 0, 0); }
  40%, 60% { transform: translate3d(4px, 0, 0); }
}

.animate-nod {
    animation: nod 0.6s ease-in-out;
}

@keyframes nod {
    0%, 100% { transform: translateY(0); }
    50% { transform: translateY(5px); }
}
</style>
