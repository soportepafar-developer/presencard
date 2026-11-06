import { TestBed } from '@angular/core/testing';
import { FormControl } from '@angular/forms';
import { PasswordField } from './password-field';

describe('PasswordField', () => {
  function create(generator = false) {
    const control = new FormControl('', { nonNullable: true });
    const fixture = TestBed.createComponent(PasswordField);
    fixture.componentRef.setInput('control', control);
    fixture.componentRef.setInput('inputId', 'pw');
    fixture.componentRef.setInput('label', 'Contraseña');
    fixture.componentRef.setInput('generator', generator);
    fixture.detectChanges();

    const root = fixture.nativeElement as HTMLElement;

    return { fixture, control, root, input: root.querySelector('input') as HTMLInputElement };
  }

  it('oculta la contraseña por defecto y la muestra al pulsar el ojo', () => {
    const { fixture, root, input } = create();

    expect(input.type).toBe('password');

    const toggle = root.querySelector('button') as HTMLButtonElement;
    expect(toggle.getAttribute('aria-label')).toBe('Mostrar contraseña');
    toggle.click();
    fixture.detectChanges();

    expect(input.type).toBe('text');
    expect(toggle.getAttribute('aria-label')).toBe('Ocultar contraseña');
    expect(toggle.getAttribute('aria-pressed')).toBe('true');
  });

  it('asocia la etiqueta con el campo', () => {
    const { root } = create();

    expect((root.querySelector('label') as HTMLLabelElement).htmlFor).toBe('pw');
  });

  it('no ofrece generar contraseña salvo que se pida', () => {
    expect(create(false).root.querySelectorAll('button').length).toBe(1);
    expect(create(true).root.querySelectorAll('button').length).toBe(2);
  });

  it('generar rellena el control con una contraseña válida y la muestra', () => {
    const { fixture, root, control, input } = create(true);

    (root.querySelectorAll('button')[1] as HTMLButtonElement).click();
    fixture.detectChanges();

    expect(control.value.length).toBe(12);
    expect(control.dirty).toBeTrue();
    expect(input.type).toBe('text');
    expect(input.value).toBe(control.value);
  });
});
