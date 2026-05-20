export const rawLocalOcupado = {
  id: 'recOC001',
  fields: {
    Nombre: 'Café Aria',
    Slug: 'cafe-aria',
    Giro: 'Restaurante',
    Estado: 'Ocupado',
    Piso: '1',
    NumeroLocal: 'A-12',
    CoordX: 100, CoordY: 200, Ancho: 50, Alto: 40,
    M2: 60, Frente: 8, Renta: 35000, Mantenimiento: 3000,
    Fotos: [{ url: 'https://x/photo.jpg', width: 1200, height: 800 }],
    Logo: [{ url: 'https://x/logo.png' }],
    Descripcion: 'Café especialidad y postres.',
    HorarioLunes: '08:00-20:00',
    HorarioMartes: '08:00-20:00',
    HorarioMiercoles: '08:00-20:00',
    HorarioJueves: '08:00-20:00',
    HorarioViernes: '08:00-22:00',
    HorarioSabado: '08:00-22:00',
    HorarioDomingo: '',
    Whatsapp: '+529981234567',
    Instagram: 'cafe_aria',
    Instalaciones: ['Agua', 'Luz', 'A/C', 'Drenaje'],
  },
};

export const rawLocalDisponible = {
  id: 'recDP001',
  fields: {
    Nombre: 'DISPONIBLE',
    Giro: 'Otro',
    Estado: 'Disponible',
    Piso: '2',
    NumeroLocal: 'B-04',
    M2: 45, Frente: 6, Renta: 28000, Mantenimiento: 2500,
    Fotos: [{ url: 'https://x/empty.jpg' }],
    Descripcion: 'Local en planta alta con vista a la plaza central.',
    HorarioLunes: '', HorarioMartes: '', HorarioMiercoles: '',
    HorarioJueves: '', HorarioViernes: '', HorarioSabado: '', HorarioDomingo: '',
    Instalaciones: ['Agua', 'Luz'],
  },
};

export const rawEvento = {
  id: 'recEV001',
  fields: {
    Titulo: 'Clase de spinning',
    Local: ['recOC001'],
    Tipo: 'Fitness',
    Recurrencia: 'Semanal',
    Fecha: '2026-05-25',
    HoraInicio: '07:00',
    HoraFin: '08:00',
    Cupo: 12,
    Descripcion: 'Spinning intermedio.',
    Foto: [{ url: 'https://x/spin.jpg' }],
    LinkReservar: 'https://wa.me/529981234567',
  },
};

export const rawConfig = {
  id: 'recCFG001',
  fields: {
    HeroVideoURL: 'https://x/hero.mp4',
    FotosGenerales: [{ url: 'https://x/g1.jpg' }, { url: 'https://x/g2.jpg' }],
    GapsGiros: 'Panadería, Cafetería, Farmacia',
    AforoEstimado: 8000,
    CajonesEstacionamiento: 120,
    Demografia: 'Ingreso medio-alto. Familias jóvenes.',
  },
};
