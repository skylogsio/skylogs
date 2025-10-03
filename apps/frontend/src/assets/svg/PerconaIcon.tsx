export default function PerconaIcon({ size }: { size: string | number }) {
  return (
    <svg xmlns="http://www.w3.org/2000/svg" width={size} height={size} viewBox="0 0 300 200">
      <defs>
        <style>{".cls-1{fill:url(#linear-gradient);}"}</style>
        <linearGradient
          id="linear-gradient"
          x1="55.63"
          y1="-28.22"
          x2="257.29"
          y2="173.27"
          gradientTransform="translate(0 237.3) scale(1 -1)"
          gradientUnits="userSpaceOnUse"
        >
          <stop offset="0" stopColor="#fc3519" />
          <stop offset="1" stopColor="#f0d136" />
        </linearGradient>
      </defs>
      <path
        className="cls-1"
        d="m221.18,148.08c28.96-18.95,38.44-57.6,20.91-87.97-8.78-15.24-22.98-26.15-39.96-30.7-15.73-4.23-32.17-2.5-46.63,4.81L135.75,0l-40.99,71.06L0,235.3h271.5l-50.32-87.22Zm-23.69-101.27c12.34,3.29,22.62,11.22,29.02,22.27,12.58,21.78,6.04,49.39-14.35,63.34l-47.64-82.57c10.26-4.94,21.84-6.02,32.97-3.05Zm-61.74-10.74l104.53,181.2h-61.16L105.16,89.09l30.59-53.02h0ZM31.22,217.26l63.53-110.1,63.53,110.1H31.22Z"
      />
    </svg>
  );
}
