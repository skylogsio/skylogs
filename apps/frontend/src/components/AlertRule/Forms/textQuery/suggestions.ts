export type TriggerContext =
  | {
      mode: "key";
      start: number;
      end: number;
      filter: string;
    }
  | {
      mode: "value";
      start: number;
      end: number;
      key: string;
      filter: string;
    };

export function getTriggerContext(text: string, caret: number): TriggerContext | null {
  if (caret < 0 || caret > text.length) {
    return null;
  }

  const before = text.slice(0, caret);

  // Scan backwards to find the start of the current term (stopped by whitespace/operators outside quotes)
  let inQuotes = false;
  let termStart = 0;

  for (let i = before.length - 1; i >= 0; i -= 1) {
    const ch = before[i];
    if (ch === '"') {
      inQuotes = !inQuotes;
      continue;
    }
    if (!inQuotes && /[\s()&|!]/.test(ch)) {
      termStart = i + 1;
      break;
    }
  }

  const term = before.slice(termStart);
  const colonIdx = term.indexOf(":");

  if (colonIdx === -1) {
    if (term === "" || /^[a-zA-Z0-9_.-]+$/.test(term)) {
      return {
        mode: "key",
        start: termStart,
        end: caret,
        filter: term
      };
    }
    return null;
  }

  const key = term.slice(0, colonIdx);
  const rawFilter = term.slice(colonIdx + 1);

  if (!key) {
    // Bare ":" -> show list of available keys
    return {
      mode: "key",
      start: termStart,
      end: caret,
      filter: rawFilter
    };
  }

  if (!/^[a-zA-Z0-9_.-]+$/.test(key)) {
    return null;
  }

  const filter = rawFilter.startsWith('"') ? rawFilter.slice(1) : rawFilter;

  return {
    mode: "value",
    start: termStart,
    end: caret,
    key,
    filter
  };
}

export function formatValueToken(key: string, value: string): string {
  const needsQuotes = /[\s()&|!"]/.test(value);
  const formatted = needsQuotes ? `"${value.replace(/"/g, '\\"')}"` : value;
  return `${key}:${formatted}`;
}

export function filterSuggestions<T>(
  items: readonly T[],
  filter: string,
  getLabel: (item: T) => string,
  mode: "prefix" | "includes" = "includes"
): T[] {
  const normalized = filter.trim().toLowerCase();
  if (!normalized) {
    return [...items];
  }

  return items.filter((item) => {
    const label = getLabel(item).toLowerCase();
    return mode === "prefix" ? label.startsWith(normalized) : label.includes(normalized);
  });
}
