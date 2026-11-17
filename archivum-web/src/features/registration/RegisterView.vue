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
 *
 * Each field says what it is for before the visitor writes (WCAG 3.3.5). The hint of the password gives advice and
 * repeats no rule: the policy is the API's (#194), and its 422 says next to the field what the password lacks. The
 * password can be shown to check it, and a box left unchecked takes the focus with its message.
 */
const session = useSessionStore()
const route = useRoute()
const router = useRouter()

const form = useTemplateRef<HTMLFormElement>('form')
const consent = useTemplateRef<HTMLInputElement>('consent')
const nombre = ref('')
const correo = ref('')
const contrasena = ref('')
const showPassword = ref(false)
const aceptaPrivacidad = ref(false)
const problem = ref<ApiProblem | null>(null)
const triedWithoutConsent = ref(false)
const sending = ref(false)

// The message of the box goes away as soon as the box is checked.
const consentMissing = computed(() => triedWithoutConsent.value && !aceptaPrivacidad.value)
// The box points to the notice and, when it has a message, to its message.
const consentInvalid = computed(() => consentMissing.value || fieldMessages(problem.value, 'acepta_privacidad').length > 0)
const consentDescription = computed(() =>
  consentInvalid.value ? `aviso-privacidad ${fieldErrorId('acepta_privacidad')}` : 'aviso-privacidad',
)

// The sign-in returns to the same route as the registration.
const signInLink = computed(() => ({
  name: 'sign-in',
  query: typeof route.query.redirect === 'string' ? { redirect: route.query.redirect } : {},
}))

async function submit(): Promise<void> {
  if (sending.value) {
    return
  }

  problem.value = null
  triedWithoutConsent.value = !aceptaPrivacidad.value

  if (triedWithoutConsent.value) {
    // The focus goes to the box, which reads its message (aria-describedby).
    await nextTick()
    consent.value?.focus()

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
    // The account exists: the sign-in shows its email, says that the account was created and returns to the same route.
    session.rememberEmail(correo.value)
    await router.replace({ name: 'sign-in', query: { redirect: redirectTarget(route.query.redirect) } })

    return
  }

  await router.replace(redirectTarget(route.query.redirect))
}
</script>

<template>
  <div class="page register">
    <h1>Crear cuenta</h1>
    <p class="form-intro">
      Con su cuenta de cliente puede reservar pruebas de olores y hacer pedidos. Todos los datos son obligatorios.
    </p>

    <ProblemNotice v-if="problem" :problem="problem" />

    <form ref="form" class="form" novalidate @submit.prevent="submit">
      <div class="form-field">
        <label for="nombre">Nombre</label>
        <p id="pista-nombre" class="field-hint">El nombre que verá la tienda en sus pedidos y citas.</p>
        <input
          id="nombre"
          v-model="nombre"
          name="nombre"
          type="text"
          autocomplete="name"
          required
          v-bind="fieldAttributes(problem, 'nombre', 'pista-nombre')"
        />
        <FieldError :problem="problem" field="nombre" />
      </div>

      <div class="form-field">
        <label for="correo">Correo</label>
        <p id="pista-correo" class="field-hint">Con este correo iniciará sesión y recibirá los avisos de la tienda.</p>
        <input
          id="correo"
          v-model="correo"
          name="correo"
          type="email"
          autocomplete="email"
          required
          v-bind="fieldAttributes(problem, 'correo', 'pista-correo')"
        />
        <FieldError :problem="problem" field="correo" />
      </div>

      <div class="form-field">
        <label for="contrasena">Contraseña</label>
        <p id="pista-contrasena" class="field-hint">
          Una frase de varias palabras es fácil de recordar y difícil de adivinar. Si la contraseña no es válida,
          al crear la cuenta se indica aquí qué le falta.
        </p>
        <input
          id="contrasena"
          v-model="contrasena"
          name="contrasena"
          :type="showPassword ? 'text' : 'password'"
          autocomplete="new-password"
          autocapitalize="none"
          :spellcheck="false"
          required
          v-bind="fieldAttributes(problem, 'contrasena', 'pista-contrasena')"
        />
        <FieldError :problem="problem" field="contrasena" />
        <label class="checkbox-field">
          <input id="mostrar_contrasena" v-model="showPassword" type="checkbox" />
          Mostrar la contraseña
        </label>
      </div>

      <PrivacyNotice id="aviso-privacidad" />

      <div class="form-field">
        <label class="checkbox-field">
          <input
            id="acepta_privacidad"
            ref="consent"
            v-model="aceptaPrivacidad"
            name="acepta_privacidad"
            type="checkbox"
            required
            :aria-invalid="consentInvalid ? 'true' : undefined"
            :aria-describedby="consentDescription"
          />
          Leí y acepto el aviso de privacidad.
        </label>
        <p v-if="consentMissing" :id="fieldErrorId('acepta_privacidad')" class="field-error">
          Debe aceptar el aviso de privacidad para crear la cuenta.
        </p>
        <FieldError v-else :problem="problem" field="acepta_privacidad" />
      </div>

      <div class="form-actions">
        <!-- aria-disabled and not disabled: the button keeps the focus while the request is on its way. -->
        <button type="submit" :aria-disabled="sending ? 'true' : undefined">
          {{ sending ? 'Creando la cuenta…' : 'Crear cuenta' }}
        </button>
      </div>
    </form>

    <p class="register__sign-in">
      ¿Ya tiene cuenta? <RouterLink :to="signInLink">Iniciar sesión</RouterLink>
    </p>
  </div>
</template>
