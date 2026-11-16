<script setup lang="ts">
import { computed, nextTick, ref, useTemplateRef } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'

import { api } from '@/api/client'
import { callApi, fieldAttributes, fieldErrorId, fieldMessages, focusFirstInvalidField, type ApiProblem } from '@/api/problem'
import { redirectTarget } from '@/features/session/guards'
import { useSessionStore } from '@/features/session/store'
import FieldError from '@/shared/FieldError.vue'
import ProblemNotice from '@/shared/ProblemNotice.vue'
import PrivacyNotice from './PrivacyNotice.vue'

/**
 * "Crear cuenta" (#194): `POST /cuentas` with the name, the email, the password and the acceptance of the privacy
 * notice, never a role or a store. With the 201 it signs in with the flow of #126 and returns to the route that the
 * visitor wanted to open. The password policy is the API's: a 422 marks each field with its message. The password
 * lives only in this form, and is cleared after each attempt.
 */
const session = useSessionStore()
const route = useRoute()
const router = useRouter()

const form = useTemplateRef<HTMLFormElement>('form')
const nombre = ref('')
const correo = ref('')
const contrasena = ref('')
const aceptaPrivacidad = ref(false)
const problem = ref<ApiProblem | null>(null)
const mustAccept = ref(false)
const sending = ref(false)

// The box points to the notice and, when it has a message, to its message.
const consentInvalid = computed(() => mustAccept.value || fieldMessages(problem.value, 'acepta_privacidad').length > 0)
const consentDescription = computed(() =>
  consentInvalid.value ? `aviso-privacidad ${fieldErrorId('acepta_privacidad')}` : 'aviso-privacidad',
)

async function submit(): Promise<void> {
  if (sending.value) {
    return
  }

  problem.value = null
  mustAccept.value = !aceptaPrivacidad.value

  if (mustAccept.value) {
    return
  }

  sending.value = true

  const created = await callApi(() =>
    api.POST('/cuentas', {
      body: { nombre: nombre.value, correo: correo.value, contrasena: contrasena.value, acepta_privacidad: true },
    }),
  )

  if (created.problem) {
    problem.value = created.problem
    contrasena.value = ''
    sending.value = false
    await nextTick()

    if (form.value) {
      focusFirstInvalidField(form.value, created.problem)
    }

    return
  }

  const signInProblem = await session.signIn(correo.value, contrasena.value)
  contrasena.value = ''
  sending.value = false

  if (signInProblem) {
    // The account exists: the sign-in shows its email and returns to the same route.
    session.rememberEmail(correo.value)
    await router.replace({ name: 'sign-in', query: { redirect: redirectTarget(route.query.redirect) } })

    return
  }

  await router.replace(redirectTarget(route.query.redirect))
}
</script>

<template>
  <main class="register">
    <h1>Crear cuenta</h1>
    <p>Con su cuenta de cliente puede reservar pruebas de olores y hacer pedidos.</p>

    <ProblemNotice v-if="problem" :problem="problem" />

    <form ref="form" novalidate @submit.prevent="submit">
      <label for="nombre">Nombre</label>
      <input
        id="nombre"
        v-model="nombre"
        name="nombre"
        type="text"
        autocomplete="name"
        required
        v-bind="fieldAttributes(problem, 'nombre')"
      />
      <FieldError :problem="problem" field="nombre" />

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
        autocomplete="new-password"
        required
        v-bind="fieldAttributes(problem, 'contrasena')"
      />
      <FieldError :problem="problem" field="contrasena" />

      <PrivacyNotice id="aviso-privacidad" />

      <label class="register__consent">
        <input
          id="acepta_privacidad"
          v-model="aceptaPrivacidad"
          name="acepta_privacidad"
          type="checkbox"
          required
          :aria-invalid="consentInvalid ? 'true' : undefined"
          :aria-describedby="consentDescription"
        />
        Leí y acepto el aviso de privacidad.
      </label>
      <p v-if="mustAccept" :id="fieldErrorId('acepta_privacidad')" class="field-error">
        Debe aceptar el aviso de privacidad para crear la cuenta.
      </p>
      <FieldError v-else :problem="problem" field="acepta_privacidad" />

      <button type="submit" :disabled="sending">
        {{ sending ? 'Creando la cuenta…' : 'Crear cuenta' }}
      </button>
    </form>

    <p class="register__sign-in">
      ¿Ya tiene cuenta?
      <RouterLink :to="{ name: 'sign-in', query: route.query.redirect ? { redirect: route.query.redirect } : {} }">
        Iniciar sesión
      </RouterLink>
    </p>
  </main>
</template>
