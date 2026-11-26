<script setup>
import { computed } from 'vue'
import { CheckCircle2, Clock, AlertTriangle, XCircle } from 'lucide-vue-next'
import { buildWorkflowSteps } from '~/lib/workflow'

const props = defineProps({
  request: { type: Object, required: true },
})

const workflow = computed(() => buildWorkflowSteps(props.request))

function stateStyle(state) {
  if (state === 'done') {
    return {
      card: 'border-emerald-200 bg-emerald-50/50',
      badge: 'bg-emerald-700 text-white',
      text: 'text-emerald-800',
      statusText: 'Selesai',
      icon: CheckCircle2,
    }
  }
  if (state === 'rejected') {
    return {
      card: 'border-rose-200 bg-rose-50/60',
      badge: 'bg-rose-700 text-white',
      text: 'text-rose-800',
      statusText: 'Ditolak',
      icon: XCircle,
    }
  }
  if (state === 'correction' || state === 'failed') {
    return {
      card: 'border-amber-300 bg-amber-50/70',
      badge: 'bg-amber-700 text-white',
      text: 'text-amber-900',
      statusText: state === 'correction' ? 'Perlu Revisi' : 'Perlu Tindakan',
      icon: AlertTriangle,
    }
  }
  if (state === 'current') {
    return {
      card: 'border-blue-300 bg-blue-50/50',
      badge: 'bg-[#1E3A5F] text-white',
      text: 'text-[#1E3A5F]',
      statusText: 'Tahap Aktif',
      icon: Clock,
    }
  }
  return {
    card: 'border-slate-200 bg-white',
    badge: 'bg-slate-200 text-slate-600',
    text: 'text-slate-500',
    statusText: 'Menunggu',
    icon: Clock,
  }
}
</script>

<template>
  <div class="space-y-3">
    <div class="flex flex-wrap items-center justify-between gap-2">
      <h4 class="text-sm font-bold text-slate-900">Alur Persetujuan &amp; Tanda Tangan</h4>
      <span class="text-xs font-semibold text-slate-600 bg-slate-100 border border-slate-200 px-2.5 py-1 rounded-lg">
        {{ workflow.summary }}
      </span>
    </div>

    <ol class="space-y-2.5">
      <li
        v-for="(step, idx) in workflow.steps"
        :key="idx"
        :class="['flex items-start gap-3 p-3.5 rounded-xl border transition-colors', stateStyle(step.state).card]"
      >
        <span
          :class="['mt-0.5 h-6 w-6 rounded-full flex items-center justify-center text-xs font-bold shrink-0', stateStyle(step.state).badge]"
          aria-hidden="true"
        >
          {{ idx + 1 }}
        </span>
        <div class="flex-1 min-w-0">
          <p class="text-sm font-semibold text-slate-900">{{ step.title }}</p>
          <p class="text-xs text-slate-600 mt-0.5 leading-relaxed">{{ step.subtitle }}</p>
        </div>
        <span :class="['inline-flex items-center gap-1 text-xs font-semibold shrink-0', stateStyle(step.state).text]">
          <component :is="stateStyle(step.state).icon" class="w-3.5 h-3.5" aria-hidden="true" />
          <span>{{ stateStyle(step.state).statusText }}</span>
        </span>
      </li>
    </ol>
  </div>
</template>

