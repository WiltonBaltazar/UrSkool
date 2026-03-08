import { useCallback, useEffect, useMemo, useRef, useState } from "react";
import { Link, useParams } from "react-router-dom";
import { useQuery } from "@tanstack/react-query";
import {
  AlertTriangle,
  CheckCircle2,
  ChevronLeft,
  ChevronRight,
  Circle,
  Code2,
  Copy,
  ExternalLink,
  Maximize2,
  Menu,
  Minimize2,
  Play,
  PlayCircle,
  RefreshCw,
  RotateCcw,
  Trophy,
  Undo2,
  XCircle,
} from "lucide-react";
import CodeHighlightEditor from "@/components/admin/CodeHighlightEditor";
import { Button } from "@/components/ui/button";
import { Progress } from "@/components/ui/progress";
import { Sheet, SheetContent, SheetHeader, SheetTitle } from "@/components/ui/sheet";
import { useToast } from "@/hooks/use-toast";
import { useIsMobile } from "@/hooks/use-mobile";
import { fetchStudentCourse, saveLessonProgress } from "@/lib/api";
import type {
  CourseProgress,
  Lesson,
  LessonProgressEntry,
  QuizQuestion,
  SaveLessonProgressPayload,
} from "@/lib/types";

type EditorPanel = "html" | "css" | "js";
type WorkspaceMode = "split" | "code" | "preview";

interface LessonCodeState {
  html: string;
  css: string;
  js: string;
}

interface QuizQuestionState {
  id: string;
  question: string;
  options: string[];
  correctOptionIndex: number;
}

interface QuizAttemptState {
  questionOrder: string[];
  selectedAnswers: Record<string, number>;
  submitted: boolean;
  score: number;
  passed: boolean;
  correctCount: number;
  total: number;
  currentQuestionIndex: number;
  passPercentage: number;
  signature: string;
  attemptNumber: number;
}

interface CodeValidationFeedback {
  isCorrect: boolean;
  message: string;
}

const normalizeLanguage = (language?: string | null) =>
  (language || "html").trim().toLowerCase();

const normalizePassPercentage = (value?: number | null): number => {
  if (!Number.isFinite(value)) return 80;
  return Math.max(1, Math.min(100, Math.round(Number(value))));
};

const shouldRandomizeQuizQuestions = (value?: boolean | null): boolean =>
  value !== false;

const defaultInstructions = (lesson: Lesson) => {
  if (lesson.type === "quiz") {
    return lesson.content || "Responde ao questionário e atinge a nota mínima para desbloquear a próxima lição.";
  }

  if (lesson.type === "text") {
    return lesson.content || "Lê a teoria desta lição com atenção e continua quando estiveres pronto.";
  }

  if (lesson.type === "video") {
    return lesson.content || "Assiste ao vídeo e toma notas dos pontos principais antes de avançar.";
  }

  return (
    lesson.content
    || `Nesta etapa, constrói e melhora "${lesson.title}". Edita HTML/CSS/JS e clica em Executar para testar o resultado.`
  );
};

const hasHtmlTags = (value: string) => /<\/?[a-z][\s\S]*>/i.test(value);

const toInstructionPlainText = (value: string): string =>
  value
    .replace(/<script[\s\S]*?>[\s\S]*?<\/script>/gi, "")
    .replace(/<li[^>]*>/gi, "\n- ")
    .replace(/<\/(p|div|h1|h2|h3|h4|h5|h6|li|ul|ol|section|article|pre)>/gi, "\n")
    .replace(/<br\s*\/?>/gi, "\n")
    .replace(/<[^>]+>/g, " ")
    .replace(/&nbsp;/gi, " ")
    .replace(/\r/g, "")
    .replace(/[ \t]+\n/g, "\n")
    .replace(/\n{3,}/g, "\n\n")
    .trim();

const extractChecklistItems = (value: string): string[] => {
  const source = hasHtmlTags(value) ? toInstructionPlainText(value) : value;

  return source
    .split("\n")
    .map((line) => line.trim())
    .filter((line) => line.length > 0)
    .filter((line) => /^(\d+[.)]\s+|[-*]\s+)/.test(line))
    .map((line) => line.replace(/^(\d+[.)]\s+|[-*]\s+)/, "").trim())
    .slice(0, 8);
};

const extractLessonTip = (value: string): string | null => {
  const source = hasHtmlTags(value) ? toInstructionPlainText(value) : value;
  const tipLine = source
    .split("\n")
    .map((line) => line.trim())
    .find((line) => /^dica[:-]/i.test(line) || /^tip[:-]/i.test(line));

  if (!tipLine) return null;
  return tipLine.replace(/^dica[:-]\s*/i, "").replace(/^tip[:-]\s*/i, "").trim() || null;
};

const truncateCodeSnippet = (value: string, maxLines = 16): string => {
  const lines = value
    .replace(/\t/g, "  ")
    .split("\n")
    .map((line) => line.replace(/\s+$/g, ""))
    .filter((line, index, arr) => !(index === arr.length - 1 && line === ""));

  if (lines.length <= maxLines) {
    return lines.join("\n");
  }

  return `${lines.slice(0, maxLines).join("\n")}\n...`;
};

interface ParsedLessonContent {
  theory: string;
  instructions: string[];
  examples: string[];
  hint: string | null;
}

const normalizeSectionHeading = (line: string): string =>
  line
    .toLowerCase()
    .replace(/[^\p{L}\p{N}\s]/gu, " ")
    .replace(/\s+/g, " ")
    .trim();

const extractCodeExamplesFromContent = (value: string): string[] => {
  const examples: string[] = [];

  const fencedMatches = value.match(/```(?:[a-z0-9_-]+)?\n([\s\S]*?)```/gi) || [];
  fencedMatches.forEach((block) => {
    const cleaned = block
      .replace(/```(?:[a-z0-9_-]+)?\n?/i, "")
      .replace(/```$/i, "")
      .trim();
    if (cleaned) {
      examples.push(cleaned);
    }
  });

  const preMatches = value.match(/<pre[\s\S]*?>[\s\S]*?<\/pre>/gi) || [];
  preMatches.forEach((block) => {
    const cleaned = toInstructionPlainText(block).trim();
    if (cleaned) {
      examples.push(cleaned);
    }
  });

  if (examples.length > 0) {
    return Array.from(new Set(examples)).slice(0, 3);
  }

  const plain = hasHtmlTags(value) ? toInstructionPlainText(value) : value;
  const lines = plain.split("\n").map((line) => line.trim());
  const candidateBlocks: string[] = [];
  let currentBlock: string[] = [];

  const looksLikeCode = (line: string): boolean =>
    /<[^>]+>/.test(line)
    || /[{};]/.test(line)
    || /^\.[a-z0-9_-]+\s*\{/i.test(line)
    || /^#[a-z0-9_-]+\s*\{/i.test(line);

  lines.forEach((line) => {
    if (looksLikeCode(line)) {
      currentBlock.push(line);
      return;
    }

    if (currentBlock.length > 0) {
      candidateBlocks.push(currentBlock.join("\n"));
      currentBlock = [];
    }
  });

  if (currentBlock.length > 0) {
    candidateBlocks.push(currentBlock.join("\n"));
  }

  return candidateBlocks
    .map((block) => block.trim())
    .filter((block) => block.length > 0)
    .slice(0, 2);
};

const parseLessonContent = (value: string): ParsedLessonContent => {
  const source = (value || "").trim();
  if (!source) {
    return {
      theory: "",
      instructions: [],
      examples: [],
      hint: null,
    };
  }

  const plain = hasHtmlTags(source) ? toInstructionPlainText(source) : source;
  const lines = plain
    .split("\n")
    .map((line) => line.trim())
    .filter((line) => line.length > 0);

  const theoryLines: string[] = [];
  const instructionLines: string[] = [];
  const hintLines: string[] = [];
  let currentSection: "theory" | "instructions" | "hint" = "theory";

  lines.forEach((line) => {
    const heading = normalizeSectionHeading(line.replace(/:$/, ""));

    if (
      /^(instrucoes|instrucoes da tarefa|instructions|instruction|task|tarefa)$/.test(heading)
    ) {
      currentSection = "instructions";
      return;
    }

    if (/^(dica|dicas|hint|hints)$/.test(heading)) {
      currentSection = "hint";
      return;
    }

    if (/^(concept review|revisao|resumo|conceptos chave|conceitos chave)$/.test(heading)) {
      currentSection = "theory";
      return;
    }

    if (currentSection === "instructions") {
      const cleaned = line.replace(/^(\d+[.)]\s+|[-*]\s+)/, "").trim();
      if (cleaned) {
        instructionLines.push(cleaned);
      }
      return;
    }

    if (currentSection === "hint") {
      hintLines.push(line);
      return;
    }

    theoryLines.push(line);
  });

  const theory = theoryLines.join("\n\n").trim() || plain.trim();
  const instructions = instructionLines.length > 0 ? instructionLines : extractChecklistItems(source);
  const hint = hintLines.join(" ").trim() || extractLessonTip(source);
  const examples = extractCodeExamplesFromContent(source);

  return {
    theory,
    instructions,
    examples,
    hint,
  };
};

