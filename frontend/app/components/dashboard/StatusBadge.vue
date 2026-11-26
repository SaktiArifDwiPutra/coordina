<script setup>
import { CheckCircle2, Clock, AlertTriangle, XCircle } from 'lucide-vue-next'

const props = defineProps({
  label: { type: String, required: true },
  tone: { type: String, default: 'slate' }, // 'emerald' | 'blue' | 'amber' | 'rose' | 'slate'
  icon: { type: String, default: 'clock' }, // 'check' | 'clock' | 'alert' | 'x'
})

const toneClasses = {
  emerald: 'bg-emerald-50 text-emerald-800 border-emerald-200',
  blue: 'bg-blue-50 text-blue-800 border-blue-200',
  amber: 'bg-amber-50 text-amber-900 border-amber-300',
  rose: 'bg-rose-50 text-rose-800 border-rose-200',
  slate: 'bg-slate-100 text-slate-700 border-slate-200',
}

const iconMap = {
  check: CheckCircle2,
  clock: Clock,
  alert: AlertTriangle,
  x: XCircle,
}
</script>

<template>
  <span
    :class="[
      'inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full border text-xs font-semibold leading-tight',
      toneClasses[props.tone] || toneClasses.slate,
    ]"
  >
    <component :is="iconMap[props.icon] || Clock" class="w-3.5 h-3.5 shrink-0" aria-hidden="true" />
    <span>{{ label }}</span>
  </span>
</template>

