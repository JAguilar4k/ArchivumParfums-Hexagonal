<script setup lang="ts">
import { computed } from 'vue'

import type { ApiProblem } from '@/api/problem'
import ForbiddenView from './ForbiddenView.vue'
import NotFoundView from './NotFoundView.vue'

/**
 * Shows a failed call of the API in plain Spanish (#127): a 403 shows the
 * "Sin permiso" view, a 404 the "No encontrado" view, and any other problem an
 * accessible notice with its message. An unexpected error or a network
 * failure also shows the request identifier for support. What the view puts
 * inside goes in the same notice, so it is announced with the message.
 */
const props = defineProps<{ problem: ApiProblem }>()

const forSupport = computed(
  () =>
    props.problem.kind === 'internal' ||
    props.problem.kind === 'network' ||
    (props.problem.status ?? 0) >= 500,
)
</script>

<template>
  <ForbiddenView v-if="problem.kind === 'forbidden'" />
  <NotFoundView v-else-if="problem.kind === 'not-found'" />
  <div v-else class="problem-notice" role="alert">
    <p class="problem-notice__message">{{ problem.message }}</p>
    <slot />
    <p v-if="forSupport && problem.requestId" class="problem-notice__support">
      Si el problema continúa, comparta con soporte este identificador de la solicitud:
      <code>{{ problem.requestId }}</code>
    </p>
  </div>
</template>
