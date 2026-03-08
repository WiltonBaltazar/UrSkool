import { Link, useNavigate, useParams } from "react-router-dom";
import { useQuery } from "@tanstack/react-query";
import {
  ArrowLeft,
  Award,
  BookOpen,
  CheckCircle2,
  Clock,
  Circle,
  FileCode2,
  FileQuestion,
  Lock,
  PlayCircle,
  Sparkles,
  Star,
  Users,
  Video,
} from "lucide-react";
import Navbar from "@/components/Navbar";
import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { fetchAuthUser, fetchCourse, fetchCourseAccess } from "@/lib/api";
import { formatMzn, toLevelPt } from "@/lib/labels";
import type { Lesson, LessonProgressEntry } from "@/lib/types";
import { addToCart } from "@/lib/cart";
import { useToast } from "@/hooks/use-toast";

const getLessonMeta = (lesson: Lesson) => {
  if (lesson.type === "quiz") {
    return { label: "Questionário", icon: FileQuestion };
  }

  if (lesson.type === "project") {
    return { label: "Projeto", icon: Award };
  }

  if (lesson.type === "code") {
    return { label: "Interativo", icon: FileCode2 };
  }

  if (lesson.type === "video") {
    return { label: "Vídeo", icon: Video };
  }

  return { label: "Lição", icon: BookOpen };
};

const isInteractive = (lesson: Lesson) => {
  const language = (lesson.language || "").toLowerCase();
  return (
    lesson.type === "code" ||
    Boolean(lesson.htmlCode || lesson.cssCode || lesson.jsCode) ||
    ["html", "css", "javascript", "js"].includes(language)
  );
};

