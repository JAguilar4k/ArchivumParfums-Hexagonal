<script setup lang="ts">
import { nextTick, ref, useTemplateRef } from 'vue'
import { useRoute, useRouter } from 'vue-router'

import { fieldAttributes, focusFirstInvalidField, type ApiProblem } from '@/api/problem'
import FieldError from '@/shared/FieldError.vue'
import ProblemNotice from '@/shared/ProblemNotice.vue'
import { redirectTarget } from './guards'
import { useSessionStore } from './store'

const session = useSessionStore()
const route = useRoute()
const router = useRouter()

const form = useTemplateRef<HTMLFormElement>('form')
const correo = ref('')
const contrasena = ref('')
const problem = ref<ApiProblem | null>(null)
const sending = ref(false)

async function submit(): Promise<void> {
  if (sending.value) {
    return
  }

  sending.value = true
  problem.value = await session.signIn(correo.value, contrasena.value)
  sending.value = false

  if (!problem.value) {
    await router.replace(redirectTarget(route.query.redirect))

    return
  }

  // The password never stays in the form after a failed attempt.
  contrasena.value = ''
  await nextTick()

  if (form.value) {
    focusFirstInvalidField(form.value, problem.value)
  }
}
</script>

<template>
  <main class="sign-in">
    <h1>Iniciar sesión</h1>

    <ProblemNotice v-if="problem" :problem="problem" />

    <form ref="form" novalidate @submit.prevent="submit">
      <label for="correo">Correo</label>
      <input
        id="correo"
        v-model="correo"
        name="correo"
        type="email"
        autocomplete="email"
        required
        v-bind="fieldAttributes(problem, 'correo')"
      />
      <FieldError :problem="problem" field="correo" />

      <label for="contrasena">Contraseña</label>
      <input
        id="contrasena"
        v-model="contrasena"
        name="contrasena"
        type="password"
        autocomplete="current-password"
        required
        v-bind="fieldAttributes(problem, 'contrasena')"
      />
      <FieldError :problem="problem" field="contrasena" />

      <button type="submit" :disabled="sending">
        {{ sending ? 'Ingresando…' : 'Iniciar sesión' }}
      </button>
    </form>
  </main>
</template>
