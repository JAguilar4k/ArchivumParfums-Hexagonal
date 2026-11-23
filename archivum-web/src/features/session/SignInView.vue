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
// A customer whose account was just created, but whose sign-in failed, finds its email already written (#194).
const rememberedEmail = session.takeRememberedEmail()
const accountCreated = rememberedEmail !== ''
const correo = ref(rememberedEmail)
const contrasena = ref('')
const showPassword = ref(false)
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
  <div class="page account-page sign-in">
    <div class="account-page__header">
      <h1 id="sign-in-heading">Iniciar sesión</h1>
      <p v-if="accountCreated" class="status-message" role="status">Se creó tu cuenta. Escribe tu contraseña para iniciar sesión.</p>
      <p v-else class="form-intro">Escribe el correo y la contraseña de tu cuenta.</p>
    </div>

    <ProblemNotice v-if="problem" :problem="problem" />

    <div class="account-page__columns">
      <form ref="form" class="form account-panel" aria-labelledby="sign-in-heading" novalidate @submit.prevent="submit">
        <div class="form-field">
          <label for="correo">Correo</label>
          <p id="pista-correo" class="field-hint">El correo que usaste para crear tu cuenta.</p>
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
          <p id="pista-contrasena" class="field-hint">La contraseña de tu cuenta.</p>
          <div class="password-control">
            <input
              id="contrasena"
              v-model="contrasena"
              name="contrasena"
              :type="showPassword ? 'text' : 'password'"
              autocomplete="current-password"
              autocapitalize="none"
              :spellcheck="false"
              required
              v-bind="fieldAttributes(problem, 'contrasena', 'pista-contrasena')"
            />
            <button type="button" class="button--secondary" :aria-pressed="showPassword" @click="showPassword = !showPassword">
              Mostrar<span class="visually-hidden"> la contraseña</span>
            </button>
          </div>
          <FieldError :problem="problem" field="contrasena" />
        </div>

        <div class="form-actions">
          <!-- aria-disabled and not disabled: the button keeps the focus while the request is on its way. -->
          <button type="submit" :aria-disabled="sending ? 'true' : undefined">
            {{ sending ? 'Ingresando…' : 'Iniciar sesión' }}
          </button>
        </div>
      </form>

      <section class="account-panel sign-in__register" aria-labelledby="sign-in-register-heading">
        <h2 id="sign-in-register-heading">¿Todavía no tienes cuenta?</h2>
        <p>Con una cuenta de cliente puedes hacer pedidos y reservar citas de prueba.</p>
        <RouterLink :to="registerLink" class="button button--secondary">Crear una cuenta de cliente</RouterLink>
      </section>
    </div>
  </div>
</template>
