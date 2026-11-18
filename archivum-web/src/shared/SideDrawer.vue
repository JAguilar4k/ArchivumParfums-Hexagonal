<script setup lang="ts">
import { useTemplateRef, watch } from 'vue'

import StoreIcon from './StoreIcon.vue'

const props = withDefaults(defineProps<{
  title: string
  headingId: string
  side?: 'left' | 'right'
  closeLabel?: string
  trigger?: HTMLElement | null
  initialFocus?: string
}>(), { side: 'right', closeLabel: 'Cerrar el panel' })

const open = defineModel<boolean>('open', { required: true })
const dialog = useTemplateRef<HTMLDialogElement>('dialog')
const heading = useTemplateRef<HTMLElement>('heading')
let opener: HTMLElement | null = null

watch([open, dialog], ([visible, element]) => {
  if (!element) return

  if (visible && !element.open) {
    opener = props.trigger ?? (document.activeElement instanceof HTMLElement ? document.activeElement : null)
    element.showModal()
    const initial = props.initialFocus ? element.querySelector<HTMLElement>(props.initialFocus) : heading.value
    initial?.focus({ preventScroll: true })
  } else if (!visible && element.open) {
    element.close()
    if (opener?.isConnected) opener.focus({ preventScroll: true })
    opener = null
  }
}, { flush: 'post' })
</script>

<template>
  <dialog
    ref="dialog"
    class="side-drawer"
    :class="`side-drawer--${side}`"
    :aria-labelledby="headingId"
    aria-modal="true"
    @cancel.prevent="open = false"
    @click.self="open = false"
  >
    <div v-if="open" class="side-drawer__panel">
      <div class="side-drawer__header">
        <h2 :id="headingId" ref="heading" tabindex="-1">{{ title }}</h2>
        <button type="button" class="icon-button side-drawer__close" :aria-label="closeLabel" @click="open = false">
          <StoreIcon name="close" :size="18" />
        </button>
      </div>
      <div class="side-drawer__body"><slot /></div>
      <div v-if="$slots.footer" class="side-drawer__footer"><slot name="footer" /></div>
    </div>
  </dialog>
</template>

<style scoped>
.side-drawer {
  position: fixed;
  inset: 0 0 0 auto;
  width: min(var(--drawer-width, 26rem), calc(100% - 1.75rem));
  max-width: none;
  height: 100dvh;
  max-height: none;
  margin: 0;
  padding: 0;
  border: 0;
  background: var(--color-surface);
  color: var(--color-text);
  box-shadow: 0 0 2rem #0003;
  overflow: hidden;
}

.side-drawer[open] {
  animation: reveal-drawer 180ms ease-out;
}

.side-drawer--left {
  --drawer-direction: -100%;
  inset: 0 auto 0 0;
}

.side-drawer::backdrop {
  background: rgb(23 18 15 / 55%);
}

.side-drawer__panel {
  display: flex;
  flex-direction: column;
  height: 100%;
}

.side-drawer__header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  padding: 1rem 1.25rem;
  border-bottom: 1px solid var(--color-divider);
}

.side-drawer__header h2 { font-size: 1.5rem; }

.side-drawer__close {
  flex: none;
  border: 1px solid var(--color-border);
}

.side-drawer__body {
  flex: 1;
  min-height: 0;
  overflow: auto;
  overscroll-behavior: contain;
}

.side-drawer__footer {
  padding: 1rem 1.25rem max(1.25rem, env(safe-area-inset-bottom));
  border-top: 1px solid var(--color-divider);
}

@keyframes reveal-drawer {
  from { transform: translateX(var(--drawer-direction, 100%)); }
  to { transform: translateX(0); }
}
</style>
