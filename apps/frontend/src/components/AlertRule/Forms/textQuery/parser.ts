import type { QueryAstNode } from "@/@types/alertRule";

export const SPECIAL_KEYS = ["data-source", "data-source-alert-name", "alertname"] as const;

type LexToken =
  | { type: "LPAREN"; pos: number }
  | { type: "RPAREN"; pos: number }
  | { type: "AND"; pos: number }
  | { type: "OR"; pos: number }
  | { type: "NOT"; pos: number }
  | { type: "TERM"; key: string; value: string; pos: number };

type ParsedExpr =
  | { kind: "TERM"; key: string; value: string }
  | { kind: "NOT"; right: ParsedExpr }
  | { kind: "AND"; left: ParsedExpr; right: ParsedExpr }
  | { kind: "OR"; left: ParsedExpr; right: ParsedExpr };

export type ValidationResult = { valid: true } | { valid: false; error: string };

class QueryParseError extends Error {
  constructor(message: string) {
    super(message);
    this.name = "QueryParseError";
  }
}

function tokenize(input: string): LexToken[] {
  const tokens: LexToken[] = [];
  let i = 0;

  while (i < input.length) {
    const ch = input[i];

    if (/\s/.test(ch)) {
      i += 1;
      continue;
    }

    if (ch === "(") {
      tokens.push({ type: "LPAREN", pos: i });
      i += 1;
      continue;
    }

    if (ch === ")") {
      tokens.push({ type: "RPAREN", pos: i });
      i += 1;
      continue;
    }

    if (ch === "&") {
      if (input[i + 1] !== "&") {
        throw new QueryParseError(`Expected "&&" instead of single "&" at position ${i + 1}.`);
      }
      tokens.push({ type: "AND", pos: i });
      i += 2;
      continue;
    }

    if (ch === "|") {
      if (input[i + 1] !== "|") {
        throw new QueryParseError(`Expected "||" instead of single "|" at position ${i + 1}.`);
      }
      tokens.push({ type: "OR", pos: i });
      i += 2;
      continue;
    }

    if (ch === "!") {
      tokens.push({ type: "NOT", pos: i });
      i += 1;
      continue;
    }

    if (ch === ":") {
      throw new QueryParseError(`Expected a key name before ":" at position ${i + 1}.`);
    }

    if (/[a-zA-Z0-9_.-]/.test(ch)) {
      const start = i;
      let key = "";
      while (i < input.length && /[a-zA-Z0-9_.-]/.test(input[i])) {
        key += input[i];
        i += 1;
      }

      if (i >= input.length || input[i] !== ":") {
        throw new QueryParseError(`Expected ":" after key "${key}" at position ${start + 1}.`);
      }

      i += 1; // skip ':'

      while (i < input.length && /[ \t]/.test(input[i])) {
        i += 1;
      }

      if (i >= input.length) {
        throw new QueryParseError(`Expected a value after "${key}:".`);
      }

      let value = "";
      if (input[i] === '"') {
        i += 1; // skip opening quote
        while (i < input.length && input[i] !== '"') {
          if (input[i] === "\\" && i + 1 < input.length) {
            i += 1;
          }
          value += input[i];
          i += 1;
        }
        if (i >= input.length || input[i] !== '"') {
          throw new QueryParseError(`Unclosed quote in "${key}:" value.`);
        }
        i += 1; // skip closing quote
      } else {
        while (i < input.length && !/[\s()&|!]/.test(input[i])) {
          value += input[i];
          i += 1;
        }
      }

      if (!value.trim()) {
        throw new QueryParseError(`Key "${key}:" requires a non-empty value.`);
      }

      tokens.push({ type: "TERM", key, value: value.trim(), pos: start });
      continue;
    }

    throw new QueryParseError(`Unexpected character "${ch}" at position ${i + 1}.`);
  }

  return tokens;
}

