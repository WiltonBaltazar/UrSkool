import { useState } from "react";
import { Search } from "lucide-react";
import { useQuery } from "@tanstack/react-query";
import { Input } from "@/components/ui/input";
import CourseCard from "@/components/CourseCard";
import Navbar from "@/components/Navbar";
import { fetchCategories, fetchCourses } from "@/lib/api";

const CoursesPage = () => {
  const [activeCategory, setActiveCategory] = useState("Todas");
  const [searchQuery, setSearchQuery] = useState("");

  const { data: categories = ["Todas"] } = useQuery({
    queryKey: ["categories"],
    queryFn: fetchCategories,
  });

  const { data: courses = [], isLoading, isError } = useQuery({
    queryKey: ["courses", activeCategory, searchQuery],
    queryFn: () => fetchCourses({ category: activeCategory, search: searchQuery }),
  });

  return (
    <div className="min-h-screen bg-background">
      <Navbar />

      <div className="container mx-auto px-4 py-10">
        <div className="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-8">
          <div>
            <h1 className="font-display text-3xl text-foreground">Todos os Cursos de Programação</h1>
            <p className="text-muted-foreground font-body text-sm mt-1">
              Explora o nosso catálogo completo de programação com {courses.length} cursos
            </p>
          </div>
          <div className="relative max-w-xs w-full">
            <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
            <Input
              placeholder="Pesquisar cursos..."
              value={searchQuery}
              onChange={(e) => setSearchQuery(e.target.value)}
              className="pl-9 rounded-full h-10 bg-surface-sunken font-body"
            />
          </div>
        </div>

        <div className="flex flex-wrap gap-2 mb-8">
          {categories.map((cat) => (
            <button
              key={cat}
              onClick={() => setActiveCategory(cat)}
              className={`px-4 py-2 rounded-full text-sm font-body font-medium transition-colors ${
                activeCategory === cat
                  ? "bg-accent text-accent-foreground"
                  : "bg-secondary text-secondary-foreground hover:bg-muted"
              }`}
            >
              {cat}
            </button>
          ))}
        </div>

        {isLoading && <p className="text-muted-foreground font-body">A carregar cursos...</p>}

        {isError && (
          <p className="text-destructive font-body">Não foi possível carregar os cursos neste momento.</p>
        )}

        {!isLoading && !isError && (
          <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
            {courses.map((course) => (
              <CourseCard key={course.id} course={course} />
            ))}
          </div>
        )}

        {!isLoading && !isError && courses.length === 0 && (
          <div className="text-center py-16">
            <p className="text-muted-foreground font-body text-lg">Nenhum curso encontrado.</p>
          </div>
        )}
      </div>
    </div>
  );
};

export default CoursesPage;
