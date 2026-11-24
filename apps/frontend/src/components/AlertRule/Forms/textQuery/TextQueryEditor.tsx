"use client";

import {
  type ChangeEvent,
  type KeyboardEvent,
  useCallback,
  useEffect,
  useId,
  useLayoutEffect,
  useMemo,
  useRef,
  useState
} from "react";

import {
  Box,
  CircularProgress,
  ClickAwayListener,
  List,
  ListItemButton,
  ListItemText,
  Paper,
  Popper,
  Stack,
  TextField,
  Typography
} from "@mui/material";
import { useQuery } from "@tanstack/react-query";

import {
  getAlertRuleDataSourcesByAlertType,
  getAlertRuleLabels,
  getAlertRuleLabelValues,
  getDataSourceAlertName
} from "@/api/alertRule";
import type { DataSourceType } from "@/features/DataSource/dataSource.utils";

import {
  filterSuggestions,
  formatValueToken,
  getTriggerContext,
  type TriggerContext
} from "./suggestions";

export interface TextQueryEditorProps {
  value: string;
  onChange: (value: string) => void;
  error?: boolean;
  helperText?: string;
  type: Extract<DataSourceType, "prometheus" | "pmm" | "grafana">;
  alertId?: string;
}

interface SuggestionOption {
  id: string;
  label: string;
  kind: "key" | "value";
}

