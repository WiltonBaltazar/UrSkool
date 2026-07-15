import { type ComponentType, useEffect, useMemo, useState } from "react";
import { Link, useNavigate, useParams } from "react-router-dom";
import { useMutation, useQuery } from "@tanstack/react-query";
import {
  ArrowDown,
  ArrowLeft,
  ArrowUp,
  Check,
  ChevronDown,
  ChevronRight,
  Circle,
  Code2,
  FileQuestion,
  FileText,
  FolderOpen,
  Plus,
  Trash2,
  Video,
} from "lucide-react";
import Navbar from "@/components/Navbar";
import CodeHighlightEditor from "@/components/admin/CodeHighlightEditor";
import { Button } from "@/components/ui/button";
import { Card, CardContent } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Textarea } from "@/components/ui/textarea";
import { cn } from "@/lib/utils";
import { createCourse, fetchCourse, updateAdminCourse } from "@/lib/api";
import { useToast } from "@/hooks/use-toast";
import type { CodeValidationRule, LessonTextMediaType, QuizQuestion } from "@/lib/types";

type LessonType = "video" | "text" | "code" | "quiz" | "project";
type TabId = "details" | "curriculum";
type CodeTab = "html" | "css" | "js";

interface LessonDraft {
  title: string;
  duration: string;
  type: LessonType;
  content: string;
  videoUrl: string;
  language: string;
  htmlCode: string;
  cssCode: string;
  jsCode: string;
  textMediaType: LessonTextMediaType;
  textMediaImageUrl: string;
  textMediaYoutubeUrl: string;
  validationRules: CodeValidationRule[];
  quizQuestions: QuizQuestion[];
  quizPassPercentage: number;
  quizRandomizeQuestions: boolean;
}

interface SectionDraft {
  title: string;
  lessons: LessonDraft[];
}

const LESSON_TYPE_META: Record<LessonType, { label: string; Icon: ComponentType<{ className?: string }>; cls: string }> = {
  text: { label: "Texto", Icon: FileText, cls: "bg-sky-500/10 text-sky-700 border-sky-300 dark:text-sky-400 dark:border-sky-700" },
  video: { label: "Vídeo", Icon: Video, cls: "bg-purple-500/10 text-purple-700 border-purple-300 dark:text-purple-400 dark:border-purple-700" },
  code: { label: "Código", Icon: Code2, cls: "bg-emerald-500/10 text-emerald-700 border-emerald-300 dark:text-emerald-400 dark:border-emerald-700" },
  quiz: { label: "Quiz", Icon: FileQuestion, cls: "bg-amber-500/10 text-amber-700 border-amber-300 dark:text-amber-400 dark:border-amber-700" },
  project: { label: "Projeto", Icon: FolderOpen, cls: "bg-rose-500/10 text-rose-700 border-rose-300 dark:text-rose-400 dark:border-rose-700" },
};

const VALIDATION_KINDS: { value: CodeValidationRule["kind"]; label: string }[] = [
  { value: "html_includes", label: "HTML inclui" },
  { value: "css_includes", label: "CSS inclui" },
  { value: "js_includes", label: "JS inclui" },
  { value: "selector_exists", label: "Seletor existe" },
  { value: "text_includes", label: "Texto inclui" },
];

const createEmptyLesson = (): LessonDraft => ({
  title: "",
  duration: "",
  type: "text",
  content: "",
  videoUrl: "",
  language: "html",
  htmlCode: "",
  cssCode: "",
  jsCode: "",
  textMediaType: "none",
  textMediaImageUrl: "",
  textMediaYoutubeUrl: "",
  validationRules: [],
  quizQuestions: [],
  quizPassPercentage: 80,
  quizRandomizeQuestions: true,
});

const createEmptyQuestion = (): QuizQuestion => ({
  question: "",
  options: ["", "", "", ""],
  correctOptionIndex: 0,
});

const lk = (si: number, li: number) => `${si}-${li}`;

const contentLabel = (type: LessonType): string => {
  if (type === "quiz") return "Instruções do quiz (opcional)";
  if (type === "code" || type === "project") return "Instruções";
  if (type === "video") return "Descrição / notas (opcional)";
  return "Conteúdo / teoria";
};

const codeTabsForLanguage = (language: string): CodeTab[] => {
  if (language === "css") return ["css"];
  if (language === "javascript") return ["js"];
  if (language === "html+css") return ["html", "css"];
  if (language === "html+css+js") return ["html", "css", "js"];
  return ["html"];
};

const isLessonComplete = (lesson: LessonDraft): boolean => {
  if (!lesson.title.trim()) return false;
  if (lesson.type === "quiz") return lesson.quizQuestions.length > 0;
  if (lesson.type === "video") return Boolean(lesson.videoUrl.trim());
  return true;
};

