<script setup lang="ts">
import { computed } from 'vue'

import { fieldErrorId, fieldMessages, type ApiProblem } from '@/api/problem'

/**
 * Messages of one form field from a 422 `datos-invalidos` (#127). The control
 * points to it with `v-bind="fieldAttributes(problem, field)"`.
 */
const props = defineProps<{ problem: ApiProblem | null; field: string }>()

const messages = computed(() => fieldMessages(props.problem, props.field))
</script>

<template>
  <p v-if="messages.length > 0" :id="fieldErrorId(field)" class="field-error">
    {{ messages.join(' ') }}
  </p>
</template>