function parseTokens(tokens: LexToken[]): ParsedExpr {
  let pos = 0;

  function peek(): LexToken | undefined {
    return tokens[pos];
  }

  function consume(): LexToken {
    const tok = tokens[pos];
    pos += 1;
    return tok;
  }

  function formatTokenLabel(tok: LexToken): string {
    switch (tok.type) {
      case "AND":
        return '"&&"';
      case "OR":
        return '"||"';
      case "NOT":
        return '"!"';
      case "LPAREN":
        return '"("';
      case "RPAREN":
        return '")"';
      case "TERM":
        return `"${tok.key}:${tok.value}"`;
    }
  }

  function parseOr(): ParsedExpr {
    let left = parseAnd();
    while (peek()?.type === "OR") {
      consume();
      const next = peek();
      if (!next) {
        throw new QueryParseError('Query cannot end with "||".');
      }
      if (next.type === "AND" || next.type === "OR" || next.type === "RPAREN") {
        throw new QueryParseError(
          `Expected a term or "(" after "||", found ${formatTokenLabel(next)}.`
        );
      }
      const right = parseAnd();
      left = { kind: "OR", left, right };
    }
    return left;
  }

  function parseAnd(): ParsedExpr {
    let left = parseUnary();
    while (peek()?.type === "AND") {
      consume();
      const next = peek();
      if (!next) {
        throw new QueryParseError('Query cannot end with "&&".');
      }
      if (next.type === "AND" || next.type === "OR" || next.type === "RPAREN") {
        throw new QueryParseError(
          `Expected a term or "(" after "&&", found ${formatTokenLabel(next)}.`
        );
      }
      const right = parseUnary();
      left = { kind: "AND", left, right };
    }
    return left;
  }

  function parseUnary(): ParsedExpr {
    if (peek()?.type === "NOT") {
      consume();
      const next = peek();
      if (!next || next.type === "AND" || next.type === "OR" || next.type === "RPAREN") {
        throw new QueryParseError('Expected a term or "(" after "!".');
      }
      return { kind: "NOT", right: parseUnary() };
    }
    return parsePrimary();
  }

  function parsePrimary(): ParsedExpr {
    const tok = peek();
    if (!tok) {
      throw new QueryParseError("Unexpected end of query.");
    }

    if (tok.type === "LPAREN") {
      consume();
      if (peek()?.type === "RPAREN") {
        throw new QueryParseError('Empty parentheses "()" are not allowed.');
      }
      if (!peek()) {
        throw new QueryParseError('Unclosed "(" in query.');
      }
      const expr = parseOr();
      const closing = peek();
      if (!closing || closing.type !== "RPAREN") {
        throw new QueryParseError('Unclosed "(" in query.');
      }
      consume();
      return expr;
    }

    if (tok.type === "TERM") {
      consume();
      return { kind: "TERM", key: tok.key, value: tok.value };
    }

    if (tok.type === "AND" || tok.type === "OR") {
      throw new QueryParseError(`Unexpected ${formatTokenLabel(tok)} at position ${tok.pos + 1}.`);
    }

    if (tok.type === "RPAREN") {
      throw new QueryParseError('Unexpected ")" without matching "(".');
    }

    throw new QueryParseError(`Unexpected token ${formatTokenLabel(tok)}.`);
  }

  const expr = parseOr();
  const leftover = peek();
  if (leftover) {
    if (leftover.type === "RPAREN") {
      throw new QueryParseError('Unexpected ")" without matching "(".');
    }
    throw new QueryParseError(
      `Expected operator "&&" or "||" before ${formatTokenLabel(leftover)}.`
    );
  }

  return expr;
}

export function validateTextQuery(input: string): ValidationResult {
  if (!input || !input.trim()) {
    return { valid: false, error: "Query cannot be empty." };
  }

  try {
    const tokens = tokenize(input);
    if (tokens.length === 0) {
      return { valid: false, error: "Query cannot be empty." };
    }
    parseTokens(tokens);
    return { valid: true };
  } catch (err) {
    if (err instanceof QueryParseError) {
      return { valid: false, error: err.message };
    }
    return { valid: false, error: "Invalid query syntax." };
  }
}

export function compileTextQueryToAst(
  input: string,
  dataSources?: Array<{ id: string; name: string }>
): QueryAstNode {
  const tokens = tokenize(input);
  const expr = parseTokens(tokens);
  const nameToId = new Map((dataSources ?? []).map((ds) => [ds.name, ds.id]));

  function toAst(node: ParsedExpr): QueryAstNode {
    switch (node.kind) {
      case "OR":
        return {
          token: { type: "OR" },
          left: toAst(node.left),
          right: toAst(node.right)
        };
      case "AND":
        return {
          token: { type: "AND" },
          left: toAst(node.left),
          right: toAst(node.right)
        };
      case "NOT":
        return {
          token: { type: "NOT" },
          right: toAst(node.right)
        };
      case "TERM": {
        if (node.key === "data-source") {
          const id = nameToId.get(node.value) ?? node.value;
          return {
            token: { type: "LITERAL", literal: `dataSourceId:${id}` }
          };
        }
        if (node.key === "data-source-alert-name" || node.key === "alertname") {
          return {
            token: { type: "LITERAL", literal: `alertname:${node.value}` }
          };
        }
        return {
          token: { type: "LITERAL", literal: `${node.key}:${node.value}` }
        };
      }
    }
  }

  return toAst(expr);
}
