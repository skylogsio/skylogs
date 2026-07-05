"use client";

import {
  createContext,
  useContext,
  useEffect,
  useRef,
  useSyncExternalStore,
  type PropsWithChildren
} from "react";

type Listener = () => void;

interface SelectionRange {
  start: number;
  end: number;
}

class ControlBarStore {
  private hoveredIndex: number | null = null;
  private selection: SelectionRange | null = null;
  private isSelecting = false;
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

  startSelection = (index: number): void => {
    this.isSelecting = true;
    this.selection = { start: index, end: index };
    this.emit();
  };

  updateSelection = (index: number): void => {
    if (!this.isSelecting || !this.selection) return;
    if (this.selection.end === index) return;
    this.selection = { start: this.selection.start, end: index };
    this.emit();
  };

  endSelection = (): void => {
    if (!this.isSelecting) return;
    this.isSelecting = false;
    this.emit();
  };

  clearSelection = (): void => {
    if (!this.selection) return;
    this.selection = null;
    this.isSelecting = false;
    this.emit();
  };

  getSelectionRange = (): SelectionRange | null => this.selection;

  getIsSelecting = (): boolean => this.isSelecting;

  isSelected = (index: number): boolean => {
    if (!this.selection) return false;
    const lo = Math.min(this.selection.start, this.selection.end);
    const hi = Math.max(this.selection.start, this.selection.end);
    return index >= lo && index <= hi;
  };

  private emit(): void {
    this.listeners.forEach((listener) => listener());
  }
}

const ControlBarContext = createContext<ControlBarStore | undefined>(undefined);

export function ControlBarProvider({ children }: PropsWithChildren) {
  const storeRef = useRef<ControlBarStore>(undefined);
  if (!storeRef.current) {
    storeRef.current = new ControlBarStore();
  }

  useEffect(() => {
    const store = storeRef.current!;
    const handlePointerUp = () => store.endSelection();
    window.addEventListener("mouseup", handlePointerUp);
    return () => window.removeEventListener("mouseup", handlePointerUp);
  }, []);

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

export function useIsSegmentHovered(index: number): boolean {
  const store = useControlBarStore();
  return useSyncExternalStore(
    store.subscribe,
    () => store.isHovered(index),
    () => false
  );
}

export function useIsSegmentSelected(index: number): boolean {
  const store = useControlBarStore();
  return useSyncExternalStore(
    store.subscribe,
    () => store.isSelected(index),
    () => false
  );
}

export function useSelectionRange(): SelectionRange | null {
  const store = useControlBarStore();
  return useSyncExternalStore(
    store.subscribe,
    () => store.getSelectionRange(),
    () => null
  );
}

export function useControlBarActions() {
  const store = useControlBarStore();
  return {
    setHoveredIndex: store.setHoveredIndex,
    clearHoveredIndex: store.clearHoveredIndex,
    startSelection: store.startSelection,
    updateSelection: store.updateSelection,
    endSelection: store.endSelection,
    clearSelection: store.clearSelection
  };
}
