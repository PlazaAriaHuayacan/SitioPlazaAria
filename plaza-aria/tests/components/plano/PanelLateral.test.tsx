import { describe, it, expect, vi } from 'vitest';
import { render, screen, fireEvent } from '@testing-library/react';
import { PanelLateral } from '@/components/plano/PanelLateral';
import type { UnidadComercialAgrupada } from '@/lib/plano/agrupar';

vi.mock('next/image', () => ({ default: (props: any) => <img {...props} /> }));
vi.mock('next/link', () => ({ default: ({ children, ...p }: any) => <a {...p}>{children}</a> }));

function unidad(over: Partial<UnidadComercialAgrupada>): UnidadComercialAgrupada {
  return {
    id: 'L1',
    loteIds: ['L1'],
    nombre: 'Local 1',
    slug: 'local-1',
    giro: 'Otro',
    estado: 'Disponible',
    piso: '1',
    ordenPlano: 1,
    m2Total: 60.35,
    fotos: [],
    descripcion: '',
    esCombinada: false,
    registroPrincipal: {} as any,
    todosRegistros: [],
    ...over,
  };
}

describe('PanelLateral', () => {
  it('renders nothing when unidad is null', () => {
    const { container } = render(<PanelLateral unidad={null} onClose={() => {}} />);
    expect(container.querySelector('[role="dialog"]')).toBeNull();
  });

  it('shows the unidad nombre when open', () => {
    render(<PanelLateral unidad={unidad({ nombre: 'Café Aria', estado: 'Ocupado' })} onClose={() => {}} />);
    expect(screen.getByRole('dialog')).toBeInTheDocument();
    expect(screen.getByText('Café Aria')).toBeInTheDocument();
  });

  it('calls onClose when X is clicked', () => {
    const onClose = vi.fn();
    render(<PanelLateral unidad={unidad({})} onClose={onClose} />);
    fireEvent.click(screen.getByLabelText('Cerrar'));
    expect(onClose).toHaveBeenCalled();
  });

  it('calls onClose on Escape', () => {
    const onClose = vi.fn();
    render(<PanelLateral unidad={unidad({})} onClose={onClose} />);
    fireEvent.keyDown(document, { key: 'Escape' });
    expect(onClose).toHaveBeenCalled();
  });

  it('shows Disponible content for Disponible units', () => {
    render(<PanelLateral unidad={unidad({ estado: 'Disponible', m2Total: 60.35 })} onClose={() => {}} />);
    expect(screen.getByText(/Superficie/i)).toBeInTheDocument();
    expect(screen.getByText(/60\.35 m²/)).toBeInTheDocument();
  });

  it('shows Ocupado content for Ocupado units', () => {
    render(<PanelLateral unidad={unidad({ estado: 'Ocupado', giro: 'Restaurante' })} onClose={() => {}} />);
    expect(screen.getByText('Restaurante')).toBeInTheDocument();
    expect(screen.getByText(/Ver ficha completa/i)).toBeInTheDocument();
  });

  it('shows Proximamente content for upcoming units', () => {
    render(<PanelLateral unidad={unidad({ estado: 'Proximamente' })} onClose={() => {}} />);
    expect(screen.getByText(/Próximamente/i)).toBeInTheDocument();
  });
});
