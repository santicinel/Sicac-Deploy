<script setup lang="ts">
import { ref, watch } from "vue";
import { Eye, EyeOff } from "lucide-vue-next";

import authService from "@/services/authService";
import { useFetchState } from "@/composables/useFetchState";
import { Button } from "@/components/ui/button";
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from "@/components/ui/card";
import {
  Field,
  FieldDescription,
  FieldGroup,
  FieldLabel,
} from "@/components/ui/field";
import { Input } from "@/components/ui/input";

const emit = defineEmits<{
  (e: "toggle-auth"): void;
  (e: "forgot-password"): void;
  (e: "login-success", response: any): void;
  (e: "login-error", error: any): void;
  (e: "password-visibility-changed", visible: boolean): void;
}>();

const email = ref("");
const password = ref("");
const showPassword = ref(false);

const normalizeEmail = (value: string) =>
  value.trim().replace(/\s+/g, "").replace(/,+/g, ".").toLowerCase();

const { loading, execute: login } = useFetchState(authService.login);

watch(showPassword, (val) => {
  emit("password-visibility-changed", val);
});

const handleEmailInput = (event: Event) => {
  const target = event.target as HTMLInputElement | null;
  if (!target) return;
  email.value = normalizeEmail(target.value);
};

const handleSubmit = async () => {
  try {
    const response = await login({
      email: normalizeEmail(email.value),
      password: password.value,
    });
    emit("login-success", response);
  } catch (err) {
    emit("login-error", err);
  }
};
</script>

<template>
  <Card class="border shadow-lg">
    <CardHeader class="text-center pb-2">
      <CardTitle class="text-xl">Iniciar sesión</CardTitle>
      <CardDescription>Ingresa tu email para acceder a tu cuenta</CardDescription>
    </CardHeader>

    <CardContent>
      <form @submit.prevent="handleSubmit">
        <FieldGroup>
          <Field>
            <FieldLabel for="email">Email</FieldLabel>
            <Input
              id="email"
              v-model.trim="email"
              type="email"
              placeholder="usuario@ejemplo.com"
              required
              :loading="loading"
              @input="handleEmailInput"
            />
          </Field>

          <Field>
            <div class="flex items-center">
              <FieldLabel for="password">Contrasena</FieldLabel>
              <a
                href="#"
                class="ml-auto text-sm underline-offset-4 hover:underline"
                @click.prevent="emit('forgot-password')"
              >
                Olvidaste tu contrasena?
              </a>
            </div>

            <div class="relative">
              <Input
                id="password"
                v-model="password"
                :type="showPassword ? 'text' : 'password'"
                required
                class="pr-10"
                :loading="loading"
              />
              <button
                type="button"
                class="absolute text-muted-foreground right-3 top-1/2 -translate-y-1/2 hover:text-foreground"
                @click="showPassword = !showPassword"
              >
                <Eye v-if="!showPassword" class="w-4 h-4" />
                <EyeOff v-else class="w-4 h-4" />
              </button>
            </div>
          </Field>

          <Field>
            <Button type="submit" class="w-full" :disabled="loading">
              {{ loading ? "Ingresando..." : "Ingresar" }}
            </Button>
            <FieldDescription class="text-center mt-4">
              No tenes una cuenta?
              <a href="#" class="font-medium text-primary hover:underline" @click.prevent="emit('toggle-auth')">
                Registrate
              </a>
            </FieldDescription>
          </Field>
        </FieldGroup>
      </form>
    </CardContent>
  </Card>
</template>
