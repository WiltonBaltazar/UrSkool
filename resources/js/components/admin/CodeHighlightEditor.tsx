import { useEffect, useMemo, useRef, type KeyboardEvent } from "react";
import { cn } from "@/lib/utils";

type EditorLanguage = "html" | "css" | "js" | "javascript";

interface CodeHighlightEditorProps {
  language: EditorLanguage;
  value: string;
  onChange?: (value: string) => void;
  readOnly?: boolean;
  placeholder?: string;
  className?: string;
  minHeightClassName?: string;
  enableTabIndentation?: boolean;
  indentWith?: string;
}

type TokenType =
  | "comment"
  | "tag"
  | "keyword"
  | "string"
  | "number"
  | "property"
  | "atrule"
  | "function";

interface TokenRule {
  type: TokenType;
  pattern: RegExp;
}

const TOKEN_STYLES: Record<TokenType, string> = {
  comment: "color:#94a3b8;font-style:italic;",
  tag: "color:#fda4af;",
  keyword: "color:#c4b5fd;",
  string: "color:#86efac;",
  number: "color:#fcd34d;",
  property: "color:#7dd3fc;",
  atrule: "color:#f9a8d4;",
  function: "color:#93c5fd;",
};

const escapeHtml = (value: string) =>
  value
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;");

const withGlobal = (regex: RegExp) => {
  const flags = regex.flags.includes("g") ? regex.flags : `${regex.flags}g`;
  return new RegExp(regex.source, flags);
};

