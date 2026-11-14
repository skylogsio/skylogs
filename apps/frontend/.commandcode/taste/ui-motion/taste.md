# UI Motion / Animation Preferences

- For tab/segment switch toggles, prefers a glass-pill container with a single moving thumb implemented via framer-motion `layoutId` (animates position between options). Active thumb uses the primary gradient. Respect `useReducedMotion` (skip motion span, render a static thumb instead). Confidence: 0.9
- When multiple views live on the same page (not separate routes), prefers local `useState` for tab/segment switching rather than URL query params. URL persistence is reserved for deeper drill-downs (e.g., detail-page tabs). Confidence: 0.8