const defaultHtml = (title: string) =>
  `<main class="lesson-card">\n  <h1>${title}</h1>\n  <p>Edita HTML, CSS e JS e depois clica em Executar.</p>\n  <button id="action">Testar</button>\n</main>`;

const defaultCss = () =>
  `body {\n  font-family: ui-sans-serif, system-ui, -apple-system;\n  padding: 1.5rem;\n}\n\n.lesson-card {\n  border: 1px solid #e5e7eb;\n  border-radius: 12px;\n  padding: 1rem;\n}\n\nbutton {\n  background: #f59e0b;\n  border: none;\n  border-radius: 8px;\n  color: white;\n  cursor: pointer;\n  padding: 0.5rem 0.8rem;\n}`;

const defaultJs = () =>
  `const btn = document.getElementById("action");\nif (btn) {\n  btn.addEventListener("click", () => {\n    btn.textContent = "Clicado";\n  });\n}\nconsole.log("Lição pronta");`;

const resolveLessonCode = (lesson: Lesson): LessonCodeState => {
  const language = normalizeLanguage(lesson.language);
  const starter = lesson.starterCode || "";

  return {
    html: lesson.htmlCode || (language === "html" ? starter : defaultHtml(lesson.title)),
    css: lesson.cssCode || (language === "css" ? starter : defaultCss()),
    js: lesson.jsCode || (["js", "javascript"].includes(language) ? starter : defaultJs()),
  };
};

const buildPreviewDoc = (code: LessonCodeState) => {
  const escapedJsLiteral = JSON.stringify(code.js).replace(/<\/script/gi, "<\\/script");

  return `<!doctype html>
<html>
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <style>${code.css}</style>
  </head>
  <body>
    ${code.html}
    <script>
      (function () {
        var logBox = document.createElement("pre");
        logBox.style.borderTop = "1px solid #e5e7eb";
        logBox.style.marginTop = "16px";
        logBox.style.paddingTop = "10px";
        logBox.style.fontFamily = "ui-monospace, SFMono-Regular, Menlo, monospace";
        logBox.style.fontSize = "12px";
        logBox.style.whiteSpace = "pre-wrap";
        logBox.id = "__urskool_console__";
        document.body.appendChild(logBox);

        var appendLog = function (type, values) {
          var line = "[" + type.toUpperCase() + "] " + values.map(function (v) {
            try { return typeof v === "string" ? v : JSON.stringify(v); } catch (_) { return String(v); }
          }).join(" ");
          logBox.textContent += (logBox.textContent ? "\\n" : "") + line;
        };

        var originalLog = console.log;
        var originalWarn = console.warn;
        var originalError = console.error;
        console.log = function () { var args = Array.prototype.slice.call(arguments); appendLog("log", args); originalLog.apply(console, args); };
        console.warn = function () { var args = Array.prototype.slice.call(arguments); appendLog("warn", args); originalWarn.apply(console, args); };
        console.error = function () { var args = Array.prototype.slice.call(arguments); appendLog("error", args); originalError.apply(console, args); };

        window.addEventListener("error", function (event) {
          appendLog("error", ["Erro JS:", event.message, "(linha " + event.lineno + ")"]);
        });

        var userCode = ${escapedJsLiteral};
        try {
          (new Function(userCode))();
        } catch (error) {
          appendLog("error", [error && error.message ? error.message : String(error)]);
        }
      })();
    </script>
  </body>
</html>`;
};

const toEmbedVideoUrl = (url?: string | null): string | null => {
  if (!url) return null;

  try {
    const parsed = new URL(url);

    if (parsed.hostname.includes("youtu.be")) {
      const id = parsed.pathname.replace("/", "");
      return id ? `https://www.youtube.com/embed/${id}` : null;
    }

    if (parsed.hostname.includes("youtube.com")) {
      const id = parsed.searchParams.get("v");
      return id ? `https://www.youtube.com/embed/${id}` : null;
    }

    if (parsed.hostname.includes("vimeo.com")) {
      const id = parsed.pathname.split("/").filter(Boolean).pop();
      return id ? `https://player.vimeo.com/video/${id}` : null;
    }

    return null;
  } catch {
    return null;
  }
};

const shuffleArray = <T,>(items: T[]): T[] => {
  const next = [...items];
  for (let index = next.length - 1; index > 0; index -= 1) {
    const swapIndex = Math.floor(Math.random() * (index + 1));
    [next[index], next[swapIndex]] = [next[swapIndex], next[index]];
  }
  return next;
};

const normalizeQuizQuestions = (lesson: Lesson): QuizQuestionState[] => {
  const questions = Array.isArray(lesson.quizQuestions) ? lesson.quizQuestions : [];

  return questions
    .map((question, index): QuizQuestionState | null => {
      const candidate = question as QuizQuestion;
      const label = (candidate.question || "").trim();
      const options = (Array.isArray(candidate.options) ? candidate.options : [])
        .map((option) => (option || "").trim())
        .filter((option) => option.length > 0);

      if (!label || options.length < 2) {
        return null;
      }

      const rawCorrect = Number(candidate.correctOptionIndex ?? 0);
      const safeCorrect = Number.isInteger(rawCorrect) ? rawCorrect : 0;

      return {
        id: candidate.id || `q-${lesson.id}-${index + 1}`,
        question: label,
        options,
        correctOptionIndex: Math.max(0, Math.min(options.length - 1, safeCorrect)),
      };
    })
    .filter((question): question is QuizQuestionState => Boolean(question));
};

const buildQuizSignature = (questions: QuizQuestionState[], passPercentage: number): string => {
  return `${passPercentage}::${questions.map((question) => `${question.id}:${question.options.length}`).join("|")}`;
};

const mapCourseProgressByLesson = (progress?: CourseProgress): Record<string, LessonProgressEntry> => {
  if (!progress || !Array.isArray(progress.lessons)) {
    return {};
  }

  return progress.lessons.reduce<Record<string, LessonProgressEntry>>((acc, lessonProgress) => {
    acc[lessonProgress.lessonId] = lessonProgress;
    return acc;
  }, {});
};

