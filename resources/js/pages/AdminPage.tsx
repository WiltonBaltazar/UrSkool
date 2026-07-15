import { useEffect, useMemo, useState } from "react";
import {
  Activity,
  AlertTriangle,
  BarChart3,
  Bell,
  BookOpen,
  ChevronDown,
  ChevronRight,
  Code2,
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
import type { AdminSettings, CodeValidationRule, LessonTextMediaType, LessonWorkspaceFile } from "@/lib/types";
import { cn } from "@/lib/utils";
import { useToast } from "@/hooks/use-toast";

interface AdminLesson {
  id: string;
  title: string;
  duration: string;
  videoUrl: string;
  textMediaType: LessonTextMediaType;
  textMediaImageUrl: string;
  textMediaYoutubeUrl: string;
  language: string;
  content: string;
  starterCode: string;
  htmlCode: string;
  cssCode: string;
  jsCode: string;
  workspaceFiles: LessonWorkspaceFile[];
  entryHtmlFileId: string;
  validationRules: CodeValidationRule[];
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

const VALIDATION_RULE_KIND_OPTIONS: Array<{ value: CodeValidationRule["kind"]; label: string }> = [
  { value: "selector_exists", label: "Elemento existe" },
  { value: "html_includes", label: "HTML inclui" },
  { value: "css_includes", label: "CSS inclui" },
  { value: "js_includes", label: "JS inclui" },
  { value: "text_includes", label: "Texto inclui" },
];

const createEmptyValidationRule = (): CodeValidationRule => ({
  kind: "selector_exists",
  value: "",
});

const normalizeAdminValidationRules = (rules?: CodeValidationRule[] | null): CodeValidationRule[] => {
  const source = Array.isArray(rules) ? rules : [];

  return source
    .map((rule) => ({
      kind: rule.kind,
      value: (rule.value || "").trim(),
    }))
    .filter((rule) => VALIDATION_RULE_KIND_OPTIONS.some((option) => option.value === rule.kind));
};

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

const resolveTextMediaType = (lesson: {
  type?: string | null;
  textMediaType?: LessonTextMediaType | null;
  textMediaImageUrl?: string | null;
  textMediaYoutubeUrl?: string | null;
  videoUrl?: string | null;
}): LessonTextMediaType => {
  if (lesson.type !== "text") return "none";

  if (lesson.textMediaType && ["none", "image", "youtube"].includes(lesson.textMediaType)) {
    return lesson.textMediaType;
  }

  if ((lesson.textMediaImageUrl || "").trim() !== "") return "image";
  if ((lesson.textMediaYoutubeUrl || lesson.videoUrl || "").trim() !== "") return "youtube";

  return "none";
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

const deriveLegacyCodeFromWorkspace = (workspaceFiles: LessonWorkspaceFile[], entryHtmlFileId?: string): {
  htmlCode: string;
  cssCode: string;
  jsCode: string;
} => {
  const entryHtml = workspaceFiles.find((file) => file.id === entryHtmlFileId && file.language === "html")
    || workspaceFiles.find((file) => file.name.toLowerCase() === "index.html" && file.language === "html")
    || workspaceFiles.find((file) => file.language === "html");
  const cssCode = workspaceFiles
    .filter((file) => file.language === "css")
    .map((file) => file.content || "")
    .join("\n\n");
  const jsCode = workspaceFiles
    .filter((file) => file.language === "js")
    .map((file) => file.content || "")
    .join("\n\n");

  return {
    htmlCode: entryHtml?.content || "",
    cssCode,
    jsCode,
  };
};

const createEmptyLesson = (): AdminLesson => {
  const id = crypto.randomUUID();
  const workspaceFiles = buildWorkspaceDefaults(id, "", "", "");

  return {
    id,
    title: "Nova lição",
    duration: "05:00",
    videoUrl: "",
    textMediaType: "none",
    textMediaImageUrl: "",
    textMediaYoutubeUrl: "",
    language: "html",
    content: "",
    starterCode: "",
    htmlCode: "",
    cssCode: "",
    jsCode: "",
    workspaceFiles,
    entryHtmlFileId: normalizeEntryHtmlFileId(workspaceFiles),
    validationRules: [],
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
  certificateSchoolName: "UrSkool",
  certificateIssuerTitle: "Diretor Executivo",
  certificateIssuerName: "Direção Académica",
  certificateSignatureUrl: "",
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
  const [certificateSignatureFile, setCertificateSignatureFile] = useState<File | null>(null);
  const [removeCertificateSignature, setRemoveCertificateSignature] = useState(false);
  const [courseSearch, setCourseSearch] = useState("");
  const [activeTab, setActiveTab] = useState("overview");
  const [editingCourseId, setEditingCourseId] = useState<string | null>(null);
  const [isPreparingEdit, setIsPreparingEdit] = useState(false);
  const [collapsedSectionIds, setCollapsedSectionIds] = useState<Record<string, boolean>>({});
  const [collapsedLessonIds, setCollapsedLessonIds] = useState<Record<string, boolean>>({});
  const [textMediaImageFiles, setTextMediaImageFiles] = useState<Record<string, File | null>>({});
  const [removeTextMediaImageByLesson, setRemoveTextMediaImageByLesson] = useState<Record<string, boolean>>({});

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
      setCertificateSignatureFile(null);
      setRemoveCertificateSignature(false);
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
    setTextMediaImageFiles({});
    setRemoveTextMediaImageByLesson({});
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
    mutationFn: (payload: AdminSettings) => updateAdminSettings(payload, {
      certificateSignatureFile,
      removeCertificateSignature,
    }),
    onSuccess: () => {
      toast({
        title: "Definições atualizadas",
        description: "As definições da plataforma foram guardadas com sucesso.",
      });
      setCertificateSignatureFile(null);
      setRemoveCertificateSignature(false);
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
      setTextMediaImageFiles((prev) => {
        const next = { ...prev };
        lessonIds.forEach((id) => {
          delete next[id];
        });
        return next;
      });
      setRemoveTextMediaImageByLesson((prev) => {
        const next = { ...prev };
        lessonIds.forEach((id) => {
          delete next[id];
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
    setTextMediaImageFiles((prev) => {
      const next = { ...prev };
      delete next[lessonId];
      return next;
    });
    setRemoveTextMediaImageByLesson((prev) => {
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
            textMediaType: resolveTextMediaType(lesson),
            textMediaImageUrl: lesson.textMediaImageUrl || "",
            textMediaYoutubeUrl: lesson.textMediaYoutubeUrl || (lesson.type === "text" ? lesson.videoUrl || "" : ""),
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
            validationRules: normalizeAdminValidationRules(lesson.validationRules),
            quizQuestions: normalizeAdminQuizQuestions(lesson.quizQuestions),
            quizPassPercentage: clampQuizPassPercentage(Number(lesson.quizPassPercentage ?? 80)),
            quizRandomizeQuestions: lesson.quizRandomizeQuestions ?? true,
            isFree: lesson.isFree,
            type: lesson.type || "code",
          })),
        })),
      );
      setTextMediaImageFiles({});
      setRemoveTextMediaImageByLesson({});
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
      lessons: section.lessons.map((lesson) => {
        const canHaveWorkspace = lesson.type === "code" || lesson.type === "project";
        const workspaceFiles = canHaveWorkspace
          ? normalizeAdminWorkspaceFiles(
            lesson.workspaceFiles,
            lesson.id,
            lesson.htmlCode,
            lesson.cssCode,
            lesson.jsCode,
          )
          : [];
        const entryHtmlFileId = canHaveWorkspace
          ? normalizeEntryHtmlFileId(workspaceFiles, lesson.entryHtmlFileId)
          : "";
        const legacyCode = canHaveWorkspace
          ? deriveLegacyCodeFromWorkspace(workspaceFiles, entryHtmlFileId)
          : {
            htmlCode: lesson.htmlCode || "",
            cssCode: lesson.cssCode || "",
            jsCode: lesson.jsCode || "",
          };

        return {
          title: lesson.title,
          duration: lesson.duration,
          videoUrl: lesson.type === "text"
            ? (lesson.textMediaType === "youtube" ? (lesson.textMediaYoutubeUrl || undefined) : undefined)
            : lesson.videoUrl || undefined,
          textMediaType: lesson.type === "text" ? lesson.textMediaType : undefined,
          textMediaImageUrl: lesson.type === "text" ? (lesson.textMediaImageUrl || undefined) : undefined,
          textMediaImageFile: lesson.type === "text" ? (textMediaImageFiles[lesson.id] || undefined) : undefined,
          removeTextMediaImage: lesson.type === "text"
            ? Boolean(removeTextMediaImageByLesson[lesson.id])
            : undefined,
          textMediaYoutubeUrl: lesson.type === "text"
            ? (lesson.textMediaYoutubeUrl || undefined)
            : undefined,
          language: lesson.language || undefined,
          content: lesson.content || undefined,
          starterCode: lesson.starterCode || undefined,
          htmlCode: legacyCode.htmlCode || undefined,
          cssCode: legacyCode.cssCode || undefined,
          jsCode: legacyCode.jsCode || undefined,
          workspaceFiles: canHaveWorkspace ? workspaceFiles : undefined,
          entryHtmlFileId: canHaveWorkspace ? (entryHtmlFileId || undefined) : undefined,
          validationRules: canHaveWorkspace
            ? lesson.validationRules
              .map((rule) => ({
                kind: rule.kind,
                value: rule.value.trim(),
              }))
              .filter((rule) => rule.value.length > 0)
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
        };
      }),
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
  const courseCategoryById = new Map(data.courses.map((course) => [course.id, toCategoryPt(course.category)]));
  const eng = data.engagement;
  const activityMax = Math.max(...eng.activitySeries.map((d) => d.activeLearners), 1);
  const activitySparkPoints = eng.activitySeries
    .map((d, i) => {
      const x = (i / Math.max(1, eng.activitySeries.length - 1)) * 100;
      const y = 92 - (d.activeLearners / activityMax) * 74;
      return `${x},${y}`;
    })
    .join(" ");
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
                  <CardDescription>Aprendentes Activos (7 dias)</CardDescription>
                  <CardTitle className="flex items-center gap-2 text-2xl">
                    <Users className="h-5 w-5 text-foreground" />
                    {eng.weeklyActiveLearners}
                  </CardTitle>
                </CardHeader>
              </Card>
              <Card className="border-border">
                <CardHeader className="pb-2">
                  <CardDescription>Taxa de Activação (30 dias)</CardDescription>
                  <CardTitle className="flex items-center gap-2 text-2xl">
                    <Activity className="h-5 w-5 text-foreground" />
                    {`${eng.activationRate}%`}
                  </CardTitle>
                </CardHeader>
              </Card>
              <Card className="border-border">
                <CardHeader className="pb-2">
                  <CardDescription>Lições/Aprendente/Semana</CardDescription>
                  <CardTitle className="flex items-center gap-2 text-2xl">
                    <BookOpen className="h-5 w-5 text-foreground" />
                    {eng.avgLessonsPerActiveLearner}
                  </CardTitle>
                </CardHeader>
              </Card>
              <Card className="border-border">
                <CardHeader className="pb-2">
                  <CardDescription>Conversão de Matrículas</CardDescription>
                  <CardTitle className="flex items-center gap-2 text-2xl">
                    <BarChart3 className="h-5 w-5 text-foreground" />
                    {`${eng.checkoutConversionRate}%`}
                  </CardTitle>
                </CardHeader>
              </Card>
            </div>

            <Card className="border-border">
              <CardHeader>
                <CardTitle className="font-display text-xl">Actividade dos Últimos 14 Dias</CardTitle>
                <CardDescription>Aprendentes activos por dia.</CardDescription>
              </CardHeader>
              <CardContent className="space-y-4">
                <div className="overflow-x-auto">
                  <div className="flex min-w-[560px] items-end gap-1.5 pb-3 pt-2">
                    {eng.activitySeries.map((day) => (
                      <div key={day.date} className="flex flex-1 flex-col items-center gap-1.5">
                        <div className="flex h-48 w-full items-end rounded-md bg-muted/40 px-1">
                          <div
                            className="w-full rounded-sm bg-foreground transition-all"
                            style={{ height: `${Math.max(4, (day.activeLearners / activityMax) * 100)}%` }}
                          />
                        </div>
                        <span className="text-[10px] text-muted-foreground">
                          {new Date(day.date + "T00:00:00").toLocaleDateString("pt", { day: "numeric", month: "numeric" })}
                        </span>
                      </div>
                    ))}
                  </div>
                </div>
              </CardContent>
            </Card>

            <div className="grid gap-6 xl:grid-cols-3">
              <Card className="border-border">
                <CardHeader>
                  <CardTitle className="font-display text-lg">Lições Concluídas (7 dias)</CardTitle>
                  <CardDescription>
                    <span className="mr-1 text-3xl font-semibold text-foreground">{eng.weeklyLessonsCompleted}</span>
                    conclusões
                  </CardDescription>
                </CardHeader>
                <CardContent>
                  <div className="h-36 rounded-lg border border-border bg-muted/20 p-3">
                    <svg viewBox="0 0 100 100" className="h-full w-full" preserveAspectRatio="none">
                      <polyline
                        points={activitySparkPoints}
                        fill="none"
                        stroke="hsl(var(--foreground))"
                        strokeWidth="2.2"
                      />
                    </svg>
                  </div>
                  <div className="mt-4 grid grid-cols-2 gap-2 text-center text-xs">
                    <div>
                      <p className="text-lg font-semibold text-foreground">{eng.weeklyActiveLearners}</p>
                      <p className="text-muted-foreground">Activos / semana</p>
                    </div>
                    <div>
                      <p className="text-lg font-semibold text-foreground">{formatMzn(eng.revenuePerLearner)}</p>
                      <p className="text-muted-foreground">Receita / aprendente</p>
                    </div>
                  </div>
                </CardContent>
              </Card>

              <Card className="border-border">
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

              <Card className="border-border">
                <CardHeader>
                  <CardTitle className="font-display text-lg">Métricas de Receita</CardTitle>
                </CardHeader>
                <CardContent className="space-y-4">
                  <div className="space-y-1">
                    <p className="text-sm text-muted-foreground">Receita Total</p>
                    <p className="text-2xl font-semibold text-foreground">{formatMzn(data.stats.totalRevenue)}</p>
                  </div>
                  <div className="space-y-1">
                    <p className="text-sm text-muted-foreground">Receita por Aprendente</p>
                    <p className="text-2xl font-semibold text-foreground">{formatMzn(eng.revenuePerLearner)}</p>
                  </div>
                  <div className="space-y-1">
                    <p className="text-sm text-muted-foreground">Total de Matrículas</p>
                    <p className="text-2xl font-semibold text-foreground">{data.stats.totalEnrollments}</p>
                  </div>
                  <div className="space-y-1.5">
                    <div className="flex items-center justify-between text-xs">
                      <span className="text-muted-foreground">Conversão</span>
                      <span className="font-semibold text-foreground">{`${eng.checkoutConversionRate}%`}</span>
                    </div>
                    <div className="h-2 rounded-full bg-muted">
                      <div
                        className="h-2 rounded-full bg-foreground"
                        style={{ width: `${Math.min(100, eng.checkoutConversionRate)}%` }}
                      />
                    </div>
                  </div>
                </CardContent>
              </Card>
            </div>

            <Card className="border-border">
              <CardHeader>
                <CardTitle className="font-display text-lg">Matrículas Recentes</CardTitle>
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
                  <div className="space-y-1">
                    <Label>Nome da Escola (Certificado)</Label>
                    <Input
                      value={settingsForm.certificateSchoolName}
                      onChange={(event) =>
                        setSettingsForm((prev) => ({
                          ...prev,
                          certificateSchoolName: event.target.value,
                        }))
                      }
                    />
                  </div>
                  <div className="space-y-1">
                    <Label>Nome do Assinante</Label>
                    <Input
                      value={settingsForm.certificateIssuerName}
                      onChange={(event) =>
                        setSettingsForm((prev) => ({
                          ...prev,
                          certificateIssuerName: event.target.value,
                        }))
                      }
                    />
                  </div>
                  <div className="space-y-1">
                    <Label>Cargo do Assinante</Label>
                    <Input
                      value={settingsForm.certificateIssuerTitle}
                      onChange={(event) =>
                        setSettingsForm((prev) => ({
                          ...prev,
                          certificateIssuerTitle: event.target.value,
                        }))
                      }
                    />
                  </div>
                </div>

                <div className="space-y-2 rounded-lg border border-border p-3">
                  <Label>Assinatura (imagem)</Label>
                  <Input
                    type="file"
                    accept="image/*"
                    onChange={(event) => {
                      const file = event.target.files?.[0] || null;
                      setCertificateSignatureFile(file);
                      if (file) {
                        setRemoveCertificateSignature(false);
                      }
                    }}
                  />
                  {certificateSignatureFile && (
                    <p className="text-xs text-muted-foreground">
                      Nova assinatura selecionada: {certificateSignatureFile.name}
                    </p>
                  )}
                  {settingsForm.certificateSignatureUrl && !removeCertificateSignature && !certificateSignatureFile && (
                    <img
                      src={settingsForm.certificateSignatureUrl}
                      alt="Assinatura atual"
                      className="h-14 w-auto max-w-full object-contain rounded border border-border bg-surface-sunken p-1"
                    />
                  )}
                  {(settingsForm.certificateSignatureUrl || certificateSignatureFile) && (
                    <Button
                      type="button"
                      variant="outline"
                      size="sm"
                      className="text-xs"
                      onClick={() => {
                        setCertificateSignatureFile(null);
                        setRemoveCertificateSignature(true);
                      }}
                    >
                      Remover assinatura
                    </Button>
                  )}
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
                                    ) : (lesson.type === "code" || lesson.type === "project") ? (
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
                                    onChange={(event) => {
                                      const nextType = event.target.value as AdminLesson["type"];

                                      if (nextType !== "text") {
                                        setTextMediaImageFiles((prev) => {
                                          const next = { ...prev };
                                          delete next[lesson.id];
                                          return next;
                                        });
                                        setRemoveTextMediaImageByLesson((prev) => {
                                          const next = { ...prev };
                                          delete next[lesson.id];
                                          return next;
                                        });
                                      }

                                      updateLesson(section.id, lesson.id, (item) => {
                                        if (nextType === "quiz") {
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
                                        }

                                        if (nextType === "text") {
                                          const inferredType = resolveTextMediaType(item);
                                          return {
                                            ...item,
                                            type: "text",
                                            textMediaType: inferredType,
                                            textMediaYoutubeUrl: item.textMediaYoutubeUrl || item.videoUrl || "",
                                          };
                                        }

                                        return {
                                          ...item,
                                          type: nextType,
                                        };
                                      });
                                    }}
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

                                {(lesson.type === "code" || lesson.type === "project") && lesson.validationRules.length === 0 && (
                                  <div className="flex justify-end">
                                    <Badge variant="outline" className="text-[10px] text-amber-700 border-amber-300 bg-amber-50">
                                      <AlertTriangle className="mr-1 h-3 w-3" />
                                      Sem regras manuais (usa fallback automático)
                                    </Badge>
                                  </div>
                                )}

                                {!collapsedLessonIds[lesson.id] && (
                                  <>
                                    {lesson.type === "video" && (
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
                                        <Input
                                          value={lesson.language}
                                          onChange={(event) =>
                                            updateLesson(section.id, lesson.id, (item) => ({
                                              ...item,
                                              language: event.target.value,
                                            }))
                                          }
                                          placeholder="Linguagem (opcional)"
                                          className="font-body text-xs"
                                        />
                                      </div>
                                    )}

                                    {lesson.type === "text" && (
                                      <div className="space-y-3 rounded-md border border-border bg-surface-sunken p-3">
                                        <div className="grid gap-2 md:grid-cols-2 md:items-center">
                                          <div>
                                            <p className="text-xs font-medium text-foreground">Mídia do lado direito</p>
                                            <p className="text-[11px] text-muted-foreground">
                                              Escolhe se a aula teórica mostra imagem, vídeo YouTube ou apenas o painel visual.
                                            </p>
                                          </div>
                                          <select
                                            value={lesson.textMediaType}
                                            onChange={(event) => {
                                              const nextType = event.target.value as LessonTextMediaType;
                                              updateLesson(section.id, lesson.id, (item) => ({
                                                ...item,
                                                textMediaType: nextType,
                                              }));
                                            }}
                                            className="h-8 rounded-md border border-input bg-background px-2 text-xs"
                                          >
                                            <option value="none">Sem mídia</option>
                                            <option value="image">Imagem</option>
                                            <option value="youtube">Vídeo YouTube</option>
                                          </select>
                                        </div>

                                        {lesson.textMediaType === "youtube" && (
                                          <Input
                                            value={lesson.textMediaYoutubeUrl}
                                            onChange={(event) =>
                                              updateLesson(section.id, lesson.id, (item) => ({
                                                ...item,
                                                textMediaYoutubeUrl: event.target.value,
                                              }))
                                            }
                                            placeholder="URL do YouTube (ex: https://www.youtube.com/watch?v=...)"
                                            className="font-body text-xs"
                                          />
                                        )}

                                        {lesson.textMediaType === "image" && (
                                          <div className="space-y-2">
                                            <Input
                                              type="file"
                                              accept="image/*"
                                              onChange={(event) => {
                                                const nextFile = event.target.files?.[0] || null;
                                                setTextMediaImageFiles((prev) => ({
                                                  ...prev,
                                                  [lesson.id]: nextFile,
                                                }));
                                                setRemoveTextMediaImageByLesson((prev) => ({
                                                  ...prev,
                                                  [lesson.id]: false,
                                                }));
                                              }}
                                              className="font-body text-xs"
                                            />

                                            {(textMediaImageFiles[lesson.id] || lesson.textMediaImageUrl) && (
                                              <div className="flex flex-wrap items-center gap-2">
                                                {textMediaImageFiles[lesson.id] ? (
                                                  <p className="text-[11px] text-muted-foreground">
                                                    Nova imagem selecionada: {textMediaImageFiles[lesson.id]?.name}
                                                  </p>
                                                ) : removeTextMediaImageByLesson[lesson.id] ? (
                                                  <p className="text-[11px] text-amber-700">A imagem atual será removida ao guardar.</p>
                                                ) : (
                                                  <a
                                                    href={lesson.textMediaImageUrl}
                                                    target="_blank"
                                                    rel="noreferrer"
                                                    className="text-[11px] text-accent hover:underline"
                                                  >
                                                    Ver imagem atual
                                                  </a>
                                                )}

                                                <Button
                                                  type="button"
                                                  variant="outline"
                                                  size="sm"
                                                  className="h-7 text-[11px]"
                                                  onClick={() => {
                                                    setTextMediaImageFiles((prev) => ({
                                                      ...prev,
                                                      [lesson.id]: null,
                                                    }));
                                                    setRemoveTextMediaImageByLesson((prev) => ({
                                                      ...prev,
                                                      [lesson.id]: true,
                                                    }));
                                                  }}
                                                >
                                                  Remover imagem
                                                </Button>

                                                {removeTextMediaImageByLesson[lesson.id] && (
                                                  <Button
                                                    type="button"
                                                    variant="ghost"
                                                    size="sm"
                                                    className="h-7 text-[11px]"
                                                    onClick={() =>
                                                      setRemoveTextMediaImageByLesson((prev) => ({
                                                        ...prev,
                                                        [lesson.id]: false,
                                                      }))
                                                    }
                                                  >
                                                    Manter imagem
                                                  </Button>
                                                )}
                                              </div>
                                            )}
                                          </div>
                                        )}
                                      </div>
                                    )}

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

                                    {(lesson.type === "code" || lesson.type === "project") && (
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

                                        <div className="rounded-md border border-border bg-surface-sunken p-3 space-y-3">
                                          <div className="flex items-center justify-between gap-2">
                                            <div>
                                              <p className="text-xs font-medium">Regras de validação</p>
                                              <p className="text-[11px] text-muted-foreground">
                                                Estas regras são usadas no servidor para validar a prática.
                                              </p>
                                            </div>
                                            <Button
                                              type="button"
                                              size="sm"
                                              variant="outline"
                                              className="h-7 text-[11px]"
                                              onClick={() =>
                                                updateLesson(section.id, lesson.id, (item) => ({
                                                  ...item,
                                                  validationRules: [...item.validationRules, createEmptyValidationRule()],
                                                }))
                                              }
                                            >
                                              <Plus className="mr-1 h-3.5 w-3.5" />
                                              Adicionar regra
                                            </Button>
                                          </div>

                                          <div className="space-y-2">
                                            {lesson.validationRules.length === 0 ? (
                                              <p className="text-[11px] text-muted-foreground">
                                                Sem regras manuais. O sistema tenta gerar regras a partir do código base.
                                              </p>
                                            ) : lesson.validationRules.map((rule, ruleIndex) => (
                                              <div
                                                key={`${lesson.id}-rule-${ruleIndex}`}
                                                className="grid gap-2 md:grid-cols-[190px_1fr_auto] md:items-center"
                                              >
                                                <select
                                                  className="h-8 rounded-md border border-border bg-background px-2 text-xs"
                                                  value={rule.kind}
                                                  onChange={(event) =>
                                                    updateLesson(section.id, lesson.id, (item) => ({
                                                      ...item,
                                                      validationRules: item.validationRules.map((candidate, candidateIndex) => (
                                                        candidateIndex === ruleIndex
                                                          ? {
                                                            ...candidate,
                                                            kind: event.target.value as CodeValidationRule["kind"],
                                                          }
                                                          : candidate
                                                      )),
                                                    }))
                                                  }
                                                >
                                                  {VALIDATION_RULE_KIND_OPTIONS.map((option) => (
                                                    <option key={option.value} value={option.value}>
                                                      {option.label}
                                                    </option>
                                                  ))}
                                                </select>
                                                <Input
                                                  value={rule.value}
                                                  onChange={(event) =>
                                                    updateLesson(section.id, lesson.id, (item) => ({
                                                      ...item,
                                                      validationRules: item.validationRules.map((candidate, candidateIndex) => (
                                                        candidateIndex === ruleIndex
                                                          ? { ...candidate, value: event.target.value }
                                                          : candidate
                                                      )),
                                                    }))
                                                  }
                                                  placeholder="Ex: #cta, .card, <button, addEventListener"
                                                  className="font-body text-xs"
                                                />
                                                <Button
                                                  type="button"
                                                  variant="ghost"
                                                  size="icon"
                                                  disabled={lesson.validationRules.length <= 1}
                                                  onClick={() =>
                                                    updateLesson(section.id, lesson.id, (item) => ({
                                                      ...item,
                                                      validationRules: item.validationRules.filter(
                                                        (_candidate, candidateIndex) => candidateIndex !== ruleIndex,
                                                      ),
                                                    }))
                                                  }
                                                  className="h-7 w-7 text-muted-foreground hover:text-destructive"
                                                  title="Eliminar regra"
                                                >
                                                  <Trash2 className="h-3.5 w-3.5" />
                                                </Button>
                                              </div>
                                            ))}
                                          </div>
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
