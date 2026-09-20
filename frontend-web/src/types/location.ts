// Référentiel du découpage administratif du Bénin (CLAUDE.md §5, ajout
// v0.19) — GET /locations/departments, .../{id}/communes,
// .../communes/{id}/arrondissements. Chaque niveau ne porte que son id et
// son nom d'affichage.
export interface LocationOption {
  id: number
  name: string
}

// Valeur d'un profil Garage/Market Space : trois niveaux en cascade (chacun
// filtre le suivant) + un quartier en texte libre.
export interface LocationValue {
  department_id: number | null
  commune_id: number | null
  arrondissement_id: number | null
  neighborhood: string | null
}
