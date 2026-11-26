<script setup lang="ts">
import { CheckCircle2, Clock, XCircle } from 'lucide-vue-next'

const props = defineProps<{
  label: string
  tone: 'emerald' | 'blue' | 'amber' | 'rose'
  icon: 'check' | 'clock' | 'x'
}>()

const toneMap = {
  emerald: 'bg-[#ECFDF5] text-[#15803D] border-[#A7F3D0]',
  blue: 'bg-[#EFF6FF] text-[#1E3A5F] border-[#BFDBFE]',
  amber: 'bg-[#FFFBEB] text-[#B45309] border-[#FDE68A]',
  rose: 'bg-[#FFF1F2] text-[#BE123C] border-[#FECDD3]',
}

const iconMap = {
  check: CheckCircle2,
  clock: Clock,
  x: XCircle,
}
</script>

<template>
  <span
    :class="[
      'inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full border text-xs font-semibold leading-tight',
      toneMap[props.tone],
    ]"
  >
    <component :is="iconMap[props.icon]" class="w-3.5 h-3.5 shrink-0" aria-hidden="true" />
    <span>{{ label }}</span>
  </span>
</template>

