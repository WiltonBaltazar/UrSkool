import { useEffect, useMemo, useState } from "react";
import {
  BarChart3,
  BookOpen,
  ChevronDown,
  ChevronRight,
  Code2,
  Banknote,
  Edit3,
  FileText,
  GripVertical,
  LayoutDashboard,
  ListChecks,
  Plus,
  Save,
  Settings,
  Trash2,
  Users,
  Video,
} from "lucide-react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import Navbar from "@/components/Navbar";
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
import {
  createCourse,
  deleteAdminCourse,
  fetchCourse,
  fetchAdminDashboard,
  updateAdminCourse,
  updateAdminSettings,
} from "@/lib/api";
import { formatMzn, toCategoryPt, toEnrollmentStatusPt, toLevelPt } from "@/lib/labels";
import type { AdminSettings } from "@/lib/types";
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

const createEmptyLesson = (): AdminLesson => ({
  id: crypto.randomUUID(),
  title: "Nova lição",
  duration: "05:00",
  videoUrl: "",
  language: "html",
  content: "",
  starterCode: "",
  htmlCode: "<h1>Olá UrSkool</h1>\n<p>Edita HTML, CSS e JS e executa a lição.</p>",
  cssCode: "body {\n  font-family: system-ui, sans-serif;\n  padding: 1.5rem;\n}",
  jsCode: "console.log('Lição iniciada');",
  quizQuestions: [createEmptyQuizQuestion()],
  quizPassPercentage: 80,
  quizRandomizeQuestions: true,
  isFree: false,
  type: "code",
});

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

