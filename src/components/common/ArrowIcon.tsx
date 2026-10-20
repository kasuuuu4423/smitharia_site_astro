type Direction = 'upRight' | 'right' | 'left' | 'down';

const arrowPaths: Record<Direction, string> = {
  upRight: 'M5 19 19 5M5 5h14v14',
  right: 'M4 12h16m-7-7 7 7-7 7',
  left: 'M20 12H4m7-7-7 7 7 7',
  down: 'M12 4v16m-7-7 7 7 7-7',
};

/** Decorative arrow; the enclosing link or button supplies the accessible name. */
const ArrowIcon = ({ direction = 'upRight' }: { direction?: Direction }) => (
  <svg
    xmlns="http://www.w3.org/2000/svg"
    viewBox="0 0 24 24"
    width="1em"
    height="1em"
    fill="none"
    stroke="currentColor"
    strokeWidth="1.5"
    strokeLinecap="round"
    strokeLinejoin="round"
    aria-hidden="true"
    focusable="false"
    style={{ display: 'inline-block', verticalAlign: '-0.125em', flexShrink: 0 }}
  >
    <path d={arrowPaths[direction]} />
  </svg>
);

export default ArrowIcon;
