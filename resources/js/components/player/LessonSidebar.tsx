import { Link } from "react-router-dom";
import { CheckCircle2, ChevronLeft, Circle } from "lucide-react";
import { Sheet, SheetContent, SheetHeader, SheetTitle } from "@/components/ui/sheet";
import type { Course, Section } from "@/lib/types";

interface LessonSidebarProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  course: Pick<Course, "id" | "title">;
  sections: Section[];
  currentLessonId: string;
  completedLessons: string[];
  allLessonsCount: number;
  progress: number;
  lessonIndexById: Record<string, number>;
  maxUnlockedLessonIndex: number;
}

export function LessonSidebar({
  open,
  onOpenChange,
  course,
  sections,
  currentLessonId,
  completedLessons,
  allLessonsCount,
  progress,
  lessonIndexById,
  maxUnlockedLessonIndex,
}: LessonSidebarProps) {
  return (
    <Sheet open={open} onOpenChange={onOpenChange}>
      <SheetContent
        side="left"
        className="w-[88vw] max-w-[420px] border-r border-white/15 bg-[#060606] p-0 text-white [&>button]:text-white [&>button:hover]:bg-white/10"
      >
        <SheetHeader className="sr-only">
          <SheetTitle>Lições do curso</SheetTitle>
        </SheetHeader>

        <div className="flex h-full flex-col">
          <div className="border-b border-white/10 p-5 space-y-3">
            <Link
              to={`/course/${course.id}`}
              onClick={() => onOpenChange(false)}
              className="inline-flex items-center rounded-md border border-white/20 px-3 py-2 text-sm font-medium text-white hover:bg-white/10"
            >
              <ChevronLeft className="mr-1 h-4 w-4" />
              Voltar ao curso
            </Link>
            <div>
              <p className="text-xs uppercase tracking-[0.14em] text-white/60">Módulo</p>
              <p className="mt-1 text-2xl font-semibold">{course.title}</p>
              <p className="mt-1 text-sm text-white/70">
                {completedLessons.length}/{allLessonsCount} concluídas
              </p>
            </div>
            <div className="h-2 w-full overflow-hidden rounded-full bg-white/15">
              <div
                className="h-full rounded-full bg-white transition-[width] duration-300"
                style={{ width: `${progress}%` }}
              />
            </div>
          </div>

          <div className="flex-1 overflow-y-auto">
            {sections.map((section) => (
              <div key={section.id}>
                <div className="px-5 py-2 text-xs uppercase tracking-[0.14em] text-white/55 border-b border-white/5">
                  {section.title}
                </div>
                {section.lessons.map((lesson) => {
                  const active = lesson.id === currentLessonId;
                  const done = completedLessons.includes(lesson.id);
                  const lessonIndex = lessonIndexById[lesson.id] ?? 0;
                  const blockedByGate = lessonIndex > maxUnlockedLessonIndex;

                  if (blockedByGate) {
                    return (
                      <div
                        key={lesson.id}
                        className="flex items-center gap-2 border-b border-white/5 px-5 py-3 text-sm text-white/55"
                      >
                        <Circle className="h-4 w-4 shrink-0" />
                        <span className="truncate">{lesson.title}</span>
                      </div>
                    );
                  }

                  return (
                    <Link
                      key={lesson.id}
                      to={`/student/${course.id}/${lesson.id}`}
                      onClick={() => onOpenChange(false)}
                      className={`flex items-center gap-2 border-b border-white/5 px-5 py-3 text-sm transition-colors ${
                        active ? "bg-white/10 text-white" : "text-white/85 hover:bg-white/5"
                      }`}
                    >
                      {done ? (
                        <CheckCircle2 className="h-4 w-4 shrink-0 text-success" />
                      ) : (
                        <Circle className="h-4 w-4 shrink-0 text-white/45" />
                      )}
                      <span className="truncate">{lesson.title}</span>
                    </Link>
                  );
                })}
              </div>
            ))}
          </div>
        </div>
      </SheetContent>
    </Sheet>
  );
}
