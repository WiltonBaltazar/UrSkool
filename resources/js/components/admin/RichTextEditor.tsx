import { useEffect, useRef, type ComponentType } from "react";
import {
  Bold,
  Eraser,
  Heading2,
  Heading3,
  Italic,
  Link2,
  List,
  ListOrdered,
  Underline,
} from "lucide-react";
import { Button } from "@/components/ui/button";
import { cn } from "@/lib/utils";

interface RichTextEditorProps {
  value: string;
  onChange: (value: string) => void;
  placeholder?: string;
  className?: string;
}

interface Action {
  label: string;
  icon: ComponentType<{ className?: string }>;
  run: () => void;
}

const RichTextEditor = ({
  value,
  onChange,
  placeholder = "Escreve o conteúdo da lição...",
  className,
}: RichTextEditorProps) => {
  const editorRef = useRef<HTMLDivElement | null>(null);

  useEffect(() => {
    const editor = editorRef.current;
    if (!editor) return;

    if (document.activeElement !== editor && editor.innerHTML !== value) {
      editor.innerHTML = value;
    }
  }, [value]);

  const runCommand = (command: string, commandValue?: string) => {
    const editor = editorRef.current;
    if (!editor) return;

    editor.focus();
    document.execCommand(command, false, commandValue);
    onChange(editor.innerHTML);
  };

  const handleLink = () => {
    const url = window.prompt("URL do link (https://...)");
    if (!url) return;
    runCommand("createLink", url);
  };

  const actions: Action[] = [
    { label: "Negrito", icon: Bold, run: () => runCommand("bold") },
    { label: "Itálico", icon: Italic, run: () => runCommand("italic") },
    { label: "Sublinhado", icon: Underline, run: () => runCommand("underline") },
    { label: "Título H2", icon: Heading2, run: () => runCommand("formatBlock", "H2") },
    { label: "Título H3", icon: Heading3, run: () => runCommand("formatBlock", "H3") },
    { label: "Lista", icon: List, run: () => runCommand("insertUnorderedList") },
    { label: "Lista ordenada", icon: ListOrdered, run: () => runCommand("insertOrderedList") },
    { label: "Adicionar link", icon: Link2, run: handleLink },
    { label: "Limpar formatação", icon: Eraser, run: () => runCommand("removeFormat") },
  ];

  return (
    <div className={cn("rounded-md border border-input bg-background", className)}>
      <div className="flex flex-wrap items-center gap-1 border-b border-border p-1.5">
        {actions.map((action) => {
          const Icon = action.icon;

          return (
            <Button
              key={action.label}
              type="button"
              size="icon"
              variant="ghost"
              className="h-7 w-7"
              title={action.label}
              onMouseDown={(event) => {
                event.preventDefault();
                action.run();
              }}
            >
              <Icon className="h-3.5 w-3.5" />
            </Button>
          );
        })}
      </div>

      <div
        ref={editorRef}
        contentEditable
        suppressContentEditableWarning
        data-placeholder={placeholder}
        onInput={(event) => onChange(event.currentTarget.innerHTML)}
        className={cn(
          "min-h-[110px] p-3 text-sm leading-6 focus:outline-none",
          "empty:before:content-[attr(data-placeholder)] empty:before:text-muted-foreground",
          "[&_p]:my-0 [&_h2]:text-base [&_h2]:font-semibold [&_h2]:mt-2 [&_h3]:text-sm [&_h3]:font-semibold [&_h3]:mt-2 [&_ul]:list-disc [&_ul]:pl-5 [&_ol]:list-decimal [&_ol]:pl-5 [&_a]:text-accent [&_a]:underline",
        )}
      />
    </div>
  );
};

export default RichTextEditor;
