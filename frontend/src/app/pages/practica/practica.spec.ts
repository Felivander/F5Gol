import { TestBed } from '@angular/core/testing';
import { Practica } from './practica';

describe('Practica', () => {
  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [Practica],
    })
      .compileComponents();
  });

  it('should create the component', () => {
    const fixture = TestBed.createComponent(Practica);
    const component = fixture.componentInstance;
    expect(component).toBeTruthy();
  });

  it('should render greeting', async () => {
    const fixture = TestBed.createComponent(Practica);
    await fixture.whenStable();
    const compiled = fixture.nativeElement as HTMLElement;
    expect(compiled.querySelector('h1')?.textContent).toContain('Hola Pablo');
  });

  it('should change the name on click', async () => {
    const fixture = TestBed.createComponent(Practica);
    await fixture.whenStable();
    const compiled = fixture.nativeElement as HTMLElement;
    compiled.querySelector('button')?.click();
    await fixture.whenStable();
    expect(compiled.querySelector('h1')?.textContent).toContain('Hola Juan');
  });

  it('should render the list of students', async () => {
    const fixture = TestBed.createComponent(Practica);
    await fixture.whenStable();
    const compiled = fixture.nativeElement as HTMLElement;
    expect(compiled.querySelectorAll('section:last-of-type p').length).toBe(4);
  });
});