const CoursePage = () => {
  const navigate = useNavigate();
  const { toast } = useToast();
  const { id } = useParams();
  const { data: course, isLoading, isError } = useQuery({
    queryKey: ["course", id],
    queryFn: () => fetchCourse(id || "1"),
    enabled: Boolean(id),
  });
  const { data: user } = useQuery({
    queryKey: ["auth-user"],
    queryFn: fetchAuthUser,
  });
  const { data: accessData, isLoading: isCheckingAccess } = useQuery({
    queryKey: ["course-access", id, user?.id],
    queryFn: () => fetchCourseAccess(id || "1"),
    enabled: Boolean(id && user && course && course.price > 0 && !course.hasAccess),
  });

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
        <p className="text-muted-foreground font-body">Curso não encontrado.</p>
      </div>
    );
  }

  const allLessons = course.sections.flatMap((section) => section.lessons);
  const firstInteractiveLesson = allLessons.find((lesson) => isInteractive(lesson));
  const startLesson = firstInteractiveLesson || allLessons[0];
  const projects = allLessons.filter((lesson) => lesson.type === "project").length;
  const quizzes = allLessons.filter((lesson) => lesson.type === "quiz").length;
  const interactiveLessons = allLessons.filter((lesson) => isInteractive(lesson)).length;
  const hasAccess = Boolean(user) && (
    course.price === 0
    || Boolean(course.hasAccess)
    || Boolean(accessData?.hasAccess)
  );
  const courseProgress = course.progress;
  const lessonProgressById = (courseProgress?.lessons || []).reduce<Record<string, LessonProgressEntry>>(
    (acc, progressEntry) => {
      acc[progressEntry.lessonId] = progressEntry;
      return acc;
    },
    {},
  );
  const progressTotalLessons = courseProgress?.totalLessons ?? allLessons.length;
  const progressCompletedLessons = courseProgress?.completedLessons
    ?? allLessons.filter((lesson) => lessonProgressById[lesson.id]?.status === "completed").length;
  const completionPercent = progressTotalLessons > 0
    ? Math.round((progressCompletedLessons / progressTotalLessons) * 100)
    : 0;
  const resumeLesson = allLessons.find((lesson) => lessonProgressById[lesson.id]?.status !== "completed") || startLesson;
  const isAwaitingAccess = course.price > 0 && Boolean(user) && isCheckingAccess;
  const canPurchase = course.price > 0 && !hasAccess && !isAwaitingAccess;
  const primaryHeroButtonClass =
    "h-12 px-10 rounded-full bg-primary text-primary-foreground hover:bg-primary/90 font-body font-semibold";
  const secondaryHeroButtonClass =
    "h-12 px-8 rounded-full bg-primary-foreground text-primary border border-primary-foreground/40 hover:bg-primary-foreground/90 hover:text-primary font-body font-semibold";
  const handleAddToCart = () => {
    if (!canPurchase) {
      return;
    }

    const result = addToCart(course.id);
    toast({
      title: result.replacedCourseId ? "Curso substituído no carrinho" : "Adicionado ao carrinho",
      description: result.replacedCourseId
        ? "Só podes comprar 1 curso por vez. O curso anterior foi removido do carrinho."
        : `${course.title} foi adicionado ao carrinho.`,
    });
    navigate("/cart");
  };

  if (hasAccess) {
    return (
      <div className="min-h-screen bg-background">
        <Navbar />

        <section className="container mx-auto px-4 py-10 space-y-8">
          <div className="grid gap-6 lg:grid-cols-[1.2fr_1fr]">
            <Card className="border-border">
              <CardContent className="pt-8 pb-8">
                <p className="text-xs uppercase tracking-wide text-muted-foreground">Curso ativo</p>
                <h1 className="font-display text-4xl mt-2">{course.title}</h1>
                <p className="text-muted-foreground mt-3 max-w-2xl">{course.subtitle}</p>

                <div className="mt-6 flex flex-wrap gap-3">
                  {resumeLesson && (
                    <Button asChild className="h-11 px-7 bg-accent hover:bg-accent-hover text-accent-foreground rounded-md">
                      <Link to={`/student/${course.id}/${resumeLesson.id}`}>Continuar curso</Link>
                    </Button>
                  )}
                  {firstInteractiveLesson && (
                    <Button asChild variant="outline" className="h-11 px-7 rounded-md">
                      <Link to={`/student/${course.id}/${firstInteractiveLesson.id}`}>Iniciar sessão prática</Link>
                    </Button>
                  )}
                </div>
              </CardContent>
            </Card>

            <Card className="border-border">
              <CardContent className="pt-8 space-y-4">
                <div className="flex items-center justify-between border-b border-border pb-3">
                  <span className="text-sm">Certificado de conclusão</span>
                  <Award className="h-4 w-4 text-accent" />
                </div>
                <div className="flex items-center justify-between border-b border-border pb-3">
                  <span className="text-sm">Projetos</span>
                  <span className="font-semibold">{projects}</span>
                </div>
                <div className="flex items-center justify-between border-b border-border pb-3">
                  <span className="text-sm">Lições</span>
                  <span className="font-semibold">{course.totalLessons}</span>
                </div>
                <div className="flex items-center justify-between">
                  <span className="text-sm">Nível</span>
                  <span className="font-semibold">{toLevelPt(course.level)}</span>
                </div>
              </CardContent>
            </Card>
          </div>

          <Card className="border-border">
            <CardHeader>
              <CardTitle className="font-display text-3xl">Progresso do Curso</CardTitle>
              <CardDescription>
                {progressCompletedLessons}/{progressTotalLessons} lições concluídas
              </CardDescription>
            </CardHeader>
            <CardContent className="space-y-3">
              <div className="h-4 rounded-full border border-border bg-surface-sunken overflow-hidden">
                <div
                  className="h-full bg-accent transition-all"
                  style={{ width: `${completionPercent}%` }}
                />
              </div>
              <p className="text-right text-sm font-semibold">{completionPercent}%</p>
            </CardContent>
          </Card>

          <div className="space-y-4">
            <h2 className="font-display text-3xl">Syllabus</h2>
            {course.sections.map((section) => {
              const completedInSection = section.lessons.filter(
                (lesson) => lessonProgressById[lesson.id]?.status === "completed",
              ).length;
              const sectionResumeLesson = section.lessons.find(
                (lesson) => lessonProgressById[lesson.id]?.status !== "completed",
              ) || section.lessons[0];

              return (
                <Card key={section.id} className="border-border">
                  <CardHeader className="pb-4">
                    <CardTitle className="font-display text-2xl">{section.title}</CardTitle>
                    <CardDescription>
                      {completedInSection}/{section.lessons.length} lições concluídas
                    </CardDescription>
                  </CardHeader>
                  <CardContent className="space-y-3">
                    {section.lessons.map((lesson) => {
                      const meta = getLessonMeta(lesson);
                      const LessonIcon = meta.icon;
                      const progressEntry = lessonProgressById[lesson.id];
                      const isCompleted = progressEntry?.status === "completed";
                      const quizScore = lesson.type === "quiz" ? progressEntry?.quizScore : null;

                      return (
                        <div
                          key={lesson.id}
                          className="grid grid-cols-[26px_120px_1fr_auto] items-center gap-3 rounded-md border border-border bg-background px-3 py-2"
                        >
                          {isCompleted ? (
                            <CheckCircle2 className="h-4 w-4 text-success" />
                          ) : (
                            <Circle className="h-4 w-4 text-muted-foreground" />
                          )}
                          <span className="text-sm font-medium">{meta.label}</span>
                          <span className="text-sm text-muted-foreground">{lesson.title}</span>
                          <div className="flex items-center gap-2">
                            {typeof quizScore === "number" && (
                              <span className="text-xs font-semibold text-muted-foreground">{quizScore}%</span>
                            )}
                            <LessonIcon className="h-4 w-4 text-accent" />
                          </div>
                        </div>
                      );
                    })}

                    {sectionResumeLesson && (
                      <div className="pt-2">
                        <Button asChild className="bg-accent hover:bg-accent-hover text-accent-foreground rounded-md">
                          <Link to={`/student/${course.id}/${sectionResumeLesson.id}`}>Retomar módulo</Link>
                        </Button>
                      </div>
                    )}
                  </CardContent>
                </Card>
              );
            })}
          </div>
        </section>
      </div>
    );
  }

  return (
    <div className="min-h-screen bg-background">
      <Navbar />

      <section className="relative" style={{ background: "var(--gradient-hero)" }}>
        <div className="container mx-auto px-4 py-12 md:py-16">
          <Link
            to="/courses"
            className="inline-flex items-center gap-1 text-primary-foreground/60 hover:text-primary-foreground font-body text-sm mb-6 transition-colors"
          >
            <ArrowLeft className="h-4 w-4" />
            Voltar aos cursos
          </Link>

          <div className="grid lg:grid-cols-[1fr_320px] gap-8 items-start">
            <div>
              <Badge className="bg-accent text-accent-foreground border-0 mb-4 font-body">
                {course.price === 0 ? "Gratuito" : "Premium"} Curso
              </Badge>
              <h1 className="font-display text-3xl md:text-5xl text-primary-foreground leading-tight">
                {course.title}
              </h1>
              <p className="text-primary-foreground/70 font-body text-lg mt-4 max-w-3xl">
                {course.subtitle}
              </p>

              <div className="mt-6 flex flex-wrap items-center gap-5 text-sm text-primary-foreground/70 font-body">
                <span className="flex items-center gap-1">
                  <Star className="h-4 w-4 fill-accent text-accent" />
                  <span className="font-semibold text-primary-foreground">{course.rating.toFixed(1)}</span>
                  ({course.reviewCount.toLocaleString()} avaliações)
                </span>
                <span className="flex items-center gap-1">
                  <Users className="h-4 w-4" />
                  {course.studentCount.toLocaleString()} estudantes
                </span>
              </div>

              <div className="mt-8 flex flex-wrap items-center gap-3">
                {startLesson ? (
                  isAwaitingAccess ? (
                    <Button disabled className="h-12 px-10 rounded-full">A verificar acesso...</Button>
                  ) : hasAccess ? (
                    <Button
                      asChild
                      className={primaryHeroButtonClass}
                    >
                      <Link to={`/student/${course.id}/${startLesson.id}`}>Começar a Aprender</Link>
                    </Button>
                  ) : (
                    <Button
                      asChild
                      className={primaryHeroButtonClass}
                    >
                      <Link to={`/checkout/${course.id}`}>Comprar para começar</Link>
                    </Button>
                  )
                ) : (
                  <Button disabled className="h-12 px-10 rounded-full">Ainda sem lições</Button>
                )}

                {canPurchase && (
                  <Button className={secondaryHeroButtonClass} onClick={handleAddToCart}>
                    Adicionar ao carrinho ({formatMzn(course.price)})
                  </Button>
                )}
              </div>
            </div>

            <Card className="bg-card/95 border-primary-foreground/20 shadow-xl">
              <CardHeader>
                <CardTitle className="font-display text-xl">Este curso inclui</CardTitle>
              </CardHeader>
              <CardContent className="space-y-3 text-sm font-body text-card-foreground">
                <div className="flex items-center gap-2 pb-2 border-b border-border">
                  <Sparkles className="h-4 w-4 text-accent" />
                  Prática de código interativa
                </div>
                <div className="flex items-center gap-2 pb-2 border-b border-border">
                  <PlayCircle className="h-4 w-4 text-accent" />
                  Pré-visualização em tempo real durante a aprendizagem
                </div>
                <div className="flex items-center gap-2 pb-2 border-b border-border">
                  <FileQuestion className="h-4 w-4 text-accent" />
                  Questionários e pontos de controlo
                </div>
                <div className="flex items-center gap-2">
                  <Award className="h-4 w-4 text-accent" />
                  Certificado de conclusão
                </div>
              </CardContent>
            </Card>
          </div>
        </div>
      </section>

      <section className="container mx-auto px-4 py-10 space-y-8">
        <div className="grid md:grid-cols-2 lg:grid-cols-4 gap-4">
          <Card>
            <CardContent className="pt-6">
              <p className="text-xs uppercase tracking-wide text-muted-foreground">Nível</p>
              <p className="font-display text-2xl mt-1">{toLevelPt(course.level)}</p>
            </CardContent>
          </Card>
          <Card>
            <CardContent className="pt-6">
              <p className="text-xs uppercase tracking-wide text-muted-foreground">Tempo de Conclusão</p>
              <p className="font-display text-2xl mt-1 flex items-center gap-2">
                <Clock className="h-5 w-5 text-accent" />
                {course.totalHours} horas
              </p>
            </CardContent>
          </Card>
          <Card>
            <CardContent className="pt-6">
              <p className="text-xs uppercase tracking-wide text-muted-foreground">Projetos</p>
              <p className="font-display text-2xl mt-1">{projects}</p>
            </CardContent>
          </Card>
          <Card>
            <CardContent className="pt-6">
              {hasAccess && courseProgress ? (
                <>
                  <p className="text-xs uppercase tracking-wide text-muted-foreground">Progresso</p>
                  <p className="font-display text-2xl mt-1">{courseProgress.completionPercent.toFixed(0)}%</p>
                  <p className="text-xs text-muted-foreground mt-1">
                    {courseProgress.completedLessons}/{courseProgress.totalLessons} lições concluídas
                  </p>
                </>
              ) : (
                <>
                  <p className="text-xs uppercase tracking-wide text-muted-foreground">Lições Interativas</p>
                  <p className="font-display text-2xl mt-1">{interactiveLessons}</p>
                </>
              )}
            </CardContent>
          </Card>
        </div>

        <Card>
          <CardHeader>
            <CardTitle className="font-display text-2xl">Sobre este curso</CardTitle>
          </CardHeader>
          <CardContent>
            <p className="text-muted-foreground font-body leading-relaxed text-lg max-w-4xl">
              {course.description}
            </p>
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle className="font-display text-2xl">Plano de Estudos</CardTitle>
            <CardDescription>
              {course.sections.length} secções • {course.totalLessons} lições • {quizzes} questionários
            </CardDescription>
          </CardHeader>
          <CardContent className="space-y-5">
            {course.sections.map((section) => (
              <div key={section.id} className="rounded-lg border border-border overflow-hidden">
                <div className="bg-surface-sunken px-4 py-3">
                  <h3 className="font-display text-xl">{section.title}</h3>
                </div>
                <div className="divide-y divide-border">
                  {section.lessons.map((lesson) => {
                    const meta = getLessonMeta(lesson);
                    const LessonIcon = meta.icon;

                    return (
                      <div key={lesson.id} className="grid grid-cols-[24px_120px_1fr_auto] gap-4 items-center px-4 py-3 text-sm">
                        <LessonIcon className="h-4 w-4 text-accent" />
                        <span className="font-medium text-foreground">{meta.label}</span>
                        <span className="text-muted-foreground">{lesson.title}</span>
                        {!lesson.isFree && (
                          <span className="inline-flex items-center gap-1 text-xs px-2 py-1 rounded border border-border">
                            <Lock className="h-3 w-3" />
                            Bloqueado
                          </span>
                        )}
                      </div>
                    );
                  })}
                </div>
              </div>
            ))}
          </CardContent>
        </Card>

        <div className="flex justify-center pb-2">
          {startLesson ? (
            isAwaitingAccess ? (
              <Button disabled className="px-12 h-12 rounded-full">A verificar acesso...</Button>
            ) : hasAccess ? (
              <Button asChild className="bg-primary hover:bg-primary/90 text-primary-foreground px-12 h-12 rounded-full">
                <Link to={`/student/${course.id}/${startLesson.id}`}>Começar</Link>
              </Button>
            ) : (
              <Button asChild className="bg-primary hover:bg-primary/90 text-primary-foreground px-12 h-12 rounded-full">
                <Link to={`/checkout/${course.id}`}>Comprar para começar</Link>
              </Button>
            )
          ) : (
            <Button disabled className="px-12 h-12 rounded-full">Ainda sem lições</Button>
          )}
        </div>
      </section>
    </div>
  );
};

export default CoursePage;
