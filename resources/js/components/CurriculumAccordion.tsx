import { ChevronDown, PlayCircle, Lock } from "lucide-react";
import { useState } from "react";
import type { Section } from "@/lib/types";

interface CurriculumAccordionProps {
  sections: Section[];
}

const CurriculumAccordion = ({ sections }: CurriculumAccordionProps) => {
  const [openSections, setOpenSections] = useState<string[]>([sections[0]?.id]);

  const toggle = (id: string) => {
    setOpenSections((prev) =>
      prev.includes(id) ? prev.filter((s) => s !== id) : [...prev, id]
    );
  };

  return (
    <div className="space-y-2">
      {sections.map((section, idx) => {
        const isOpen = openSections.includes(section.id);
        return (
          <div key={section.id} className="border border-border rounded-lg overflow-hidden">
            <button
              onClick={() => toggle(section.id)}
              className="w-full flex items-center justify-between p-4 bg-surface-elevated hover:bg-muted transition-colors"
            >
              <div className="flex items-center gap-3">
                <span className="text-xs font-semibold text-muted-foreground w-8">
                  {String(idx + 1).padStart(2, "0")}
                </span>
                <span className="font-body font-semibold text-sm text-foreground text-left">
                  {section.title}
                </span>
              </div>
              <div className="flex items-center gap-3">
                <span className="text-xs text-muted-foreground">
                  {section.lessons.length} lições
                </span>
                <ChevronDown
                  className={`h-4 w-4 text-muted-foreground transition-transform ${isOpen ? "rotate-180" : ""}`}
                />
              </div>
            </button>

            {isOpen && (
              <div className="divide-y divide-border">
                {section.lessons.map((lesson) => (
                  <div
                    key={lesson.id}
                    className="flex items-center justify-between px-4 py-3 hover:bg-muted/50 transition-colors"
                  >
                    <div className="flex items-center gap-3">
                      {lesson.isFree ? (
                        <PlayCircle className="h-4 w-4 text-accent" />
                      ) : (
                        <Lock className="h-4 w-4 text-muted-foreground" />
                      )}
                      <span className="text-sm font-body text-foreground">{lesson.title}</span>
                      {lesson.isFree && (
                        <span className="text-[10px] font-semibold text-accent bg-accent-soft px-2 py-0.5 rounded-full">
                          GRÁTIS
                        </span>
                      )}
                    </div>
                    <span className="text-xs text-muted-foreground">{lesson.duration}</span>
                  </div>
                ))}
              </div>
            )}
          </div>
        );
      })}
    </div>
  );
};

export default CurriculumAccordion;
