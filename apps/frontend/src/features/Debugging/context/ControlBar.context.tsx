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

interface SelectionTimeRange {
  start: number;
  end: number;
}

interface SelectionAnchor {
  index: number;
  startTime: number;
  endTime: number;
}

class ControlBarStore {
  private hoveredIndex: number | null = null;
  private selection: SelectionRange | null = null;
  private selectionAnchor: SelectionAnchor | null = null;
  private selectionTimeRange: SelectionTimeRange | null = null;
  private isSelecting = false;
  private listeners = new Set<Listener>();
  private selectionEndListeners = new Set<(range: SelectionTimeRange) => void>();

  subscribe = (listener: Listener): (() => void) => {
    this.listeners.add(listener);
    return () => this.listeners.delete(listener);
  };

  onSelectionEnd = (listener: (range: SelectionTimeRange) => void): (() => void) => {
    this.selectionEndListeners.add(listener);
    return () => this.selectionEndListeners.delete(listener);
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

  startSelection = (index: number, startTime: number, endTime: number): void => {
    this.isSelecting = true;
    this.selectionAnchor = { index, startTime, endTime };
    this.selection = { start: index, end: index };
    this.selectionTimeRange = { start: startTime, end: endTime };
    this.emit();
  };

  updateSelection = (index: number, startTime: number, endTime: number): void => {
    if (!this.isSelecting || !this.selection || !this.selectionAnchor) return;
    if (this.selection.end === index) return;

    const isForward = index >= this.selectionAnchor.index;
    this.selection = { start: this.selectionAnchor.index, end: index };
    this.selectionTimeRange = isForward
      ? { start: this.selectionAnchor.startTime, end: endTime }
      : { start: startTime, end: this.selectionAnchor.endTime };
    this.emit();
  };

  endSelection = (): void => {
    if (!this.isSelecting) return;
    this.isSelecting = false;
    const range = this.selectionTimeRange;
    this.emit();
    if (range) {
      this.selectionEndListeners.forEach((listener) => listener(range));
    }
  };

  clearSelection = (): void => {
    if (!this.selection) return;
    this.selection = null;
    this.selectionAnchor = null;
    this.selectionTimeRange = null;
    this.isSelecting = false;
    this.emit();
  };

  getSelectionRange = (): SelectionRange | null => this.selection;

  getSelectionTimeRange = (): SelectionTimeRange | null => this.selectionTimeRange;

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

export function useSelectionTimeRange(): SelectionTimeRange | null {
  const store = useControlBarStore();
  return useSyncExternalStore(
    store.subscribe,
    () => store.getSelectionTimeRange(),
    () => null
  );
}

export function useIsSelecting(): boolean {
  const store = useControlBarStore();
  return useSyncExternalStore(
    store.subscribe,
    () => store.getIsSelecting(),
    () => false
  );
}

export function useOnSelectionEnd(callback: (range: SelectionTimeRange) => void): void {
  const store = useControlBarStore();
  const callbackRef = useRef(callback);
  callbackRef.current = callback;

  useEffect(() => {
    return store.onSelectionEnd((range) => callbackRef.current(range));
  }, [store]);
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
