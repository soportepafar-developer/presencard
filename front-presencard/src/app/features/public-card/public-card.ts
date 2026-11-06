import { DOCUMENT } from '@angular/common';
import { HttpErrorResponse } from '@angular/common/http';
import { ChangeDetectionStrategy, Component, OnInit, computed, inject, signal } from '@angular/core';
import { Title } from '@angular/platform-browser';
import { ActivatedRoute } from '@angular/router';
import { readEmbeddedState } from '../../core/embedded-state';
import { API_BASE } from '../../core/api';
import { PresencardService } from '../../core/presencard.service';
import { PublicCardData } from '../../shared/models/presencard';
import { Icon } from '../../shared/ui/icon';
import { initialsOf } from '../../shared/utils/initials';
import { paletteVars } from '../../shared/utils/palette';

type Status = 'loading' | 'ready' | 'notFound' | 'error';

@Component({
  selector: 'app-public-card',
  imports: [Icon],
  changeDetection: ChangeDetectionStrategy.OnPush,
  host: { '[style]': 'paletteStyle()' },
  templateUrl: './public-card.html',
  styleUrl: './public-card.scss',
})
export class PublicCard implements OnInit {
  private readonly route = inject(ActivatedRoute);
  private readonly service = inject(PresencardService);
  private readonly document = inject(DOCUMENT);
  private readonly title = inject(Title);

  protected readonly status = signal<Status>('loading');
  protected readonly card = signal<PublicCardData | null>(null);

  /** Colores propios de la tarjeta como variables CSS; null = paleta por defecto. */
  protected readonly paletteStyle = computed(() => {
    const card = this.card();

    return card ? paletteVars(card.colorPrimary, card.colorAccent) : null;
  });
  protected readonly initials = computed(() => initialsOf(this.card()?.fullName ?? ''));
  protected readonly links = computed(() => {
    const card = this.card();

    if (!card) {
      return null;
    }

    return {
      vcard: `${API_BASE}/public/cards/${encodeURIComponent(card.hashId)}/vcard.vcf`,
      mailto: card.email ? `mailto:${card.email}` : null,
      tel: card.phone ? `tel:${card.phone.replace(/[^\d+]/g, '')}` : null,
      whatsapp: card.whatsapp ? `https://wa.me/${card.whatsapp}` : null,
      instagram: card.instagram ? `https://instagram.com/${card.instagram}` : null,
      telegram: card.telegram ? `https://t.me/${card.telegram}` : null,
    };
  });

  ngOnInit(): void {
    this.load();
  }

  protected load(): void {
    const hashId = this.route.snapshot.paramMap.get('hashId') ?? '';
    const embedded = readEmbeddedState(this.document);

    // Si el servidor ya incluyó esta tarjeta en el HTML, se pinta sin pedirla otra vez.
    if (embedded?.card?.hashId === hashId) {
      this.show(embedded.card);
      return;
    }

    this.status.set('loading');
    this.service.publicCard(hashId).subscribe({
      next: (card) => this.show(card),
      error: (error: unknown) => {
        const notFound = error instanceof HttpErrorResponse && error.status === 404;
        this.status.set(notFound ? 'notFound' : 'error');
        this.title.setTitle(notFound ? 'Tarjeta no encontrada · Presencard' : 'Presencard');
      },
    });
  }

  private show(card: PublicCardData): void {
    this.card.set(card);
    this.status.set('ready');
    this.title.setTitle([card.fullName, card.jobTitle, card.company].filter(Boolean).join(' · '));
  }
}
