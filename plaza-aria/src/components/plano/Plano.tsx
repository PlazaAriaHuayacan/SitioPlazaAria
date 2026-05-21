import { Edificio } from './svg/Edificio';
import { Decoraciones } from './svg/Decoraciones';

export function Plano() {
  return (
    <div className="w-full overflow-x-auto">
      <svg
        viewBox="0 0 1400 800"
        xmlns="http://www.w3.org/2000/svg"
        className="w-full h-auto max-w-page"
        role="img"
        aria-label="Plano isométrico de Plaza Aria"
      >
        <Decoraciones />
        <Edificio />
      </svg>
    </div>
  );
}
