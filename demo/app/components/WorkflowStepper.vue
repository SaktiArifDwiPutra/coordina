<script setup lang="ts">
import { computed } from 'vue'
import { CheckCircle2, Clock, XCircle } from 'lucide-vue-next'
import { buildWorkflowSteps } from '~/lib/workflow'
import type { BorrowRequestItem, OrganizationProfile } from '~/types/coordina'

const props = defineProps<{
  request: BorrowRequestItem
  organizations: OrganizationProfile[]
}>()

const workflow = computed(() => buildWorkflowSteps(props.request, props.organizations))

function styleFor(state: 'done' | 'current' | 'rejected' | 'pending') {
  if (state === 'done') {
    return {
      card: 'border-[#A7F3D0] bg-[#ECFDF5]/60',
      badge: 'bg-[#15803D] text-white',
      statusCls: 'text-[#15803D]',
      statusText: 'Selesai',
      icon: CheckCircle2,
    }
  }
  if (state === 'rejected') {
    return {
      card: 'border-[#FECDD3] bg-[#FFF1F2]/70',
      badge: 'bg-[#BE123C] text-white',
      statusCls: 'text-[#BE123C]',
      statusText: 'Ditolak',
      icon: XCircle,
    }
  }
  if (state === 'current') {
    return {
      card: 'border-[#93C5FD] bg-[#EFF6FF]/60',
      badge: 'bg-[#1E3A5F] text-white',
      statusCls: 'text-[#1E3A5F]',
      statusText: 'Tahap Aktif',
      icon: Clock,
    }
  }
  return {
    card: 'border-[#DCE4EE] bg-white',
    badge: 'bg-[#E2E8F0] text-[#475569]',
    statusCls: 'text-[#475569]',
    statusText: 'Menunggu',
    icon: Clock,
  }
}
</script>

<template>
  <div class="space-y-3">
    <div class="flex flex-wrap items-center justify-between gap-2">
      <h4 class="text-sm font-bold text-[#0F172A]">Tahapan Alur Persetujuan</h4>
      <span class="text-xs font-semibold text-[#1E3A5F] bg-[#EFF6FF] border border-[#BFDBFE] px-2.5 py-1 rounded-lg">
        {{ workflow.summary }}
      </span>
    </div>

    <ol class="space-y-2.5">
      <li
        v-for="(step, idx) in workflow.steps"
        :key="idx"
        :class="['flex items-start gap-3 p-3.5 rounded-xl border', styleFor(step.state).card]"
      >
        <span
          :class="['mt-0.5 h-6 w-6 rounded-full flex items-center justify-center text-xs font-bold shrink-0', styleFor(step.state).badge]"
          aria-hidden="true"
        >
          {{ idx + 1 }}
        </span>
        <div class="flex-1 min-w-0">
          <p class="text-sm font-semibold text-[#0F172A]">{{ step.title }}</p>
          <p class="text-xs text-[#475569] mt-0.5 leading-relaxed">{{ step.subtitle }}</p>
        </div>
        <span :class="['inline-flex items-center gap-1 text-xs font-semibold shrink-0', styleFor(step.state).statusCls]">
          <component :is="styleFor(step.state).icon" class="w-3.5 h-3.5" aria-hidden="true" />
          <span>{{ styleFor(step.state).statusText }}</span>
        </span>
      </li>
    </ol>
  </div>
</template>

