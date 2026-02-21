

<script setup lang="ts">
import { computed } from 'vue';
import { useRoute } from 'vue-router';
import AuthLayout from './layouts/AuthLayout.vue';
import AppLayout from './layouts/AppLayout.vue';
import SimpleLayout from './layouts/SimpleLayout.vue';

const route = useRoute();
const layout = computed(() => route.meta.layout || 'auth');
</script>

<template>
    <template v-if="layout == 'auth'" >
        <AuthLayout/>
    </template>
    <template v-else-if="layout=='app'">
        <AppLayout/>
    </template>
    <template v-else-if="layout=='simple'">
        <SimpleLayout/>
    </template>
</template>

<style lang="scss" scoped>

</style>
