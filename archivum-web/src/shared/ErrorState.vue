<script setup lang="ts">
import { computed } from 'vue'

import type { ApiProblem } from '@/api/problem'
import ProblemNotice from './ProblemNotice.vue'

/**
 * Shown when a view could not load its data (#126). The problem goes through
 * the interpreter of #127, and a failure that may pass offers to try again.
 */
const props = defineProps<{ problem: ApiProblem }>()
defineEmits<{ retry: [] }>()

const canRetry = computed(() => ['internal', 'network', 'rate-limited', 'other'].includes(props.problem.kind))
</script>

<template>
  <div class="error-state">
    <ProblemNotice :problem="problem" />
    <button v-if="canRetry" type="button" @click="$emit('retry')">Intentar de nuevo</button>
  </div>
</template>
