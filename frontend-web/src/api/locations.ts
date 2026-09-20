import http from '@/api/http'
import type { LocationOption } from '@/types/location'

// Même enveloppe { data } que le reste de l'API (voir
// app/Http/Controllers/Api/Controller.php::success() côté backend). Routes
// publiques : pas de token requis, mais l'intercepteur Axios l'ajoute quand
// il existe, sans effet ici.
interface ApiEnvelope<T> {
  data: T
}

// Les listes de référence ne changent jamais pendant une session : on garde
// en mémoire la *promesse* de chaque appel (et non son résultat), ce qui
// évite aussi un doublon si deux composants demandent la même liste en même
// temps. Une requête échouée est retirée du cache pour pouvoir être retentée.
const cache = new Map<string, Promise<LocationOption[]>>()

function fetchOptions(url: string): Promise<LocationOption[]> {
  let pending = cache.get(url)
  if (!pending) {
    pending = http
      .get<ApiEnvelope<LocationOption[]>>(url)
      .then((response) => response.data.data)
      .catch((error) => {
        cache.delete(url)
        throw error
      })
    cache.set(url, pending)
  }
  return pending
}

export function fetchDepartments(): Promise<LocationOption[]> {
  return fetchOptions('/locations/departments')
}

export function fetchCommunes(departmentId: number): Promise<LocationOption[]> {
  return fetchOptions(`/locations/departments/${departmentId}/communes`)
}

export function fetchArrondissements(communeId: number): Promise<LocationOption[]> {
  return fetchOptions(`/locations/communes/${communeId}/arrondissements`)
}
