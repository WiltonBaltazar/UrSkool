import { Link } from "react-router-dom";
import { useQuery } from "@tanstack/react-query";
import { BookOpenCheck, PlayCircle } from "lucide-react";
import Navbar from "@/components/Navbar";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { fetchStudentCourses } from "@/lib/api";

const MyLearningPage = () => {
  const { data: courses = [], isLoading, isError } = useQuery({
    queryKey: ["student-courses"],
    queryFn: fetchStudentCourses,
  });

  return (
    <div className="min-h-screen bg-background">
      <Navbar />

      <section className="container mx-auto px-4 py-10 space-y-6">
        <div>
          <h1 className="font-display text-3xl text-foreground flex items-center gap-2">
            <BookOpenCheck className="h-7 w-7 text-accent" />
            Minha Aprendizagem
          </h1>
          <p className="text-muted-foreground font-body text-sm mt-1">
            Continua os cursos em que já estás inscrito.
          </p>
        </div>

        {isLoading && <p className="text-muted-foreground font-body">A carregar os teus cursos...</p>}

        {isError && (
          <p className="text-destructive font-body">Não foi possível carregar os teus cursos neste momento.</p>
        )}

        {!isLoading && !isError && courses.length === 0 && (
          <Card>
            <CardContent className="pt-6">
              <p className="text-muted-foreground font-body">Ainda não tens cursos inscritos.</p>
              <Button asChild className="mt-4 bg-accent hover:bg-accent-hover text-accent-foreground">
                <Link to="/courses">Explorar cursos</Link>
              </Button>
            </CardContent>
          </Card>
        )}

        {!isLoading && !isError && courses.length > 0 && (
          <div className="grid gap-5 md:grid-cols-2 xl:grid-cols-3">
            {courses.map((course) => {
              const progress = course.progress;
              const completion = Math.round(progress?.completionPercent ?? 0);
              const resumeLessonId = course.resumeLessonId || course.sections[0]?.lessons[0]?.id;

              return (
                <Card key={course.id}>
                  <CardHeader className="space-y-2">
                    <CardTitle className="font-display text-xl line-clamp-2">{course.title}</CardTitle>
                    <CardDescription className="line-clamp-2">{course.subtitle}</CardDescription>
                  </CardHeader>
                  <CardContent className="space-y-4">
                    <div className="space-y-2">
                      <div className="flex items-center justify-between text-sm">
                        <span className="text-muted-foreground">Progresso</span>
                        <span className="font-semibold">{completion}%</span>
                      </div>
                      <div className="h-2 rounded-full bg-surface-sunken border border-border overflow-hidden">
                        <div className="h-full bg-accent" style={{ width: `${completion}%` }} />
                      </div>
                      <p className="text-xs text-muted-foreground">
                        {progress?.completedLessons ?? 0}/{progress?.totalLessons ?? course.totalLessons} lições concluídas
                      </p>
                    </div>

                    <div className="flex items-center gap-2">
                      {resumeLessonId && (
                        <Button asChild className="bg-accent hover:bg-accent-hover text-accent-foreground">
                          <Link to={`/student/${course.id}/${resumeLessonId}`}>
                            <PlayCircle className="h-4 w-4 mr-1" />
                            Continuar
                          </Link>
                        </Button>
                      )}
                      <Button asChild variant="outline">
                        <Link to={`/course/${course.id}`}>Ver curso</Link>
                      </Button>
                    </div>
                  </CardContent>
                </Card>
              );
            })}
          </div>
        )}
      </section>
    </div>
  );
};

export default MyLearningPage;
