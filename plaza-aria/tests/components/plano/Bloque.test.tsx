import { describe, it, expect, vi } from 'vitest';
import { render, screen, fireEvent } from '@testing-library/react';
import { Bloque } from '@/components/plano/Bloque';
import type { BloqueLayout } from '@/lib/plano/geometry';
import type { UnidadComercialAgrupada } from '@/lib/plano/agrupar';

function makeUnidad(over: Partial<UnidadComercialAgrupada>): UnidadComercialAgrupada {
  return {
    id: over.id ?? 'L1',
    loteIds: over.loteIds ?? ['L1'],
    nombre: over.nombre ?? 'Local',
    slug: over.slug ?? 'local',
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

function bloqueLayoutFor(unidad: UnidadComercialAgrupada): Extract<BloqueLayout, { type: 'bloque' }> {
  return { type: 'bloque', unidad, x: 100, y: 0, ancho: 80, alto: 140 };
}

function renderBloque(props: Partial<React.ComponentProps<typeof Bloque>> = {}) {
  const unidad = props.layout?.unidad ?? makeUnidad({});
  const layout = props.layout ?? bloqueLayoutFor(unidad);
  return render(
    <svg>
      <Bloque
        layout={layout}
        yOffset={0}
        onClick={vi.fn()}
        onHoverChange={vi.fn()}
        {...props}
      />
    </svg>,
  );
}

describe('Bloque', () => {
  it('renders with role=button and the unidad nombre in aria-label', () => {
    const unidad = makeUnidad({ id: 'L1', nombre: 'Café Aria', estado: 'Ocupado' });
    renderBloque({ layout: bloqueLayoutFor(unidad) });
    const btn = screen.getByRole('button');
    expect(btn.getAttribute('aria-label')).toContain('Café Aria');
    expect(btn.getAttribute('aria-label')).toContain('Ocupado');
  });

  it('renders the DISPONIBLE badge for available units', () => {
    const unidad = makeUnidad({ estado: 'Disponible' });
    renderBloque({ layout: bloqueLayoutFor(unidad) });
    expect(screen.getByText('DISPONIBLE')).toBeInTheDocument();
  });

  it('renders the PRÓXIMAMENTE badge for upcoming units', () => {
    const unidad = makeUnidad({ estado: 'Proximamente' });
    renderBloque({ layout: bloqueLayoutFor(unidad) });
    expect(screen.getByText('PRÓXIMAMENTE')).toBeInTheDocument();
  });

  it('does not show DISPONIBLE badge for occupied units', () => {
    const unidad = makeUnidad({ estado: 'Ocupado' });
    renderBloque({ layout: bloqueLayoutFor(unidad) });
    expect(screen.queryByText('DISPONIBLE')).not.toBeInTheDocument();
  });

  it('calls onClick with the unidad id when clicked', () => {
    const onClick = vi.fn();
    const unidad = makeUnidad({ id: 'L6-7' });
    renderBloque({ layout: bloqueLayoutFor(unidad), onClick });
    fireEvent.click(screen.getByRole('button'));
    expect(onClick).toHaveBeenCalledWith('L6-7');
  });

  it('calls onClick when Enter or Space is pressed', () => {
    const onClick = vi.fn();
    const unidad = makeUnidad({ id: 'L1' });
    renderBloque({ layout: bloqueLayoutFor(unidad), onClick });
    const btn = screen.getByRole('button');
    fireEvent.keyDown(btn, { key: 'Enter' });
    expect(onClick).toHaveBeenCalledTimes(1);
    fireEvent.keyDown(btn, { key: ' ' });
    expect(onClick).toHaveBeenCalledTimes(2);
  });

  it('calls onHoverChange with id on mouse enter and null on leave', () => {
    const onHoverChange = vi.fn();
    const unidad = makeUnidad({ id: 'L1' });
    renderBloque({ layout: bloqueLayoutFor(unidad), onHoverChange });
    const btn = screen.getByRole('button');
    fireEvent.mouseEnter(btn);
    expect(onHoverChange).toHaveBeenLastCalledWith('L1');
    fireEvent.mouseLeave(btn);
    expect(onHoverChange).toHaveBeenLastCalledWith(null);
  });

  it('renders an image when the unidad has a logo URL', () => {
    const unidad = makeUnidad({ logo: { url: 'https://example.com/logo.png' } });
    const { container } = renderBloque({ layout: bloqueLayoutFor(unidad) });
    const img = container.querySelector('image[href="https://example.com/logo.png"]');
    expect(img).not.toBeNull();
  });

  it('renders the text label when no logo is available', () => {
    const unidad = makeUnidad({ nombre: 'Onigiri', logo: undefined });
    renderBloque({ layout: bloqueLayoutFor(unidad) });
    // Label may be truncated; just check the first chars appear
    const txt = screen.getByText(/Onigiri/);
    expect(txt).toBeInTheDocument();
  });
});
