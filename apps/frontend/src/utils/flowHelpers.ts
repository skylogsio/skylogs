import { IFlowStep, TimeUnit } from "@/@types/flow";

export function formatDuration(
  duration: number | undefined,
  timeUnit: TimeUnit | undefined
): string {
  if (!duration || !timeUnit) return "0s";

  switch (timeUnit) {
    case "s":
      return `${duration}s`;
    case "m":
      return `${Math.floor(duration / 60)}m ${duration % 60}s`;
    case "h":
      const hours = Math.floor(duration / 3600);
      const minutes = Math.floor((duration % 3600) / 60);
      const seconds = duration % 60;
      return `${hours}h ${minutes}m ${seconds}s`;
    default:
      return `${duration}${timeUnit}`;
  }
}

export function parseDurationInput(input: string, timeUnit: TimeUnit): number {
  if (timeUnit === "s") {
    return parseInt(input) || 0;
  }

  const parts = input.split(":").map((part) => parseInt(part) || 0);

  if (timeUnit === "m") {
    // Format: MM:SS
    return (parts[0] || 0) * 60 + (parts[1] || 0);
  } else if (timeUnit === "h") {
    // Format: HH:MM:SS
    return (parts[0] || 0) * 3600 + (parts[1] || 0) * 60 + (parts[2] || 0);
  }

  return 0;
}

export function formatDurationForInput(
  duration: number | undefined,
  timeUnit: TimeUnit | undefined
): string {
  if (!duration || !timeUnit) return "";

  if (timeUnit === "s") {
    return duration.toString();
  } else if (timeUnit === "m") {
    const minutes = Math.floor(duration / 60);
    const seconds = duration % 60;
    return `${minutes.toString().padStart(2, "0")}:${seconds.toString().padStart(2, "0")}`;
  } else if (timeUnit === "h") {
    const hours = Math.floor(duration / 3600);
    const minutes = Math.floor((duration % 3600) / 60);
    const seconds = duration % 60;
    return `${hours.toString().padStart(2, "0")}:${minutes.toString().padStart(2, "0")}:${seconds.toString().padStart(2, "0")}`;
  }

  return "";
}

export function validateFlowSteps(steps: IFlowStep[]): string[] {
  const errors: string[] = [];

  steps.forEach((step, index) => {
    if (step.type === "wait") {
      if (!step.duration || step.duration <= 0) {
        errors.push(`Step ${index + 1}: Wait duration must be greater than 0`);
      }
      if (!step.timeUnit) {
        errors.push(`Step ${index + 1}: Time unit is required for wait steps`);
      }
    } else if (step.type === "endpoint") {
      if (!step.endpointIds || step.endpointIds.length === 0) {
        errors.push(`Step ${index + 1}: At least one endpoint must be selected`);
      }
    }
  });

  return errors;
}
