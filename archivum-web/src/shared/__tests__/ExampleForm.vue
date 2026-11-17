<script setup lang="ts">
import { nextTick, ref, useTemplateRef } from 'vue'

import { fieldAttributes, focusFirstInvalidField, type ApiProblem } from '@/api/problem'
import FieldError from '../FieldError.vue'

/** A form like the ones of the features, to test the field helpers. */
const form = useTemplateRef<HTMLFormElement>('form')
const problem = ref<ApiProblem | null>(null)

async function show(next: ApiProblem): Promise<void> {
  problem.value = next
  await nextTick()

  if (form.value) {
    focusFirstInvalidField(form.value, next)
  }
}

defineExpose({ show })
</script>

<template>
  <form ref="form">
    <label for="nombre">Nombre</label>
    <input id="nombre" name="nombre" v-bind="fieldAttributes(problem, 'nombre')" />
    <FieldError :problem="problem" field="nombre" />

    <label for="correo">Correo</label>
    <input id="correo" name="correo" v-bind="fieldAttributes(problem, 'correo')" />
    <FieldError :problem="problem" field="correo" />

    <label for="contrasena">Contraseña</label>
    <input id="contrasena" name="contrasena" type="password" v-bind="fieldAttributes(problem, 'contrasena')" />
    <FieldError :problem="problem" field="contrasena" />
  </form>
</template>