const StudentPlayerPage = () => {
  const { toast } = useToast();
  const { courseId, lessonId } = useParams();
  const { data: course, isLoading, isError, error } = useQuery({
    queryKey: ["student-course", courseId],
    queryFn: () => fetchStudentCourse(courseId || "1"),
    enabled: Boolean(courseId),
  });

  const [lessonDrawerOpen, setLessonDrawerOpen] = useState(false);
  const [completedLessons, setCompletedLessons] = useState<string[]>([]);
  const [lessonProgressByLesson, setLessonProgressByLesson] = useState<Record<string, LessonProgressEntry>>({});
  const [codeValidationFeedbackByLesson, setCodeValidationFeedbackByLesson] = useState<
    Record<string, CodeValidationFeedback>
  >({});
  const [progressHydrated, setProgressHydrated] = useState(false);
  const [codeByLesson, setCodeByLesson] = useState<Record<string, LessonCodeState>>({});
  const [quizStateByLesson, setQuizStateByLesson] = useState<Record<string, QuizAttemptState>>({});
  const [previewDoc, setPreviewDoc] = useState("");
  const [previewVersion, setPreviewVersion] = useState(0);
  const [activePanel, setActivePanel] = useState<EditorPanel>("html");
  const [workspaceMode, setWorkspaceMode] = useState<WorkspaceMode>("split");
  const [lessonPaneWidth, setLessonPaneWidth] = useState(32);
  const [editorPaneRatio, setEditorPaneRatio] = useState(52);
  const [dragTarget, setDragTarget] = useState<"lesson" | "editor" | null>(null);
  const markedInProgressRef = useRef<Record<string, boolean>>({});
  const workspaceRef = useRef<HTMLDivElement | null>(null);
  const previewFrameRef = useRef<HTMLIFrameElement | null>(null);
  const isMobile = useIsMobile();

  useEffect(() => {
    setProgressHydrated(false);
  }, [courseId]);

  useEffect(() => {
    setLessonDrawerOpen(false);
  }, [courseId, lessonId]);

  const sections = useMemo(
    () => (course && Array.isArray(course.sections) ? course.sections : []),
    [course],
  );

  const allLessons = useMemo(
    () => sections.reduce<Lesson[]>((acc, section) => {
      const lessonList = Array.isArray(section.lessons) ? section.lessons : [];
      return acc.concat(lessonList);
    }, []),
    [sections],
  );

  const lessonIndexById = useMemo(() => {
    return allLessons.reduce<Record<string, number>>((acc, lesson, index) => {
      acc[lesson.id] = index;
      return acc;
    }, {});
  }, [allLessons]);

  const currentLesson = useMemo(() => {
    if (!allLessons.length) return null;
    return allLessons.find((lesson) => lesson.id === String(lessonId)) || allLessons[0];
  }, [allLessons, lessonId]);

  useEffect(() => {
    if (!course) return;

    markedInProgressRef.current = {};
    const progress = course.progress;
    setCompletedLessons(progress?.completedLessonIds || []);
    setLessonProgressByLesson(mapCourseProgressByLesson(progress));
    setProgressHydrated(true);
  }, [course]);

  const currentProgress = currentLesson ? lessonProgressByLesson[currentLesson.id] : undefined;
  const isTextLesson = currentLesson?.type === "text";
  const isQuizLesson = currentLesson?.type === "quiz";

  const isCodeLesson = useMemo(() => {
    if (!currentLesson) return false;
    if (currentLesson.type === "quiz" || currentLesson.type === "text" || currentLesson.type === "video") return false;
    const language = normalizeLanguage(currentLesson.language);

    return (
      currentLesson.type === "code"
      || Boolean(currentLesson.htmlCode || currentLesson.cssCode || currentLesson.jsCode)
      || ["html", "css", "javascript", "js"].includes(language)
    );
  }, [currentLesson]);

  const quizQuestions = useMemo(() => {
    if (!currentLesson || !isQuizLesson) return [];
    return normalizeQuizQuestions(currentLesson);
  }, [currentLesson, isQuizLesson]);

  const quizPassPercentage = useMemo(
    () => normalizePassPercentage(currentLesson?.quizPassPercentage),
    [currentLesson?.quizPassPercentage],
  );

  const quizRandomize = useMemo(
    () => shouldRandomizeQuizQuestions(currentLesson?.quizRandomizeQuestions),
    [currentLesson?.quizRandomizeQuestions],
  );

  const quizSignature = useMemo(
    () => buildQuizSignature(quizQuestions, quizPassPercentage),
    [quizPassPercentage, quizQuestions],
  );

  const currentCode = useMemo(() => {
    if (!currentLesson) {
      return { html: "", css: "", js: "" };
    }

    return codeByLesson[currentLesson.id] || resolveLessonCode(currentLesson);
  }, [codeByLesson, currentLesson]);
  const currentHtml = currentCode.html;
  const currentCss = currentCode.css;
  const currentJs = currentCode.js;

  const currentQuizState = currentLesson ? quizStateByLesson[currentLesson.id] : undefined;

  const orderedQuizQuestions = useMemo(() => {
    if (!isQuizLesson) return [];

    const byId = new Map(quizQuestions.map((question) => [question.id, question]));
    const orderedFromState = (currentQuizState?.questionOrder ?? [])
      .map((questionId) => byId.get(questionId))
      .filter((question): question is QuizQuestionState => Boolean(question));

    if (orderedFromState.length === quizQuestions.length) {
      return orderedFromState;
    }

    return quizQuestions;
  }, [currentQuizState?.questionOrder, isQuizLesson, quizQuestions]);

  const currentQuizQuestion = useMemo(() => {
    if (!isQuizLesson || !currentQuizState || orderedQuizQuestions.length === 0) return null;

    const safeIndex = Math.max(0, Math.min(orderedQuizQuestions.length - 1, currentQuizState.currentQuestionIndex));
    return orderedQuizQuestions[safeIndex] || null;
  }, [currentQuizState, isQuizLesson, orderedQuizQuestions]);

  const applyCourseProgress = (progress: CourseProgress) => {
    setCompletedLessons(progress.completedLessonIds || []);
    setLessonProgressByLesson(mapCourseProgressByLesson(progress));
  };

  const persistLessonProgress = useCallback(async (
    lessonIdToPersist: string,
    payload: SaveLessonProgressPayload,
  ): Promise<void> => {
    if (!course?.id) return;

    try {
      const progress = await saveLessonProgress(course.id, lessonIdToPersist, payload);
      applyCourseProgress(progress);
    } catch (persistError) {
      toast({
        variant: "destructive",
        title: "Falha ao guardar progresso",
        description: persistError instanceof Error ? persistError.message : "Tenta novamente.",
      });
    }
  }, [course?.id, toast]);

  useEffect(() => {
    if (!currentLesson || !isCodeLesson) return;

    setCodeByLesson((prev) => {
      if (prev[currentLesson.id]) return prev;

      return {
        ...prev,
        [currentLesson.id]: resolveLessonCode(currentLesson),
      };
    });
  }, [currentLesson, isCodeLesson]);

  useEffect(() => {
    if (!currentLesson || !isQuizLesson) return;

    const persistedQuizPassed = Boolean(currentProgress?.quizPassed || currentProgress?.status === "completed");
    const persistedQuizScore = currentProgress?.quizScore ?? 0;

    setQuizStateByLesson((prev) => {
      const existingState = prev[currentLesson.id];
      if (existingState && existingState.signature === quizSignature) {
        return prev;
      }

      const questionIds = quizQuestions.map((question) => question.id);
      const questionOrder = quizRandomize ? shuffleArray(questionIds) : questionIds;

      return {
        ...prev,
        [currentLesson.id]: {
          questionOrder,
          selectedAnswers: {},
          submitted: false,
          score: persistedQuizScore,
          passed: persistedQuizPassed,
          correctCount: 0,
          total: questionIds.length,
          currentQuestionIndex: 0,
          passPercentage: quizPassPercentage,
          signature: quizSignature,
          attemptNumber: 1,
        },
      };
    });
  }, [currentLesson, currentProgress, isQuizLesson, quizPassPercentage, quizQuestions, quizRandomize, quizSignature]);

  useEffect(() => {
    if (!currentLesson || !isCodeLesson) return;

    const timer = window.setTimeout(() => {
      setPreviewDoc(
        buildPreviewDoc({
          html: currentHtml,
          css: currentCss,
          js: currentJs,
        }),
      );
      setPreviewVersion((prev) => prev + 1);
    }, 220);

    return () => window.clearTimeout(timer);
  }, [currentLesson, currentHtml, currentCss, currentJs, isCodeLesson]);

  useEffect(() => {
    if (!currentLesson || !isCodeLesson) return;

    if (!currentProgress?.codeIsCorrect && currentProgress?.status !== "completed") {
      return;
    }

    setCodeValidationFeedbackByLesson((prev) => ({
      ...prev,
      [currentLesson.id]: prev[currentLesson.id] || {
        isCorrect: true,
        message: "Tarefa validada com sucesso.",
      },
    }));
  }, [currentLesson, currentProgress?.codeIsCorrect, currentProgress?.status, isCodeLesson]);

  useEffect(() => {
    if (isMobile && workspaceMode !== "split") {
      setWorkspaceMode("split");
    }
  }, [isMobile, workspaceMode]);

  useEffect(() => {
    if (!dragTarget || isMobile || workspaceMode !== "split") return;

    const handleMove = (event: MouseEvent) => {
      const rect = workspaceRef.current?.getBoundingClientRect();
      if (!rect) return;

      if (dragTarget === "lesson") {
        const raw = (event.clientX - rect.left) / rect.width;
        const next = Math.max(24, Math.min(45, raw * 100));
        setLessonPaneWidth(next);
        return;
      }

      const lessonPx = rect.width * (lessonPaneWidth / 100);
      const remainingPx = Math.max(1, rect.width - lessonPx);
      const rawEditor = (event.clientX - rect.left - lessonPx) / remainingPx;
      const nextRatio = Math.max(30, Math.min(70, rawEditor * 100));
      setEditorPaneRatio(nextRatio);
    };

    const stopDragging = () => setDragTarget(null);

    document.addEventListener("mousemove", handleMove);
    document.addEventListener("mouseup", stopDragging);
    document.body.style.cursor = "col-resize";
    document.body.style.userSelect = "none";

    return () => {
      document.removeEventListener("mousemove", handleMove);
      document.removeEventListener("mouseup", stopDragging);
      document.body.style.cursor = "";
      document.body.style.userSelect = "";
    };
  }, [dragTarget, isMobile, lessonPaneWidth, workspaceMode]);

  useEffect(() => {
    if (!progressHydrated) return;
    if (!currentLesson) return;

    if (completedLessons.includes(currentLesson.id)) {
      markedInProgressRef.current[currentLesson.id] = true;
      return;
    }

    const existingStatus = lessonProgressByLesson[currentLesson.id]?.status;
    if (existingStatus === "in_progress" || existingStatus === "completed") {
      markedInProgressRef.current[currentLesson.id] = true;
      return;
    }

    if (markedInProgressRef.current[currentLesson.id]) {
      return;
    }

    markedInProgressRef.current[currentLesson.id] = true;
    void persistLessonProgress(currentLesson.id, { status: "in_progress" });
  }, [completedLessons, currentLesson, lessonProgressByLesson, persistLessonProgress, progressHydrated]);

  if (isLoading) {
    return (
      <div className="min-h-screen bg-background flex items-center justify-center">
        <p className="text-muted-foreground font-body">A carregar curso...</p>
      </div>
    );
  }

  if (isError || !course) {
    return (
      <div className="min-h-screen bg-background flex items-center justify-center">
        <div className="text-center max-w-md space-y-4 px-4">
          <p className="text-muted-foreground font-body">
            {error instanceof Error ? error.message : "Não foi possível abrir este curso."}
          </p>
          <div className="flex items-center justify-center gap-2">
            <Link to={`/course/${courseId || ""}`}>
              <Button variant="outline">Voltar ao curso</Button>
            </Link>
            <Link to={`/checkout/${courseId || ""}`}>
              <Button className="bg-accent hover:bg-accent-hover text-accent-foreground">Comprar acesso</Button>
            </Link>
          </div>
        </div>
      </div>
    );
  }

  if (!currentLesson) {
    return (
      <div className="min-h-screen bg-background flex items-center justify-center">
        <p className="text-muted-foreground font-body">Este curso ainda não tem lições.</p>
      </div>
    );
  }

  const currentIndex = allLessons.findIndex((lesson) => lesson.id === currentLesson.id);
  const prevLesson = currentIndex > 0 ? allLessons[currentIndex - 1] : null;
  const nextLesson = currentIndex < allLessons.length - 1 ? allLessons[currentIndex + 1] : null;
  const progress = allLessons.length > 0 ? (completedLessons.length / allLessons.length) * 100 : 0;
  const embeddedVideoUrl = toEmbedVideoUrl(currentLesson.videoUrl);
  const instructionContent = defaultInstructions(currentLesson);
  const parsedLessonContent = parseLessonContent(instructionContent);

  const toggleComplete = (id: string) => {
    const wasCompleted = completedLessons.includes(id);
    const nextCompleted = wasCompleted
      ? completedLessons.filter((lesson) => lesson !== id)
      : [...completedLessons, id];

    setCompletedLessons(nextCompleted);
    void persistLessonProgress(id, {
      status: wasCompleted ? "in_progress" : "completed",
    });
  };

  const updateCodePanel = (panel: EditorPanel, value: string) => {
    setCodeByLesson((prev) => ({
      ...prev,
      [currentLesson.id]: {
        ...(prev[currentLesson.id] || resolveLessonCode(currentLesson)),
        [panel]: value,
      },
    }));
  };

  const runCode = () => {
    setPreviewDoc(buildPreviewDoc(currentCode));
    setPreviewVersion((prev) => prev + 1);
  };

  const copyToClipboard = async (value: string, successMessage: string) => {
    try {
      await navigator.clipboard.writeText(value);
      toast({
        title: "Copiado",
        description: successMessage,
      });
    } catch {
      toast({
        variant: "destructive",
        title: "Não foi possível copiar",
        description: "Verifica as permissões do navegador para copiar texto.",
      });
    }
  };

  const copyCurrentPanel = () => {
    if (!isCodeLesson) return;
    void copyToClipboard(currentCode[activePanel], `Conteúdo de ${activePanel.toUpperCase()} copiado.`);
  };

  const resetCurrentPanel = () => {
    if (!isCodeLesson) return;

    const baseline = resolveLessonCode(currentLesson);
    updateCodePanel(activePanel, baseline[activePanel]);
    toast({
      title: "Painel reposto",
      description: `${activePanel.toUpperCase()} voltou ao estado inicial da lição.`,
    });
  };

  const resetLessonCode = () => {
    if (!isCodeLesson) return;

    const baseline = resolveLessonCode(currentLesson);
    setCodeByLesson((prev) => ({
      ...prev,
      [currentLesson.id]: baseline,
    }));

    setCodeValidationFeedbackByLesson((prev) => {
      const next = { ...prev };
      delete next[currentLesson.id];
      return next;
    });

    setCompletedLessons((prev) => prev.filter((lessonId) => lessonId !== currentLesson.id));
    void persistLessonProgress(currentLesson.id, {
      status: "in_progress",
      codeIsCorrect: false,
    });

    setPreviewDoc(buildPreviewDoc(baseline));
    setPreviewVersion((prev) => prev + 1);
    toast({
      title: "Lição reiniciada",
      description: "Código e validação foram repostos para tentares novamente.",
    });
  };

  const previewGoBack = () => {
    const frame = previewFrameRef.current?.contentWindow;
    if (!frame) return;

    try {
      frame.history.back();
    } catch {
      toast({
        variant: "destructive",
        title: "Sem histórico no preview",
        description: "Abre uma página no preview antes de voltar.",
      });
    }
  };

  const previewGoForward = () => {
    const frame = previewFrameRef.current?.contentWindow;
    if (!frame) return;

    try {
      frame.history.forward();
    } catch {
      toast({
        variant: "destructive",
        title: "Sem histórico no preview",
        description: "Ainda não existe navegação para avançar.",
      });
    }
  };

  const previewReload = () => {
    const frame = previewFrameRef.current?.contentWindow;
    if (!frame) return;

    try {
      frame.location.reload();
    } catch {
      runCode();
    }
  };

  const previewOpenInNewTab = () => {
    const frame = previewFrameRef.current?.contentWindow;
    if (!frame) return;

    try {
      const href = frame.location.href;
      if (href && href !== "about:srcdoc" && href !== "about:blank") {
        window.open(href, "_blank", "noopener,noreferrer");
        return;
      }
    } catch {
      // Cross-origin URL; still try to open via location href string access fallback.
    }

    const blob = new Blob([previewDoc], { type: "text/html" });
    const blobUrl = URL.createObjectURL(blob);
    window.open(blobUrl, "_blank", "noopener,noreferrer");
    window.setTimeout(() => URL.revokeObjectURL(blobUrl), 30_000);
  };

  const currentCodeValidation = currentLesson
    ? codeValidationFeedbackByLesson[currentLesson.id]
    : undefined;

  const isCodeTaskCorrect = Boolean(
    isCodeLesson
    && (
      currentProgress?.codeIsCorrect
      || currentProgress?.status === "completed"
      || currentCodeValidation?.isCorrect
    ),
  );

  const validateCodeTask = () => {
    if (!currentLesson || !isCodeLesson) return;

    const baselineCode = resolveLessonCode(currentLesson);
    const hasChangedCode = (
      currentCode.html.trim() !== baselineCode.html.trim()
      || currentCode.css.trim() !== baselineCode.css.trim()
      || currentCode.js.trim() !== baselineCode.js.trim()
    );

    let feedbackMessage = "";
    if (!hasChangedCode) {
      feedbackMessage = "Resultado incorreto: altera o código da tarefa antes de validar.";
    } else if (currentCode.html.trim() === "") {
      feedbackMessage = "Resultado incorreto: o HTML não pode estar vazio.";
    } else {
      try {
        // Validate JS syntax before allowing progression.
        new Function(currentCode.js || "");
      } catch (validationError) {
        feedbackMessage = `Resultado incorreto: ${
          validationError instanceof Error ? validationError.message : "erro de sintaxe em JavaScript."
        }`;
      }
    }

    const codeIsCorrect = feedbackMessage === "";
    const feedback: CodeValidationFeedback = {
      isCorrect: codeIsCorrect,
      message: codeIsCorrect
        ? "Tarefa validada com sucesso. Podes avançar."
        : feedbackMessage,
    };

    setCodeValidationFeedbackByLesson((prev) => ({
      ...prev,
      [currentLesson.id]: feedback,
    }));

    setCompletedLessons((prev) => {
      if (codeIsCorrect) {
        return prev.includes(currentLesson.id) ? prev : [...prev, currentLesson.id];
      }

      return prev.filter((lessonId) => lessonId !== currentLesson.id);
    });

    void persistLessonProgress(currentLesson.id, {
      status: codeIsCorrect ? "completed" : "in_progress",
      codeIsCorrect,
    });
  };

  const updateCurrentQuizState = (updater: (state: QuizAttemptState) => QuizAttemptState) => {
    if (!currentLesson || !isQuizLesson) return;

    setQuizStateByLesson((prev) => {
      const existingState = prev[currentLesson.id];
      if (!existingState) return prev;

      return {
        ...prev,
        [currentLesson.id]: updater(existingState),
      };
    });
  };

  const selectQuizOption = (questionId: string, optionIndex: number) => {
    updateCurrentQuizState((state) => ({
      ...state,
      selectedAnswers: {
        ...state.selectedAnswers,
        [questionId]: optionIndex,
      },
    }));
  };

  const goToNextQuizQuestion = () => {
    if (!currentQuizState || !currentQuizQuestion) return;

    const selectedAnswer = currentQuizState.selectedAnswers[currentQuizQuestion.id];
    if (selectedAnswer === undefined) {
      toast({
        variant: "destructive",
        title: "Seleciona uma opção",
        description: "Escolhe uma resposta antes de continuar para a próxima pergunta.",
      });
      return;
    }

    updateCurrentQuizState((state) => ({
      ...state,
      currentQuestionIndex: Math.min(state.total - 1, state.currentQuestionIndex + 1),
    }));
  };

  const goToPreviousQuizQuestion = () => {
    updateCurrentQuizState((state) => ({
      ...state,
      currentQuestionIndex: Math.max(0, state.currentQuestionIndex - 1),
    }));
  };

  const submitQuiz = () => {
    if (!currentQuizState) return;

    if (orderedQuizQuestions.length === 0) {
      toast({
        variant: "destructive",
        title: "Questionário vazio",
        description: "Este questionário ainda não tem perguntas configuradas.",
      });
      return;
    }

    const unanswered = orderedQuizQuestions.filter(
      (question) => currentQuizState.selectedAnswers[question.id] === undefined,
    );

    if (unanswered.length > 0) {
      toast({
        variant: "destructive",
        title: "Questionário incompleto",
        description: `Faltam ${unanswered.length} pergunta(s) por responder.`,
      });
      return;
    }

    const correctCount = orderedQuizQuestions.reduce((total, question) => {
      return total + (currentQuizState.selectedAnswers[question.id] === question.correctOptionIndex ? 1 : 0);
    }, 0);

    const score = Math.round((correctCount / orderedQuizQuestions.length) * 100);
    const passed = score >= currentQuizState.passPercentage;

    updateCurrentQuizState((state) => ({
      ...state,
      submitted: true,
      score,
      passed,
      correctCount,
      total: orderedQuizQuestions.length,
    }));

    setCompletedLessons((prev) => {
      if (passed) {
        return prev.includes(currentLesson.id) ? prev : [...prev, currentLesson.id];
      }

      return prev.filter((lessonId) => lessonId !== currentLesson.id);
    });

    void persistLessonProgress(currentLesson.id, {
      status: passed ? "completed" : "in_progress",
      quizScore: score,
      quizPassed: passed,
    });

    toast({
      title: passed ? "Questionário concluído" : "Ainda não atingiste a meta",
      description: passed
        ? `Pontuação ${score}%. Podes avançar para a próxima lição.`
        : `Pontuação ${score}%. Precisas de ${currentQuizState.passPercentage}% para avançar.`,
      variant: passed ? "default" : "destructive",
    });
  };

  const retryQuiz = () => {
    const questionIds = quizQuestions.map((question) => question.id);

    updateCurrentQuizState((state) => ({
      ...state,
      questionOrder: quizRandomize ? shuffleArray(questionIds) : questionIds,
      selectedAnswers: {},
      submitted: false,
      score: 0,
      passed: false,
      correctCount: 0,
      total: questionIds.length,
      currentQuestionIndex: 0,
      attemptNumber: state.attemptNumber + 1,
    }));

    setCompletedLessons((prev) => prev.filter((lessonId) => lessonId !== currentLesson.id));
    void persistLessonProgress(currentLesson.id, {
      status: "in_progress",
      quizScore: 0,
      quizPassed: false,
    });
  };

  const desktopLayoutStyle = (() => {
    if (isMobile) return undefined;

    if (isQuizLesson || isTextLesson) {
      return {
        gridTemplateColumns: "100%",
      };
    }

    if (!isCodeLesson && !isQuizLesson) {
      return {
        gridTemplateColumns: "42% 58%",
      };
    }

    const focusMode = isCodeLesson && workspaceMode !== "split";
    if (focusMode) {
      return {
        gridTemplateColumns: "100%",
      };
    }

    const safeLesson = Math.max(24, Math.min(45, lessonPaneWidth));
    const remaining = 100 - safeLesson;

    const safeEditor = Math.max(30, Math.min(70, editorPaneRatio));
    const middle = (remaining * safeEditor) / 100;
    const right = remaining - middle;

    return {
      gridTemplateColumns: `${safeLesson}% ${middle}% ${right}%`,
    };
  })();

  const isWorkspaceFocused = isCodeLesson && !isMobile && workspaceMode !== "split";
  const showLessonPane = !isQuizLesson && (!isCodeLesson || isMobile || workspaceMode === "split");
  const showMiddlePane = isQuizLesson || isCodeLesson;
  const showPreviewPane = !isQuizLesson && !isTextLesson && (isMobile || !isCodeLesson || workspaceMode !== "code");
  const quizPassedFromProgress = Boolean(currentProgress?.quizPassed || currentProgress?.status === "completed");
  const quizAdvanceLocked = Boolean(isQuizLesson && !quizPassedFromProgress && !currentQuizState?.passed);
  const codeAdvanceLocked = Boolean(isCodeLesson && !isCodeTaskCorrect);
  const advancementLocked = quizAdvanceLocked || codeAdvanceLocked;
  const scoreCircleValue = currentQuizState?.score ?? 0;
  const instructionChecklist = parsedLessonContent.instructions;
  const lessonTip = parsedLessonContent.hint;
  const lessonExamples = parsedLessonContent.examples;
  const lessonTheory = parsedLessonContent.theory || instructionContent;
  const lessonTypeLabel = currentLesson.type === "video"
    ? "Vídeo"
    : currentLesson.type === "quiz"
      ? "Questionário"
      : currentLesson.type === "text"
        ? "Teoria"
        : "Prática";

  return (
    <div className="h-screen bg-background flex flex-col">
      <header className="h-14 border-b border-border bg-card px-4 flex items-center justify-between gap-3 shrink-0">
        <div className="flex items-center gap-3 min-w-0">
          <Button
            variant="ghost"
            size="icon"
            className="text-foreground"
            onClick={() => setLessonDrawerOpen(true)}
          >
            <Menu className="h-5 w-5" />
          </Button>
          <div className="min-w-0">
            <p className="text-xs text-muted-foreground">{course.title}</p>
            <p className="font-body font-semibold truncate">{currentLesson.title}</p>
          </div>
        </div>

        <div className="hidden md:flex items-center gap-3 w-[320px]">
          <div className="w-full">
            <Progress value={progress} className="h-2" />
          </div>
          <span className="text-xs text-muted-foreground whitespace-nowrap">
            {currentIndex + 1}/{allLessons.length}
          </span>
        </div>
      </header>

      <div className="flex-1 min-h-0">
        <Sheet open={lessonDrawerOpen} onOpenChange={setLessonDrawerOpen}>
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
                  onClick={() => setLessonDrawerOpen(false)}
                  className="inline-flex items-center rounded-md border border-white/20 px-3 py-2 text-sm font-medium text-white hover:bg-white/10"
                >
                  <ChevronLeft className="mr-1 h-4 w-4" />
                  Voltar ao curso
                </Link>
                <div>
                  <p className="text-xs uppercase tracking-[0.14em] text-white/60">Módulo</p>
                  <p className="mt-1 text-2xl font-semibold">{course.title}</p>
                  <p className="mt-1 text-sm text-white/70">
                    {completedLessons.length}/{allLessons.length} concluídas
                  </p>
                </div>
                <div className="h-2 w-full overflow-hidden rounded-full bg-white/15">
                  <div className="h-full rounded-full bg-white transition-[width] duration-300" style={{ width: `${progress}%` }} />
                </div>
              </div>

              <div className="flex-1 overflow-y-auto">
                {sections.map((section) => (
                  <div key={section.id}>
                    <div className="px-5 py-2 text-xs uppercase tracking-[0.14em] text-white/55 border-b border-white/5">
                      {section.title}
                    </div>
                    {section.lessons.map((lesson) => {
                      const active = lesson.id === currentLesson.id;
                      const done = completedLessons.includes(lesson.id);
                      const lessonIndex = lessonIndexById[lesson.id] ?? 0;
                      const blockedByGate = advancementLocked && lessonIndex > currentIndex;

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
                          onClick={() => setLessonDrawerOpen(false)}
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

        <main
          className={`flex-1 min-w-0 min-h-0 overflow-hidden flex flex-col ${
            isWorkspaceFocused ? "p-0 bg-primary" : "p-3 md:p-4 bg-background"
          }`}
        >
          <div
            ref={workspaceRef}
            className={`flex-1 min-h-0 grid grid-cols-1 relative ${isWorkspaceFocused ? "gap-0" : "gap-3"}`}
            style={desktopLayoutStyle}
          >
            {showLessonPane && (
              <section className="relative min-h-0 overflow-hidden rounded-lg border border-black/15 bg-[#efefef] flex flex-col">
                <div className="shrink-0 border-b border-black/10 bg-white px-4 py-4">
                  <p className="text-xs uppercase tracking-[0.14em] text-black/55">Learn</p>
                  <h2 className="mt-1 text-2xl font-display text-black">{currentLesson.title}</h2>
                  <p className="mt-1 text-sm text-black/60">
                    {currentLesson.duration || "Ao teu ritmo"} · {lessonTypeLabel}
                  </p>
                </div>

                <div className="flex-1 overflow-y-auto px-4 py-5 space-y-4">
                  <article className="rounded-lg border border-black/10 bg-white p-4">
                    <p className="text-[11px] uppercase tracking-[0.14em] text-black/50">Teoria</p>
                    <p className="mt-3 whitespace-pre-wrap leading-7 text-black">{lessonTheory}</p>
                  </article>

                  {instructionChecklist.length > 0 && (
                    <article className="rounded-lg border border-black/10 bg-white p-4">
                      <p className="text-[11px] uppercase tracking-[0.14em] text-black/50">Instruções</p>
                      <ol className="mt-3 space-y-2">
                        {instructionChecklist.map((item, index) => (
                          <li key={`${currentLesson.id}-instruction-${index}`} className="flex items-start gap-2 text-sm text-black/85">
                            <span className="mt-0.5 inline-flex h-5 w-5 shrink-0 items-center justify-center rounded-full border border-black/20 text-[11px] font-medium">
                              {index + 1}
                            </span>
                            <span>{item}</span>
                          </li>
                        ))}
                      </ol>
                    </article>
                  )}

                  {lessonExamples.length > 0 && (
                    <article className="rounded-lg border border-black/10 bg-white p-4 space-y-3">
                      <p className="text-[11px] uppercase tracking-[0.14em] text-black/50">Exemplos</p>
                      {lessonExamples.map((snippet, index) => (
                        <div key={`${currentLesson.id}-example-${index}`} className="space-y-2">
                          <div className="flex items-center justify-between gap-2">
                            <p className="text-xs font-medium text-black/70">Exemplo {index + 1}</p>
                            <Button
                              variant="ghost"
                              size="sm"
                              className="h-7 px-2 text-xs text-black/70 hover:bg-black/5 hover:text-black"
                              onClick={() => void copyToClipboard(snippet, "Exemplo copiado.")}
                            >
                              <Copy className="mr-1 h-3.5 w-3.5" />
                              Copiar
                            </Button>
                          </div>
                          <pre className="overflow-x-auto rounded-md border border-black/15 bg-[#111111] p-3 text-xs leading-6 text-[#f3f3f3]">
                            <code>{truncateCodeSnippet(snippet, 18)}</code>
                          </pre>
                        </div>
                      ))}
                    </article>
                  )}

                  {lessonTip && (
                    <aside className="rounded-lg border border-black/20 bg-[#fff2b2] px-4 py-3 text-sm text-black/85">
                      <p className="font-semibold">Dica</p>
                      <p className="mt-1">{lessonTip}</p>
                    </aside>
                  )}

                  {!isTextLesson && (
                    <div className="rounded-lg border border-black/10 bg-white p-3 text-sm">
                      <p className="font-semibold text-black">Tarefa</p>
                      <p className="mt-1 text-black/70">
                        Conclui esta etapa e passa para a próxima lição com os controlos abaixo.
                      </p>
                    </div>
                  )}
                </div>
                {!isMobile && workspaceMode === "split" && showMiddlePane && (
                  <button
                    type="button"
                    aria-label="Redimensionar coluna da lição"
                    title="Arrasta para redimensionar"
                    onMouseDown={() => setDragTarget("lesson")}
                    className="absolute top-0 -right-1.5 h-full w-3 cursor-col-resize z-20"
                  >
                    <span className="mx-auto block h-full w-0.5 bg-border/70 hover:bg-accent transition-colors" />
                  </button>
                )}
              </section>
            )}

            {showMiddlePane && (isCodeLesson ? (
              <section
                className={`relative min-h-0 flex flex-col overflow-hidden bg-primary ${
                  isWorkspaceFocused ? "rounded-none border-0" : "rounded-lg border border-border"
                }`}
              >
                <div className="h-12 px-2 md:px-3 border-b border-primary-foreground/20 flex items-center justify-between gap-2">
                  <div className="flex items-center gap-1">
                    <Button
                      size="sm"
                      variant={activePanel === "html" ? "default" : "ghost"}
                      className={
                        activePanel === "html"
                          ? "h-8 bg-accent hover:bg-accent-hover text-accent-foreground"
                          : "h-8 text-primary-foreground hover:bg-primary-foreground/10"
                      }
                      onClick={() => setActivePanel("html")}
                    >
                      HTML
                    </Button>
                    <Button
                      size="sm"
                      variant={activePanel === "css" ? "default" : "ghost"}
                      className={
                        activePanel === "css"
                          ? "h-8 bg-accent hover:bg-accent-hover text-accent-foreground"
                          : "h-8 text-primary-foreground hover:bg-primary-foreground/10"
                      }
                      onClick={() => setActivePanel("css")}
                    >
                      CSS
                    </Button>
                    <Button
                      size="sm"
                      variant={activePanel === "js" ? "default" : "ghost"}
                      className={
                        activePanel === "js"
                          ? "h-8 bg-accent hover:bg-accent-hover text-accent-foreground"
                          : "h-8 text-primary-foreground hover:bg-primary-foreground/10"
                      }
                      onClick={() => setActivePanel("js")}
                    >
                      JS
                    </Button>
                  </div>
                  {!isMobile && (
                    <Button
                      size="icon"
                      variant="outline"
                      className="h-8 bg-transparent border-primary-foreground/30 text-primary-foreground hover:bg-primary-foreground/10 hover:text-primary-foreground"
                      onClick={() => setWorkspaceMode((prev) => (prev === "code" ? "split" : "code"))}
                      title={workspaceMode === "code" ? "Minimizar código" : "Maximizar código"}
                    >
                      {workspaceMode === "code" ? <Minimize2 className="h-4 w-4" /> : <Maximize2 className="h-4 w-4" />}
                    </Button>
                  )}
                </div>

                <div className="h-10 px-4 flex items-center border-b border-primary-foreground/15 text-primary-foreground/70 text-xs uppercase tracking-wide">
                  <Code2 className="h-3.5 w-3.5 mr-2" />
                  {activePanel === "html" ? "index.html" : activePanel === "css" ? "styles.css" : "script.js"}
                </div>

                <CodeHighlightEditor
                  key={activePanel}
                  language={activePanel}
                  value={currentCode[activePanel]}
                  onChange={(nextCode) => updateCodePanel(activePanel, nextCode)}
                  placeholder={activePanel === "html" ? "HTML" : activePanel === "css" ? "CSS" : "JavaScript"}
                  className="flex-1 min-h-0 rounded-none border-0 bg-primary"
                  minHeightClassName="h-full min-h-0"
                  enableTabIndentation
                  indentWith="  "
                />
                <div className="border-t border-primary-foreground/20 bg-[#0a0a14] px-3 py-2">
                  <div className="inline-flex items-center gap-1 rounded-md border border-primary-foreground/20 bg-black/30 p-1">
                    <Button
                      size="sm"
                      className="h-8 bg-accent hover:bg-accent-hover text-accent-foreground"
                      onClick={runCode}
                    >
                      <Play className="h-4 w-4 mr-1" />
                      Run
                    </Button>
                    <Button
                      size="icon"
                      variant="ghost"
                      className="h-8 w-8 text-primary-foreground hover:bg-primary-foreground/10"
                      onClick={copyCurrentPanel}
                      title="Copiar código atual"
                    >
                      <Copy className="h-4 w-4" />
                    </Button>
                    <Button
                      size="icon"
                      variant="ghost"
                      className="h-8 w-8 text-primary-foreground hover:bg-primary-foreground/10"
                      onClick={resetCurrentPanel}
                      title="Repor painel atual"
                    >
                      <Undo2 className="h-4 w-4" />
                    </Button>
                    <Button
                      size="icon"
                      variant="ghost"
                      className="h-8 w-8 text-primary-foreground hover:bg-primary-foreground/10"
                      onClick={resetLessonCode}
                      title="Retry da lição"
                    >
                      <RotateCcw className="h-4 w-4" />
                    </Button>
                  </div>
                </div>
                {!isMobile && workspaceMode === "split" && showPreviewPane && (
                  <button
                    type="button"
                    aria-label="Redimensionar colunas de código e preview"
                    title="Arrasta para redimensionar"
                    onMouseDown={() => setDragTarget("editor")}
                    className="absolute top-0 -right-1.5 h-full w-3 cursor-col-resize z-20"
                  >
                    <span className="mx-auto block h-full w-0.5 bg-border/70 hover:bg-accent transition-colors" />
                  </button>
                )}
              </section>
            ) : isQuizLesson ? (
              <section className="min-h-0 flex flex-col overflow-hidden rounded-lg border border-[#2a2a2a] bg-[#000000] text-[#f5f5f5]">
                <div className="px-5 py-4 border-b border-[#1f1f1f] bg-[#050505]">
                  <p className="text-xs uppercase tracking-[0.18em] text-[#a3a3a3]">Questionário</p>
                  <h2 className="text-2xl font-semibold mt-1">{currentLesson.title}</h2>
                  <p className="text-sm text-[#cfcfcf] mt-1">
                    Nota mínima para avançar: <span className="font-semibold text-[#ffffff]">{quizPassPercentage}%</span>
                  </p>
                </div>

                {quizQuestions.length === 0 ? (
                  <div className="flex-1 p-6 flex items-center justify-center">
                    <div className="rounded-lg border border-[#2f2f2f] bg-[#0c0c0c] px-5 py-4 max-w-md text-center">
                      <AlertTriangle className="h-6 w-6 mx-auto text-[#ffffff]" />
                      <p className="mt-3 font-medium">Questionário ainda não configurado</p>
                      <p className="mt-1 text-sm text-[#b3b3b3]">O administrador precisa adicionar perguntas e respostas primeiro.</p>
                    </div>
                  </div>
                ) : currentQuizState?.submitted ? (
                  <div className="flex-1 overflow-y-auto p-4 md:p-6 space-y-5">
                    <div className="rounded-xl border border-[#2f2f2f] bg-[#0b0b0b] p-5">
                      <div className="flex flex-col md:flex-row md:items-center gap-4 md:justify-between">
                        <div className="flex items-center gap-4">
                          <div
                            className="h-16 w-16 rounded-full p-[3px]"
                            style={{
                              background: `conic-gradient(#ffffff ${(scoreCircleValue / 100) * 360}deg, #2a2a2a 0deg)`,
                            }}
                          >
                            <div className="h-full w-full rounded-full bg-[#000000] flex items-center justify-center text-sm font-semibold">
                              {scoreCircleValue}%
                            </div>
                          </div>
                          <div>
                            <p className="text-sm uppercase tracking-wide text-[#c2c2c2]">Resultado</p>
                            <p className="text-xl font-semibold">
                              {currentQuizState.passed ? "Aprovado" : "Reprovado"}
                            </p>
                            <p className="text-sm text-[#c2c2c2]">
                              {currentQuizState.correctCount}/{currentQuizState.total} respostas corretas
                            </p>
                          </div>
                        </div>
                        <div className="flex items-center gap-2">
                          {currentQuizState.passed ? (
                            <span className="inline-flex items-center gap-1 rounded-full bg-[#183b2d] px-3 py-1 text-xs font-medium text-[#90f3c4] border border-[#2d7656]">
                              <Trophy className="h-3.5 w-3.5" />
                              Aprovado
                            </span>
                          ) : (
                            <span className="inline-flex items-center gap-1 rounded-full bg-[#4b1d2b] px-3 py-1 text-xs font-medium text-[#ff9bb6] border border-[#7a3248]">
                              <XCircle className="h-3.5 w-3.5" />
                              Repetir exercício
                            </span>
                          )}
                        </div>
                      </div>
                    </div>

                    <div className="space-y-4">
                      {orderedQuizQuestions.map((question, questionIndex) => {
                        const selected = currentQuizState.selectedAnswers[question.id];
                        const isCorrect = selected === question.correctOptionIndex;

                        return (
                          <article key={question.id} className="rounded-xl border border-[#2c2c2c] bg-[#0a0a0a] p-4 space-y-3">
                            <div className="flex items-start justify-between gap-3">
                              <p className="font-medium">
                                {questionIndex + 1}. {question.question}
                              </p>
                              {isCorrect ? (
                                <CheckCircle2 className="h-5 w-5 text-[#6cf1b9] shrink-0" />
                              ) : (
                                <XCircle className="h-5 w-5 text-[#ff8fae] shrink-0" />
                              )}
                            </div>
                            <div className="space-y-2">
                              {question.options.map((option, optionIndex) => {
                                const isSelected = selected === optionIndex;
                                const isAnswer = optionIndex === question.correctOptionIndex;

                                return (
                                  <div
                                    key={`${question.id}-option-${optionIndex}`}
                                    className={`rounded-md px-3 py-2 text-sm border ${
                                      isAnswer
                                        ? "bg-[#173b2e] border-[#46be86] text-[#aff7d9]"
                                        : isSelected
                                          ? "bg-[#422338] border-[#a34967] text-[#ffc2d4]"
                                          : "bg-[#0d0d0d] border-[#2b2b2b] text-[#d6d6d6]"
                                    }`}
                                  >
                                    {option}
                                  </div>
                                );
                              })}
                            </div>
                          </article>
                        );
                      })}
                    </div>
                  </div>
                ) : (
                  <>
                    <div className="px-5 py-3 border-b border-[#1f1f1f] bg-[#050505] space-y-2">
                      <div className="flex items-center justify-between text-xs text-[#c2c2c2]">
                        <span>
                          Pergunta {(currentQuizState?.currentQuestionIndex || 0) + 1} de {orderedQuizQuestions.length}
                        </span>
                        <span>Tentativa {currentQuizState?.attemptNumber || 1}</span>
                      </div>
                      <Progress
                        value={
                          orderedQuizQuestions.length > 0
                            ? (((currentQuizState?.currentQuestionIndex || 0) + 1) / orderedQuizQuestions.length) * 100
                            : 0
                        }
                        className="h-2 bg-[#1f1f1f]"
                      />
                    </div>

                    <div className="flex-1 overflow-y-auto p-5 md:p-7 space-y-6">
                      {currentQuizQuestion && (
                        <>
                          <h3 className="text-xl md:text-2xl font-semibold leading-relaxed">{currentQuizQuestion.question}</h3>
                          <div className="space-y-3">
                            {currentQuizQuestion.options.map((option, optionIndex) => {
                              const isSelected = currentQuizState?.selectedAnswers[currentQuizQuestion.id] === optionIndex;

                              return (
                                <button
                                  key={`${currentQuizQuestion.id}-${optionIndex}`}
                                  type="button"
                                  onClick={() => selectQuizOption(currentQuizQuestion.id, optionIndex)}
                                  className={`w-full rounded-lg border px-4 py-3 text-left transition-colors ${
                                    isSelected
                                      ? "border-[#ffffff] bg-[#171717] text-[#ffffff]"
                                      : "border-[#2d2d2d] bg-[#0c0c0c] text-[#ededed] hover:bg-[#1a1a1a]"
                                  }`}
                                >
                                  {option}
                                </button>
                              );
                            })}
                          </div>
                        </>
                      )}
                    </div>

                    <div className="px-5 py-4 border-t border-[#1f1f1f] bg-[#050505] flex items-center justify-between gap-2">
                      <Button
                        variant="outline"
                        className="border-[#4a4a4a] text-[#ffffff] bg-transparent hover:bg-[#121212]"
                        disabled={(currentQuizState?.currentQuestionIndex || 0) === 0}
                        onClick={goToPreviousQuizQuestion}
                      >
                        <ChevronLeft className="h-4 w-4 mr-1" />
                        Anterior
                      </Button>

                      {(currentQuizState?.currentQuestionIndex || 0) < orderedQuizQuestions.length - 1 ? (
                        <Button
                          className="bg-[#ffffff] hover:bg-[#e8e8e8] text-[#000000]"
                          onClick={goToNextQuizQuestion}
                        >
                          Seguinte
                          <ChevronRight className="h-4 w-4 ml-1" />
                        </Button>
                      ) : (
                        <Button
                          className="bg-[#ffffff] hover:bg-[#e8e8e8] text-[#000000]"
                          onClick={submitQuiz}
                        >
                          Submeter Questionário
                        </Button>
                      )}
                    </div>
                  </>
                )}
              </section>
            ) : (
              <section className="relative rounded-lg border border-border min-h-0 flex flex-col overflow-hidden bg-card">
                <div className="h-12 px-4 border-b border-border flex items-center">
                  <p className="text-sm text-foreground">Área da lição</p>
                </div>
                <div className="flex-1 p-4 space-y-3">
                  <p className="font-semibold text-foreground">
                    {currentLesson.type === "video" ? "Assiste e reflete" : "Lê e aplica"}
                  </p>
                  <p className="text-sm text-muted-foreground">
                    Trabalha o conteúdo no painel da lição e depois marca como concluída para acompanhar o teu progresso.
                  </p>
                  {currentLesson.videoUrl && (
                    <a
                      href={currentLesson.videoUrl}
                      target="_blank"
                      rel="noreferrer"
                      className="inline-flex items-center gap-2 text-accent hover:underline"
                    >
                      <PlayCircle className="h-4 w-4" />
                      Abrir vídeo da lição
                    </a>
                  )}
                </div>
                {!isMobile && workspaceMode === "split" && showPreviewPane && (
                  <button
                    type="button"
                    aria-label="Redimensionar colunas de conteúdo e preview"
                    title="Arrasta para redimensionar"
                    onMouseDown={() => setDragTarget("editor")}
                    className="absolute top-0 -right-1.5 h-full w-3 cursor-col-resize z-20"
                  >
                    <span className="mx-auto block h-full w-0.5 bg-border/70 hover:bg-accent transition-colors" />
                  </button>
                )}
              </section>
            ))}

            {showPreviewPane && (
              <section
                className={`min-h-0 overflow-hidden flex flex-col ${
                  isWorkspaceFocused ? "bg-card rounded-none border-0" : "bg-card rounded-lg border border-border"
                }`}
              >
                <div className="h-12 px-4 border-b border-border flex items-center justify-between">
                  <p className="text-sm font-medium text-foreground">Pré-visualização</p>
                  <div className="flex items-center gap-2">
                    {isCodeLesson && (
                      <>
                        <Button
                          size="icon"
                          variant="outline"
                          className="h-8"
                          onClick={previewGoBack}
                          title="Voltar no preview"
                        >
                          <ChevronLeft className="h-4 w-4" />
                        </Button>
                        <Button
                          size="icon"
                          variant="outline"
                          className="h-8"
                          onClick={previewGoForward}
                          title="Avançar no preview"
                        >
                          <ChevronRight className="h-4 w-4" />
                        </Button>
                        <Button
                          size="icon"
                          variant="outline"
                          className="h-8"
                          onClick={previewReload}
                          title="Recarregar preview"
                        >
                          <RefreshCw className="h-4 w-4" />
                        </Button>
                        <Button
                          size="icon"
                          variant="outline"
                          className="h-8"
                          onClick={previewOpenInNewTab}
                          title="Abrir em nova aba"
                        >
                          <ExternalLink className="h-4 w-4" />
                        </Button>
                      </>
                    )}
                    {isCodeLesson && !isMobile && (
                      <Button
                        size="icon"
                        variant="outline"
                        onClick={() => setWorkspaceMode((prev) => (prev === "preview" ? "split" : "preview"))}
                        title={workspaceMode === "preview" ? "Minimizar preview" : "Maximizar preview"}
                      >
                        {workspaceMode === "preview" ? <Minimize2 className="h-4 w-4" /> : <Maximize2 className="h-4 w-4" />}
                      </Button>
                    )}
                    {currentLesson.videoUrl && !isCodeLesson && !embeddedVideoUrl && (
                      <a
                        href={currentLesson.videoUrl}
                        target="_blank"
                        rel="noreferrer"
                        className="text-sm text-accent hover:underline"
                      >
                        Abrir vídeo
                      </a>
                    )}
                  </div>
                </div>
                {isCodeLesson ? (
                  <iframe
                    ref={previewFrameRef}
                    key={`${currentLesson.id}-${previewVersion}`}
                    title={`preview-${currentLesson.id}`}
                    className="w-full flex-1 bg-white"
                    sandbox="allow-scripts allow-modals allow-same-origin allow-forms allow-popups"
                    srcDoc={previewDoc}
                  />
                ) : embeddedVideoUrl ? (
                  <iframe
                    title={`video-${currentLesson.id}`}
                    className="w-full flex-1 bg-black"
                    src={embeddedVideoUrl}
                    allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                    allowFullScreen
                  />
                ) : (
                  <div className="flex-1 p-4 bg-surface-sunken text-muted-foreground">
                    <p className="font-medium text-foreground">Sem pré-visualização ao vivo para este tipo de lição</p>
                    <p className="text-sm mt-1">A pré-visualização interativa está disponível nas lições de código.</p>
                  </div>
                )}
              </section>
            )}
          </div>
        </main>
      </div>

      <footer className="h-16 bg-card border-t border-border px-4 md:px-8 flex items-center justify-between gap-3 shrink-0">
        <div>
          {prevLesson ? (
            <Link to={`/student/${course.id}/${prevLesson.id}`}>
              <Button variant="outline" className="font-body text-sm">
                <ChevronLeft className="h-4 w-4 mr-1" />
                Anterior
              </Button>
            </Link>
          ) : null}
        </div>

        {isQuizLesson ? (
          <div className="flex items-center gap-2">
            {currentQuizState?.submitted && !currentQuizState.passed && (
              <Button
                variant="outline"
                className="font-body text-sm"
                onClick={retryQuiz}
              >
                <RotateCcw className="h-4 w-4 mr-1" />
                Repetir exercício
              </Button>
            )}
            {currentQuizState?.submitted ? (
              <span
                className={`inline-flex items-center gap-1 rounded-full px-3 py-1 text-xs font-medium border ${
                  currentQuizState.passed
                    ? "bg-success/10 text-success border-success/40"
                    : "bg-destructive/10 text-destructive border-destructive/40"
                }`}
              >
                {currentQuizState.passed ? <CheckCircle2 className="h-3.5 w-3.5" /> : <XCircle className="h-3.5 w-3.5" />}
                {currentQuizState.score}% ({currentQuizState.correctCount}/{currentQuizState.total})
              </span>
            ) : quizPassedFromProgress ? (
              <span className="inline-flex items-center gap-1 rounded-full px-3 py-1 text-xs font-medium border bg-success/10 text-success border-success/40">
                <CheckCircle2 className="h-3.5 w-3.5" />
                Questionário concluído
              </span>
            ) : (
              <span className="text-xs text-muted-foreground">Responde e submete o questionário para avançar</span>
            )}
          </div>
        ) : isCodeLesson ? (
          <div className="flex items-center gap-2">
            <Button
              onClick={validateCodeTask}
              variant={isCodeTaskCorrect ? "outline" : "default"}
              className={
                isCodeTaskCorrect
                  ? "font-body text-sm"
                  : "bg-accent hover:bg-accent-hover text-accent-foreground font-body text-sm"
              }
            >
              <CheckCircle2 className="h-4 w-4 mr-1" />
              {isCodeTaskCorrect ? "Tarefa validada" : "Validar tarefa"}
            </Button>
            {currentCodeValidation ? (
              <span
                className={`text-xs ${
                  currentCodeValidation.isCorrect ? "text-success" : "text-destructive"
                }`}
              >
                {currentCodeValidation.message}
              </span>
            ) : (
              <span className="text-xs text-muted-foreground">
                Valida a tarefa para desbloquear a próxima lição.
              </span>
            )}
          </div>
        ) : (
          <Button
            onClick={() => toggleComplete(currentLesson.id)}
            variant={completedLessons.includes(currentLesson.id) ? "outline" : "default"}
            className={
              completedLessons.includes(currentLesson.id)
                ? "font-body text-sm"
                : "bg-accent hover:bg-accent-hover text-accent-foreground font-body text-sm"
            }
          >
            <CheckCircle2 className="h-4 w-4 mr-1" />
            {completedLessons.includes(currentLesson.id) ? "Concluída" : "Marcar como concluída"}
          </Button>
        )}

        <div>
          {nextLesson ? (
            advancementLocked ? (
              <Button disabled className="font-body text-sm">
                Seguinte
                <ChevronRight className="h-4 w-4 ml-1" />
              </Button>
            ) : (
              <Link to={`/student/${course.id}/${nextLesson.id}`}>
                <Button className="bg-accent hover:bg-accent-hover text-accent-foreground font-body text-sm">
                  Seguinte
                  <ChevronRight className="h-4 w-4 ml-1" />
                </Button>
              </Link>
            )
          ) : advancementLocked ? (
            <Button disabled className="font-body text-sm">Terminar Curso</Button>
          ) : (
            <Link to={`/course/${course.id}`}>
              <Button className="bg-accent hover:bg-accent-hover text-accent-foreground font-body text-sm">
                Terminar Curso
              </Button>
            </Link>
          )}
        </div>
      </footer>
    </div>
  );
};

export default StudentPlayerPage;
