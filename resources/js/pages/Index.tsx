import { useState } from "react";
import { Link } from "react-router-dom";
import { Search, TrendingUp, Award, BookOpen } from "lucide-react";
import { useQuery } from "@tanstack/react-query";
import { Input } from "@/components/ui/input";
import { Button } from "@/components/ui/button";
import CourseCard from "@/components/CourseCard";
import Navbar from "@/components/Navbar";
import { fetchCategories, fetchCourses } from "@/lib/api";
import heroImage from "@/assets/hero-learning.jpg";

const Index = () => {
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

      {/* Hero */}
      <section className="relative overflow-hidden">
        <div
          className="absolute inset-0 bg-cover bg-center"
          style={{ backgroundImage: `url(${heroImage})` }}
        />
        <div className="absolute inset-0" style={{ background: "var(--gradient-hero)", opacity: 0.88 }} />
        <div className="relative container mx-auto px-4 py-24 md:py-32">
          <div className="max-w-2xl">
            <h1 className="font-display text-4xl md:text-6xl text-primary-foreground mb-4 leading-tight">
              Torna-te um Melhor Programador
            </h1>
            <p className="font-body text-lg text-primary-foreground/75 mb-8 max-w-lg">
              Cursos interativos de programação focados em competências web com prática real em HTML, CSS e JavaScript.
            </p>
            <div className="flex gap-3">
              <Link to="/courses">
                <Button className="bg-primary hover:bg-primary/90 text-primary-foreground font-body font-semibold px-8 h-12 rounded-full text-base">
                  Explorar Cursos
                </Button>
              </Link>
              <Link to="/admin">
                <Button className="bg-primary-foreground text-primary border border-primary-foreground/40 hover:bg-primary-foreground/90 hover:text-primary font-body font-semibold h-12 rounded-full px-8 text-base">
                  Começar a Ensinar
                </Button>
              </Link>
            </div>
          </div>
        </div>
      </section>

      {/* Stats */}
      <section className="border-b border-border bg-card">
        <div className="container mx-auto px-4 py-8">
          <div className="grid grid-cols-3 gap-8 text-center">
            <div>
              <div className="flex items-center justify-center gap-2 mb-1">
                <BookOpen className="h-5 w-5 text-accent" />
                <span className="font-display text-2xl text-foreground">120+</span>
              </div>
              <p className="text-sm text-muted-foreground font-body">Cursos de Programação</p>
            </div>
            <div>
              <div className="flex items-center justify-center gap-2 mb-1">
                <TrendingUp className="h-5 w-5 text-accent" />
                <span className="font-display text-2xl text-foreground">180K+</span>
              </div>
              <p className="text-sm text-muted-foreground font-body">Programadores</p>
            </div>
            <div>
              <div className="flex items-center justify-center gap-2 mb-1">
                <Award className="h-5 w-5 text-accent" />
                <span className="font-display text-2xl text-foreground">65+</span>
              </div>
              <p className="text-sm text-muted-foreground font-body">Instrutores de Tecnologia</p>
            </div>
          </div>
        </div>
      </section>

      {/* Courses */}
      <section className="container mx-auto px-4 py-16">
        <div className="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-8">
          <h2 className="font-display text-3xl text-foreground">Cursos de Programação em Destaque</h2>
          <div className="relative max-w-xs w-full">
            <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
            <Input
              placeholder="Pesquisar..."
              value={searchQuery}
              onChange={(e) => setSearchQuery(e.target.value)}
              className="pl-9 rounded-full h-10 bg-surface-sunken font-body"
            />
          </div>
        </div>

        {/* Category Tabs */}
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
          <p className="text-destructive font-body">Não foi possível carregar o catálogo de cursos neste momento.</p>
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
      </section>

      {/* Footer */}
      <footer className="border-t border-border bg-card py-8">
        <div className="container mx-auto px-4 text-center">
          <p className="text-sm text-muted-foreground font-body">
            © 2026 Learnova. Todos os direitos reservados.
          </p>
        </div>
      </footer>
    </div>
  );
};

export default Index;
