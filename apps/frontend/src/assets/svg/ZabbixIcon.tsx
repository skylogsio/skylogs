export default function ZabbixIcon({ size }: { size: string | number }) {
  return (
    <svg
      width={size}
      height={size}
      style={{ position: "relative" }}
      viewBox="0 0 200 200"
      xmlns="http://www.w3.org/2000/svg"
    >
      <rect width="200" height="200" fill="#D32F2F" />
      <text
        x="50%"
        y="50%"
        fontFamily="Arial, sans-serif"
        fontSize="150"
        fontWeight="bold"
        fill="white"
        textAnchor="middle"
        dominantBaseline="middle"
        // alignmentBaseline="central"
        dy="1rem"
      >
        Z
      </text>
    </svg>
  );
}