const CourseBuilderPage = () => {
  const { toast } = useToast();
  const navigate = useNavigate();
  const { courseId } = useParams();
  const isEditMode = Boolean(courseId);

  const [activeTab, setActiveTab] = useState<TabId>("details");
  const [expandedLessons, setExpandedLessons] = useState<Set<string>>(new Set());
  const [codeTabByLesson, setCodeTabByLesson] = useState<Record<string, CodeTab>>({});
  const [pendingDeleteSection, setPendingDeleteSection] = useState<number | null>(null);
  const [pendingDeleteLesson, setPendingDeleteLesson] = useState<{ si: number; li: number } | null>(null);

  const [title, setTitle] = useState("");
  const [subtitle, setSubtitle] = useState("");
  const [instructor, setInstructor] = useState("");
  const [description, setDescription] = useState("");
  const [price, setPrice] = useState("");
  const [originalPrice, setOriginalPrice] = useState("");
  const [image, setImage] = useState("");
  const [category, setCategory] = useState("");
  const [level, setLevel] = useState("beginner");
  const [sections, setSections] = useState<SectionDraft[]>([]);

  const { data: existingCourse, isLoading: isLoadingCourse } = useQuery({
    queryKey: ["course-editor", courseId],
    queryFn: () => fetchCourse(courseId || ""),
    enabled: isEditMode,
  });

  useEffect(() => {
    if (!existingCourse) return;
    setTitle(existingCourse.title || "");
    setSubtitle(existingCourse.subtitle || "");
    setInstructor(existingCourse.instructor || "");
    setDescription(existingCourse.description || "");
    setPrice(String(existingCourse.price ?? ""));
    setOriginalPrice(String(existingCourse.originalPrice ?? ""));
    setImage(existingCourse.image || "");
    setCategory(existingCourse.category || "");
    setLevel(existingCourse.level || "beginner");
    setSections(
      (existingCourse.sections || []).map((section) => ({
        title: section.title,
        lessons: (section.lessons || []).map((lesson) => ({
          title: lesson.title,
          duration: lesson.duration || "",
          type: (lesson.type || "text") as LessonType,
          content: lesson.content || "",
          videoUrl: lesson.videoUrl || "",
          language: lesson.language || "html",
          htmlCode: lesson.htmlCode || "",
          cssCode: lesson.cssCode || "",
          jsCode: lesson.jsCode || "",
          textMediaType: (lesson.textMediaType || "none") as LessonTextMediaType,
          textMediaImageUrl: lesson.textMediaImageUrl || "",
          textMediaYoutubeUrl: lesson.textMediaYoutubeUrl || "",
          validationRules: lesson.validationRules || [],
          quizQuestions: lesson.quizQuestions || [],
          quizPassPercentage: lesson.quizPassPercentage ?? 80,
          quizRandomizeQuestions: lesson.quizRandomizeQuestions ?? true,
        })),
      })),
    );
  }, [existingCourse]);

  const createMutation = useMutation({
    mutationFn: createCourse,
    onSuccess: (course) => {
      toast({ title: "Curso criado", description: `"${course.title}" foi criado com sucesso.` });
      navigate(`/course/${course.id}`);
    },
    onError: (error: Error) => {
      toast({ variant: "destructive", title: "Falha ao criar curso", description: error.message });
    },
  });

  const updateMutation = useMutation({
    mutationFn: (p: Parameters<typeof createCourse>[0]) => updateAdminCourse(courseId || "", p),
    onSuccess: (course) => {
      toast({ title: "Curso atualizado", description: `"${course.title}" foi atualizado.` });
      navigate(`/course/${course.id}`);
    },
    onError: (error: Error) => {
      toast({ variant: "destructive", title: "Falha ao atualizar", description: error.message });
    },
  });

  const pending = createMutation.isPending || updateMutation.isPending;

  const updateSection = (si: number, fn: (s: SectionDraft) => SectionDraft) =>
    setSections((prev) => prev.map((s, i) => (i === si ? fn(s) : s)));

  const updateLesson = (si: number, li: number, fn: (l: LessonDraft) => LessonDraft) =>
    updateSection(si, (s) => ({ ...s, lessons: s.lessons.map((l, i) => (i === li ? fn(l) : l)) }));

  const addSection = () => setSections((prev) => [...prev, { title: "", lessons: [] }]);

  const removeSection = (si: number) => setPendingDeleteSection(si);

  const commitDeleteSection = () => {
    if (pendingDeleteSection === null) return;
    setSections((prev) => prev.filter((_, i) => i !== pendingDeleteSection));
    setPendingDeleteSection(null);
  };

  const moveSectionUp = (si: number) => setSections((prev) => {
    if (si === 0) return prev;
    const next = [...prev];
    [next[si - 1], next[si]] = [next[si], next[si - 1]];
    return next;
  });

  const moveSectionDown = (si: number) => setSections((prev) => {
    if (si >= prev.length - 1) return prev;
    const next = [...prev];
    [next[si], next[si + 1]] = [next[si + 1], next[si]];
    return next;
  });

  const addLesson = (si: number) => {
    const key = lk(si, sections[si]?.lessons.length ?? 0);
    setExpandedLessons((prev) => new Set([...prev, key]));
    updateSection(si, (s) => ({ ...s, lessons: [...s.lessons, createEmptyLesson()] }));
  };

  const removeLesson = (si: number, li: number) => setPendingDeleteLesson({ si, li });

  const commitDeleteLesson = () => {
    if (!pendingDeleteLesson) return;
    const { si, li } = pendingDeleteLesson;
    updateSection(si, (s) => ({ ...s, lessons: s.lessons.filter((_, i) => i !== li) }));
    setPendingDeleteLesson(null);
  };

  const moveLessonUp = (si: number, li: number) => updateSection(si, (s) => {
    if (li === 0) return s;
    const lessons = [...s.lessons];
    [lessons[li - 1], lessons[li]] = [lessons[li], lessons[li - 1]];
    return { ...s, lessons };
  });

  const moveLessonDown = (si: number, li: number) => updateSection(si, (s) => {
    if (li >= s.lessons.length - 1) return s;
    const lessons = [...s.lessons];
    [lessons[li], lessons[li + 1]] = [lessons[li + 1], lessons[li]];
    return { ...s, lessons };
  });

  const toggleLesson = (key: string) => setExpandedLessons((prev) => {
    const next = new Set(prev);
    if (next.has(key)) next.delete(key); else next.add(key);
    return next;
  });

  const addQuestion = (si: number, li: number) =>
    updateLesson(si, li, (l) => ({ ...l, quizQuestions: [...l.quizQuestions, createEmptyQuestion()] }));

  const removeQuestion = (si: number, li: number, qi: number) =>
    updateLesson(si, li, (l) => ({ ...l, quizQuestions: l.quizQuestions.filter((_, i) => i !== qi) }));

  const updateQuestion = (si: number, li: number, qi: number, fn: (q: QuizQuestion) => QuizQuestion) =>
    updateLesson(si, li, (l) => ({ ...l, quizQuestions: l.quizQuestions.map((q, i) => (i === qi ? fn(q) : q)) }));

  const addRule = (si: number, li: number) =>
    updateLesson(si, li, (l) => ({ ...l, validationRules: [...l.validationRules, { kind: "html_includes", value: "" }] }));

  const removeRule = (si: number, li: number, ri: number) =>
    updateLesson(si, li, (l) => ({ ...l, validationRules: l.validationRules.filter((_, i) => i !== ri) }));

  const updateRule = (si: number, li: number, ri: number, fn: (r: CodeValidationRule) => CodeValidationRule) =>
    updateLesson(si, li, (l) => ({ ...l, validationRules: l.validationRules.map((r, i) => (i === ri ? fn(r) : r)) }));

  const payload = useMemo(() => ({
    title, subtitle, instructor,
    rating: 0, reviewCount: 0, studentCount: 0,
    price: Number(price || 0),
    originalPrice: Number(originalPrice || 0),
    image, category, level,
    totalHours: sections.reduce((acc, s) => acc + s.lessons.length, 0),
    description,
    sections: sections
      .filter((s) => s.title.trim())
      .map((s) => ({
        title: s.title,
        lessons: s.lessons
          .filter((l) => l.title.trim())
          .map((l) => ({
            title: l.title,
            duration: l.duration,
            type: l.type,
            content: l.content || undefined,
            videoUrl: l.type === "video" ? l.videoUrl || undefined : undefined,
            language: (l.type === "code" || l.type === "project") ? l.language || undefined : undefined,
            htmlCode: (l.type === "code" || l.type === "project") ? l.htmlCode || undefined : undefined,
            cssCode: (l.type === "code" || l.type === "project") ? l.cssCode || undefined : undefined,
            jsCode: (l.type === "code" || l.type === "project") ? l.jsCode || undefined : undefined,
            textMediaType: l.type === "text" ? l.textMediaType : undefined,
            textMediaImageUrl: l.type === "text" && l.textMediaType === "image" ? l.textMediaImageUrl || undefined : undefined,
            textMediaYoutubeUrl: l.type === "text" && l.textMediaType === "youtube" ? l.textMediaYoutubeUrl || undefined : undefined,
            validationRules: (l.type === "code" || l.type === "project") && l.validationRules.length > 0 ? l.validationRules : undefined,
            quizQuestions: l.type === "quiz" && l.quizQuestions.length > 0 ? l.quizQuestions : undefined,
            quizPassPercentage: l.type === "quiz" ? l.quizPassPercentage : undefined,
            quizRandomizeQuestions: l.type === "quiz" ? l.quizRandomizeQuestions : undefined,
          })),
      })),
  }), [title, subtitle, instructor, price, originalPrice, image, category, level, sections, description]);

  const submit = () => {
    if (!title.trim()) {
      toast({ variant: "destructive", title: "Título obrigatório", description: "Preenche o título antes de guardar." });
      setActiveTab("details");
      return;
    }
    if (isEditMode) updateMutation.mutate(payload);
    else createMutation.mutate(payload);
  };

  if (isLoadingCourse) {
    return (
      <div className="min-h-screen bg-background">
        <Navbar />
        <div className="container mx-auto px-4 py-10 text-muted-foreground font-body">A carregar curso...</div>
      </div>
    );
  }

  const totalLessons = sections.reduce((acc, s) => acc + s.lessons.length, 0);

  return (
    <div className="min-h-screen bg-background">
      <Navbar />

      {/* Sticky tab bar */}
      <div className="sticky top-16 z-40 border-b border-border bg-card/95 backdrop-blur-md">
        <div className="container mx-auto px-4">
          <div className="flex items-center justify-between gap-4 h-14">
            <div className="flex items-center gap-2 min-w-0">
              <Link to="/courses">
                <Button variant="ghost" size="sm" className="gap-1 font-body text-sm shrink-0">
                  <ArrowLeft className="h-4 w-4" />
                  Cursos
                </Button>
              </Link>
              <span className="text-muted-foreground hidden sm:block">/</span>
              <span className="text-sm font-medium font-body truncate hidden sm:block text-muted-foreground">
                {title || (isEditMode ? "Editar curso" : "Novo curso")}
              </span>
            </div>

            <div className="flex items-center gap-3 shrink-0">
              <div className="flex items-center bg-surface-sunken rounded-lg p-1 gap-0.5">
                {(["details", "curriculum"] as TabId[]).map((tab) => (
                  <button
                    key={tab}
                    onClick={() => setActiveTab(tab)}
                    className={cn(
                      "px-3 py-1.5 rounded-md text-sm font-body font-medium transition-colors whitespace-nowrap",
                      activeTab === tab
                        ? "bg-card text-foreground shadow-sm"
                        : "text-muted-foreground hover:text-foreground",
                    )}
                  >
                    {tab === "details" ? "Detalhes" : `Currículo${totalLessons > 0 ? ` (${totalLessons})` : ""}`}
                  </button>
                ))}
              </div>
              <Button
                onClick={submit}
                disabled={pending || !title.trim()}
                className="bg-accent hover:bg-accent-hover text-accent-foreground font-body"
              >
                {pending ? "A guardar..." : isEditMode ? "Guardar" : "Criar curso"}
              </Button>
            </div>
          </div>
        </div>
      </div>

      <div className="container mx-auto px-4 py-8">

        {/* ── DETAILS TAB ── */}
        {activeTab === "details" && (
          <div className="max-w-2xl space-y-6">
            <div>
              <h1 className="font-display text-2xl">{isEditMode ? "Detalhes do curso" : "Novo curso"}</h1>
              <p className="text-muted-foreground text-sm font-body mt-1">
                Informações visíveis para os estudantes na página do curso.
              </p>
            </div>

            <Card>
              <CardContent className="pt-6 grid md:grid-cols-2 gap-5">
                <div className="md:col-span-2 space-y-1.5">
                  <Label className="font-body">Título <span className="text-destructive">*</span></Label>
                  <Input value={title} onChange={(e) => setTitle(e.target.value)} placeholder="Ex: Introdução ao HTML e CSS" />
                </div>
                <div className="md:col-span-2 space-y-1.5">
                  <Label className="font-body">Subtítulo</Label>
                  <Input value={subtitle} onChange={(e) => setSubtitle(e.target.value)} placeholder="Uma frase curta que descreve o valor do curso" />
                </div>
                <div className="space-y-1.5">
                  <Label className="font-body">Instrutor</Label>
                  <Input value={instructor} onChange={(e) => setInstructor(e.target.value)} placeholder="Nome do instrutor" />
                </div>
                <div className="space-y-1.5">
                  <Label className="font-body">Categoria</Label>
                  <Input
                    list="category-suggestions"
                    value={category}
                    onChange={(e) => setCategory(e.target.value)}
                    placeholder="Ex: Desenvolvimento Web"
                  />
                  <datalist id="category-suggestions">
                    <option value="Desenvolvimento Web" />
                    <option value="Design Web" />
                    <option value="Design de UI" />
                    <option value="JavaScript" />
                    <option value="Python" />
                    <option value="Marketing Digital" />
                    <option value="Negócios" />
                  </datalist>
                </div>
                <div className="space-y-1.5">
                  <Label className="font-body">Nível</Label>
                  <select
                    value={level}
                    onChange={(e) => setLevel(e.target.value)}
                    className="h-10 w-full rounded-md border border-input bg-background px-3 font-body text-sm"
                  >
                    <option value="beginner">Iniciante</option>
                    <option value="intermediate">Intermédio</option>
                    <option value="advanced">Avançado</option>
                  </select>
                </div>
                <div className="space-y-1.5">
                  <Label className="font-body">Preço (MZN)</Label>
                  <Input type="number" min="0" value={price} onChange={(e) => setPrice(e.target.value)} placeholder="0 para gratuito" />
                </div>
                <div className="space-y-1.5">
                  <Label className="font-body">Preço original (MZN)</Label>
                  <Input type="number" min="0" value={originalPrice} onChange={(e) => setOriginalPrice(e.target.value)} placeholder="Antes do desconto" />
                </div>
                <div className="md:col-span-2 space-y-1.5">
                  <Label className="font-body">Imagem de capa (URL)</Label>
                  <Input value={image} onChange={(e) => setImage(e.target.value)} placeholder="https://..." />
                </div>
                <div className="md:col-span-2 space-y-1.5">
                  <Label className="font-body">Descrição</Label>
                  <Textarea
                    value={description}
                    onChange={(e) => setDescription(e.target.value)}
                    rows={5}
                    placeholder="O que os estudantes vão aprender, para quem é o curso, pré-requisitos..."
                  />
                </div>
              </CardContent>
            </Card>
          </div>
        )}

        {/* ── CURRICULUM TAB ── */}
        {activeTab === "curriculum" && (
          <div className="space-y-3 max-w-4xl">
            <div className="flex items-center justify-between mb-5">
              <div>
                <h1 className="font-display text-2xl">Currículo</h1>
                <p className="text-muted-foreground text-sm font-body mt-1">
                  {sections.length === 0
                    ? "Sem secções ainda."
                    : `${sections.length} secção${sections.length !== 1 ? "ões" : ""} · ${totalLessons} lição${totalLessons !== 1 ? "ões" : ""}`}
                </p>
              </div>
              <Button variant="outline" onClick={addSection} className="font-body gap-1">
                <Plus className="h-4 w-4" />
                Secção
              </Button>
            </div>

            {sections.length === 0 && (
              <Card className="border-dashed">
                <CardContent className="py-14 flex flex-col items-center gap-3 text-center">
                  <FolderOpen className="h-10 w-10 text-muted-foreground/30" />
                  <p className="text-muted-foreground font-body text-sm max-w-xs">
                    Começa por adicionar uma secção — por exemplo "Introdução" ou "Módulo 1".
                  </p>
                  <Button onClick={addSection} className="bg-accent hover:bg-accent-hover text-accent-foreground font-body gap-1">
                    <Plus className="h-4 w-4" />
                    Adicionar primeira secção
                  </Button>
                </CardContent>
              </Card>
            )}

            {sections.map((section, si) => (
              <Card key={si} className="border-border overflow-hidden">
                {/* Section header */}
                <div className="flex items-center gap-2 px-4 py-2.5 bg-surface-sunken border-b border-border">
                  <span className="text-xs font-bold text-muted-foreground w-5 shrink-0 tabular-nums">{si + 1}</span>
                  <Input
                    value={section.title}
                    onChange={(e) => updateSection(si, (s) => ({ ...s, title: e.target.value }))}
                    placeholder="Título da secção"
                    className="h-8 font-body font-medium text-sm bg-transparent border-0 shadow-none focus-visible:ring-0 px-0"
                  />
                  <div className="flex items-center gap-0.5 ml-auto shrink-0">
                    <Button variant="ghost" size="icon" className="h-7 w-7" onClick={() => moveSectionUp(si)} disabled={si === 0} title="Mover para cima">
                      <ArrowUp className="h-3.5 w-3.5" />
                    </Button>
                    <Button variant="ghost" size="icon" className="h-7 w-7" onClick={() => moveSectionDown(si)} disabled={si === sections.length - 1} title="Mover para baixo">
                      <ArrowDown className="h-3.5 w-3.5" />
                    </Button>
                    <Button variant="ghost" size="sm" className="h-7 px-2 font-body text-xs gap-1 ml-1" onClick={() => addLesson(si)}>
                      <Plus className="h-3.5 w-3.5" />
                      Lição
                    </Button>
                    <Button variant="ghost" size="icon" className="h-7 w-7 text-destructive/70 hover:text-destructive" onClick={() => removeSection(si)} title="Remover secção">
                      <Trash2 className="h-3.5 w-3.5" />
                    </Button>
                  </div>
                </div>

                {/* Lessons list */}
                <CardContent className="p-3 space-y-2">
                  {section.lessons.length === 0 && (
                    <button
                      onClick={() => addLesson(si)}
                      className="w-full rounded-lg border border-dashed border-border py-5 text-xs font-body text-muted-foreground hover:text-foreground hover:border-foreground/30 transition-colors flex items-center justify-center gap-1.5"
                    >
                      <Plus className="h-3.5 w-3.5" />
                      Adicionar primeira lição
                    </button>
                  )}

                  {section.lessons.map((lesson, li) => {
                    const key = lk(si, li);
                    const isExpanded = expandedLessons.has(key);
                    const meta = LESSON_TYPE_META[lesson.type];
                    const LessonIcon = meta.Icon;
                    const codeTab = codeTabByLesson[key] ?? "html";

                    return (
                      <div key={li} className="rounded-lg border border-border overflow-hidden">
                        {/* Lesson row */}
                        <div
                          className="flex items-center gap-2 px-3 py-2.5 cursor-pointer hover:bg-accent-soft/30 transition-colors select-none"
                          onClick={() => toggleLesson(key)}
                        >
                          <span className={cn("inline-flex items-center gap-1 rounded border px-2 py-0.5 text-xs font-medium shrink-0", meta.cls)}>
                            <LessonIcon className="h-3 w-3" />
                            {meta.label}
                          </span>
                          <span className="text-sm font-body flex-1 truncate">
                            {lesson.title || <span className="text-muted-foreground italic">Sem título</span>}
                          </span>
                          {lesson.duration && (
                            <span className="text-xs text-muted-foreground shrink-0 hidden sm:block">{lesson.duration}</span>
                          )}
                          <Circle
                            className={cn(
                              "h-2.5 w-2.5 shrink-0 hidden sm:block",
                              isLessonComplete(lesson)
                                ? "fill-emerald-500 text-emerald-500"
                                : "fill-amber-400 text-amber-400",
                            )}
                          />
                          <div className="flex items-center gap-0.5 shrink-0" onClick={(e) => e.stopPropagation()}>
                            <Button variant="ghost" size="icon" className="h-6 w-6" onClick={() => moveLessonUp(si, li)} disabled={li === 0} title="Mover para cima">
                              <ArrowUp className="h-3 w-3" />
                            </Button>
                            <Button variant="ghost" size="icon" className="h-6 w-6" onClick={() => moveLessonDown(si, li)} disabled={li === section.lessons.length - 1} title="Mover para baixo">
                              <ArrowDown className="h-3 w-3" />
                            </Button>
                            <Button variant="ghost" size="icon" className="h-6 w-6 text-destructive/70 hover:text-destructive" onClick={() => removeLesson(si, li)} title="Remover lição">
                              <Trash2 className="h-3 w-3" />
                            </Button>
                          </div>
                          {isExpanded
                            ? <ChevronDown className="h-4 w-4 text-muted-foreground shrink-0" />
                            : <ChevronRight className="h-4 w-4 text-muted-foreground shrink-0" />}
                        </div>

                        {/* Lesson editor (expanded) */}
                        {isExpanded && (
                          <div className="border-t border-border p-4 space-y-5 bg-background">

                            {/* Type selector */}
                            <div className="space-y-1.5">
                              <p className="text-xs uppercase tracking-wide text-muted-foreground font-body font-medium">Tipo</p>
                              <div className="flex flex-wrap gap-1.5">
                                {(Object.keys(LESSON_TYPE_META) as LessonType[]).map((t) => {
                                  const tm = LESSON_TYPE_META[t];
                                  const TIcon = tm.Icon;
                                  return (
                                    <button
                                      key={t}
                                      onClick={() => updateLesson(si, li, (l) => ({ ...l, type: t }))}
                                      className={cn(
                                        "inline-flex items-center gap-1.5 rounded-md border px-3 py-1.5 text-xs font-medium transition-colors",
                                        lesson.type === t
                                          ? tm.cls
                                          : "border-border text-muted-foreground hover:text-foreground hover:bg-surface-sunken",
                                      )}
                                    >
                                      <TIcon className="h-3.5 w-3.5" />
                                      {tm.label}
                                    </button>
                                  );
                                })}
                              </div>
                            </div>

                            {/* Title + Duration */}
                            <div className="grid grid-cols-[1fr_130px] gap-3">
                              <div className="space-y-1.5">
                                <Label className="font-body">Título</Label>
                                <Input
                                  value={lesson.title}
                                  onChange={(e) => updateLesson(si, li, (l) => ({ ...l, title: e.target.value }))}
                                  placeholder="Título da lição"
                                />
                              </div>
                              <div className="space-y-1.5">
                                <Label className="font-body">Duração</Label>
                                <Input
                                  value={lesson.duration}
                                  onChange={(e) => updateLesson(si, li, (l) => ({ ...l, duration: e.target.value }))}
                                  placeholder="Ex: 10min"
                                />
                              </div>
                            </div>

                            {/* Content */}
                            <div className="space-y-1.5">
                              <Label className="font-body">{contentLabel(lesson.type)}</Label>
                              <Textarea
                                value={lesson.content}
                                onChange={(e) => updateLesson(si, li, (l) => ({ ...l, content: e.target.value }))}
                                rows={4}
                                placeholder="Teoria, objetivos ou instruções para o estudante..."
                              />
                            </div>

                            {/* VIDEO */}
                            {lesson.type === "video" && (
                              <div className="space-y-1.5">
                                <Label className="font-body">URL do vídeo</Label>
                                <Input
                                  value={lesson.videoUrl}
                                  onChange={(e) => updateLesson(si, li, (l) => ({ ...l, videoUrl: e.target.value }))}
                                  placeholder="https://youtube.com/watch?v=..."
                                />
                              </div>
                            )}

                            {/* TEXT media */}
                            {lesson.type === "text" && (
                              <div className="space-y-3">
                                <div className="space-y-1.5">
                                  <p className="text-xs uppercase tracking-wide text-muted-foreground font-body font-medium">Média de apoio</p>
                                  <div className="flex gap-1.5">
                                    {(["none", "image", "youtube"] as const).map((mt) => (
                                      <button
                                        key={mt}
                                        onClick={() => updateLesson(si, li, (l) => ({ ...l, textMediaType: mt }))}
                                        className={cn(
                                          "rounded-md border px-3 py-1.5 text-xs font-medium transition-colors",
                                          lesson.textMediaType === mt
                                            ? "bg-accent text-accent-foreground border-accent"
                                            : "border-border text-muted-foreground hover:text-foreground",
                                        )}
                                      >
                                        {mt === "none" ? "Nenhuma" : mt === "image" ? "Imagem" : "YouTube"}
                                      </button>
                                    ))}
                                  </div>
                                </div>
                                {lesson.textMediaType === "image" && (
                                  <div className="space-y-1.5">
                                    <Label className="font-body">URL da imagem</Label>
                                    <Input
                                      value={lesson.textMediaImageUrl}
                                      onChange={(e) => updateLesson(si, li, (l) => ({ ...l, textMediaImageUrl: e.target.value }))}
                                      placeholder="https://..."
                                    />
                                  </div>
                                )}
                                {lesson.textMediaType === "youtube" && (
                                  <div className="space-y-1.5">
                                    <Label className="font-body">URL do YouTube</Label>
                                    <Input
                                      value={lesson.textMediaYoutubeUrl}
                                      onChange={(e) => updateLesson(si, li, (l) => ({ ...l, textMediaYoutubeUrl: e.target.value }))}
                                      placeholder="https://youtube.com/watch?v=..."
                                    />
                                  </div>
                                )}
                              </div>
                            )}

                            {/* CODE / PROJECT */}
                            {(lesson.type === "code" || lesson.type === "project") && (
                              <div className="space-y-4">
                                <div className="space-y-1.5">
                                  <Label className="font-body">Linguagem principal</Label>
                                  <select
                                    value={lesson.language}
                                    onChange={(e) => updateLesson(si, li, (l) => ({ ...l, language: e.target.value }))}
                                    className="h-10 w-52 rounded-md border border-input bg-background px-3 font-body text-sm"
                                  >
                                    <option value="html">HTML</option>
                                    <option value="css">CSS</option>
                                    <option value="javascript">JavaScript</option>
                                    <option value="html+css">HTML + CSS</option>
                                    <option value="html+css+js">HTML + CSS + JS</option>
                                  </select>
                                </div>

                                <div className="space-y-2">
                                  <Label className="font-body">Código inicial</Label>
                                  {(() => {
                                    const tabs = codeTabsForLanguage(lesson.language);
                                    const activeCodeTab = tabs.includes(codeTab) ? codeTab : tabs[0];
                                    return (
                                      <>
                                        {tabs.length > 1 && (
                                          <div className="flex gap-1.5">
                                            {tabs.map((tab) => (
                                              <button
                                                key={tab}
                                                onClick={() => setCodeTabByLesson((prev) => ({ ...prev, [key]: tab }))}
                                                className={cn(
                                                  "rounded-md px-3 py-1 text-xs font-medium border uppercase tracking-wide transition-colors",
                                                  activeCodeTab === tab
                                                    ? "bg-foreground text-background border-foreground"
                                                    : "border-border text-muted-foreground hover:text-foreground",
                                                )}
                                              >
                                                {tab}
                                              </button>
                                            ))}
                                          </div>
                                        )}
                                        {activeCodeTab === "html" && (
                                          <CodeHighlightEditor
                                            language="html"
                                            value={lesson.htmlCode}
                                            onChange={(v) => updateLesson(si, li, (l) => ({ ...l, htmlCode: v }))}
                                          />
                                        )}
                                        {activeCodeTab === "css" && (
                                          <CodeHighlightEditor
                                            language="css"
                                            value={lesson.cssCode}
                                            onChange={(v) => updateLesson(si, li, (l) => ({ ...l, cssCode: v }))}
                                          />
                                        )}
                                        {activeCodeTab === "js" && (
                                          <CodeHighlightEditor
                                            language="js"
                                            value={lesson.jsCode}
                                            onChange={(v) => updateLesson(si, li, (l) => ({ ...l, jsCode: v }))}
                                          />
                                        )}
                                      </>
                                    );
                                  })()}
                                </div>

                                {/* Validation rules */}
                                <div className="space-y-2">
                                  <div className="flex items-center justify-between">
                                    <Label className="font-body">Regras de validação</Label>
                                    <Button variant="outline" size="sm" className="h-7 gap-1 text-xs font-body" onClick={() => addRule(si, li)}>
                                      <Plus className="h-3.5 w-3.5" />
                                      Regra
                                    </Button>
                                  </div>
                                  {lesson.validationRules.length === 0 && (
                                    <p className="text-xs text-muted-foreground font-body">Sem regras — a lição é concluída sem validação de código.</p>
                                  )}
                                  {lesson.validationRules.map((rule, ri) => (
                                    <div key={ri} className="flex items-center gap-2">
                                      <select
                                        value={rule.kind}
                                        onChange={(e) => updateRule(si, li, ri, (r) => ({ ...r, kind: e.target.value as CodeValidationRule["kind"] }))}
                                        className="h-9 shrink-0 rounded-md border border-input bg-background px-2 font-body text-xs"
                                      >
                                        {VALIDATION_KINDS.map((k) => (
                                          <option key={k.value} value={k.value}>{k.label}</option>
                                        ))}
                                      </select>
                                      <Input
                                        value={rule.value}
                                        onChange={(e) => updateRule(si, li, ri, (r) => ({ ...r, value: e.target.value }))}
                                        placeholder="Valor a verificar..."
                                        className="h-9 font-body text-sm flex-1"
                                      />
                                      <Button variant="ghost" size="icon" className="h-9 w-9 text-destructive/70 hover:text-destructive shrink-0" onClick={() => removeRule(si, li, ri)}>
                                        <Trash2 className="h-3.5 w-3.5" />
                                      </Button>
                                    </div>
                                  ))}
                                </div>
                              </div>
                            )}

                            {/* QUIZ */}
                            {lesson.type === "quiz" && (
                              <div className="space-y-4">
                                <div className="flex flex-wrap gap-5">
                                  <div className="space-y-1.5">
                                    <Label className="font-body">Nota mínima (%)</Label>
                                    <Input
                                      type="number"
                                      min={1}
                                      max={100}
                                      value={lesson.quizPassPercentage}
                                      onChange={(e) => updateLesson(si, li, (l) => ({ ...l, quizPassPercentage: Number(e.target.value) }))}
                                      className="w-24"
                                    />
                                  </div>
                                  <div className="space-y-1.5">
                                    <Label className="font-body">Perguntas aleatórias</Label>
                                    <div className="flex items-center gap-2 h-10">
                                      <button
                                        type="button"
                                        role="switch"
                                        aria-checked={lesson.quizRandomizeQuestions}
                                        onClick={() => updateLesson(si, li, (l) => ({ ...l, quizRandomizeQuestions: !l.quizRandomizeQuestions }))}
                                        className={cn(
                                          "relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors focus-visible:outline-none",
                                          lesson.quizRandomizeQuestions ? "bg-accent" : "bg-muted",
                                        )}
                                      >
                                        <span className={cn(
                                          "pointer-events-none inline-block h-5 w-5 rounded-full bg-white shadow-lg transition-transform",
                                          lesson.quizRandomizeQuestions ? "translate-x-5" : "translate-x-0",
                                        )} />
                                      </button>
                                      <span className="text-sm font-body text-muted-foreground">
                                        {lesson.quizRandomizeQuestions ? "Sim" : "Não"}
                                      </span>
                                    </div>
                                  </div>
                                </div>

                                <div className="space-y-3">
                                  <div className="flex items-center justify-between">
                                    <Label className="font-body">
                                      {lesson.quizQuestions.length} pergunta{lesson.quizQuestions.length !== 1 ? "s" : ""}
                                    </Label>
                                    <Button variant="outline" size="sm" className="h-7 gap-1 text-xs font-body" onClick={() => addQuestion(si, li)}>
                                      <Plus className="h-3.5 w-3.5" />
                                      Pergunta
                                    </Button>
                                  </div>
                                  {lesson.quizQuestions.length === 0 && (
                                    <p className="text-xs text-muted-foreground font-body">Sem perguntas ainda. O quiz não ficará disponível sem perguntas.</p>
                                  )}
                                  {lesson.quizQuestions.map((q, qi) => (
                                    <div key={qi} className="rounded-lg border border-border p-3 space-y-3 bg-surface-sunken">
                                      <div className="flex items-start gap-2">
                                        <span className="shrink-0 text-xs font-bold text-muted-foreground mt-2.5 w-5 tabular-nums">{qi + 1}.</span>
                                        <Input
                                          value={q.question}
                                          onChange={(e) => updateQuestion(si, li, qi, (question) => ({ ...question, question: e.target.value }))}
                                          placeholder="Escreve a pergunta aqui..."
                                          className="flex-1"
                                        />
                                        <Button variant="ghost" size="icon" className="h-9 w-9 text-destructive/70 hover:text-destructive shrink-0" onClick={() => removeQuestion(si, li, qi)}>
                                          <Trash2 className="h-3.5 w-3.5" />
                                        </Button>
                                      </div>
                                      <p className="pl-7 text-xs text-muted-foreground font-body">Clica no círculo para marcar a resposta correcta</p>
                                      <div className="grid grid-cols-2 gap-2 pl-7">
                                        {q.options.map((opt, oi) => {
                                          const isCorrect = q.correctOptionIndex === oi;
                                          return (
                                            <div
                                              key={oi}
                                              className={cn(
                                                "flex items-center gap-2 rounded-md border p-2 transition-colors",
                                                isCorrect
                                                  ? "border-emerald-500/50 bg-emerald-500/8"
                                                  : "border-transparent bg-background",
                                              )}
                                            >
                                              <button
                                                type="button"
                                                title="Marcar como correcta"
                                                onClick={() => updateQuestion(si, li, qi, (question) => ({ ...question, correctOptionIndex: oi }))}
                                                className={cn(
                                                  "h-5 w-5 rounded-full border-2 shrink-0 flex items-center justify-center transition-colors",
                                                  isCorrect
                                                    ? "border-emerald-500 bg-emerald-500 text-white"
                                                    : "border-muted-foreground/40 hover:border-emerald-400",
                                                )}
                                              >
                                                {isCorrect && <Check className="h-3 w-3" />}
                                              </button>
                                              <Input
                                                value={opt}
                                                onChange={(e) => updateQuestion(si, li, qi, (question) => {
                                                  const options = [...question.options];
                                                  options[oi] = e.target.value;
                                                  return { ...question, options };
                                                })}
                                                placeholder={`Opção ${String.fromCharCode(65 + oi)}`}
                                                className={cn(
                                                  "h-8 text-sm font-body border-0 bg-transparent shadow-none focus-visible:ring-0 p-0",
                                                  isCorrect ? "font-medium text-emerald-700 dark:text-emerald-400" : "",
                                                )}
                                              />
                                            </div>
                                          );
                                        })}
                                      </div>
                                    </div>
                                  ))}
                                </div>
                              </div>
                            )}

                          </div>
                        )}
                      </div>
                    );
                  })}
                </CardContent>
              </Card>
            ))}

            {sections.length > 0 && (
              <button
                onClick={addSection}
                className="w-full rounded-lg border border-dashed border-border py-3 text-sm font-body text-muted-foreground hover:text-foreground hover:border-foreground/30 transition-colors flex items-center justify-center gap-1.5"
              >
                <Plus className="h-4 w-4" />
                Adicionar secção
              </button>
            )}

            {/* Delete section confirmation */}
            {pendingDeleteSection !== null && (
              <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
                <div className="w-full max-w-sm rounded-lg border border-border bg-card p-5 space-y-4 shadow-xl">
                  <div className="space-y-1">
                    <p className="font-semibold text-foreground font-body">Remover secção?</p>
                    <p className="text-sm text-muted-foreground font-body">
                      A secção <span className="font-medium text-foreground">"{sections[pendingDeleteSection]?.title || "Sem título"}"</span> e todas as suas lições serão removidas. Esta ação não pode ser desfeita.
                    </p>
                  </div>
                  <div className="flex justify-end gap-2">
                    <Button variant="outline" className="font-body" onClick={() => setPendingDeleteSection(null)}>Cancelar</Button>
                    <Button variant="destructive" className="font-body" onClick={commitDeleteSection}>Remover</Button>
                  </div>
                </div>
              </div>
            )}

            {/* Delete lesson confirmation */}
            {pendingDeleteLesson !== null && (
              <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
                <div className="w-full max-w-sm rounded-lg border border-border bg-card p-5 space-y-4 shadow-xl">
                  <div className="space-y-1">
                    <p className="font-semibold text-foreground font-body">Remover lição?</p>
                    <p className="text-sm text-muted-foreground font-body">
                      A lição <span className="font-medium text-foreground">"{sections[pendingDeleteLesson.si]?.lessons[pendingDeleteLesson.li]?.title || "Sem título"}"</span> será removida permanentemente.
                    </p>
                  </div>
                  <div className="flex justify-end gap-2">
                    <Button variant="outline" className="font-body" onClick={() => setPendingDeleteLesson(null)}>Cancelar</Button>
                    <Button variant="destructive" className="font-body" onClick={commitDeleteLesson}>Remover</Button>
                  </div>
                </div>
              </div>
            )}
          </div>
        )}
      </div>
    </div>
  );
};

export default CourseBuilderPage;
