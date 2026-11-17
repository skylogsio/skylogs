import { keyframes } from "@mui/material/styles";

const pulseCache = new Map<string, ReturnType<typeof keyframes>>();

export function createPulseKeyframes(color: string) {
  let cached = pulseCache.get(color);
  if (!cached) {
    cached = keyframes`
      0% {
        box-shadow: 0 0 0 0 color-mix(in srgb, ${color} 70%, transparent);
      }
      70% {
        box-shadow: 0 0 0 5px color-mix(in srgb, ${color} 0%, transparent);
      }
      100% {
        box-shadow: 0 0 0 0 color-mix(in srgb, ${color} 0%, transparent);
      }
    `;
    pulseCache.set(color, cached);
  }
  return cached;
}

export function createPulseAnimation(color: string, duration = "2s") {
  const kf = createPulseKeyframes(color);
  return `${kf} ${duration} infinite ease-in-out`;
}
