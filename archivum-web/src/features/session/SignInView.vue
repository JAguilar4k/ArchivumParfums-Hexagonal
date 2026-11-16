<script setup lang="ts">
import { computed, nextTick, ref, useTemplateRef } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'

import { fieldAttributes, focusFirstInvalidField, type ApiProblem } from '@/api/problem'
import FieldError from '@/shared/FieldError.vue'
import ProblemNotice from '@/shared/ProblemNotice.vue'
import { redirectTarget } from './guards'
import { useSessionStore } from './store'

const session = useSessionStore()
const route = useRoute()
const router = useRouter()

const form = useTemplateRef<HTMLFormElement>('form')
// A customer who just created the account finds its email already written (#194).
const correo = ref(session.takeRememberedEmail())
const contrasena = ref('')
const problem = ref<ApiProblem | null>(null)
const sending = ref(false)

// The registration returns to the same route as the sign-in.
const registerLink = computed(() => ({
  name: 'register',
  query: typeof route.query.redirect === 'string' ? { redirect: route.query.redirect } : {},
}))

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
  <div class="page sign-in">
    <h1>Iniciar sesión</h1>
    <p class="form-intro">Escriba el correo y la contraseña de su cuenta.</p>

    <ProblemNotice v-if="problem" :problem="problem" />

    <form ref="form" class="form" novalidate @submit.prevent="submit">
      <div class="form-field">
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
      </div>

      <div class="form-field">
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
      </div>

      <div class="form-actions">
        <!-- aria-disabled and not disabled: the button keeps the focus while the request is on its way. -->
        <button type="submit" :aria-disabled="sending ? 'true' : undefined">
          {{ sending ? 'Ingresando…' : 'Iniciar sesión' }}
        </button>
      </div>
    </form>

    <p class="sign-in__register">
      ¿Todavía no tiene cuenta? <RouterLink :to="registerLink">Crear una cuenta de cliente</RouterLink>
    </p>
  </div>
</template>
