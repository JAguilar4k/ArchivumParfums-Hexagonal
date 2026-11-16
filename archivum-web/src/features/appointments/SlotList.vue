<script setup lang="ts">
import { groupAttributes, type ApiProblem } from '@/api/problem'
import FieldError from '@/shared/FieldError.vue'
import { peopleOf, type Slot } from './appointment'
import { formatCalendarDay, formatHours } from './time'

/**
 * The free slots of one day as a group of options (#129), one per slot, with who can attend it. The value is the start
 * of the slot as the API wrote it, so it goes back to the API unchanged. Its messages are those of `inicia_en`.
 * `withPeople` leaves out who attends when the person is already chosen, as in a new time for an appointment.
 */
const props = withDefaults(defineProps<{ slots: Slot[]; day: string; problem: ApiProblem | null; withPeople?: boolean }>(), {
  withPeople: true,
})
const start = defineModel<string | null>('start', { required: true })
</script>

<template>
  <fieldset class="choice-group slot-list" v-bind="groupAttributes(props.problem, 'inicia_en')">
    <legend>Hora de la cita, el {{ formatCalendarDay(day) }}</legend>
    <div class="choice-group__options">
      <label v-for="slot in slots" :key="slot.inicia_en" class="choice">
        <input v-model="start" type="radio" name="inicia_en" :value="slot.inicia_en" />
        <span class="choice__text">
          {{ formatHours(slot.inicia_en, slot.termina_en) }}
          <span v-if="withPeople && peopleOf(slot)" class="choice__detail">Le atiende {{ peopleOf(slot) }}</span>
        </span>
      </label>
    </div>
    <FieldError :problem="problem" field="inicia_en" />
  </fieldset>
</template>
