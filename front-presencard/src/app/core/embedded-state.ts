import { PublicCardData } from '../shared/models/presencard';

export interface EmbeddedState {
  card?: PublicCardData;
  notFound?: boolean;
}

/**
 * Symfony inyecta en /c/{hash} los datos de la tarjeta dentro de un <script type="application/json">,
 * así la vista pública se pinta sin esperar una segunda petición.
 */
export function readEmbeddedState(doc: Document): EmbeddedState | null {
  const text = doc.getElementById('presencard-state')?.textContent;

  if (!text) {
    return null;
  }

  try {
    return JSON.parse(text) as EmbeddedState;
  } catch {
    return null;
  }
}
