<script setup lang="ts">
import { ref } from "vue";
import { toTypedSchema } from "@vee-validate/zod";
import { useForm } from "vee-validate";
import * as z from "zod";
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
import FieldError from "@/components/ui/field/FieldError.vue";
import { Input } from "@/components/ui/input";

const emit = defineEmits<{
  (e: "toggle-auth"): void;
}>();

const showPassword = ref(false);
const showConfirmPassword = ref(false);
const { loading, execute: register } = useFetchState(authService.register);

const normalizeEmail = (value: string) =>
  value.trim().replace(/\s+/g, "").replace(/,+/g, ".").toLowerCase();

const isValidEmail = (value: string) => z.string().email().safeParse(value).success;

const formSchema = toTypedSchema(
  z
    .object({
      firstName: z
        .string()
        .min(1, "El nombre es requerido")
        .max(255, "El nombre no puede exceder los 255 caracteres"),
      lastName: z
        .string()
        .min(1, "El apellido es requerido")
        .max(255, "El apellido no puede exceder los 255 caracteres"),
      dni: z.string().min(1, "El DNI es requerido"),
      email: z
        .string()
        .min(1, "El email es requerido")
        .max(255, "El email no puede exceder los 255 caracteres")
        .transform((value) => normalizeEmail(value))
        .refine((value) => isValidEmail(value), { message: "Email invalido" }),
      password: z
        .string()
        .min(1, "La contrasena es requerida")
        .min(8, "La contrasena debe tener al menos 8 caracteres"),
      confirmPassword: z.string().min(1, "Repetir la contrasena es requerido"),
      phone: z.string().min(1, "El telefono es requerido"),
      address: z.string().min(1, "La direccion es requerida"),
      city: z.string().min(1, "La localidad es requerida"),
    })
    .refine((data) => data.password === data.confirmPassword, {
      message: "Las contrasenas no coinciden",
      path: ["confirmPassword"],
    })
);

const { handleSubmit, errors, defineField } = useForm({
  validationSchema: formSchema,
});

const [firstName, firstNameAttrs] = defineField("firstName");
const [lastName, lastNameAttrs] = defineField("lastName");
const [dni, dniAttrs] = defineField("dni");
const [email, emailAttrs] = defineField("email");
const [password, passwordAttrs] = defineField("password");
const [confirmPassword, confirmPasswordAttrs] = defineField("confirmPassword");
const [phone, phoneAttrs] = defineField("phone");
const [address, addressAttrs] = defineField("address");
const [city, cityAttrs] = defineField("city");

const handleEmailInput = (event: Event) => {
  const target = event.target as HTMLInputElement | null;
  if (!target) return;
  email.value = normalizeEmail(target.value);
};

const handleRegister = handleSubmit(async (values) => {
  try {
    await register({
      name: `${values.firstName} ${values.lastName}`,
      email: normalizeEmail(values.email),
      password: values.password,
      dni: values.dni,
      address: values.address,
      city: values.city,
      phone: values.phone,
    });

    emit("toggle-auth");
  } catch (err) {
    console.error(err);
  }
});
</script>

<template>
  <Card class="border shadow-lg">
    <CardHeader class="text-center pb-2">
      <CardTitle class="text-xl">Crear cuenta</CardTitle>
      <CardDescription>Completa tus datos para registrarte</CardDescription>
    </CardHeader>

    <CardContent>
      <form novalidate @submit.prevent="handleRegister">
        <FieldGroup>
          <div class="grid grid-cols-2 gap-4">
            <Field>
              <FieldLabel for="nombre">Nombre</FieldLabel>
              <Input
                id="nombre"
                v-model="firstName"
                v-bind="firstNameAttrs"
                type="text"
                placeholder="Juan"
                :loading="loading"
              />
              <FieldError :errors="[errors.firstName]" />
            </Field>

            <Field>
              <FieldLabel for="apellido">Apellido</FieldLabel>
              <Input
                id="apellido"
                v-model="lastName"
                v-bind="lastNameAttrs"
                type="text"
                placeholder="Perez"
                :loading="loading"
              />
              <FieldError :errors="[errors.lastName]" />
            </Field>
          </div>

          <Field>
            <FieldLabel for="dni">DNI</FieldLabel>
            <Input
              id="dni"
              v-model="dni"
              v-bind="dniAttrs"
              type="text"
              placeholder="30123456"
              :loading="loading"
            />
            <FieldError :errors="[errors.dni]" />
          </Field>

          <Field>
            <FieldLabel for="email">Email</FieldLabel>
            <Input
              id="email"
              v-model="email"
              v-bind="emailAttrs"
              type="email"
              placeholder="usuario@ejemplo.com"
              :loading="loading"
              @input="handleEmailInput"
            />
            <FieldError :errors="[errors.email]" />
          </Field>

          <Field>
            <FieldLabel for="password">Contrasena</FieldLabel>
            <div class="relative">
              <Input
                id="password"
                v-model="password"
                v-bind="passwordAttrs"
                :type="showPassword ? 'text' : 'password'"
                class="pr-10"
                :loading="loading"
              />
              <button
                type="button"
                class="absolute right-3 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-primary"
                @click="showPassword = !showPassword"
              >
                <component :is="showPassword ? EyeOff : Eye" class="h-4 w-4" />
              </button>
            </div>
            <FieldError :errors="[errors.password]" />
          </Field>

          <Field>
            <FieldLabel for="confirm-password">Repetir Contrasena</FieldLabel>
            <div class="relative">
              <Input
                id="confirm-password"
                v-model="confirmPassword"
                v-bind="confirmPasswordAttrs"
                :type="showConfirmPassword ? 'text' : 'password'"
                class="pr-10"
                :loading="loading"
              />
              <button
                type="button"
                class="absolute right-3 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-primary"
                @click="showConfirmPassword = !showConfirmPassword"
              >
                <component :is="showConfirmPassword ? EyeOff : Eye" class="h-4 w-4" />
              </button>
            </div>
            <FieldError :errors="[errors.confirmPassword]" />
          </Field>

          <Field>
            <FieldLabel for="telefono">Telefono</FieldLabel>
            <Input
              id="telefono"
              v-model="phone"
              v-bind="phoneAttrs"
              type="tel"
              placeholder="+54 11 1234 5678"
              :loading="loading"
            />
            <FieldError :errors="[errors.phone]" />
          </Field>

          <div class="grid grid-cols-2 gap-4">
            <Field>
              <FieldLabel for="direccion">Direccion</FieldLabel>
              <Input
                id="direccion"
                v-model="address"
                v-bind="addressAttrs"
                type="text"
                placeholder="Av. Siempre Viva 123"
                :loading="loading"
              />
              <FieldError :errors="[errors.address]" />
            </Field>

            <Field>
              <FieldLabel for="localidad">Localidad</FieldLabel>
              <Input
                id="localidad"
                v-model="city"
                v-bind="cityAttrs"
                type="text"
                placeholder="Springfield"
                :loading="loading"
              />
              <FieldError :errors="[errors.city]" />
            </Field>
          </div>

          <Field>
            <Button type="submit" class="w-full" :loading="loading">Registrarse</Button>
            <FieldDescription class="text-center mt-4">
              Ya tenes una cuenta?
              <a href="#" class="font-medium text-primary hover:underline" @click.prevent="emit('toggle-auth')">
                Ingresa
              </a>
            </FieldDescription>
          </Field>
        </FieldGroup>
      </form>
    </CardContent>
  </Card>
</template>