export default function TextQueryEditor({
  value,
  onChange,
  error,
  helperText,
  type
}: TextQueryEditorProps) {
  const listboxId = useId();
  const inputRef = useRef<HTMLTextAreaElement | null>(null);
  const mirrorRef = useRef<HTMLDivElement | null>(null);
  const caretAnchorRef = useRef<HTMLSpanElement | null>(null);
  const optionRefs = useRef<Array<HTMLDivElement | null>>([]);

  const [caret, setCaret] = useState<number>(0);
  const [isFocused, setIsFocused] = useState(false);
  const [dismissedTriggerKey, setDismissedTriggerKey] = useState<string | null>(null);
  const [highlightedIndex, setHighlightedIndex] = useState<number>(0);

  const trigger: TriggerContext | null = useMemo(() => {
    if (!isFocused) {
      return null;
    }
    return getTriggerContext(value, caret);
  }, [isFocused, value, caret]);

  const triggerKey = trigger
    ? `${trigger.mode}:${trigger.start}:${trigger.mode === "value" ? trigger.key : ""}:${trigger.filter}`
    : null;

  const isOpen = Boolean(trigger && triggerKey !== dismissedTriggerKey);
  const activeKey = trigger?.mode === "value" ? trigger.key : null;

  const isDataSourceKey =
    activeKey === "data-source" ||
    activeKey === "prometheus_instance" ||
    activeKey === "grafana_instance";
  const isAlertNameKey = activeKey === "data-source-alert-name" || activeKey === "alertname";
  const isLabelValueKey = Boolean(activeKey && !isDataSourceKey && !isAlertNameKey);

  const {
    data: dataSourceList,
    isLoading: isLoadingDataSources,
    isError: isErrorDataSources
  } = useQuery({
    queryKey: ["alert-rule-data-source", type],
    queryFn: () => getAlertRuleDataSourcesByAlertType(type),
    enabled: isOpen && isDataSourceKey
  });

  const {
    data: alertRuleNameList,
    isLoading: isLoadingAlertNames,
    isError: isErrorAlertNames
  } = useQuery({
    queryKey: ["all-alert-rule-names", type],
    queryFn: () => getDataSourceAlertName(type),
    enabled: isOpen && isAlertNameKey
  });

  const {
    data: prometheusLabels,
    isLoading: isLoadingLabels,
    isError: isErrorLabels
  } = useQuery({
    queryKey: ["prometheus-label"],
    queryFn: () => getAlertRuleLabels(),
    enabled: isOpen && trigger?.mode === "key",
    retry: 1,
    staleTime: 5 * 60 * 1000
  });

  const {
    data: prometheusLabelValues,
    isLoading: isLoadingLabelValues,
    isError: isErrorLabelValues
  } = useQuery({
    queryKey: ["prometheus-label-value", activeKey],
    queryFn: () => getAlertRuleLabelValues(activeKey as string),
    enabled: isOpen && isLabelValueKey,
    retry: 1,
    staleTime: 5 * 60 * 1000
  });

  const { options, isLoading, isError, emptyMessage } = useMemo((): {
    options: SuggestionOption[];
    isLoading: boolean;
    isError: boolean;
    emptyMessage: string;
  } => {
    if (!trigger) {
      return { options: [], isLoading: false, isError: false, emptyMessage: "" };
    }

    if (trigger.mode === "key") {
      const filtered = filterSuggestions(
        prometheusLabels ?? [],
        trigger.filter,
        (k) => k,
        "includes"
      );
      return {
        options: filtered.map((keyName) => ({
          id: `key-${keyName}`,
          label: keyName,
          kind: "key"
        })),
        isLoading: isLoadingLabels,
        isError: isErrorLabels,
        emptyMessage: "No matching keys."
      };
    }

    if (isDataSourceKey) {
      const filtered = filterSuggestions(dataSourceList ?? [], trigger.filter, (ds) => ds.name);
      return {
        options: filtered.map((ds) => ({
          id: `ds-${ds.id}`,
          label: ds.name,
          kind: "value"
        })),
        isLoading: isLoadingDataSources,
        isError: isErrorDataSources,
        emptyMessage: "No data sources found."
      };
    }

    if (isAlertNameKey) {
      const filtered = filterSuggestions(alertRuleNameList ?? [], trigger.filter, (name) => name);
      return {
        options: filtered.map((name) => ({
          id: `alert-${name}`,
          label: name,
          kind: "value"
        })),
        isLoading: isLoadingAlertNames,
        isError: isErrorAlertNames,
        emptyMessage:
          type !== "prometheus"
            ? "No matching alert names. Continue typing to use a custom name."
            : "No alert names found."
      };
    }

    const filtered = filterSuggestions(prometheusLabelValues ?? [], trigger.filter, (val) => val);
    return {
      options: filtered.map((val) => ({
        id: `val-${val}`,
        label: val,
        kind: "value"
      })),
      isLoading: isLoadingLabelValues,
      isError: isErrorLabelValues,
      emptyMessage: `No values found for "${trigger.key}".`
    };
  }, [
    trigger,
    prometheusLabels,
    isLoadingLabels,
    isErrorLabels,
    isDataSourceKey,
    dataSourceList,
    isLoadingDataSources,
    isErrorDataSources,
    isAlertNameKey,
    alertRuleNameList,
    isLoadingAlertNames,
    isErrorAlertNames,
    type,
    prometheusLabelValues,
    isLoadingLabelValues,
    isErrorLabelValues
  ]);

  useEffect(() => {
    setHighlightedIndex(0);
  }, [triggerKey, options.length]);

  useEffect(() => {
    const activeEl = optionRefs.current[highlightedIndex];
    if (activeEl) {
      activeEl.scrollIntoView({ block: "nearest" });
    }
  }, [highlightedIndex]);

  const syncMirrorLayout = useCallback(() => {
    const textarea = inputRef.current;
    const mirror = mirrorRef.current;
    if (!textarea || !mirror) {
      return;
    }

    const computed = window.getComputedStyle(textarea);
    mirror.style.width = `${textarea.clientWidth}px`;
    mirror.style.fontFamily = computed.fontFamily;
    mirror.style.fontSize = computed.fontSize;
    mirror.style.fontWeight = computed.fontWeight;
    mirror.style.lineHeight = computed.lineHeight;
    mirror.style.letterSpacing = computed.letterSpacing;
    mirror.style.paddingTop = computed.paddingTop;
    mirror.style.paddingRight = computed.paddingRight;
    mirror.style.paddingBottom = computed.paddingBottom;
    mirror.style.paddingLeft = computed.paddingLeft;
    mirror.style.borderTopWidth = computed.borderTopWidth;
    mirror.style.borderLeftWidth = computed.borderLeftWidth;
    mirror.style.boxSizing = computed.boxSizing;
    mirror.style.transform = `translate(${-textarea.scrollLeft}px, ${-textarea.scrollTop}px)`;
  }, []);

  useLayoutEffect(() => {
    syncMirrorLayout();
  }, [value, caret, isOpen, syncMirrorLayout]);

  const applySelection = useCallback(
    (option: SuggestionOption) => {
      if (!trigger) {
        return;
      }

      const replacement =
        option.kind === "key"
          ? `${option.label}:`
          : formatValueToken(trigger.mode === "value" ? trigger.key : "", option.label);

      const nextValue = value.slice(0, trigger.start) + replacement + value.slice(trigger.end);
      const nextCaret = trigger.start + replacement.length;

      setDismissedTriggerKey(null);
      onChange(nextValue);
      setCaret(nextCaret);

      requestAnimationFrame(() => {
        const el = inputRef.current;
        if (el) {
          el.focus();
          el.setSelectionRange(nextCaret, nextCaret);
        }
      });
    },
    [trigger, value, onChange]
  );

  function handleChange(e: ChangeEvent<HTMLTextAreaElement>) {
    const nextVal = e.target.value;
    const nextCaret = e.target.selectionStart ?? nextVal.length;
    setDismissedTriggerKey(null);
    onChange(nextVal);
    setCaret(nextCaret);
  }

  function handleSelectOrClick() {
    const el = inputRef.current;
    if (el) {
      setCaret(el.selectionStart ?? value.length);
    }
  }

  function handleKeyDown(e: KeyboardEvent<HTMLElement>) {
    if (!isOpen) {
      return;
    }

    if (e.key === "Escape") {
      e.preventDefault();
      e.stopPropagation();
      setDismissedTriggerKey(triggerKey);
      return;
    }

    if (options.length === 0) {
      return;
    }

    if (e.key === "ArrowDown") {
      e.preventDefault();
      setHighlightedIndex((prev) => (prev + 1) % options.length);
      return;
    }

    if (e.key === "ArrowUp") {
      e.preventDefault();
      setHighlightedIndex((prev) => (prev - 1 + options.length) % options.length);
      return;
    }

    if (e.key === "Enter" || e.key === "Tab") {
      const selected = options[highlightedIndex];
      if (selected) {
        e.preventDefault();
        applySelection(selected);
      }
    }
  }

  function getOptionId(index: number) {
    return `${listboxId}-option-${index}`;
  }

  const mirrorText = value.slice(0, caret);

  return (
    <ClickAwayListener onClickAway={() => setDismissedTriggerKey(triggerKey)}>
      <Box sx={{ position: "relative", width: "100%" }}>
        <Box
          ref={mirrorRef}
          aria-hidden="true"
          sx={{
            position: "absolute",
            top: 0,
            left: 0,
            visibility: "hidden",
            pointerEvents: "none",
            whiteSpace: "pre-wrap",
            wordWrap: "break-word",
            overflowWrap: "break-word",
            direction: "ltr"
          }}
        >
          {mirrorText}
          <span
            ref={caretAnchorRef}
            style={{ display: "inline-block", height: "1.25em", verticalAlign: "text-bottom" }}
          >
            &#8203;
          </span>
        </Box>

        <TextField
          label="Query"
          variant="filled"
          multiline
          minRows={4}
          fullWidth
          inputRef={inputRef}
          value={value}
          onChange={handleChange}
          onKeyDown={handleKeyDown}
          onClick={handleSelectOrClick}
          onKeyUp={handleSelectOrClick}
          onScroll={syncMirrorLayout}
          onFocus={() => setIsFocused(true)}
          onBlur={() => setIsFocused(false)}
          error={error}
          helperText={helperText}
          placeholder="alertname:HighRequestLatency && ( severity:critical || ! job:api )"
          slotProps={{
            htmlInput: {
              dir: "ltr",
              role: "combobox",
              "aria-expanded": isOpen,
              "aria-controls": isOpen ? listboxId : undefined,
              "aria-activedescendant":
                isOpen && options.length > 0 && highlightedIndex >= 0
                  ? getOptionId(highlightedIndex)
                  : undefined,
              spellCheck: false,
              style: {
                fontFamily: "monospace"
              }
            }
          }}
        />

        <Popper
          open={isOpen}
          anchorEl={caretAnchorRef.current}
          placement="bottom-start"
          modifiers={[{ name: "offset", options: { offset: [0, 24] } }]}
          sx={{ zIndex: (theme) => theme.zIndex.modal + 1 }}
        >
          <Paper
            elevation={6}
            sx={{
              mt: 0.5,
              minWidth: 260,
              maxWidth: 420,
              maxHeight: 240,
              overflowY: "auto",
              border: 1,
              borderColor: "divider",
              bgcolor: "background.paper"
            }}
          >
            {isLoading ? (
              <Stack direction="row" spacing={1} sx={{ alignItems: "center", px: 1.5, py: 1.25 }}>
                <CircularProgress size={14} />
                <Typography variant="body2" color="text.secondary">
                  Loading suggestions...
                </Typography>
              </Stack>
            ) : isError ? (
              <Typography variant="body2" color="error" sx={{ px: 1.5, py: 1.25 }}>
                Failed to load suggestions.
              </Typography>
            ) : options.length === 0 ? (
              <Typography variant="body2" color="text.secondary" sx={{ px: 1.5, py: 1.25 }}>
                {emptyMessage}
              </Typography>
            ) : (
              <List id={listboxId} role="listbox" dense disablePadding>
                {options.map((option, index) => {
                  const isSelected = index === highlightedIndex;
                  return (
                    <ListItemButton
                      key={option.id}
                      id={getOptionId(index)}
                      ref={(el) => {
                        optionRefs.current[index] = el;
                      }}
                      role="option"
                      aria-selected={isSelected}
                      selected={isSelected}
                      onMouseDown={(e) => {
                        e.preventDefault();
                      }}
                      onMouseEnter={() => setHighlightedIndex(index)}
                      onClick={() => applySelection(option)}
                      sx={{ py: 0.75, px: 1.5 }}
                    >
                      <ListItemText
                        primary={option.label}
                        slotProps={{
                          primary: {
                            variant: "body2",
                            sx: { fontFamily: "monospace" }
                          }
                        }}
                      />
                    </ListItemButton>
                  );
                })}
              </List>
            )}
          </Paper>
        </Popper>
      </Box>
    </ClickAwayListener>
  );
}
