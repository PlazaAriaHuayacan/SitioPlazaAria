import { describe, it, expect, vi } from 'vitest';
import { render, screen, fireEvent } from '@testing-library/react';
import { PlanoInteractivo } from '@/components/plano/PlanoInteractivo';
import type { UnidadComercialAgrupada } from '@/lib/plano/agrupar';

function makeUnidad(over: Partial<UnidadComercialAgrupada>): UnidadComercialAgrupada {
  return {
    id: over.id ?? 'L1',
    loteIds: over.loteIds ?? ['L1'],
    nombre: over.nombre ?? 'Local 1',
    slug: over.slug ?? 'local-1',
    giro: over.giro ?? 'Otro',
    estado: over.estado ?? 'Disponible',
    piso: over.piso ?? '1',
    ordenPlano: over.ordenPlano ?? 1,
    m2Total: over.m2Total ?? 60.35,
    fotos: [],
    descripcion: '',
    esCombinada: false,
    registroPrincipal: {} as any,
    todosRegistros: [],
    ...over,
  };
}

const sampleUnidades = [
  ...Array.from({ length: 6 }, (_, i) =>
    makeUnidad({ id: `P1-${i + 1}`, ordenPlano: i + 1, piso: '1' }),
  ),
  ...Array.from({ length: 6 }, (_, i) =>
    makeUnidad({ id: `P2-${i + 1}`, ordenPlano: i + 1, piso: '2' }),
  ),
];

describe('PlanoInteractivo', () => {
  it('renders both piso toggle buttons', () => {
    render(<PlanoInteractivo unidades={sampleUnidades} />);
    expect(screen.getByRole('tab', { name: 'Piso 1' })).toBeInTheDocument();
    expect(screen.getByRole('tab', { name: 'Piso 2' })).toBeInTheDocument();
  });

  it('shows Piso 1 unidades by default', () => {
    render(<PlanoInteractivo unidades={sampleUnidades} />);
    expect(screen.getByTestId('bloque-P1-1')).toBeInTheDocument();
    expect(screen.queryByTestId('bloque-P2-1')).not.toBeInTheDocument();
  });

  it('switches to Piso 2 when its tab is clicked', () => {
    render(<PlanoInteractivo unidades={sampleUnidades} />);
    fireEvent.click(screen.getByRole('tab', { name: 'Piso 2' }));
    // Piso 2 bloques are now mounted; Piso 1 may still be present (AnimatePresence exit)
    expect(screen.getByTestId('bloque-P2-1')).toBeInTheDocument();
    // Piso 2 tab should be selected
    expect(screen.getByRole('tab', { name: 'Piso 2' })).toHaveAttribute('aria-selected', 'true');
  });

  it('calls onUnidadSelect when a bloque is clicked', () => {
    const onSelect = vi.fn();
    render(<PlanoInteractivo unidades={sampleUnidades} onUnidadSelect={onSelect} />);
    fireEvent.click(screen.getByTestId('bloque-P1-1'));
    expect(onSelect).toHaveBeenCalledWith('P1-1');
  });

  it('renders correctly with empty unidades array', () => {
    render(<PlanoInteractivo unidades={[]} />);
    // Toggle still shows
    expect(screen.getByRole('tab', { name: 'Piso 1' })).toBeInTheDocument();
    // No bloques
    expect(screen.queryByTestId(/^bloque-/)).not.toBeInTheDocument();
  });
});
