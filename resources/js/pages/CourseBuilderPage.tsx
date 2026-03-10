import { useEffect, useMemo, useState } from "react";
import { Link, useNavigate, useParams } from "react-router-dom";
import { useMutation, useQuery } from "@tanstack/react-query";
import Navbar from "@/components/Navbar";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Textarea } from "@/components/ui/textarea";
import { createCourse, fetchCourse, updateAdminCourse } from "@/lib/api";
import { useToast } from "@/hooks/use-toast";

type LessonType = "video" | "text" | "code" | "quiz" | "project";

interface LessonDraft {
  title: string;
  duration: string;
  type: LessonType;
  isFree: boolean;
  content: string;
  videoUrl: string;
  language: string;
  htmlCode: string;
  cssCode: string;
  jsCode: string;
}

interface SectionDraft {
  title: string;
  lessons: LessonDraft[];
}

const createEmptyLesson = (): LessonDraft => ({
  title: "",
  duration: "",
  type: "text",
  isFree: false,
  content: "",
  videoUrl: "",
  language: "",
  htmlCode: "",
  cssCode: "",
  jsCode: "",
});

const CourseBuilderPage = () => {
  const { toast } = useToast();
  const navigate = useNavigate();
  const { courseId } = useParams();
  const isEditMode = Boolean(courseId);

  const [title, setTitle] = useState("");
  const [subtitle, setSubtitle] = useState("");
  const [instructor, setInstructor] = useState("");
  const [description, setDescription] = useState("");
  const [price, setPrice] = useState("");
  const [originalPrice, setOriginalPrice] = useState("");
  const [image, setImage] = useState("");
  const [category, setCategory] = useState("");
  const [level, setLevel] = useState("");
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
    setLevel(existingCourse.level || "");
    setSections(
      (existingCourse.sections || []).map((section) => ({
        title: section.title,
        lessons: (section.lessons || []).map((lesson) => ({
          title: lesson.title,
          duration: lesson.duration || "",
          type: lesson.type || "text",
          isFree: lesson.isFree,
          content: lesson.content || "",
          videoUrl: lesson.videoUrl || "",
          language: lesson.language || "",
          htmlCode: lesson.htmlCode || "",
          cssCode: lesson.cssCode || "",
          jsCode: lesson.jsCode || "",
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
    mutationFn: (payload: Parameters<typeof createCourse>[0]) => updateAdminCourse(courseId || "", payload),
    onSuccess: (course) => {
      toast({ title: "Curso atualizado", description: `"${course.title}" foi atualizado com sucesso.` });
      navigate(`/course/${course.id}`);
    },
    onError: (error: Error) => {
      toast({ variant: "destructive", title: "Falha ao atualizar curso", description: error.message });
    },
  });

  const pending = createMutation.isPending || updateMutation.isPending;

  const updateSection = (index: number, updater: (section: SectionDraft) => SectionDraft) => {
    setSections((prev) => prev.map((section, i) => (i === index ? updater(section) : section)));
  };

  const updateLesson = (
    sectionIndex: number,
    lessonIndex: number,
    updater: (lesson: LessonDraft) => LessonDraft,
  ) => {
    updateSection(sectionIndex, (section) => ({
      ...section,
      lessons: section.lessons.map((lesson, i) => (i === lessonIndex ? updater(lesson) : lesson)),
    }));
  };

  const payload = useMemo(() => ({
    title,
    subtitle,
    instructor,
    rating: 0,
    reviewCount: 0,
    studentCount: 0,
    price: Number(price || 0),
    originalPrice: Number(originalPrice || 0),
    image,
    category,
    level,
    totalHours: Math.max(0, sections.reduce((acc, section) => acc + section.lessons.length, 0)),
    description,
    sections: sections
      .filter((section) => section.title.trim().length > 0)
      .map((section) => ({
        title: section.title,
        lessons: section.lessons
          .filter((lesson) => lesson.title.trim().length > 0)
          .map((lesson) => ({
            title: lesson.title,
            duration: lesson.duration,
            videoUrl: lesson.videoUrl || undefined,
            language: lesson.language || undefined,
            content: lesson.content || undefined,
            htmlCode: lesson.type === "code" ? lesson.htmlCode || undefined : undefined,
            cssCode: lesson.type === "code" ? lesson.cssCode || undefined : undefined,
            jsCode: lesson.type === "code" ? lesson.jsCode || undefined : undefined,
            isFree: lesson.isFree,
            type: lesson.type,
          })),
      })),
  }), [title, subtitle, instructor, price, originalPrice, image, category, level, sections, description]);

  const submit = () => {
    if (isEditMode) {
      updateMutation.mutate(payload);
      return;
    }

    createMutation.mutate(payload);
  };

  if (isLoadingCourse) {
    return (
      <div className="min-h-screen bg-background">
        <Navbar />
        <div className="container mx-auto px-4 py-10 text-muted-foreground">A carregar curso...</div>
      </div>
    );
  }

  return (
    <div className="min-h-screen bg-background">
      <Navbar />
      <div className="container mx-auto px-4 py-8 space-y-6">
        <div className="flex items-center justify-between gap-3">
          <div>
            <h1 className="font-display text-3xl text-foreground">{isEditMode ? "Editar curso" : "Criar novo curso"}</h1>
            <p className="text-muted-foreground font-body text-sm mt-1">
              {isEditMode ? "Atualiza o conteúdo e estrutura do curso." : "Todos os campos começam vazios para preencher do zero."}
            </p>
          </div>
          <Link to="/courses">
            <Button variant="outline">Voltar aos cursos</Button>
          </Link>
        </div>

        <Card>
          <CardHeader><CardTitle>Dados principais</CardTitle></CardHeader>
          <CardContent className="grid md:grid-cols-2 gap-4">
            <div className="space-y-1 md:col-span-2"><Label>Título</Label><Input value={title} onChange={(event) => setTitle(event.target.value)} /></div>
            <div className="space-y-1 md:col-span-2"><Label>Subtítulo</Label><Input value={subtitle} onChange={(event) => setSubtitle(event.target.value)} /></div>
            <div className="space-y-1"><Label>Instrutor</Label><Input value={instructor} onChange={(event) => setInstructor(event.target.value)} /></div>
            <div className="space-y-1"><Label>Categoria</Label><Input value={category} onChange={(event) => setCategory(event.target.value)} placeholder="Desenvolvimento Web" /></div>
            <div className="space-y-1"><Label>Nível</Label><Input value={level} onChange={(event) => setLevel(event.target.value)} placeholder="Iniciante" /></div>
            <div className="space-y-1"><Label>Preço</Label><Input type="number" min="0" value={price} onChange={(event) => setPrice(event.target.value)} /></div>
            <div className="space-y-1"><Label>Preço original</Label><Input type="number" min="0" value={originalPrice} onChange={(event) => setOriginalPrice(event.target.value)} /></div>
            <div className="space-y-1 md:col-span-2"><Label>Imagem (URL)</Label><Input value={image} onChange={(event) => setImage(event.target.value)} /></div>
            <div className="space-y-1 md:col-span-2"><Label>Descrição</Label><Textarea value={description} onChange={(event) => setDescription(event.target.value)} rows={4} /></div>
          </CardContent>
        </Card>

        {sections.map((section, sectionIndex) => (
          <Card key={`section-${sectionIndex}`}>
            <CardHeader className="flex flex-row items-center justify-between">
              <CardTitle>Secção {sectionIndex + 1}</CardTitle>
              <Button variant="outline" size="sm" onClick={() => setSections((prev) => prev.filter((_, idx) => idx !== sectionIndex))}>Remover secção</Button>
            </CardHeader>
            <CardContent className="space-y-4">
              <Input value={section.title} onChange={(event) => updateSection(sectionIndex, (current) => ({ ...current, title: event.target.value }))} placeholder="Título da secção" />
              {section.lessons.map((lesson, lessonIndex) => (
                <div key={`lesson-${lessonIndex}`} className="rounded-md border border-border p-4 space-y-3">
                  <div className="grid md:grid-cols-4 gap-3">
                    <Input value={lesson.title} onChange={(event) => updateLesson(sectionIndex, lessonIndex, (current) => ({ ...current, title: event.target.value }))} placeholder="Título da lição" />
                    <Input value={lesson.duration} onChange={(event) => updateLesson(sectionIndex, lessonIndex, (current) => ({ ...current, duration: event.target.value }))} placeholder="Duração" />
                    <select value={lesson.type} onChange={(event) => updateLesson(sectionIndex, lessonIndex, (current) => ({ ...current, type: event.target.value as LessonType }))} className="h-10 rounded-md border border-input bg-background px-3">
                      <option value="text">texto</option><option value="video">vídeo</option><option value="code">código</option><option value="quiz">questionário</option><option value="project">projeto</option>
                    </select>
                    <label className="flex items-center gap-2 text-sm"><input type="checkbox" checked={lesson.isFree} onChange={(event) => updateLesson(sectionIndex, lessonIndex, (current) => ({ ...current, isFree: event.target.checked }))} /> Aula grátis</label>
                  </div>
                  <Textarea value={lesson.content} onChange={(event) => updateLesson(sectionIndex, lessonIndex, (current) => ({ ...current, content: event.target.value }))} placeholder="Conteúdo" rows={3} />
                  {lesson.type === "video" && <Input value={lesson.videoUrl} onChange={(event) => updateLesson(sectionIndex, lessonIndex, (current) => ({ ...current, videoUrl: event.target.value }))} placeholder="URL do vídeo" />}
                  {lesson.type === "code" && (
                    <div className="grid gap-3">
                      <Input value={lesson.language} onChange={(event) => updateLesson(sectionIndex, lessonIndex, (current) => ({ ...current, language: event.target.value }))} placeholder="Linguagem" />
                      <Textarea value={lesson.htmlCode} onChange={(event) => updateLesson(sectionIndex, lessonIndex, (current) => ({ ...current, htmlCode: event.target.value }))} placeholder="HTML (opcional)" rows={4} />
                      <Textarea value={lesson.cssCode} onChange={(event) => updateLesson(sectionIndex, lessonIndex, (current) => ({ ...current, cssCode: event.target.value }))} placeholder="CSS (opcional)" rows={4} />
                      <Textarea value={lesson.jsCode} onChange={(event) => updateLesson(sectionIndex, lessonIndex, (current) => ({ ...current, jsCode: event.target.value }))} placeholder="JavaScript (opcional)" rows={4} />
                    </div>
                  )}
                  <Button variant="ghost" size="sm" onClick={() => updateSection(sectionIndex, (current) => ({ ...current, lessons: current.lessons.filter((_, idx) => idx !== lessonIndex) }))}>Remover lição</Button>
                </div>
              ))}
              <Button variant="outline" size="sm" onClick={() => updateSection(sectionIndex, (current) => ({ ...current, lessons: [...current.lessons, createEmptyLesson()] }))}>Adicionar lição</Button>
            </CardContent>
          </Card>
        ))}

        <div className="flex flex-wrap gap-2">
          <Button variant="outline" onClick={() => setSections((prev) => [...prev, { title: "", lessons: [] }])}>Adicionar secção</Button>
          <Button onClick={submit} disabled={pending || !title.trim() || !instructor.trim()} className="bg-accent hover:bg-accent-hover text-accent-foreground">
            {pending ? "A guardar..." : isEditMode ? "Guardar alterações" : "Criar curso"}
          </Button>
        </div>
      </div>
    </div>
  );
};

export default CourseBuilderPage;
