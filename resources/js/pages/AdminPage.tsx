import { useEffect, useMemo, useState } from "react";
import {
  Activity,
  BarChart3,
  Bell,
  BookOpen,
  ChevronDown,
  ChevronRight,
  Clock3,
  Code2,
  Eye,
  Banknote,
  Edit3,
  FileText,
  GripVertical,
  LayoutDashboard,
  LineChart,
  LifeBuoy,
  ListChecks,
  LogOut,
  Moon,
  MousePointerClick,
  Plus,
  Search,
  Save,
  Settings,
  Shield,
  Trash2,
  User,
  Users,
  Video,
} from "lucide-react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import CodeHighlightEditor from "@/components/admin/CodeHighlightEditor";
import RichTextEditor from "@/components/admin/RichTextEditor";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Textarea } from "@/components/ui/textarea";
import { Badge } from "@/components/ui/badge";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";
import { Switch } from "@/components/ui/switch";
import { Avatar, AvatarFallback, AvatarImage } from "@/components/ui/avatar";
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import {
  createCourse,
  deleteAdminCourse,
  fetchAuthUser,
  fetchCourse,
  fetchAdminDashboard,
  logout,
  updateAdminCourse,
  updateAdminSettings,
} from "@/lib/api";
import { formatMzn, toCategoryPt, toEnrollmentStatusPt, toLevelPt } from "@/lib/labels";
import type { AdminSettings, LessonWorkspaceFile } from "@/lib/types";
import { cn } from "@/lib/utils";
import { useToast } from "@/hooks/use-toast";

interface AdminLesson {
  id: string;
  title: string;
  duration: string;
  videoUrl: string;
  language: string;
  content: string;
  starterCode: string;
  htmlCode: string;
  cssCode: string;
  jsCode: string;
  workspaceFiles: LessonWorkspaceFile[];
  entryHtmlFileId: string;
  quizQuestions: AdminQuizQuestion[];
  quizPassPercentage: number;
  quizRandomizeQuestions: boolean;
  isFree: boolean;
  type: "video" | "text" | "code" | "quiz" | "project";
}

interface AdminQuizQuestion {
  id: string;
  question: string;
  options: string[];
  correctOptionIndex: number;
}

interface AdminSection {
  id: string;
  title: string;
  lessons: AdminLesson[];
}

const clampQuizPassPercentage = (value: number): number => {
  if (!Number.isFinite(value)) return 80;
  return Math.max(1, Math.min(100, Math.round(value)));
};

const createEmptyQuizQuestion = (): AdminQuizQuestion => ({
  id: crypto.randomUUID(),
  question: "Nova pergunta",
  options: ["Opção 1", "Opção 2", "Opção 3", "Opção 4"],
  correctOptionIndex: 0,
});

const normalizeAdminQuizQuestions = (questions?: Array<{
  id?: string;
  question?: string | null;
  options?: (string | null)[] | null;
  correctOptionIndex?: number | null;
}> | null): AdminQuizQuestion[] => {
  const source = Array.isArray(questions) && questions.length > 0 ? questions : [createEmptyQuizQuestion()];

  return source.map((question) => {
    const options = (Array.isArray(question.options) ? question.options : [])
      .map((option) => (option || "").trim())
      .filter(Boolean);
    const safeOptions = options.length >= 2 ? options : ["Opção 1", "Opção 2"];
    const rawIndex = Number(question.correctOptionIndex ?? 0);
    const safeIndex = Number.isInteger(rawIndex) ? rawIndex : 0;

    return {
      id: question.id || crypto.randomUUID(),
      question: (question.question || "").trim(),
      options: safeOptions,
      correctOptionIndex: Math.max(0, Math.min(safeOptions.length - 1, safeIndex)),
    };
  });
};

const buildWorkspaceDefaults = (lessonId?: string, htmlCode = "", cssCode = "", jsCode = ""): LessonWorkspaceFile[] => {
  const base = lessonId || crypto.randomUUID();

  return [
    {
      id: `${base}-index-html`,
      name: "index.html",
      language: "html",
      content: htmlCode,
    },
    {
      id: `${base}-style-css`,
      name: "style.css",
      language: "css",
      content: cssCode,
    },
    {
      id: `${base}-script-js`,
      name: "script.js",
      language: "js",
      content: jsCode,
    },
  ];
};

const normalizeAdminWorkspaceFiles = (
  workspaceFiles: LessonWorkspaceFile[] | null | undefined,
  lessonId?: string,
  htmlCode = "",
  cssCode = "",
  jsCode = "",
): LessonWorkspaceFile[] => {
  const source = Array.isArray(workspaceFiles) ? workspaceFiles : [];
  const normalized = source
    .map((file) => ({
      id: (file.id || "").trim(),
      name: (file.name || "").trim(),
      language: file.language,
      content: file.content || "",
    }))
    .filter((file) => file.id && file.name && ["html", "css", "js"].includes(file.language));

  if (normalized.length > 0) {
    return normalized;
  }

  return buildWorkspaceDefaults(lessonId, htmlCode, cssCode, jsCode);
};

const normalizeEntryHtmlFileId = (workspaceFiles: LessonWorkspaceFile[], entryHtmlFileId?: string | null): string => {
  const preferred = (entryHtmlFileId || "").trim();
  if (preferred && workspaceFiles.some((file) => file.id === preferred && file.language === "html")) {
    return preferred;
  }

  return workspaceFiles.find((file) => file.name.toLowerCase() === "index.html" && file.language === "html")?.id
    || workspaceFiles.find((file) => file.language === "html")?.id
    || "";
};

const createEmptyLesson = (): AdminLesson => {
  const id = crypto.randomUUID();
  const workspaceFiles = buildWorkspaceDefaults(id, "", "", "");

  return {
    id,
    title: "Nova lição",
    duration: "05:00",
    videoUrl: "",
    language: "html",
    content: "",
    starterCode: "",
    htmlCode: "",
    cssCode: "",
    jsCode: "",
    workspaceFiles,
    entryHtmlFileId: normalizeEntryHtmlFileId(workspaceFiles),
    quizQuestions: [createEmptyQuizQuestion()],
    quizPassPercentage: 80,
    quizRandomizeQuestions: true,
    isFree: false,
    type: "code",
  };
};

const createEmptySection = (): AdminSection => ({
  id: crypto.randomUUID(),
  title: "Nova secção",
  lessons: [],
});

const defaultSettings: AdminSettings = {
  platformName: "UrSkool",
  supportEmail: "support@urskool.test",
  currency: "MZN",
  maintenanceMode: false,
  allowSelfSignup: true,
  defaultCourseVisibility: "public",
};

const formatDate = (value?: string) => {
  if (!value) return "-";
  return new Date(value).toLocaleDateString("pt-MZ");
};

const CATEGORIES = ["Desenvolvimento Web", "JavaScript", "Design Web", "Design de UI", "UX/UI"];
const LEVELS = ["Iniciante", "Intermediário", "Avançado"];
const ADMIN_SECTIONS = [
  { value: "overview", label: "Dashboard", helper: "Resumo", icon: LayoutDashboard },
  { value: "analytics", label: "Analytics", helper: "Tráfego", icon: LineChart },
  { value: "courses", label: "Cursos", helper: "Catálogo", icon: BookOpen },
  { value: "users", label: "Utilizadores", helper: "Contas", icon: Users },
  { value: "enrollments", label: "Inscrições", helper: "Compras", icon: BarChart3 },
  { value: "settings", label: "Definições", helper: "Plataforma", icon: Settings },
  { value: "create", label: "Criar Curso", helper: "Editor", icon: Code2 },
] as const;

