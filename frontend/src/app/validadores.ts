import { AbstractControl, ValidationErrors, ValidatorFn } from '@angular/forms';

// Validadores propios de los formularios reactivos.
// Las reglas de Laravel siguen siendo las que valen: estos solo avisan antes de mandar el pedido.

// Rechaza textos que son solo espacios (Validators.required los deja pasar)
export function noSoloEspacios(control: AbstractControl): ValidationErrors | null {
  const valor = control.value as string | null;
  return valor && valor.trim() === '' ? { soloEspacios: true } : null;
}

// Para el grupo del formulario: los dos campos tienen que tener el mismo valor
export function camposIguales(campo: string, confirmacion: string): ValidatorFn {
  return (grupo: AbstractControl): ValidationErrors | null => {
    const valor = grupo.get(campo)?.value;
    const repetido = grupo.get(confirmacion)?.value;
    return repetido && valor !== repetido ? { noCoinciden: true } : null;
  };
}

// Teléfono argentino escrito a mano: números, espacios, +, - y paréntesis
export const PATRON_TELEFONO = /^[0-9+\-\s()]*$/;