const getRules = (language: EditorLanguage): TokenRule[] => {
  const lang = language === "javascript" ? "js" : language;

  if (lang === "html") {
    return [
      { type: "comment", pattern: /<!--[\s\S]*?-->/g },
      { type: "tag", pattern: /<\/?[A-Za-z][^>]*>/g },
      { type: "string", pattern: /"(?:\\.|[^"\\])*"|'(?:\\.|[^'\\])*'/g },
    ];
  }

  if (lang === "css") {
    return [
      { type: "comment", pattern: /\/\*[\s\S]*?\*\//g },
      { type: "atrule", pattern: /@[A-Za-z-]+/g },
      { type: "property", pattern: /\b[A-Za-z-]+(?=\s*:)/g },
      { type: "string", pattern: /"(?:\\.|[^"\\])*"|'(?:\\.|[^'\\])*'/g },
      { type: "number", pattern: /\b\d+(?:\.\d+)?(?:px|rem|em|vh|vw|%|s|ms)?\b/g },
    ];
  }

  return [
    { type: "comment", pattern: /\/\*[\s\S]*?\*\/|\/\/[^\n]*/g },
    {
      type: "keyword",
      pattern:
        /\b(?:const|let|var|function|return|if|else|for|while|switch|case|break|continue|class|new|import|from|export|default|try|catch|finally|throw|await|async|true|false|null|undefined)\b/g,
    },
    { type: "string", pattern: /`(?:\\.|[^`\\])*`|"(?:\\.|[^"\\])*"|'(?:\\.|[^'\\])*'/g },
    { type: "number", pattern: /\b\d+(?:\.\d+)?\b/g },
    { type: "function", pattern: /\b[A-Za-z_$][\w$]*(?=\s*\()/g },
  ];
};

const highlightCode = (source: string, language: EditorLanguage) => {
  if (!source) return "";

  const rules = getRules(language).map((rule) => ({
    ...rule,
    pattern: withGlobal(rule.pattern),
  }));

  let cursor = 0;
  let output = "";

  while (cursor < source.length) {
    let nextMatch:
      | {
          index: number;
          text: string;
          type: TokenType;
        }
      | null = null;

    for (const rule of rules) {
      rule.pattern.lastIndex = cursor;
      const match = rule.pattern.exec(source);
      if (!match || !match[0]) continue;

      if (
        !nextMatch ||
        match.index < nextMatch.index ||
        (match.index === nextMatch.index && match[0].length > nextMatch.text.length)
      ) {
        nextMatch = {
          index: match.index,
          text: match[0],
          type: rule.type,
        };
      }
    }

    if (!nextMatch) {
      output += escapeHtml(source.slice(cursor));
      break;
    }

    if (nextMatch.index > cursor) {
      output += escapeHtml(source.slice(cursor, nextMatch.index));
    }

    output += `<span style="${TOKEN_STYLES[nextMatch.type]}">${escapeHtml(nextMatch.text)}</span>`;
    cursor = nextMatch.index + nextMatch.text.length;
  }

  return output;
};

const CodeHighlightEditor = ({
  language,
  value,
  onChange,
  readOnly = false,
  placeholder,
  className,
  minHeightClassName = "min-h-[170px]",
  enableTabIndentation = true,
  indentWith = "  ",
}: CodeHighlightEditorProps) => {
  const preRef = useRef<HTMLPreElement | null>(null);
  const textareaRef = useRef<HTMLTextAreaElement | null>(null);

  const highlighted = useMemo(() => {
    if (!value) return "";
    return `${highlightCode(value, language)}\n`;
  }, [language, value]);

  useEffect(() => {
    if (!preRef.current || !textareaRef.current) return;
    preRef.current.scrollTop = textareaRef.current.scrollTop;
    preRef.current.scrollLeft = textareaRef.current.scrollLeft;
  }, [language, value]);

  const handleKeyDown = (event: KeyboardEvent<HTMLTextAreaElement>) => {
    if (readOnly) return;
    if (!enableTabIndentation || event.key !== "Tab") return;

    event.preventDefault();
    const target = event.currentTarget;
    const start = target.selectionStart;
    const end = target.selectionEnd;

    if (!event.shiftKey) {
      if (start === end) {
        const nextValue = `${value.slice(0, start)}${indentWith}${value.slice(end)}`;
        onChange?.(nextValue);
        const nextCursor = start + indentWith.length;
        window.requestAnimationFrame(() => {
          target.selectionStart = nextCursor;
          target.selectionEnd = nextCursor;
        });
        return;
      }

      const blockStart = value.lastIndexOf("\n", start - 1) + 1;
      const blockEnd = value.indexOf("\n", end);
      const safeBlockEnd = blockEnd === -1 ? value.length : blockEnd;
      const block = value.slice(blockStart, safeBlockEnd);
      const lines = block.split("\n");
      const indented = lines.map((line) => `${indentWith}${line}`).join("\n");
      const nextValue = `${value.slice(0, blockStart)}${indented}${value.slice(safeBlockEnd)}`;

      onChange?.(nextValue);
      const nextStart = start + indentWith.length;
      const nextEnd = end + indentWith.length * lines.length;
      window.requestAnimationFrame(() => {
        target.selectionStart = nextStart;
        target.selectionEnd = nextEnd;
      });
      return;
    }

    const blockStart = value.lastIndexOf("\n", start - 1) + 1;
    const blockEnd = value.indexOf("\n", end);
    const safeBlockEnd = blockEnd === -1 ? value.length : blockEnd;
    const block = value.slice(blockStart, safeBlockEnd);
    const lines = block.split("\n");

    let removedFromStartLine = 0;
    let removedTotal = 0;

    const dedented = lines
      .map((line, index) => {
        let removeCount = 0;
        if (line.startsWith("\t")) {
          removeCount = 1;
        } else {
          const leadingSpaces = line.match(/^ +/)?.[0].length || 0;
          removeCount = Math.min(indentWith.length, leadingSpaces);
        }

        if (removeCount > 0) {
          if (index === 0) {
            removedFromStartLine = Math.min(removeCount, Math.max(0, start - blockStart));
          }
          removedTotal += removeCount;
        }

        return line.slice(removeCount);
      })
      .join("\n");

    const nextValue = `${value.slice(0, blockStart)}${dedented}${value.slice(safeBlockEnd)}`;
    onChange?.(nextValue);

    const nextStart = Math.max(blockStart, start - removedFromStartLine);
    const nextEnd = Math.max(nextStart, end - removedTotal);
    window.requestAnimationFrame(() => {
      target.selectionStart = nextStart;
      target.selectionEnd = nextEnd;
    });
  };

  return (
    <div className={cn("relative rounded-md border border-input bg-[#1f2a44]", className)}>
      <pre
        ref={preRef}
        aria-hidden
        className={cn(
          "pointer-events-none overflow-auto whitespace-pre p-3 font-mono text-xs leading-6 text-slate-200",
          minHeightClassName,
        )}
        style={{ tabSize: 2 }}
        dangerouslySetInnerHTML={{
          __html: highlighted || `<span style="color:#64748b;">${escapeHtml(placeholder || "")}</span>\n`,
        }}
      />
      {!readOnly && (
        <textarea
          ref={textareaRef}
          value={value}
          spellCheck={false}
          onChange={(event) => onChange?.(event.target.value)}
          onKeyDown={handleKeyDown}
          onScroll={(event) => {
            if (!preRef.current) return;
            preRef.current.scrollTop = event.currentTarget.scrollTop;
            preRef.current.scrollLeft = event.currentTarget.scrollLeft;
          }}
          className={cn(
            "absolute inset-0 w-full resize-none overflow-auto bg-transparent p-3 font-mono text-xs leading-6",
            "text-transparent caret-slate-100 outline-none selection:bg-slate-300/30",
            minHeightClassName,
          )}
          style={{ tabSize: 2 }}
        />
      )}
    </div>
  );
};

export default CodeHighlightEditor;
