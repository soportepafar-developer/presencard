import { ChangeDetectionStrategy, Component, OnInit, computed, inject, signal } from '@angular/core';
import { toSignal } from '@angular/core/rxjs-interop';
import { AbstractControl, NonNullableFormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { ActivatedRoute, Router, RouterLink } from '@angular/router';
import { apiErrorMessage } from '../../../core/api';
import { PresencardService } from '../../../core/presencard.service';
import { Presencard, PresencardInput } from '../../../shared/models/presencard';
import { Icon } from '../../../shared/ui/icon';
import { ShareDialog } from '../share-dialog/share-dialog';
import {
  DEFAULT_ACCENT,
  DEFAULT_PRIMARY,
  HEX_COLOR_PATTERN,
  PALETTE_PRESETS,
  PalettePreset,
  isHexColor,
  paletteVars,
} from '../../../shared/utils/palette';
import { INSTAGRAM_PATTERN, TELEGRAM_PATTERN, whatsappValidator } from './card-form.validators';

const blankToNull = (value: string): string | null => (value.trim() === '' ? null : value.trim());

@Component({
  selector: 'app-card-form',
  imports: [ReactiveFormsModule, RouterLink, Icon, ShareDialog],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './card-form.html',
  styleUrl: './card-form.scss',
})
export class CardForm implements OnInit {
  private readonly service = inject(PresencardService);
  private readonly router = inject(Router);
  private readonly route = inject(ActivatedRoute);

  /** Presente al editar (/panel/cards/:id/edit); ausente al crear. */
  protected readonly cardId = Number(this.route.snapshot.paramMap.get('id')) || null;
  protected readonly isEdit = this.cardId !== null;

  protected readonly form = inject(NonNullableFormBuilder).group({
    fullName: ['', [Validators.required, Validators.maxLength(150)]],
    jobTitle: ['', [Validators.maxLength(150)]],
    company: ['', [Validators.maxLength(150)]],
    email: ['', [Validators.email, Validators.maxLength(180)]],
    phone: ['', [Validators.maxLength(30)]],
    whatsapp: ['', [whatsappValidator]],
    instagram: ['', [Validators.pattern(INSTAGRAM_PATTERN), Validators.maxLength(120)]],
    telegram: ['', [Validators.pattern(TELEGRAM_PATTERN), Validators.maxLength(120)]],
    colorPrimary: [DEFAULT_PRIMARY, [Validators.required, Validators.pattern(HEX_COLOR_PATTERN)]],
    colorAccent: [DEFAULT_ACCENT, [Validators.required, Validators.pattern(HEX_COLOR_PATTERN)]],
  });

  protected readonly presets = PALETTE_PRESETS;
  private readonly colors = toSignal(this.form.valueChanges, { initialValue: this.form.getRawValue() });

  /** Variables CSS de la vista previa; siempre con los colores válidos que haya escrito la persona. */
  protected readonly previewStyle = computed(() => {
    const value = this.colors();

    return paletteVars(
      isHexColor(value.colorPrimary) ? value.colorPrimary : DEFAULT_PRIMARY,
      isHexColor(value.colorAccent) ? value.colorAccent : DEFAULT_ACCENT,
    );
  });

  protected readonly loading = signal(this.isEdit);
  protected readonly loadError = signal<string | null>(null);
  protected readonly saving = signal(false);
  protected readonly error = signal<string | null>(null);

  /** Tarjeta recién creada: abre el modal de confirmación con su URL única. */
  protected readonly created = signal<Presencard | null>(null);
  /** Tarjeta que se está editando (para mostrar su enlace). */
  protected readonly editing = signal<Presencard | null>(null);
  protected readonly sharing = signal(false);

  ngOnInit(): void {
    if (this.cardId === null) {
      return;
    }

    this.service.get(this.cardId).subscribe({
      next: (card) => {
        this.editing.set(card);
        this.form.patchValue({
          fullName: card.fullName,
          jobTitle: card.jobTitle ?? '',
          company: card.company ?? '',
          email: card.email ?? '',
          phone: card.phone ?? '',
          whatsapp: card.whatsapp ?? '',
          instagram: card.instagram ?? '',
          telegram: card.telegram ?? '',
          colorPrimary: card.colorPrimary ?? DEFAULT_PRIMARY,
          colorAccent: card.colorAccent ?? DEFAULT_ACCENT,
        });
        this.loading.set(false);
      },
      error: (error: unknown) => {
        this.loadError.set(apiErrorMessage(error, 'No se pudo cargar la tarjeta.'));
        this.loading.set(false);
      },
    });
  }

  protected isPreset(preset: PalettePreset): boolean {
    const { colorPrimary, colorAccent } = this.form.getRawValue();

    return colorPrimary.toUpperCase() === preset.primary && colorAccent.toUpperCase() === preset.accent;
  }

  protected applyPreset(preset: PalettePreset): void {
    this.form.patchValue({ colorPrimary: preset.primary, colorAccent: preset.accent });
    this.form.markAsDirty();
  }

  /** Valor del selector nativo de color: solo acepta #rrggbb, así que si lo escrito no es válido muestra el de fábrica. */
  protected pickerValue(control: AbstractControl<string>, fallback: string): string {
    return isHexColor(control.value) ? control.value.toLowerCase() : fallback.toLowerCase();
  }

  protected setColor(control: AbstractControl<string>, event: Event): void {
    control.setValue((event.target as HTMLInputElement).value.toUpperCase());
    control.markAsDirty();
  }

  protected invalid(control: AbstractControl): boolean {
    return control.invalid && (control.touched || control.dirty);
  }

  protected submit(): void {
    if (this.form.invalid) {
      this.form.markAllAsTouched();
      return;
    }

    const raw = this.form.getRawValue();
    // Con la paleta por defecto no se guarda nada: la tarjeta sigue la paleta de Presencard.
    const isDefaultPalette =
      raw.colorPrimary.toUpperCase() === DEFAULT_PRIMARY && raw.colorAccent.toUpperCase() === DEFAULT_ACCENT;
    const input: PresencardInput = {
      fullName: raw.fullName.trim(),
      jobTitle: blankToNull(raw.jobTitle),
      company: blankToNull(raw.company),
      email: blankToNull(raw.email),
      phone: blankToNull(raw.phone),
      whatsapp: blankToNull(raw.whatsapp),
      instagram: blankToNull(raw.instagram),
      telegram: blankToNull(raw.telegram),
      colorPrimary: isDefaultPalette ? null : raw.colorPrimary.toUpperCase(),
      colorAccent: isDefaultPalette ? null : raw.colorAccent.toUpperCase(),
    };

    this.saving.set(true);
    this.error.set(null);

    const request = this.cardId === null ? this.service.create(input) : this.service.update(this.cardId, input);

    request.subscribe({
      next: (card) => {
        this.saving.set(false);

        if (this.isEdit) {
          this.goToList(`Se guardaron los cambios de ${card.fullName}.`);
        } else {
          this.created.set(card);
        }
      },
      error: (error: unknown) => {
        this.saving.set(false);
        this.error.set(apiErrorMessage(error, 'No se pudo guardar la tarjeta.'));
      },
    });
  }

  protected closeCreated(): void {
    const card = this.created();
    this.created.set(null);
    this.goToList(card ? `Se creó la tarjeta de ${card.fullName}.` : null);
  }

  private goToList(flash: string | null): void {
    void this.router.navigate(['/panel/cards'], { state: flash ? { flash } : {} });
  }
}
