"use client";

import {
  createContext,
  useContext,
  useRef,
  useSyncExternalStore,
  type PropsWithChildren
} from "react";

type Listener = () => void;

/**
 * Plain (non-React-state) store. Mutating this and notifying listeners does
 * NOT re-render every subscriber — useSyncExternalStore only re-renders a
 * given subscriber if ITS getSnapshot() return value actually changed.
 * This lets us highlight a dot across 200+ bars while only touching the
 * ~2 dots (previous + next hovered index) that actually flip state.
 */
class ControlBarStore {
  private hoveredIndex: number | null = null;
  private listeners = new Set<Listener>();

  subscribe = (listener: Listener): (() => void) => {
    this.listeners.add(listener);
    return () => this.listeners.delete(listener);
  };

  setHoveredIndex = (index: number): void => {
    if (this.hoveredIndex === index) return;
    this.hoveredIndex = index;
    this.emit();
  };

  clearHoveredIndex = (): void => {
    if (this.hoveredIndex === null) return;
    this.hoveredIndex = null;
    this.emit();
  };

  isHovered = (index: number): boolean => this.hoveredIndex === index;

  private emit(): void {
    this.listeners.forEach((listener) => listener());
  }
}

const ControlBarContext = createContext<ControlBarStore | undefined>(undefined);

export function ControlBarProvider({ children }: PropsWithChildren) {
  const storeRef = useRef<ControlBarStore>();
  if (!storeRef.current) {
    storeRef.current = new ControlBarStore();
  }

  return (
    <ControlBarContext.Provider value={storeRef.current}>{children}</ControlBarContext.Provider>
  );
}

function useControlBarStore(): ControlBarStore {
  const store = useContext(ControlBarContext);
  if (!store) {
    throw new Error("useControlBarStore must be used within a ControlBarProvider");
  }
  return store;
}

/**
 * Subscribe to whether THIS specific dot index is the hovered one.
 * Only re-renders the component when its own boolean flips.
 */
export function useIsSegmentHovered(index: number): boolean {
  const store = useControlBarStore();
  return useSyncExternalStore(
    store.subscribe,
    () => store.isHovered(index),
    () => false
  );
}

/**
 * Stable setter/clearer, doesn't cause re-renders on its own.
 */
export function useControlBarActions() {
  const store = useControlBarStore();
  return { setHoveredIndex: store.setHoveredIndex, clearHoveredIndex: store.clearHoveredIndex };
}