const AdminPage = () => {
  const { toast } = useToast();
  const queryClient = useQueryClient();

  const { data, isLoading, isError } = useQuery({
    queryKey: ["admin-dashboard"],
    queryFn: fetchAdminDashboard,
  });
  const { data: authUser } = useQuery({
    queryKey: ["auth-user"],
    queryFn: fetchAuthUser,
  });

  const [settingsForm, setSettingsForm] = useState<AdminSettings>(defaultSettings);
  const [courseSearch, setCourseSearch] = useState("");
  const [activeTab, setActiveTab] = useState("overview");
  const [editingCourseId, setEditingCourseId] = useState<string | null>(null);
  const [isPreparingEdit, setIsPreparingEdit] = useState(false);
  const [collapsedSectionIds, setCollapsedSectionIds] = useState<Record<string, boolean>>({});
  const [collapsedLessonIds, setCollapsedLessonIds] = useState<Record<string, boolean>>({});

  const [courseTitle, setCourseTitle] = useState("");
  const [courseSubtitle, setCourseSubtitle] = useState("");
  const [courseInstructor, setCourseInstructor] = useState("");
  const [courseDescription, setCourseDescription] = useState("");
  const [courseRating, setCourseRating] = useState(0);
  const [courseReviewCount, setCourseReviewCount] = useState(0);
  const [courseStudentCount, setCourseStudentCount] = useState(0);
  const [price, setPrice] = useState("");
  const [originalPrice, setOriginalPrice] = useState("");
  const [category, setCategory] = useState("");
  const [level, setLevel] = useState("");
  const [thumbnail, setThumbnail] = useState("");

  const [sections, setSections] = useState<AdminSection[]>([]);
  const adminDisplayName = authUser?.name || "Admin";
  const adminEmail = authUser?.email || "admin@urskool.com";
  const adminInitials = adminDisplayName
    .split(" ")
    .map((part) => part.trim().charAt(0))
    .filter(Boolean)
    .slice(0, 2)
    .join("")
    .toUpperCase();

  useEffect(() => {
    if (data?.settings) {
      setSettingsForm(data.settings);
    }
  }, [data?.settings]);

  const filteredCourses = useMemo(
    () =>
      (data?.courses ?? []).filter((course) =>
        course.title.toLowerCase().includes(courseSearch.toLowerCase()),
      ),
    [data?.courses, courseSearch],
  );

  const totalHours = Math.max(
    1,
    Math.round(sections.reduce((acc, section) => acc + section.lessons.length * 0.4, 0)),
  );

  const resetCourseBuilder = () => {
    setEditingCourseId(null);
    setCourseTitle("");
    setCourseSubtitle("");
    setCourseInstructor("");
    setCourseDescription("");
    setCourseRating(0);
    setCourseReviewCount(0);
    setCourseStudentCount(0);
    setPrice("");
    setOriginalPrice("");
    setCategory("");
    setLevel("");
    setThumbnail("");
    setSections([]);
    setCollapsedSectionIds({});
    setCollapsedLessonIds({});
  };

  const createCourseMutation = useMutation({
    mutationFn: createCourse,
    onSuccess: (course) => {
      toast({
        title: "Curso guardado",
        description: `${course.title} está agora disponível no catálogo.`,
      });
      queryClient.invalidateQueries({ queryKey: ["admin-dashboard"] });
      resetCourseBuilder();
    },
    onError: (error: Error) => {
      toast({
        variant: "destructive",
        title: "Falha ao guardar",
        description: error.message,
      });
    },
  });

  const updateCourseMutation = useMutation({
    mutationFn: ({
      courseId,
      payload,
    }: {
      courseId: string;
      payload: Parameters<typeof createCourse>[0];
    }) => updateAdminCourse(courseId, payload),
    onSuccess: (course) => {
      toast({
        title: "Curso atualizado",
        description: `${course.title} foi atualizado com sucesso.`,
      });
      queryClient.invalidateQueries({ queryKey: ["admin-dashboard"] });
      resetCourseBuilder();
      setActiveTab("courses");
    },
    onError: (error: Error) => {
      toast({
        variant: "destructive",
        title: "Falha na atualização",
        description: error.message,
      });
    },
  });

  const removeCourse = useMutation({
    mutationFn: deleteAdminCourse,
    onSuccess: () => {
      toast({
        title: "Curso removido",
        description: "O curso foi eliminado.",
      });
      queryClient.invalidateQueries({ queryKey: ["admin-dashboard"] });
    },
    onError: (error: Error) => {
      toast({
        variant: "destructive",
        title: "Falha ao eliminar",
        description: error.message,
      });
    },
  });

  const saveSettings = useMutation({
    mutationFn: updateAdminSettings,
    onSuccess: () => {
      toast({
        title: "Definições atualizadas",
        description: "As definições da plataforma foram guardadas com sucesso.",
      });
      queryClient.invalidateQueries({ queryKey: ["admin-dashboard"] });
    },
    onError: (error: Error) => {
      toast({
        variant: "destructive",
        title: "Falha ao atualizar definições",
        description: error.message,
      });
    },
  });

  const logoutMutation = useMutation({
    mutationFn: logout,
    onSuccess: async () => {
      queryClient.setQueryData(["auth-user"], null);
      await queryClient.invalidateQueries({ queryKey: ["auth-user"] });
      window.location.href = "/login";
    },
    onError: async (error: Error) => {
      queryClient.setQueryData(["auth-user"], null);
      await queryClient.invalidateQueries({ queryKey: ["auth-user"] });
      toast({
        variant: "destructive",
        title: "Falha ao terminar sessão",
        description: error.message,
      });
      window.location.href = "/login";
    },
  });

  const addSection = () => {
    const newSection = createEmptySection();
    setSections((prev) => [...prev, newSection]);
    setCollapsedSectionIds((prev) => ({ ...prev, [newSection.id]: false }));
  };

  const collapseAllSections = () => {
    setCollapsedSectionIds(
      sections.reduce<Record<string, boolean>>((acc, section) => {
        acc[section.id] = true;
        return acc;
      }, {}),
    );
  };

  const expandAllSections = () => {
    setCollapsedSectionIds(
      sections.reduce<Record<string, boolean>>((acc, section) => {
        acc[section.id] = false;
        return acc;
      }, {}),
    );
  };

  const addLesson = (sectionId: string) => {
    const newLesson = createEmptyLesson();
    setSections((prev) =>
      prev.map((section) =>
        section.id === sectionId
          ? {
              ...section,
              lessons: [...section.lessons, newLesson],
            }
          : section,
      ),
    );
    setCollapsedLessonIds((prev) => ({ ...prev, [newLesson.id]: false }));
  };

  const removeSection = (sectionId: string) => {
    const removedSection = sections.find((section) => section.id === sectionId);
    setSections((prev) => prev.filter((section) => section.id !== sectionId));
    setCollapsedSectionIds((prev) => {
      const next = { ...prev };
      delete next[sectionId];
      return next;
    });
    if (removedSection) {
      const lessonIds = new Set(removedSection.lessons.map((lesson) => lesson.id));
      setCollapsedLessonIds((prev) => {
        const next = { ...prev };
        Object.keys(next).forEach((id) => {
          if (lessonIds.has(id)) delete next[id];
        });
        return next;
      });
    }
  };

  const removeLesson = (sectionId: string, lessonId: string) => {
    setSections((prev) =>
      prev.map((section) =>
        section.id === sectionId
          ? {
              ...section,
              lessons: section.lessons.filter((lesson) => lesson.id !== lessonId),
            }
          : section,
      ),
    );
    setCollapsedLessonIds((prev) => {
      const next = { ...prev };
      delete next[lessonId];
      return next;
    });
  };

  const updateLesson = (
    sectionId: string,
    lessonId: string,
    updater: (lesson: AdminLesson) => AdminLesson,
  ) => {
    setSections((prev) =>
      prev.map((section) =>
        section.id === sectionId
          ? {
              ...section,
              lessons: section.lessons.map((lesson) =>
                lesson.id === lessonId ? updater(lesson) : lesson,
              ),
            }
          : section,
      ),
    );
  };

  const startEditCourse = async (courseId: string) => {
    setIsPreparingEdit(true);

    try {
      const course = await fetchCourse(courseId);

      setEditingCourseId(course.id);
      setCourseTitle(course.title);
      setCourseSubtitle(course.subtitle || "");
      setCourseInstructor(course.instructor);
      setCourseDescription(course.description || "");
      setCourseRating(course.rating);
      setCourseReviewCount(course.reviewCount);
      setCourseStudentCount(course.studentCount);
      setPrice(String(course.price));
      setOriginalPrice(String(course.originalPrice));
      setCategory(toCategoryPt(course.category));
      setLevel(toLevelPt(course.level));
      setThumbnail(course.image);
      setSections(
        course.sections.map((section) => ({
          id: section.id || crypto.randomUUID(),
          title: section.title,
          lessons: section.lessons.map((lesson) => ({
            id: lesson.id || crypto.randomUUID(),
            title: lesson.title,
            duration: lesson.duration || "",
            videoUrl: lesson.videoUrl || "",
            language: lesson.language || "html",
            content: lesson.content || "",
            starterCode: lesson.starterCode || "",
            htmlCode:
              lesson.htmlCode ||
              ((lesson.language || "html").toLowerCase() === "html" ? lesson.starterCode || "" : ""),
            cssCode:
              lesson.cssCode ||
              ((lesson.language || "").toLowerCase() === "css" ? lesson.starterCode || "" : ""),
            jsCode:
              lesson.jsCode ||
              (["js", "javascript"].includes((lesson.language || "").toLowerCase())
                ? lesson.starterCode || ""
                : ""),
            workspaceFiles: normalizeAdminWorkspaceFiles(
              lesson.workspaceFiles,
              lesson.id,
              lesson.htmlCode || "",
              lesson.cssCode || "",
              lesson.jsCode || "",
            ),
            entryHtmlFileId: normalizeEntryHtmlFileId(
              normalizeAdminWorkspaceFiles(
                lesson.workspaceFiles,
                lesson.id,
                lesson.htmlCode || "",
                lesson.cssCode || "",
                lesson.jsCode || "",
              ),
              lesson.entryHtmlFileId,
            ),
            quizQuestions: normalizeAdminQuizQuestions(lesson.quizQuestions),
            quizPassPercentage: clampQuizPassPercentage(Number(lesson.quizPassPercentage ?? 80)),
            quizRandomizeQuestions: lesson.quizRandomizeQuestions ?? true,
            isFree: lesson.isFree,
            type: lesson.type || "code",
          })),
        })),
      );
      setCollapsedSectionIds({});
      setCollapsedLessonIds({});
      setActiveTab("create");
    } catch (error) {
      toast({
        variant: "destructive",
        title: "Não foi possível carregar o curso",
        description: error instanceof Error ? error.message : "Tenta novamente.",
      });
    } finally {
      setIsPreparingEdit(false);
    }
  };

  const saveCoursePayload = {
    title: courseTitle,
    subtitle: courseSubtitle,
    instructor: courseInstructor,
    rating: courseRating,
    reviewCount: courseReviewCount,
    studentCount: courseStudentCount,
    price: Number(price),
    originalPrice: Number(originalPrice),
    image: thumbnail,
    category,
    level,
    totalHours,
    description: courseDescription,
    sections: sections.map((section) => ({
      title: section.title,
      lessons: section.lessons.map((lesson) => ({
        title: lesson.title,
        duration: lesson.duration,
        videoUrl: lesson.videoUrl || undefined,
        language: lesson.language || undefined,
        content: lesson.content || undefined,
        starterCode: lesson.starterCode || undefined,
        htmlCode: lesson.htmlCode || undefined,
        cssCode: lesson.cssCode || undefined,
        jsCode: lesson.jsCode || undefined,
        workspaceFiles: lesson.type === "code" || lesson.type === "project"
          ? lesson.workspaceFiles
          : undefined,
        entryHtmlFileId: lesson.type === "code" || lesson.type === "project"
          ? (lesson.entryHtmlFileId || undefined)
          : undefined,
        quizQuestions: lesson.type === "quiz"
          ? lesson.quizQuestions.map((question) => ({
            id: question.id,
            question: question.question,
            options: question.options,
            correctOptionIndex: question.correctOptionIndex,
          }))
          : undefined,
        quizPassPercentage: lesson.type === "quiz" ? clampQuizPassPercentage(lesson.quizPassPercentage) : undefined,
        quizRandomizeQuestions: lesson.type === "quiz" ? lesson.quizRandomizeQuestions : undefined,
        isFree: lesson.isFree,
        type: lesson.type,
      })),
    })),
  };

  const saveCourseAction = () => {
    if (editingCourseId) {
      updateCourseMutation.mutate({
        courseId: editingCourseId,
        payload: saveCoursePayload,
      });
      return;
    }

    createCourseMutation.mutate(saveCoursePayload);
  };

  if (isLoading) {
    return (
      <div className="min-h-screen bg-background px-4 py-8">
        <div className="mx-auto max-w-7xl rounded-2xl border border-border bg-card p-6 shadow-sm">
          <p className="text-sm font-medium text-muted-foreground">A carregar painel de administração...</p>
        </div>
      </div>
    );
  }

  if (isError || !data) {
    return (
      <div className="min-h-screen bg-background px-4 py-8">
        <div className="mx-auto max-w-7xl rounded-2xl border border-border bg-card p-6 shadow-sm">
          <p className="text-sm font-medium text-muted-foreground">
            Não foi possível carregar os dados do painel de administração.
          </p>
        </div>
      </div>
    );
  }

  const activeSection = ADMIN_SECTIONS.find((section) => section.value === activeTab) ?? ADMIN_SECTIONS[0];
  const revenueTarget = Math.max(1, Math.round(data.stats.totalRevenue ? data.stats.totalRevenue * 1.15 : 1));
  const revenueProgress = Math.min(
    100,
    Math.round(((data.stats.totalRevenue / revenueTarget) * 100)),
  );
  const isOverviewTab = activeTab === "overview";
  const analyticsTraffic = Array.from({ length: 25 }, (_item, index) => {
    const perf = data.coursePerformance[index % Math.max(1, data.coursePerformance.length)];
    const baseValue = perf
      ? perf.enrollments * 8 + perf.activeStudents * 5 + Math.round(perf.completionRate)
      : 120 + (index * 37) % 210;
    return Math.max(60, Math.min(390, baseValue));
  });
  const maxTraffic = Math.max(...analyticsTraffic, 1);
  const uniqueVisitors = Math.max(1, Math.round(data.stats.totalUsers * 6.2));
  const totalPageviews = analyticsTraffic.reduce((acc, value) => acc + value, 0);
  const bounceRate = Math.max(
    20,
    Math.min(
      75,
      Math.round(
        100
        - data.studentPerformance.reduce((acc, student) => acc + student.completionRate, 0)
          / Math.max(1, data.studentPerformance.length),
      ),
    ),
  );
  const visitDurationSeconds = Math.max(
    65,
    Math.round(
      data.coursePerformance.reduce((acc, course) => acc + course.averageQuizScore, 0)
      / Math.max(1, data.coursePerformance.length) + 95,
    ),
  );
  const topChannels = data.categories.slice(0, 5).map((category) => ({
    name: category.name,
    visitors: Math.max(100, Math.round((category.count / Math.max(1, data.stats.totalCourses)) * uniqueVisitors)),
  }));
  const topPages = data.courses.slice(0, 5).map((course) => ({
    name: course.title,
    views: Math.max(100, Math.round((course.studentCount + 1) * 18)),
  }));
  const courseCategoryById = new Map(data.courses.map((course) => [course.id, toCategoryPt(course.category)]));
  const sparklineTraffic = analyticsTraffic.slice(0, 12);
  const sparklineMax = Math.max(...sparklineTraffic, 1);
  const sparklinePoints = sparklineTraffic
    .map((value, index) => {
      const x = (index / Math.max(1, sparklineTraffic.length - 1)) * 100;
      const y = 92 - (value / sparklineMax) * 74;
      return `${x},${y}`;
    })
    .join(" ");
  const acquisitionMonths = ["Jan", "Fev", "Mar", "Abr", "Mai", "Jun", "Jul"];
  const acquisitionSeries = acquisitionMonths.map((month, index) => {
    const anchor = analyticsTraffic[index] ?? 120;
    const direct = 28 + (anchor % 30);
    const referral = 12 + ((anchor * 2) % 20);
    const organic = 10 + ((anchor * 3) % 17);
    const social = 8 + ((anchor * 4) % 14);
    return { month, direct, referral, organic, social };
  });
  const maxAcquisition = Math.max(
    ...acquisitionSeries.map((entry) => entry.direct + entry.referral + entry.organic + entry.social),
    1,
  );
  const deviceStats = [
    { name: "Desktop", value: 48 },
    { name: "Mobile", value: 37 },
    { name: "Tablet", value: 15 },
  ];
  const recentEnrollments = data.enrollments.slice(0, 7);

  return (
    <div className="min-h-screen bg-background text-foreground">
      <div className="grid min-h-screen md:grid-cols-[260px_1fr]">
        <aside className="hidden border-r border-border bg-card md:flex md:flex-col">
          <div className="flex h-16 items-center border-b border-border px-5">
            <div className="flex h-9 w-9 items-center justify-center rounded-lg bg-foreground text-background">
              <LayoutDashboard className="h-5 w-5" />
            </div>
            <div className="ml-3">
              <p className="text-sm font-semibold text-foreground">UrSkool Admin</p>
              <p className="text-xs text-muted-foreground">Painel</p>
            </div>
          </div>

          <div className="flex-1 overflow-y-auto p-4">
            <p className="px-2 text-[11px] font-semibold uppercase tracking-[0.18em] text-muted-foreground">Menu</p>
            <div className="mt-3 space-y-1.5">
              {ADMIN_SECTIONS.map((section) => {
                const SectionIcon = section.icon;
                const isActive = activeTab === section.value;

                return (
                  <button
                    key={section.value}
                    type="button"
                    onClick={() => setActiveTab(section.value)}
                    className={cn(
                      "flex w-full items-center justify-between rounded-xl px-3 py-2.5 text-left transition-colors",
                      isActive
                        ? "bg-muted text-foreground"
                        : "text-muted-foreground hover:bg-muted hover:text-foreground",
                    )}
                  >
                    <span className="flex items-center gap-2.5">
                      <SectionIcon className="h-4 w-4 shrink-0" />
                      <span className="text-sm font-medium">{section.label}</span>
                    </span>
                    <span className="text-[10px] font-semibold uppercase tracking-wide text-muted-foreground">
                      {section.helper}
                    </span>
                  </button>
                );
              })}
            </div>
          </div>

          <div className="border-t border-border p-4">
            <div className="rounded-xl border border-border bg-muted/40 p-3">
              <p className="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Segurança</p>
              <p className="mt-1 text-sm text-foreground">Sessão de administração protegida.</p>
              <div className="mt-2 inline-flex items-center gap-1 rounded-full border border-border bg-background px-2 py-1 text-[11px] font-medium text-foreground">
                <Shield className="h-3 w-3" />
                Ativo
              </div>
            </div>
          </div>
        </aside>

        <div className="min-w-0">
          <header className="sticky top-0 z-30 border-b border-border bg-card/95 backdrop-blur">
            <div className="flex h-16 items-center gap-3 px-4 md:px-6">
              <div className="relative flex-1">
                <Search className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                <Input
                  value={courseSearch}
                  onChange={(event) => setCourseSearch(event.target.value)}
                  placeholder="Pesquisar cursos e conteúdos..."
                  className="h-10 border-border bg-muted/40 pl-9 font-body"
                />
              </div>

              <Button variant="ghost" size="icon" className="h-11 w-11 rounded-full border border-border text-muted-foreground">
                <Moon className="h-4 w-4" />
              </Button>
              <Button
                variant="ghost"
                size="icon"
                className="relative h-11 w-11 rounded-full border border-border text-muted-foreground"
              >
                <Bell className="h-4 w-4" />
                <span className="absolute right-2.5 top-2.5 h-2.5 w-2.5 rounded-full border border-background bg-foreground" />
              </Button>

              <DropdownMenu>
                <DropdownMenuTrigger asChild>
                  <button
                    type="button"
                    className="flex items-center gap-2 rounded-full border border-border bg-background px-2 py-1.5 text-left transition-colors hover:bg-muted/50"
                  >
                    <Avatar className="h-9 w-9">
                      <AvatarImage src={undefined} alt={adminDisplayName} />
                      <AvatarFallback className="bg-muted text-xs font-semibold text-foreground">
                        {adminInitials || "AD"}
                      </AvatarFallback>
                    </Avatar>
                    <span className="hidden text-sm font-semibold text-foreground md:block">{adminDisplayName}</span>
                    <ChevronDown className="h-4 w-4 text-muted-foreground" />
                  </button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end" className="w-72 rounded-2xl border-border p-3">
                  <div className="space-y-1 px-1 pb-2">
                    <p className="text-lg font-semibold text-foreground">{adminDisplayName}</p>
                    <p className="text-sm text-muted-foreground">{adminEmail}</p>
                  </div>
                  <DropdownMenuSeparator />
                  <DropdownMenuItem
                    className="mt-1 h-11 rounded-lg px-3 text-base"
                    onSelect={() =>
                      toast({
                        title: "Perfil",
                        description: "Página de perfil em breve.",
                      })
                    }
                  >
                    <User className="mr-3 h-5 w-5" />
                    Edit profile
                  </DropdownMenuItem>
                  <DropdownMenuItem
                    className="h-11 rounded-lg px-3 text-base"
                    onSelect={() => setActiveTab("settings")}
                  >
                    <Settings className="mr-3 h-5 w-5" />
                    Account settings
                  </DropdownMenuItem>
                  <DropdownMenuItem
                    className="h-11 rounded-lg px-3 text-base"
                    onSelect={() =>
                      toast({
                        title: "Suporte",
                        description: "Canal de suporte em breve.",
                      })
                    }
                  >
                    <LifeBuoy className="mr-3 h-5 w-5" />
                    Support
                  </DropdownMenuItem>
                  <DropdownMenuSeparator className="my-2" />
                  <DropdownMenuItem
                    className="h-11 rounded-lg px-3 text-base"
                    onSelect={() => logoutMutation.mutate()}
                    disabled={logoutMutation.isPending}
                  >
                    <LogOut className="mr-3 h-5 w-5" />
                    {logoutMutation.isPending ? "A terminar..." : "Sign out"}
                  </DropdownMenuItem>
                </DropdownMenuContent>
              </DropdownMenu>
            </div>
          </header>

          <main className="p-4 md:p-6 lg:p-8">
            <div className="mb-5 flex gap-2 overflow-x-auto pb-1 md:hidden">
              {ADMIN_SECTIONS.map((section) => {
                const SectionIcon = section.icon;
                const isActive = activeTab === section.value;

                return (
                  <Button
                    key={section.value}
                    type="button"
                    variant="outline"
                    onClick={() => setActiveTab(section.value)}
                    className={cn(
                      "rounded-full border-border bg-background",
                      isActive && "bg-muted text-foreground",
                    )}
                  >
                    <SectionIcon className="mr-1.5 h-3.5 w-3.5" />
                    {section.label}
                  </Button>
                );
              })}
            </div>

            <div className="mb-6 flex flex-col gap-3 md:flex-row md:items-end md:justify-between">
              <div>
                <h1 className="text-2xl font-semibold text-foreground md:text-3xl">{activeSection.label}</h1>
                <p className="text-sm text-muted-foreground">
                  Organização centralizada para catálogo, estudantes e operações.
                </p>
              </div>
              {isOverviewTab && (
                <div className="flex flex-wrap gap-2">
                  <Badge variant="outline" className="border-border bg-background px-3 py-1 text-foreground">
                    {data.stats.totalCourses} cursos
                  </Badge>
                  <Badge variant="outline" className="border-border bg-background px-3 py-1 text-foreground">
                    {data.stats.totalUsers} utilizadores
                  </Badge>
                  <Badge variant="outline" className="border-border bg-background px-3 py-1 text-foreground">
                    {data.stats.totalEnrollments} inscrições
                  </Badge>
                </div>
              )}
            </div>

            {isOverviewTab && (
              <div className="mb-6 grid gap-4 lg:grid-cols-3">
                <Card className="border-border shadow-sm">
                  <CardHeader className="pb-3">
                    <CardDescription className="text-muted-foreground">Receita Total</CardDescription>
                    <CardTitle className="flex items-center gap-2 text-2xl text-foreground">
                      <Banknote className="h-5 w-5 text-foreground" />
                      {formatMzn(data.stats.totalRevenue)}
                    </CardTitle>
                  </CardHeader>
                </Card>
                <Card className="border-border shadow-sm">
                  <CardHeader className="pb-3">
                    <CardDescription className="text-muted-foreground">Utilizadores Ativos</CardDescription>
                    <CardTitle className="flex items-center gap-2 text-2xl text-foreground">
                      <Users className="h-5 w-5 text-foreground" />
                      {data.stats.totalUsers.toLocaleString()}
                    </CardTitle>
                  </CardHeader>
                </Card>
                <Card className="border-border shadow-sm">
                  <CardHeader className="pb-3">
                    <CardDescription className="text-muted-foreground">Meta Mensal</CardDescription>
                    <CardTitle className="text-2xl text-foreground">{revenueProgress}%</CardTitle>
                    <CardDescription className="text-xs text-muted-foreground">
                      Meta estimada: {formatMzn(revenueTarget)}
                    </CardDescription>
                  </CardHeader>
                </Card>
              </div>
            )}

            <Tabs value={activeTab} onValueChange={setActiveTab} className="space-y-6">
              <TabsList className="sr-only">
                {ADMIN_SECTIONS.map((section) => (
                  <TabsTrigger key={section.value} value={section.value}>
                    {section.label}
                  </TabsTrigger>
                ))}
              </TabsList>

          <TabsContent value="overview" className="space-y-6">
            <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
              <Card>
                <CardHeader className="pb-2">
                  <CardDescription>Receita Total</CardDescription>
                  <CardTitle className="text-2xl flex items-center gap-2">
                    <Banknote className="h-5 w-5 text-accent" />
                    {formatMzn(data.stats.totalRevenue)}
                  </CardTitle>
                </CardHeader>
              </Card>
              <Card>
                <CardHeader className="pb-2">
                  <CardDescription>Instrutores Ativos</CardDescription>
                  <CardTitle className="text-2xl flex items-center gap-2">
                    <Users className="h-5 w-5 text-accent" />
                    {data.stats.instructorsCount}
                  </CardTitle>
                </CardHeader>
              </Card>
              <Card>
                <CardHeader className="pb-2">
                  <CardDescription>Total de Lições</CardDescription>
                  <CardTitle className="text-2xl flex items-center gap-2">
                    <BookOpen className="h-5 w-5 text-accent" />
                    {data.stats.totalLessons}
                  </CardTitle>
                </CardHeader>
              </Card>
            </div>

            <div className="grid gap-6 xl:grid-cols-2">
              <Card>
                <CardHeader>
                  <CardTitle className="font-display text-xl">Performance por Curso</CardTitle>
                  <CardDescription>Inscrições, progresso e resultados de questionários.</CardDescription>
                </CardHeader>
                <CardContent>
                  <Table>
                    <TableHeader>
                      <TableRow>
                        <TableHead>Curso</TableHead>
                        <TableHead>Inscritos</TableHead>
                        <TableHead>Conclusão</TableHead>
                        <TableHead>Quiz médio</TableHead>
                      </TableRow>
                    </TableHeader>
                    <TableBody>
                      {data.coursePerformance.slice(0, 8).map((course) => (
                        <TableRow key={course.courseId}>
                          <TableCell className="max-w-[260px]">
                            <p className="font-medium truncate">{course.courseTitle}</p>
                            <p className="text-xs text-muted-foreground">
                              Ativos: {course.activeStudents} · Aprovação: {course.quizPassRate.toFixed(0)}%
                            </p>
                          </TableCell>
                          <TableCell>{course.enrollments}</TableCell>
                          <TableCell>{course.completionRate.toFixed(0)}%</TableCell>
                          <TableCell>{course.averageQuizScore.toFixed(0)}%</TableCell>
                        </TableRow>
                      ))}
                    </TableBody>
                  </Table>
                </CardContent>
              </Card>

              <Card>
                <CardHeader>
                  <CardTitle className="font-display text-xl">Performance de Estudantes</CardTitle>
                  <CardDescription>Quem está ativo e como está a evoluir.</CardDescription>
                </CardHeader>
                <CardContent>
                  <Table>
                    <TableHeader>
                      <TableRow>
                        <TableHead>Estudante</TableHead>
                        <TableHead>Cursos</TableHead>
                        <TableHead>Conclusão</TableHead>
                        <TableHead>Última atividade</TableHead>
                      </TableRow>
                    </TableHeader>
                    <TableBody>
                      {data.studentPerformance.slice(0, 8).map((student) => (
                        <TableRow key={student.userId}>
                          <TableCell>
                            <p className="font-medium">{student.name}</p>
                            <p className="text-xs text-muted-foreground">{student.email}</p>
                          </TableCell>
                          <TableCell>{student.enrolledCourses}</TableCell>
                          <TableCell>{student.completionRate.toFixed(0)}%</TableCell>
                          <TableCell>{formatDate(student.lastActivityAt ?? undefined)}</TableCell>
                        </TableRow>
                      ))}
                    </TableBody>
                  </Table>
                </CardContent>
              </Card>
            </div>

            <div className="grid gap-6 lg:grid-cols-3">
              <Card className="lg:col-span-2">
                <CardHeader>
                  <CardTitle className="font-display text-xl">Inscrições Recentes</CardTitle>
                  <CardDescription>Compras mais recentes em todo o catálogo.</CardDescription>
                </CardHeader>
                <CardContent>
                  <Table>
                    <TableHeader>
                      <TableRow>
                        <TableHead>Estudante</TableHead>
                        <TableHead>Curso</TableHead>
                        <TableHead>Estado</TableHead>
                        <TableHead className="text-right">Valor</TableHead>
                      </TableRow>
                    </TableHeader>
                    <TableBody>
                      {data.enrollments.slice(0, 8).map((enrollment) => (
                        <TableRow key={enrollment.id}>
                          <TableCell>
                            <div>
                              <p className="font-medium">{enrollment.fullName}</p>
                              <p className="text-xs text-muted-foreground">{enrollment.email}</p>
                            </div>
                          </TableCell>
                          <TableCell>{enrollment.courseTitle}</TableCell>
                          <TableCell>
                            <Badge variant="outline">{toEnrollmentStatusPt(enrollment.status)}</Badge>
                          </TableCell>
                          <TableCell className="text-right">
                            {formatMzn(enrollment.amount)}
                          </TableCell>
                        </TableRow>
                      ))}
                    </TableBody>
                  </Table>
                </CardContent>
              </Card>

              <Card>
                <CardHeader>
                  <CardTitle className="font-display text-xl">Mix de Categorias</CardTitle>
                  <CardDescription>Distribuição atual de cursos por categoria.</CardDescription>
                </CardHeader>
                <CardContent className="space-y-3">
                  {data.categories.map((item) => (
                    <div
                      key={item.name}
                      className="flex items-center justify-between bg-surface-sunken rounded-lg px-3 py-2"
                    >
                      <span className="font-body text-sm">{item.name}</span>
                      <Badge>{item.count}</Badge>
                    </div>
                  ))}
                </CardContent>
              </Card>
            </div>
          </TabsContent>

          <TabsContent value="analytics" className="space-y-6">
            <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
              <Card className="border-border">
                <CardHeader className="pb-2">
                  <CardDescription>Visitantes Únicos</CardDescription>
                  <CardTitle className="flex items-center gap-2 text-2xl">
                    <MousePointerClick className="h-5 w-5 text-foreground" />
                    {`${(uniqueVisitors / 1000).toFixed(1)}K`}
                  </CardTitle>
                </CardHeader>
              </Card>
              <Card className="border-border">
                <CardHeader className="pb-2">
                  <CardDescription>Total Pageviews</CardDescription>
                  <CardTitle className="flex items-center gap-2 text-2xl">
                    <Eye className="h-5 w-5 text-foreground" />
                    {`${(totalPageviews / 1000).toFixed(1)}K`}
                  </CardTitle>
                </CardHeader>
              </Card>
              <Card className="border-border">
                <CardHeader className="pb-2">
                  <CardDescription>Bounce Rate</CardDescription>
                  <CardTitle className="flex items-center gap-2 text-2xl">
                    <Activity className="h-5 w-5 text-foreground" />
                    {`${bounceRate}%`}
                  </CardTitle>
                </CardHeader>
              </Card>
              <Card className="border-border">
                <CardHeader className="pb-2">
                  <CardDescription>Visit Duration</CardDescription>
                  <CardTitle className="flex items-center gap-2 text-2xl">
                    <Clock3 className="h-5 w-5 text-foreground" />
                    {`${Math.floor(visitDurationSeconds / 60)}m ${String(visitDurationSeconds % 60).padStart(2, "0")}s`}
                  </CardTitle>
                </CardHeader>
              </Card>
            </div>

            <Card className="border-border">
              <CardHeader className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                  <CardTitle className="font-display text-xl">Analytics</CardTitle>
                  <CardDescription>Visitor analytics dos últimos 30 dias.</CardDescription>
                </div>
                <div className="flex flex-wrap gap-2">
                  <Button variant="outline" size="sm" className="border-border bg-background">12 meses</Button>
                  <Button variant="outline" size="sm" className="border-border bg-background">30 dias</Button>
                  <Button variant="outline" size="sm" className="border-border bg-background">7 dias</Button>
                  <Button variant="outline" size="sm" className="border-border bg-background">24 horas</Button>
                </div>
              </CardHeader>
              <CardContent className="space-y-4">
                <div className="grid grid-cols-4 text-xs text-muted-foreground">
                  <span>0</span>
                  <span className="text-center">100</span>
                  <span className="text-center">250</span>
                  <span className="text-right">400</span>
                </div>
                <div className="overflow-x-auto">
                  <div className="flex min-w-[900px] items-end gap-2 pb-3">
                    {analyticsTraffic.map((value, index) => (
                      <div key={`analytics-${index}`} className="flex w-8 flex-col items-center gap-2">
                        <div className="flex h-64 w-full items-end rounded-md bg-muted/40 px-1">
                          <div
                            className="w-full rounded-sm bg-foreground"
                            style={{ height: `${Math.max(8, (value / maxTraffic) * 100)}%` }}
                          />
                        </div>
                        <span className="text-[10px] text-muted-foreground">{index + 1}</span>
                      </div>
                    ))}
                  </div>
                </div>
              </CardContent>
            </Card>

            <div className="grid gap-6 xl:grid-cols-3">
              <Card className="border-border">
                <CardHeader>
                  <CardTitle className="font-display text-lg">Top Channels</CardTitle>
                </CardHeader>
                <CardContent className="space-y-3">
                  {topChannels.map((channel) => (
                    <div key={channel.name} className="flex items-center justify-between border-b border-border pb-2 last:border-0 last:pb-0">
                      <span className="text-sm text-foreground">{channel.name}</span>
                      <span className="text-sm font-semibold text-foreground">{`${(channel.visitors / 1000).toFixed(1)}K`}</span>
                    </div>
                  ))}
                </CardContent>
              </Card>

              <Card className="border-border">
                <CardHeader>
                  <CardTitle className="font-display text-lg">Top Pages</CardTitle>
                </CardHeader>
                <CardContent className="space-y-3">
                  {topPages.map((page) => (
                    <div key={page.name} className="flex items-center justify-between border-b border-border pb-2 last:border-0 last:pb-0">
                      <span className="max-w-[210px] truncate text-sm text-foreground">{page.name}</span>
                      <span className="text-sm font-semibold text-foreground">{`${(page.views / 1000).toFixed(1)}K`}</span>
                    </div>
                  ))}
                </CardContent>
              </Card>

              <Card className="border-border">
                <CardHeader>
                  <CardTitle className="font-display text-lg">Active Users</CardTitle>
                  <CardDescription>
                    <span className="mr-1 text-3xl font-semibold text-foreground">{Math.round(uniqueVisitors / 11)}</span>
                    Live visitors
                  </CardDescription>
                </CardHeader>
                <CardContent>
                  <div className="h-36 rounded-lg border border-border bg-muted/20 p-3">
                    <svg viewBox="0 0 100 100" className="h-full w-full" preserveAspectRatio="none">
                      <polyline
                        points={sparklinePoints}
                        fill="none"
                        stroke="hsl(var(--foreground))"
                        strokeWidth="2.2"
                      />
                    </svg>
                  </div>
                  <div className="mt-4 grid grid-cols-3 gap-2 text-center text-xs">
                    <div>
                      <p className="text-lg font-semibold text-foreground">{Math.round(uniqueVisitors / 30)}</p>
                      <p className="text-muted-foreground">Avg. Daily</p>
                    </div>
                    <div>
                      <p className="text-lg font-semibold text-foreground">{`${(uniqueVisitors / 18).toFixed(1)}K`}</p>
                      <p className="text-muted-foreground">Avg. Weekly</p>
                    </div>
                    <div>
                      <p className="text-lg font-semibold text-foreground">{`${(uniqueVisitors / 10).toFixed(1)}K`}</p>
                      <p className="text-muted-foreground">Avg. Monthly</p>
                    </div>
                  </div>
                </CardContent>
              </Card>
            </div>

            <div className="grid gap-6 xl:grid-cols-3">
              <Card className="border-border xl:col-span-2">
                <CardHeader>
                  <CardTitle className="font-display text-lg">Acquisition Channels</CardTitle>
                </CardHeader>
                <CardContent>
                  <div className="mb-4 flex flex-wrap gap-4 text-xs text-muted-foreground">
                    <span className="inline-flex items-center gap-1"><span className="h-2 w-2 rounded-full bg-foreground" />Direct</span>
                    <span className="inline-flex items-center gap-1"><span className="h-2 w-2 rounded-full bg-muted-foreground" />Referral</span>
                    <span className="inline-flex items-center gap-1"><span className="h-2 w-2 rounded-full bg-muted" />Organic</span>
                    <span className="inline-flex items-center gap-1"><span className="h-2 w-2 rounded-full border border-border bg-background" />Social</span>
                  </div>
                  <div className="grid grid-cols-7 gap-3">
                    {acquisitionSeries.map((entry) => {
                      const total = entry.direct + entry.referral + entry.organic + entry.social;
                      return (
                        <div key={entry.month} className="space-y-2">
                          <div className="flex h-44 flex-col justify-end overflow-hidden rounded-lg border border-border bg-muted/20 p-1">
                            <div
                              className="w-full bg-foreground"
                              style={{ height: `${Math.max(5, (entry.direct / maxAcquisition) * 100)}%` }}
                            />
                            <div
                              className="w-full bg-muted-foreground"
                              style={{ height: `${Math.max(5, (entry.referral / maxAcquisition) * 100)}%` }}
                            />
                            <div
                              className="w-full bg-muted"
                              style={{ height: `${Math.max(5, (entry.organic / maxAcquisition) * 100)}%` }}
                            />
                            <div
                              className="w-full border-t border-border bg-background"
                              style={{ height: `${Math.max(4, (entry.social / maxAcquisition) * 100)}%` }}
                            />
                          </div>
                          <div className="text-center">
                            <p className="text-xs text-muted-foreground">{entry.month}</p>
                            <p className="text-[10px] text-foreground">{total}</p>
                          </div>
                        </div>
                      );
                    })}
                  </div>
                </CardContent>
              </Card>

              <Card className="border-border">
                <CardHeader>
                  <CardTitle className="font-display text-lg">Sessions By Device</CardTitle>
                </CardHeader>
                <CardContent>
                  <div className="mx-auto h-48 w-48 rounded-full" style={{ background: "conic-gradient(hsl(var(--foreground)) 0% 48%, hsl(var(--muted-foreground)) 48% 85%, hsl(var(--border)) 85% 100%)" }}>
                    <div className="mx-auto mt-8 h-32 w-32 rounded-full border border-border bg-card" />
                  </div>
                  <div className="mt-5 space-y-3">
                    {deviceStats.map((device) => (
                      <div key={device.name} className="space-y-1">
                        <div className="flex items-center justify-between text-xs">
                          <span className="text-muted-foreground">{device.name}</span>
                          <span className="font-semibold text-foreground">{`${device.value}%`}</span>
                        </div>
                        <div className="h-2 rounded-full bg-muted">
                          <div className="h-2 rounded-full bg-foreground" style={{ width: `${device.value}%` }} />
                        </div>
                      </div>
                    ))}
                  </div>
                </CardContent>
              </Card>
            </div>

            <div className="grid gap-6 xl:grid-cols-5">
              <Card className="border-border xl:col-span-2">
                <CardHeader>
                  <CardTitle className="font-display text-lg">Distribuição de Categorias</CardTitle>
                </CardHeader>
                <CardContent className="space-y-3">
                  {data.categories.map((item) => (
                    <div key={item.name} className="space-y-1.5">
                      <div className="flex items-center justify-between text-sm">
                        <span className="text-muted-foreground">{item.name}</span>
                        <span className="font-semibold text-foreground">{item.count}</span>
                      </div>
                      <div className="h-2 rounded-full bg-muted">
                        <div
                          className="h-2 rounded-full bg-foreground"
                          style={{ width: `${Math.max(8, (item.count / Math.max(1, data.stats.totalCourses)) * 100)}%` }}
                        />
                      </div>
                    </div>
                  ))}
                </CardContent>
              </Card>

              <Card className="border-border xl:col-span-3">
                <CardHeader>
                  <CardTitle className="font-display text-lg">Recent Orders</CardTitle>
                </CardHeader>
                <CardContent>
                  <Table>
                    <TableHeader>
                      <TableRow>
                        <TableHead>Produto</TableHead>
                        <TableHead>Categoria</TableHead>
                        <TableHead>Estado</TableHead>
                        <TableHead className="text-right">Valor</TableHead>
                      </TableRow>
                    </TableHeader>
                    <TableBody>
                      {recentEnrollments.map((enrollment) => (
                        <TableRow key={enrollment.id}>
                          <TableCell>{enrollment.courseTitle}</TableCell>
                          <TableCell>{courseCategoryById.get(enrollment.courseId) ?? "N/A"}</TableCell>
                          <TableCell>
                            <Badge variant="outline">{toEnrollmentStatusPt(enrollment.status)}</Badge>
                          </TableCell>
                          <TableCell className="text-right">{formatMzn(enrollment.amount)}</TableCell>
                        </TableRow>
                      ))}
                    </TableBody>
                  </Table>
                </CardContent>
              </Card>
            </div>
          </TabsContent>

          <TabsContent value="courses">
            <Card>
              <CardHeader>
                <CardTitle className="font-display text-xl">Catálogo de Cursos</CardTitle>
                <CardDescription>Navega, revê e remove cursos da plataforma.</CardDescription>
              </CardHeader>
              <CardContent>
                <div className="mb-4">
                  <Input
                    value={courseSearch}
                    onChange={(event) => setCourseSearch(event.target.value)}
                    placeholder="Pesquisar cursos..."
                    className="max-w-sm"
                  />
                </div>
                <Table>
                  <TableHeader>
                    <TableRow>
                      <TableHead>Título</TableHead>
                      <TableHead>Categoria</TableHead>
                      <TableHead>Preço</TableHead>
                      <TableHead>Atualizado</TableHead>
                      <TableHead className="text-right">Ação</TableHead>
                    </TableRow>
                  </TableHeader>
                  <TableBody>
                    {filteredCourses.map((course) => (
                      <TableRow key={course.id}>
                        <TableCell className="max-w-[280px]">
                          <p className="font-medium truncate">{course.title}</p>
                          <p className="text-xs text-muted-foreground">{toLevelPt(course.level)}</p>
                        </TableCell>
                        <TableCell>
                          <Badge variant="outline">{toCategoryPt(course.category)}</Badge>
                        </TableCell>
                        <TableCell>
                          {formatMzn(course.price)}
                        </TableCell>
                        <TableCell>{formatDate(course.updatedAt)}</TableCell>
                        <TableCell className="text-right">
                          <div className="flex items-center justify-end gap-2">
                            <Button
                              variant="outline"
                              size="sm"
                              disabled={isPreparingEdit}
                              onClick={() => startEditCourse(course.id)}
                            >
                              <Edit3 className="h-3.5 w-3.5" />
                              Editar
                            </Button>
                            <Button
                              variant="destructive"
                              size="sm"
                              disabled={removeCourse.isPending}
                              onClick={() => removeCourse.mutate(course.id)}
                            >
                              <Trash2 className="h-3.5 w-3.5" />
                              Eliminar
                            </Button>
                          </div>
                        </TableCell>
                      </TableRow>
                    ))}
                  </TableBody>
                </Table>
              </CardContent>
            </Card>
          </TabsContent>

          <TabsContent value="users">
            <Card>
              <CardHeader>
                <CardTitle className="font-display text-xl">Utilizadores</CardTitle>
                <CardDescription>Visualiza estudantes registados e membros admin.</CardDescription>
              </CardHeader>
              <CardContent>
                <Table>
                  <TableHeader>
                    <TableRow>
                      <TableHead>Nome</TableHead>
                      <TableHead>E-mail</TableHead>
                      <TableHead>Função</TableHead>
                      <TableHead>Registo</TableHead>
                    </TableRow>
                  </TableHeader>
                  <TableBody>
                    {data.users.map((user) => (
                      <TableRow key={user.id}>
                        <TableCell>{user.name}</TableCell>
                        <TableCell>{user.email}</TableCell>
                        <TableCell>
                          <Badge variant={user.isAdmin ? "default" : "outline"}>
                            {user.isAdmin ? "Admin" : "Estudante"}
                          </Badge>
                        </TableCell>
                        <TableCell>{formatDate(user.createdAt)}</TableCell>
                      </TableRow>
                    ))}
                  </TableBody>
                </Table>
              </CardContent>
            </Card>
          </TabsContent>

          <TabsContent value="enrollments">
            <Card>
              <CardHeader>
                <CardTitle className="font-display text-xl">Inscrições</CardTitle>
                <CardDescription>Acompanha compras e monitoriza a entrada de estudantes.</CardDescription>
              </CardHeader>
              <CardContent>
                <Table>
                  <TableHeader>
                    <TableRow>
                      <TableHead>Estudante</TableHead>
                      <TableHead>Curso</TableHead>
                      <TableHead>Estado</TableHead>
                      <TableHead>Criado</TableHead>
                      <TableHead className="text-right">Valor</TableHead>
                    </TableRow>
                  </TableHeader>
                  <TableBody>
                    {data.enrollments.map((enrollment) => (
                      <TableRow key={enrollment.id}>
                        <TableCell>
                          <p className="font-medium">{enrollment.fullName}</p>
                          <p className="text-xs text-muted-foreground">{enrollment.email}</p>
                        </TableCell>
                        <TableCell>{enrollment.courseTitle}</TableCell>
                        <TableCell>
                          <Badge variant="outline">{toEnrollmentStatusPt(enrollment.status)}</Badge>
                        </TableCell>
                        <TableCell>{formatDate(enrollment.createdAt)}</TableCell>
                        <TableCell className="text-right">
                          {formatMzn(enrollment.amount)}
                        </TableCell>
                      </TableRow>
                    ))}
                  </TableBody>
                </Table>
              </CardContent>
            </Card>
          </TabsContent>

          <TabsContent value="settings">
            <Card>
              <CardHeader>
                <CardTitle className="font-display text-xl flex items-center gap-2">
                  <Settings className="h-5 w-5 text-accent" />
                  Definições da Plataforma
                </CardTitle>
                <CardDescription>Configura comportamento global de catálogo e acesso.</CardDescription>
              </CardHeader>
              <CardContent className="space-y-6">
                <div className="grid gap-4 md:grid-cols-2">
                  <div className="space-y-1">
                    <Label>Nome da Plataforma</Label>
                    <Input
                      value={settingsForm.platformName}
                      onChange={(event) =>
                        setSettingsForm((prev) => ({
                          ...prev,
                          platformName: event.target.value,
                        }))
                      }
                    />
                  </div>
                  <div className="space-y-1">
                    <Label>E-mail de Suporte</Label>
                    <Input
                      type="email"
                      value={settingsForm.supportEmail}
                      onChange={(event) =>
                        setSettingsForm((prev) => ({
                          ...prev,
                          supportEmail: event.target.value,
                        }))
                      }
                    />
                  </div>
                  <div className="space-y-1">
                    <Label>Moeda</Label>
                    <Input
                      value={settingsForm.currency}
                      readOnly
                      disabled
                    />
                  </div>
                  <div className="space-y-1">
                    <Label>Visibilidade Padrão do Curso</Label>
                    <Input
                      value={settingsForm.defaultCourseVisibility}
                      onChange={(event) =>
                        setSettingsForm((prev) => ({
                          ...prev,
                          defaultCourseVisibility:
                            event.target.value.toLowerCase() === "private" ? "private" : "public",
                        }))
                      }
                    />
                  </div>
                </div>

                <div className="space-y-3">
                  <div className="flex items-center justify-between border rounded-lg p-3">
                    <div>
                      <p className="font-medium">Modo de Manutenção</p>
                      <p className="text-xs text-muted-foreground">
                        Desativa temporariamente operações normais da plataforma.
                      </p>
                    </div>
                    <Switch
                      checked={settingsForm.maintenanceMode}
                      onCheckedChange={(checked) =>
                        setSettingsForm((prev) => ({ ...prev, maintenanceMode: checked }))
                      }
                    />
                  </div>

                  <div className="flex items-center justify-between border rounded-lg p-3">
                    <div>
                      <p className="font-medium">Permitir Auto-registo</p>
                      <p className="text-xs text-muted-foreground">
                        Permite que os estudantes se registem diretamente.
                      </p>
                    </div>
                    <Switch
                      checked={settingsForm.allowSelfSignup}
                      onCheckedChange={(checked) =>
                        setSettingsForm((prev) => ({ ...prev, allowSelfSignup: checked }))
                      }
                    />
                  </div>
                </div>

                <Button
                  className="bg-accent hover:bg-accent-hover text-accent-foreground"
                  disabled={saveSettings.isPending}
                  onClick={() => saveSettings.mutate({ ...settingsForm, currency: "MZN" })}
                >
                  {saveSettings.isPending ? "A guardar..." : "Guardar Definições"}
                </Button>
              </CardContent>
            </Card>
          </TabsContent>

          <TabsContent value="create">
            <div className="grid lg:grid-cols-3 gap-8">
              <Card className="lg:col-span-1">
                <CardHeader>
                  <CardTitle className="font-display text-xl flex items-center gap-2">
                    Detalhes do Curso
                    {editingCourseId && <Badge>A editar</Badge>}
                  </CardTitle>
                  <CardDescription>
                    {editingCourseId
                      ? "Atualiza metadados e currículo deste curso existente."
                      : "Define metadados e preço para o teu novo curso."}
                  </CardDescription>
                </CardHeader>
                <CardContent className="space-y-4">
                  <div>
                    <Label className="font-body text-sm">Título</Label>
                    <Input
                      value={courseTitle}
                      onChange={(e) => setCourseTitle(e.target.value)}
                      className="mt-1 font-body"
                    />
                  </div>

                  <div>
                    <Label className="font-body text-sm">Subtítulo</Label>
                    <Input
                      value={courseSubtitle}
                      onChange={(e) => setCourseSubtitle(e.target.value)}
                      className="mt-1 font-body"
                    />
                  </div>

                  <div>
                    <Label className="font-body text-sm">Instrutor</Label>
                    <Input
                      value={courseInstructor}
                      onChange={(e) => setCourseInstructor(e.target.value)}
                      className="mt-1 font-body"
                    />
                  </div>

                  <div>
                    <Label className="font-body text-sm">Descrição</Label>
                    <Textarea
                      value={courseDescription}
                      onChange={(e) => setCourseDescription(e.target.value)}
                      placeholder="O que os estudantes vão aprender?"
                      className="mt-1 font-body min-h-[120px]"
                    />
                  </div>

                  <div className="grid grid-cols-2 gap-3">
                    <div>
                      <Label className="font-body text-sm">Preço (MZN)</Label>
                      <Input
                        type="number"
                        value={price}
                        onChange={(e) => setPrice(e.target.value)}
                        className="mt-1 font-body"
                      />
                    </div>
                    <div>
                      <Label className="font-body text-sm">Original</Label>
                      <Input
                        type="number"
                        value={originalPrice}
                        onChange={(e) => setOriginalPrice(e.target.value)}
                        className="mt-1 font-body"
                      />
                    </div>
                  </div>

                  <div className="grid grid-cols-2 gap-3">
                    <div>
                      <Label className="font-body text-sm">Categoria</Label>
                      <select
                        value={category}
                        onChange={(e) => setCategory(e.target.value)}
                        className="mt-1 flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm font-body"
                      >
                        <option value="">Selecionar categoria</option>
                        {CATEGORIES.map((item) => (
                          <option key={item} value={item}>
                            {item}
                          </option>
                        ))}
                      </select>
                    </div>
                    <div>
                      <Label className="font-body text-sm">Nível</Label>
                      <select
                        value={level}
                        onChange={(e) => setLevel(e.target.value)}
                        className="mt-1 flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm font-body"
                      >
                        <option value="">Selecionar nível</option>
                        {LEVELS.map((item) => (
                          <option key={item} value={item}>
                            {item}
                          </option>
                        ))}
                      </select>
                    </div>
                  </div>

                  <div>
                    <Label className="font-body text-sm">URL da Miniatura</Label>
                    <Input
                      value={thumbnail}
                      onChange={(e) => setThumbnail(e.target.value)}
                      className="mt-1 font-body"
                    />
                  </div>

                  <div className="space-y-2">
                    <Button
                      onClick={saveCourseAction}
                      disabled={
                        createCourseMutation.isPending ||
                        updateCourseMutation.isPending ||
                        isPreparingEdit
                      }
                      className="w-full bg-accent hover:bg-accent-hover text-accent-foreground font-body font-semibold rounded-lg"
                    >
                      <Save className="h-4 w-4 mr-2" />
                      {createCourseMutation.isPending || updateCourseMutation.isPending
                        ? "A guardar..."
                        : editingCourseId
                          ? "Atualizar Curso"
                          : "Guardar Curso"}
                    </Button>
                    {editingCourseId && (
                      <Button
                        variant="outline"
                        className="w-full"
                        onClick={() => {
                          resetCourseBuilder();
                          setActiveTab("courses");
                        }}
                      >
                        Cancelar Edição
                      </Button>
                    )}
                  </div>
                </CardContent>
              </Card>

              <div className="lg:col-span-2 space-y-4">
                <Card>
                  <CardHeader className="flex flex-row items-center justify-between">
                    <div>
                      <CardTitle className="font-display text-xl">Construtor de Currículo</CardTitle>
                      <CardDescription>Estrutura secções e lições antes de publicar.</CardDescription>
                    </div>
                    <div className="flex items-center gap-2">
                      <Button variant="outline" onClick={expandAllSections} className="font-body text-xs">
                        Expandir tudo
                      </Button>
                      <Button variant="outline" onClick={collapseAllSections} className="font-body text-xs">
                        Recolher tudo
                      </Button>
                      <Button variant="outline" onClick={addSection} className="font-body text-sm">
                        <Plus className="h-4 w-4 mr-1" />
                        Adicionar Secção
                      </Button>
                    </div>
                  </CardHeader>
                </Card>

                {sections.map((section, sectionIndex) => (
                  <Card key={section.id} className="overflow-hidden">
                    <CardContent className="p-0">
                      <div className="flex items-center gap-3 p-4 bg-surface-elevated border-b border-border">
                        <Button
                          type="button"
                          variant="ghost"
                          size="icon"
                          onClick={() =>
                            setCollapsedSectionIds((prev) => ({
                              ...prev,
                              [section.id]: !prev[section.id],
                            }))
                          }
                          className="h-7 w-7 text-muted-foreground"
                          title={collapsedSectionIds[section.id] ? "Expandir secção" : "Recolher secção"}
                        >
                          {collapsedSectionIds[section.id] ? (
                            <ChevronRight className="h-4 w-4" />
                          ) : (
                            <ChevronDown className="h-4 w-4" />
                          )}
                        </Button>
                        <GripVertical className="h-4 w-4 text-muted-foreground cursor-grab" />
                        <span className="text-xs font-semibold text-muted-foreground font-body">
                          {String(sectionIndex + 1).padStart(2, "0")}
                        </span>
                        <Input
                          value={section.title}
                          onChange={(event) =>
                            setSections((prev) =>
                              prev.map((item) =>
                                item.id === section.id ? { ...item, title: event.target.value } : item,
                              ),
                            )
                          }
                          className="flex-1 font-body font-semibold border-0 bg-transparent p-0 h-auto focus-visible:ring-0"
                        />
                        <Button
                          variant="ghost"
                          size="icon"
                          onClick={() => removeSection(section.id)}
                          className="h-8 w-8 text-muted-foreground hover:text-destructive"
                        >
                          <Trash2 className="h-4 w-4" />
                        </Button>
                      </div>

                      {collapsedSectionIds[section.id] ? (
                        <div className="px-4 py-3 text-xs text-muted-foreground">
                          Secção recolhida ({section.lessons.length} lições)
                        </div>
                      ) : (
                        <>
                          <div className="divide-y divide-border">
                            {section.lessons.map((lesson) => (
                              <div key={lesson.id} className="px-4 py-3 hover:bg-muted/30 transition-colors space-y-2">
                                <div className="grid grid-cols-12 gap-3 items-center">
                                  <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    className="col-span-1 h-7 w-7 text-muted-foreground"
                                    onClick={() =>
                                      setCollapsedLessonIds((prev) => ({
                                        ...prev,
                                        [lesson.id]: !prev[lesson.id],
                                      }))
                                    }
                                    title={collapsedLessonIds[lesson.id] ? "Expandir lição" : "Recolher lição"}
                                  >
                                    {collapsedLessonIds[lesson.id] ? (
                                      <ChevronRight className="h-3.5 w-3.5" />
                                    ) : (
                                      <ChevronDown className="h-3.5 w-3.5" />
                                    )}
                                  </Button>
                                  <div className="col-span-1">
                                    {lesson.type === "video" ? (
                                      <Video className="h-4 w-4 text-accent shrink-0" />
                                    ) : lesson.type === "quiz" ? (
                                      <ListChecks className="h-4 w-4 text-accent shrink-0" />
                                    ) : lesson.type === "code" ? (
                                      <Code2 className="h-4 w-4 text-accent shrink-0" />
                                    ) : (
                                      <FileText className="h-4 w-4 text-muted-foreground shrink-0" />
                                    )}
                                  </div>
                                  <Input
                                    value={lesson.title}
                                    onChange={(event) =>
                                      updateLesson(section.id, lesson.id, (item) => ({
                                        ...item,
                                        title: event.target.value,
                                      }))
                                    }
                                    className="col-span-4 font-body text-sm border-0 bg-transparent p-0 h-auto focus-visible:ring-0"
                                  />
                                  <Input
                                    value={lesson.duration}
                                    onChange={(event) =>
                                      updateLesson(section.id, lesson.id, (item) => ({
                                        ...item,
                                        duration: event.target.value,
                                      }))
                                    }
                                    className="col-span-2 font-body text-xs"
                                    placeholder="mm:ss"
                                  />
                                  <Badge
                                    variant={lesson.isFree ? "default" : "outline"}
                                    className="col-span-1 justify-center text-xs font-body shrink-0 cursor-pointer"
                                    onClick={() =>
                                      updateLesson(section.id, lesson.id, (item) => ({
                                        ...item,
                                        isFree: !item.isFree,
                                      }))
                                    }
                                  >
                                    {lesson.isFree ? "grátis" : "pago"}
                                  </Badge>
                                  <select
                                    value={lesson.type}
                                    onChange={(event) =>
                                      updateLesson(section.id, lesson.id, (item) => {
                                        const nextType = event.target.value as AdminLesson["type"];
                                        if (nextType !== "quiz") {
                                          return {
                                            ...item,
                                            type: nextType,
                                          };
                                        }

                                        return {
                                          ...item,
                                          type: "quiz",
                                          quizQuestions:
                                            item.quizQuestions.length > 0
                                              ? item.quizQuestions
                                              : [createEmptyQuizQuestion()],
                                          quizPassPercentage: clampQuizPassPercentage(item.quizPassPercentage),
                                          quizRandomizeQuestions: item.quizRandomizeQuestions ?? true,
                                        };
                                      })
                                    }
                                    className="col-span-2 h-8 rounded-md border border-input bg-background px-2 text-xs"
                                  >
                                    <option value="code">código</option>
                                    <option value="video">vídeo</option>
                                    <option value="text">texto</option>
                                    <option value="quiz">questionário</option>
                                    <option value="project">projeto</option>
                                  </select>
                                  <Button
                                    variant="ghost"
                                    size="icon"
                                    onClick={() => removeLesson(section.id, lesson.id)}
                                    className="col-span-1 h-7 w-7 text-muted-foreground hover:text-destructive"
                                  >
                                    <Trash2 className="h-3.5 w-3.5" />
                                  </Button>
                                </div>

                                {!collapsedLessonIds[lesson.id] && (
                                  <>
                                    <div className="grid md:grid-cols-2 gap-2">
                                      <Input
                                        value={lesson.videoUrl}
                                        onChange={(event) =>
                                          updateLesson(section.id, lesson.id, (item) => ({
                                            ...item,
                                            videoUrl: event.target.value,
                                          }))
                                        }
                                        placeholder="URL do vídeo (YouTube, Vimeo, MP4...)"
                                        className="font-body text-xs"
                                      />
                                      {lesson.type !== "code" && lesson.type !== "quiz" && (
                                        <Input
                                          value={lesson.language}
                                          onChange={(event) =>
                                            updateLesson(section.id, lesson.id, (item) => ({
                                              ...item,
                                              language: event.target.value,
                                            }))
                                          }
                                          placeholder="Linguagem (html, js, php...)"
                                          className="font-body text-xs"
                                        />
                                      )}
                                    </div>

                                    <RichTextEditor
                                      value={lesson.content}
                                      onChange={(nextContent) =>
                                        updateLesson(section.id, lesson.id, (item) => ({
                                          ...item,
                                          content: nextContent,
                                        }))
                                      }
                                      placeholder="Instruções da lição / desafio"
                                    />

                                    {lesson.type === "code" && (
                                      <div className="grid grid-cols-1 gap-2">
                                        <div className="space-y-1">
                                          <p className="text-[11px] font-semibold uppercase tracking-wide text-muted-foreground">
                                            HTML
                                          </p>
                                          <CodeHighlightEditor
                                            language="html"
                                            value={lesson.htmlCode}
                                            onChange={(nextCode) =>
                                              updateLesson(section.id, lesson.id, (item) => ({
                                                ...item,
                                                language: "html",
                                                htmlCode: nextCode,
                                              }))
                                            }
                                            placeholder="HTML"
                                          />
                                        </div>
                                        <div className="space-y-1">
                                          <p className="text-[11px] font-semibold uppercase tracking-wide text-muted-foreground">
                                            CSS
                                          </p>
                                          <CodeHighlightEditor
                                            language="css"
                                            value={lesson.cssCode}
                                            onChange={(nextCode) =>
                                              updateLesson(section.id, lesson.id, (item) => ({
                                                ...item,
                                                cssCode: nextCode,
                                              }))
                                            }
                                            placeholder="CSS"
                                          />
                                        </div>
                                        <div className="space-y-1">
                                          <p className="text-[11px] font-semibold uppercase tracking-wide text-muted-foreground">
                                            JavaScript
                                          </p>
                                          <CodeHighlightEditor
                                            language="js"
                                            value={lesson.jsCode}
                                            onChange={(nextCode) =>
                                              updateLesson(section.id, lesson.id, (item) => ({
                                                ...item,
                                                jsCode: nextCode,
                                              }))
                                            }
                                            placeholder="JavaScript"
                                          />
                                        </div>
                                      </div>
                                    )}

                                    {lesson.type === "quiz" && (
                                      <div className="rounded-md border border-border bg-surface-sunken p-3 space-y-3">
                                        <div className="grid gap-3 md:grid-cols-[180px_1fr] md:items-end">
                                          <div className="space-y-1">
                                            <Label className="text-[11px] uppercase tracking-wide text-muted-foreground">
                                              Nota mínima (%)
                                            </Label>
                                            <Input
                                              type="number"
                                              min={1}
                                              max={100}
                                              value={lesson.quizPassPercentage}
                                              onChange={(event) =>
                                                updateLesson(section.id, lesson.id, (item) => ({
                                                  ...item,
                                                  quizPassPercentage: clampQuizPassPercentage(Number(event.target.value)),
                                                }))
                                              }
                                              className="font-body text-xs"
                                            />
                                          </div>
                                          <div className="flex items-center justify-between rounded-md border border-border bg-background px-3 py-2">
                                            <div>
                                              <p className="text-xs font-medium">Randomizar perguntas</p>
                                              <p className="text-[11px] text-muted-foreground">
                                                A ordem das perguntas muda a cada tentativa.
                                              </p>
                                            </div>
                                            <Switch
                                              checked={lesson.quizRandomizeQuestions}
                                              onCheckedChange={(checked) =>
                                                updateLesson(section.id, lesson.id, (item) => ({
                                                  ...item,
                                                  quizRandomizeQuestions: checked,
                                                }))
                                              }
                                            />
                                          </div>
                                        </div>

                                        <div className="space-y-3">
                                          {lesson.quizQuestions.map((question, questionIndex) => (
                                            <div key={question.id} className="rounded-md border border-border bg-background p-3 space-y-2">
                                              <div className="flex items-center gap-2">
                                                <Badge variant="outline" className="text-[10px] px-2 py-0.5">
                                                  P{questionIndex + 1}
                                                </Badge>
                                                <Input
                                                  value={question.question}
                                                  onChange={(event) =>
                                                    updateLesson(section.id, lesson.id, (item) => ({
                                                      ...item,
                                                      quizQuestions: item.quizQuestions.map((candidate) =>
                                                        candidate.id === question.id
                                                          ? { ...candidate, question: event.target.value }
                                                          : candidate,
                                                      ),
                                                    }))
                                                  }
                                                  placeholder="Escreve a pergunta"
                                                  className="font-body text-xs"
                                                />
                                                <Button
                                                  type="button"
                                                  variant="ghost"
                                                  size="icon"
                                                  disabled={lesson.quizQuestions.length <= 1}
                                                  onClick={() =>
                                                    updateLesson(section.id, lesson.id, (item) => ({
                                                      ...item,
                                                      quizQuestions: item.quizQuestions
                                                        .filter((candidate) => candidate.id !== question.id)
                                                        .map((candidate) => ({
                                                          ...candidate,
                                                          correctOptionIndex: Math.max(
                                                            0,
                                                            Math.min(candidate.correctOptionIndex, candidate.options.length - 1),
                                                          ),
                                                        })),
                                                    }))
                                                  }
                                                  className="h-7 w-7 text-muted-foreground hover:text-destructive"
                                                  title="Eliminar pergunta"
                                                >
                                                  <Trash2 className="h-3.5 w-3.5" />
                                                </Button>
                                              </div>

                                              <div className="space-y-2">
                                                {question.options.map((option, optionIndex) => (
                                                  <div key={`${question.id}-${optionIndex}`} className="flex items-center gap-2">
                                                    <Button
                                                      type="button"
                                                      variant={question.correctOptionIndex === optionIndex ? "default" : "outline"}
                                                      size="sm"
                                                      className={
                                                        question.correctOptionIndex === optionIndex
                                                          ? "h-7 text-[11px]"
                                                          : "h-7 text-[11px]"
                                                      }
                                                      onClick={() =>
                                                        updateLesson(section.id, lesson.id, (item) => ({
                                                          ...item,
                                                          quizQuestions: item.quizQuestions.map((candidate) =>
                                                            candidate.id === question.id
                                                              ? { ...candidate, correctOptionIndex: optionIndex }
                                                              : candidate,
                                                          ),
                                                        }))
                                                      }
                                                    >
                                                      {question.correctOptionIndex === optionIndex ? "Correta" : "Marcar"}
                                                    </Button>
                                                    <Input
                                                      value={option}
                                                      onChange={(event) =>
                                                        updateLesson(section.id, lesson.id, (item) => ({
                                                          ...item,
                                                          quizQuestions: item.quizQuestions.map((candidate) =>
                                                            candidate.id === question.id
                                                              ? {
                                                                ...candidate,
                                                                options: candidate.options.map((candidateOption, candidateIndex) =>
                                                                  candidateIndex === optionIndex
                                                                    ? event.target.value
                                                                    : candidateOption,
                                                                ),
                                                              }
                                                              : candidate,
                                                          ),
                                                        }))
                                                      }
                                                      placeholder={`Opção ${optionIndex + 1}`}
                                                      className="font-body text-xs"
                                                    />
                                                    <Button
                                                      type="button"
                                                      variant="ghost"
                                                      size="icon"
                                                      disabled={question.options.length <= 2}
                                                      onClick={() =>
                                                        updateLesson(section.id, lesson.id, (item) => ({
                                                          ...item,
                                                          quizQuestions: item.quizQuestions.map((candidate) => {
                                                            if (candidate.id !== question.id) {
                                                              return candidate;
                                                            }

                                                            const nextOptions = candidate.options.filter(
                                                              (_candidateOption, candidateIndex) => candidateIndex !== optionIndex,
                                                            );
                                                            const nextCorrectIndex = candidate.correctOptionIndex > optionIndex
                                                              ? candidate.correctOptionIndex - 1
                                                              : candidate.correctOptionIndex;

                                                            return {
                                                              ...candidate,
                                                              options: nextOptions,
                                                              correctOptionIndex: Math.max(
                                                                0,
                                                                Math.min(nextCorrectIndex, nextOptions.length - 1),
                                                              ),
                                                            };
                                                          }),
                                                        }))
                                                      }
                                                      className="h-7 w-7 text-muted-foreground hover:text-destructive"
                                                      title="Eliminar opção"
                                                    >
                                                      <Trash2 className="h-3.5 w-3.5" />
                                                    </Button>
                                                  </div>
                                                ))}
                                              </div>

                                              <Button
                                                type="button"
                                                variant="ghost"
                                                size="sm"
                                                className="text-xs"
                                                onClick={() =>
                                                  updateLesson(section.id, lesson.id, (item) => ({
                                                    ...item,
                                                    quizQuestions: item.quizQuestions.map((candidate) =>
                                                      candidate.id === question.id
                                                        ? {
                                                          ...candidate,
                                                          options: [...candidate.options, `Opção ${candidate.options.length + 1}`],
                                                        }
                                                        : candidate,
                                                    ),
                                                  }))
                                                }
                                              >
                                                <Plus className="h-3.5 w-3.5 mr-1" />
                                                Adicionar opção
                                              </Button>
                                            </div>
                                          ))}
                                        </div>

                                        <Button
                                          type="button"
                                          variant="outline"
                                          size="sm"
                                          className="text-xs"
                                          onClick={() =>
                                            updateLesson(section.id, lesson.id, (item) => ({
                                              ...item,
                                              quizQuestions: [...item.quizQuestions, createEmptyQuizQuestion()],
                                            }))
                                          }
                                        >
                                          <Plus className="h-3.5 w-3.5 mr-1" />
                                          Adicionar pergunta
                                        </Button>
                                      </div>
                                    )}
                                  </>
                                )}
                              </div>
                            ))}
                          </div>

                          <div className="p-3 border-t border-border">
                            <Button
                              variant="ghost"
                              size="sm"
                              onClick={() => addLesson(section.id)}
                              className="font-body text-xs text-muted-foreground"
                            >
                              <Plus className="h-3.5 w-3.5 mr-1" />
                              Adicionar Lição
                            </Button>
                          </div>
                        </>
                      )}
                    </CardContent>
                  </Card>
                ))}
              </div>
            </div>
          </TabsContent>
        </Tabs>

        <div className="mt-10 grid sm:grid-cols-2 lg:grid-cols-4 gap-4">
          <Card>
            <CardContent className="pt-6">
              <p className="text-sm text-muted-foreground">Versão do Painel</p>
              <p className="font-display text-xl mt-1">1.0</p>
            </CardContent>
          </Card>
          <Card>
            <CardContent className="pt-6">
              <p className="text-sm text-muted-foreground">Saúde do Catálogo</p>
              <p className="font-display text-xl mt-1">Estável</p>
            </CardContent>
          </Card>
          <Card>
            <CardContent className="pt-6">
              <p className="text-sm text-muted-foreground">Sinal de Crescimento</p>
              <p className="font-display text-xl mt-1 flex items-center gap-2">
                <BarChart3 className="h-4 w-4 text-accent" />
                Positivo
              </p>
            </CardContent>
          </Card>
          <Card>
            <CardContent className="pt-6">
              <p className="text-sm text-muted-foreground">Modo de Admin</p>
              <p className="font-display text-xl mt-1">
                {data.settings.maintenanceMode ? "Manutenção" : "Ativo"}
              </p>
            </CardContent>
          </Card>
        </div>
      </main>
    </div>
      </div>
    </div>
  );
};

export default AdminPage;
