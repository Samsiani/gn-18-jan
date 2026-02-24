import { defineStore } from 'pinia'

export const useNotificationStore = defineStore('notifications', {
  state: () => ({
    items: []
  }),

  getters: {
    unreadCount: (state) => state.items.filter(n => !n.read).length
  },

  actions: {
    init() {
      const now = new Date()
      this.items = [
        { id: 1, type: 'invoice',     titleKey: 'notif.newPayment',         messageKey: 'notif.msg.payment',       messageParams: { prefix: 'GN-1002 · ₾5,700.00', methodKey: 'label.bankTransfer' }, time: new Date(now - 1000*60*30).toISOString(),      read: false, icon: 'credit-card',    invoiceId: 2  },
        { id: 2, type: 'reservation', titleKey: 'notif.reservationExpiring', messageKey: 'notif.msg.daysRemaining', messageParams: { prefix: 'GN-1024', n: 3 },                               time: new Date(now - 1000*60*60*2).toISOString(),   read: false, icon: 'clock',          invoiceId: 24 },
        { id: 3, type: 'stock',       titleKey: 'notif.lowStock',            messageKey: 'notif.msg.unitsLeft',     messageParams: { product: 'Centrifugal Pump', n: 6 },                     time: new Date(now - 1000*60*60*5).toISOString(),   read: false, icon: 'alert-triangle'                },
        { id: 4, type: 'invoice',     titleKey: 'notif.invoiceCompleted',    messageKey: 'notif.msg.fullyPaid',     messageParams: { prefix: 'GN-1001' },                                     time: new Date(now - 1000*60*60*24).toISOString(),  read: true,  icon: 'check-circle',   invoiceId: 1  },
        { id: 5, type: 'system',      titleKey: 'notif.systemUpdate',        message: 'Phase 16 features available',                                                                          time: new Date(now - 1000*60*60*48).toISOString(),  read: true,  icon: 'info'                          },
      ]
    },

    // Push a live notification and play a chime
    // Pass: { type, titleKey, message, icon, invoiceId? }
    push(notif) {
      this.items.unshift({
        id: Date.now(),
        read: false,
        time: new Date().toISOString(),
        ...notif,
      })
      this.playSound()
    },

    markAllRead() {
      this.items.forEach(n => { n.read = true })
    },

    markRead(id) {
      const n = this.items.find(n => n.id === id)
      if (n) n.read = true
    },

    playSound() {
      try {
        const AC = window.AudioContext || window.webkitAudioContext
        if (!AC) return
        const ctx = new AC()
        const t0 = ctx.currentTime
        // Two-tone descending chime: E6 → C6
        ;[
          { freq: 1318, start: 0,    dur: 0.35 },
          { freq: 1046, start: 0.13, dur: 0.55 },
        ].forEach(({ freq, start, dur }) => {
          const osc  = ctx.createOscillator()
          const gain = ctx.createGain()
          osc.connect(gain)
          gain.connect(ctx.destination)
          osc.type = 'sine'
          osc.frequency.value = freq
          gain.gain.setValueAtTime(0.18, t0 + start)
          gain.gain.exponentialRampToValueAtTime(0.001, t0 + start + dur)
          osc.start(t0 + start)
          osc.stop(t0 + start + dur)
        })
      } catch (e) { /* AudioContext not available */ }
    },
  },
})
