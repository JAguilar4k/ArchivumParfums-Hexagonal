// jsdom has HTMLDialogElement but does not implement the modal methods.
if (!HTMLDialogElement.prototype.showModal) {
  Object.defineProperties(HTMLDialogElement.prototype, {
    showModal: { value(this: HTMLDialogElement) { this.open = true } },
    close: { value(this: HTMLDialogElement) { this.open = false } },
  })
}
