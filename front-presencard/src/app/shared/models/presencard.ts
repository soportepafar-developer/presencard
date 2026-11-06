/** Tarjeta tal como la ve quien la gestiona (incluye id, responsable y fechas de auditoría). */
export interface Presencard {
  id: number;
  hashId: string;
  /** Usuario responsable de la tarjeta. */
  ownerId: number;
  /** Nombre del responsable (solo se resuelve en listado y detalle). */
  ownerName: string | null;
  fullName: string;
  /** Cargo, correo y teléfono son opcionales: no siempre se conocen. */
  jobTitle: string | null;
  /** Empresa u organización (opcional); se exporta como ORG en el contacto .vcf. */
  company: string | null;
  email: string | null;
  phone: string | null;
  whatsapp: string | null;
  instagram: string | null;
  telegram: string | null;
  /** Colores de la tarjeta (#RRGGBB); null = paleta por defecto. */
  colorPrimary: string | null;
  colorAccent: string | null;
  universoSolucionesUrl: string;
  createdAt: string;
  updatedAt: string;
  publicUrl: string;
}

/** Datos que se capturan en el formulario de la tarjeta. */
export interface PresencardInput {
  fullName: string;
  /** Cargo, correo y teléfono son opcionales: no siempre se conocen. */
  jobTitle: string | null;
  /** Empresa u organización (opcional); se exporta como ORG en el contacto .vcf. */
  company: string | null;
  email: string | null;
  phone: string | null;
  whatsapp: string | null;
  instagram: string | null;
  telegram: string | null;
  /** Colores de la tarjeta (#RRGGBB); null = paleta por defecto. */
  colorPrimary: string | null;
  colorAccent: string | null;
}

export interface PresencardPage {
  items: Presencard[];
  total: number;
  page: number;
  perPage: number;
  totalPages: number;
}

export interface PresencardListQuery {
  q: string;
  page: number;
}

/** Datos de la vista pública: sin id interno ni fechas. */
export interface PublicCardData {
  hashId: string;
  fullName: string;
  /** Cargo, correo y teléfono son opcionales: no siempre se conocen. */
  jobTitle: string | null;
  /** Empresa u organización (opcional); se exporta como ORG en el contacto .vcf. */
  company: string | null;
  email: string | null;
  phone: string | null;
  whatsapp: string | null;
  instagram: string | null;
  telegram: string | null;
  /** Colores de la tarjeta (#RRGGBB); null = paleta por defecto. */
  colorPrimary: string | null;
  colorAccent: string | null;
  universoSolucionesUrl: string;
  publicUrl: string;
}
