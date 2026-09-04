export function isEmptyFilterValue(value: unknown): boolean {
  return (
    value === undefined ||
    value === null ||
    value === "" ||
    (Array.isArray(value) && value.length === 0)
  );
}

export function cleanFilters(filter: Record<string, unknown>): Record<string, unknown> {
  return Object.entries(filter).reduce(
    (acc, [key, value]) => {
      if (!isEmptyFilterValue(value)) {
        acc[key] = value;
      }
      return acc;
    },
    {} as Record<string, unknown>
  );
}

export function omitFilterKeys(
  filter: Record<string, unknown>,
  keys: string[] = []
): Record<string, unknown> {
  if (keys.length === 0) return filter;
  const excluded = new Set(keys);
  return Object.fromEntries(Object.entries(filter).filter(([key]) => !excluded.has(key)));
}

export function countActiveFilters(
  filter: Record<string, unknown>,
  excludeKeys: string[] = []
): number {
  return Object.keys(cleanFilters(omitFilterKeys(filter, excludeKeys))).length;
}

export function filtersToSearchParams(filters: Record<string, unknown>): string {
  const cleaned = cleanFilters(filters);
  const params = new URLSearchParams();

  Object.entries(cleaned).forEach(([key, value]) => {
    if (Array.isArray(value)) {
      params.append(key, value.join(","));
    } else {
      params.append(key, String(value));
    }
  });

  return params.toString();
}

export function parseFiltersFromUrl(filterParam: string | null): Record<string, unknown> {
  if (!filterParam) return {};
  try {
    return JSON.parse(decodeURIComponent(filterParam)) as Record<string, unknown>;
  } catch (error) {
    console.error("Error parsing filters from URL:", error);
    return {};
  }
}

export function areFiltersEqual(a: Record<string, unknown>, b: Record<string, unknown>): boolean {
  const cleanA = cleanFilters(a);
  const cleanB = cleanFilters(b);
  const keysA = Object.keys(cleanA).sort();
  const keysB = Object.keys(cleanB).sort();

  if (keysA.length !== keysB.length) return false;
  if (keysA.some((key, index) => key !== keysB[index])) return false;

  return keysA.every((key) => {
    const valueA = cleanA[key];
    const valueB = cleanB[key];

    if (Array.isArray(valueA) && Array.isArray(valueB)) {
      if (valueA.length !== valueB.length) return false;
      return valueA.every((item, index) => String(item) === String(valueB[index]));
    }

    return String(valueA) === String(valueB);
  });
}

export function formatFilterValue(value: unknown): string {
  if (Array.isArray(value)) {
    return value.map(String).join(", ");
  }
  if (typeof value === "boolean") {
    return value ? "Yes" : "No";
  }
  return String(value);
}

export function isBuiltInToolbarAction(action: unknown): action is "search" | "filter" | "create" {
  return action === "search" || action === "filter" || action === "create";
}