const AdminPage = () => {
  const { toast } = useToast();
  const queryClient = useQueryClient();

  const { data, isLoading, isError } = useQuery({
    queryKey: ["admin-dashboard"],
    queryFn: fetchAdminDashboard,
  });

  const [settingsForm, setSettingsForm] = useState<AdminSettings>(defaultSettings);
  const [courseSearch, setCourseSearch] = useState("");
  const [activeTab, setActiveTab] = useState("overview");
  const [editingCourseId, setEditingCourseId] = useState<string | null>(null);
  const [isPreparingEdit, setIsPreparingEdit] = useState(false);
  const [collapsedSectionIds, setCollapsedSectionIds] = useState<Record<string, boolean>>({});
  const [collapsedLessonIds, setCollapsedLessonIds] = useState<Record<string, boolean>>({});

  const [courseTitle, setCourseTitle] = useState("Meu Novo Curso");
  const [courseSubtitle, setCourseSubtitle] = useState(
    "Desenvolve competências de programação prontas para produção com prática interativa.",
  );
  const [courseInstructor, setCourseInstructor] = useState("Instrutor do Curso");
  const [courseDescription, setCourseDescription] = useState("");
  const [courseRating, setCourseRating] = useState(4.8);
  const [courseReviewCount, setCourseReviewCount] = useState(0);
  const [courseStudentCount, setCourseStudentCount] = useState(0);
  const [price, setPrice] = useState("2999.00");
  const [originalPrice, setOriginalPrice] = useState("9999.00");
  const [category, setCategory] = useState("Desenvolvimento Web");
  const [level, setLevel] = useState("Iniciante");
  const [thumbnail, setThumbnail] = useState(
    "https://images.unsplash.com/photo-1516321318423-f06f85e504b3?w=600&h=400&fit=crop",
  );

  const [sections, setSections] = useState<AdminSection[]>([
    {
      id: crypto.randomUUID(),
      title: "Introdução",
      lessons: [
        {
          ...createEmptyLesson(),
          title: "Boas-vindas",
          duration: "04:00",
          isFree: true,
        },
        {
          ...createEmptyLesson(),
          title: "Visão Geral do Curso",
          duration: "06:30",
          isFree: true,
        },
      ],
    },
  ]);

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
    setCourseTitle("Meu Novo Curso");
    setCourseSubtitle("Desenvolve competências de programação prontas para produção com prática interativa.");
    setCourseInstructor("Instrutor do Curso");
    setCourseDescription("");
    setCourseRating(4.8);
    setCourseReviewCount(0);
    setCourseStudentCount(0);
    setPrice("2999.00");
    setOriginalPrice("9999.00");
    setCategory("Desenvolvimento Web");
    setLevel("Iniciante");
    setThumbnail("https://images.unsplash.com/photo-1516321318423-f06f85e504b3?w=600&h=400&fit=crop");
    setSections([
      {
        id: crypto.randomUUID(),
        title: "Introdução",
        lessons: [
          {
            ...createEmptyLesson(),
            title: "Boas-vindas",
            duration: "04:00",
            isFree: true,
          },
          {
            ...createEmptyLesson(),
            title: "Visão Geral do Curso",
            duration: "06:30",
            isFree: true,
          },
        ],
      },
    ]);
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

  const addSection = () => {
    const newSection = createEmptySection();
    setSections((prev) => [...prev, newSection]);
    setCollapsedSectionIds((prev) => ({ ...prev, [newSection.id]: false }));
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
      <div className="min-h-screen bg-background">
        <Navbar />
        <div className="container mx-auto px-4 py-12">
          <p className="text-muted-foreground font-body">A carregar painel de administração...</p>
        </div>
      </div>
    );
  }

  if (isError || !data) {
    return (
      <div className="min-h-screen bg-background">
        <Navbar />
        <div className="container mx-auto px-4 py-12">
          <p className="text-destructive font-body">Não foi possível carregar os dados do painel de administração.</p>
        </div>
      </div>
    );
  }

  return (
    <div className="min-h-screen bg-background">
      <Navbar />

      <div className="container mx-auto px-4 py-8 md:py-10">
        <div className="flex flex-col md:flex-row md:items-end md:justify-between gap-4 mb-8">
          <div>
            <h1 className="font-display text-3xl text-foreground flex items-center gap-2">
              <LayoutDashboard className="h-7 w-7 text-accent" />
              Painel de Administração
            </h1>
            <p className="text-muted-foreground font-body text-sm mt-1">
              Gere catálogo, estudantes, definições da plataforma e crescimento num único espaço.
            </p>
          </div>
          <div className="flex gap-2">
            <Badge variant="outline" className="font-body">
              {data.stats.totalCourses} cursos
            </Badge>
            <Badge variant="outline" className="font-body">
              {data.stats.totalUsers} utilizadores
            </Badge>
            <Badge variant="outline" className="font-body">
              {data.stats.totalEnrollments} inscrições
            </Badge>
          </div>
        </div>

        <Tabs value={activeTab} onValueChange={setActiveTab}>
          <TabsList className="mb-6 flex flex-wrap h-auto justify-start">
            <TabsTrigger value="overview">Visão Geral</TabsTrigger>
            <TabsTrigger value="courses">Cursos</TabsTrigger>
            <TabsTrigger value="users">Utilizadores</TabsTrigger>
            <TabsTrigger value="enrollments">Inscrições</TabsTrigger>
            <TabsTrigger value="settings">Definições</TabsTrigger>
            <TabsTrigger value="create">Criar Curso</TabsTrigger>
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
                      <TableHead>Instrutor</TableHead>
                      <TableHead>Categoria</TableHead>
                      <TableHead>Estudantes</TableHead>
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
                        <TableCell>{course.instructor}</TableCell>
                        <TableCell>
                          <Badge variant="outline">{toCategoryPt(course.category)}</Badge>
                        </TableCell>
                        <TableCell>{course.studentCount.toLocaleString()}</TableCell>
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
                    <Button variant="outline" onClick={addSection} className="font-body text-sm">
                      <Plus className="h-4 w-4 mr-1" />
                      Adicionar Secção
                    </Button>
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
      </div>
    </div>
  );
};

export default AdminPage;
