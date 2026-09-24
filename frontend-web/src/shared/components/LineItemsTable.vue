<script setup lang="ts" generic="T extends LineItem">
import type { LineItem } from '@/types/lineItem'
import { formatAmount } from '@/utils/money'

// Lignes d'un devis ou d'une commande (libellé, quantité, prix unitaire,
// total), avec le total général. Lecture seule. `typeLabel` : nature de la
// ligne, affichée sous le libellé quand elle est fournie (devis). `generic`
// : `typeLabel` reçoit le vrai type de ligne de l'appelant (ex. `QuoteLine`).

defineProps<{
  lines: T[]
  total: string
  typeLabel?: (line: T) => string | null
}>()
</script>

<template>
  <div class="overflow-x-auto">
    <table class="min-w-full text-sm">
      <thead>
        <tr class="border-b border-slate-200 text-left text-xs font-semibold uppercase tracking-wide text-slate-400">
          <th class="py-2 pr-4">Libellé</th>
          <th class="py-2 pr-4 text-right">Qté</th>
          <th class="py-2 pr-4 text-right">Prix unitaire</th>
          <th class="py-2 text-right">Total</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-slate-100">
        <tr v-if="lines.length === 0">
          <td colspan="4" class="py-3 text-slate-500">Aucune ligne.</td>
        </tr>
        <tr v-for="line in lines" :key="line.id">
          <td class="py-2 pr-4 text-slate-700">
            {{ line.label }}
            <span v-if="typeLabel?.(line)" class="block text-xs text-slate-400">{{ typeLabel(line) }}</span>
          </td>
          <td class="py-2 pr-4 text-right text-slate-700">{{ line.quantity }}</td>
          <td class="whitespace-nowrap py-2 pr-4 text-right text-slate-700">{{ formatAmount(line.unit_price) }}</td>
          <td class="whitespace-nowrap py-2 text-right text-slate-700">{{ formatAmount(line.line_total) }}</td>
        </tr>
      </tbody>
      <tfoot>
        <tr class="border-t border-slate-200">
          <td colspan="3" class="py-2 pr-4 text-right font-semibold text-slate-900">Total</td>
          <td class="whitespace-nowrap py-2 text-right font-semibold text-slate-900">{{ formatAmount(total) }}</td>
        </tr>
      </tfoot>
    </table>
  </div>
</template>
