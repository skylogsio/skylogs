/** Append a JSON-serializable value onto FormData with Laravel-style bracket keys. */
export function appendFormValue(formData: FormData, key: string, value: unknown): void {
  if (value === undefined || value === null) return;

  if (typeof File !== "undefined" && value instanceof File) {
    formData.append(key, value);
    return;
  }

  if (Array.isArray(value)) {
    value.forEach((item, index) => {
      appendFormValue(formData, `${key}[${index}]`, item);
    });
    return;
  }

  if (typeof value === "object") {
    Object.entries(value as Record<string, unknown>).forEach(([childKey, childValue]) => {
      appendFormValue(formData, `${key}[${childKey}]`, childValue);
    });
    return;
  }

  if (typeof value === "boolean") {
    formData.append(key, value ? "1" : "0");
    return;
  }

  formData.append(key, String(value));
}

export function objectToFormData(payload: Record<string, unknown>): FormData {
  const formData = new FormData();
  Object.entries(payload).forEach(([key, value]) => {
    appendFormValue(formData, key, value);
  });
  return formData;
}
