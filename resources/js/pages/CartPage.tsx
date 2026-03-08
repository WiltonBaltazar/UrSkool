import { useEffect, useMemo, useState } from "react";
import { Link, useNavigate } from "react-router-dom";
import { useQuery } from "@tanstack/react-query";
import { ArrowLeft, ShoppingCart, Trash2 } from "lucide-react";
import Navbar from "@/components/Navbar";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { fetchAuthUser, fetchCourse } from "@/lib/api";
import { clearCart, getCartItems, removeFromCart, subscribeToCart } from "@/lib/cart";
import { formatMzn } from "@/lib/labels";
import type { Course } from "@/lib/types";
import { useToast } from "@/hooks/use-toast";

const CartPage = () => {
  const navigate = useNavigate();
  const { toast } = useToast();
  const [cartItems, setCartItems] = useState(getCartItems());
  const courseIds = useMemo(
    () => Array.from(new Set(cartItems.map((item) => item.courseId))),
    [cartItems],
  );

  useEffect(() => {
    const unsubscribe = subscribeToCart((items) => {
      setCartItems(items);
    });

    return unsubscribe;
  }, []);
  const { data: user } = useQuery({
    queryKey: ["auth-user"],
    queryFn: fetchAuthUser,
  });

  const { data: loadedCourses = [], isLoading } = useQuery({
    queryKey: ["cart-courses", courseIds, user?.id ?? "guest"],
    enabled: courseIds.length > 0,
    queryFn: async (): Promise<Course[]> => {
      const results = await Promise.all(
        courseIds.map(async (courseId) => {
          try {
            const course = await fetchCourse(courseId);
            return { courseId, course };
          } catch {
            return { courseId, course: null };
          }
        }),
      );

      const missingCourseIds = results
        .filter((result) => !result.course)
        .map((result) => result.courseId);

      if (missingCourseIds.length > 0) {
        missingCourseIds.forEach((courseId) => removeFromCart(courseId));
      }

      const purchasedCourseIds = results
        .filter((result): result is { courseId: string; course: Course } => Boolean(result.course))
        .filter((result) => Boolean(result.course.hasAccess))
        .map((result) => result.courseId);

      if (purchasedCourseIds.length > 0) {
        purchasedCourseIds.forEach((courseId) => removeFromCart(courseId));
      }

      return results
        .filter((result): result is { courseId: string; course: Course } => Boolean(result.course))
        .filter((result) => !result.course.hasAccess)
        .map((result) => result.course);
    },
  });

  const courses = useMemo(
    () =>
      courseIds
        .map((courseId) => loadedCourses.find((course) => course.id === courseId))
        .filter((course): course is Course => Boolean(course)),
    [courseIds, loadedCourses],
  );

  const total = courses.reduce((sum, course) => sum + course.price, 0);

  const handleRemove = (courseId: string) => {
    removeFromCart(courseId);
    toast({
      title: "Curso removido",
      description: "O curso foi removido do carrinho.",
    });
  };

  const handleClearCart = () => {
    clearCart();
    toast({
      title: "Carrinho limpo",
      description: "Todos os itens foram removidos.",
    });
  };

  const handleCheckoutFirst = () => {
    if (!courses[0]) {
      return;
    }

    navigate(`/checkout/${courses[0].id}`);
  };

  return (
    <div className="min-h-screen bg-background">
      <Navbar />

      <div className="container mx-auto px-4 py-10">
        <Link
          to="/courses"
          className="inline-flex items-center gap-1 text-muted-foreground hover:text-foreground font-body text-sm mb-8 transition-colors"
        >
          <ArrowLeft className="h-4 w-4" />
          Voltar aos cursos
        </Link>

        <div className="flex items-center justify-between gap-3 mb-6">
          <h1 className="font-display text-3xl text-foreground">Carrinho (1 curso por vez)</h1>
          {courses.length > 0 && (
            <Button variant="outline" className="font-body" onClick={handleClearCart}>
              Limpar carrinho
            </Button>
          )}
        </div>

        {isLoading && <p className="text-muted-foreground font-body">A carregar carrinho...</p>}

        {!isLoading && courses.length === 0 && (
          <Card>
            <CardContent className="py-12 text-center space-y-4">
              <ShoppingCart className="h-10 w-10 mx-auto text-muted-foreground" />
              <p className="font-body text-muted-foreground">O teu carrinho está vazio.</p>
              <Button asChild className="rounded-full px-8 bg-primary text-primary-foreground hover:bg-primary/90">
                <Link to="/courses">Explorar cursos</Link>
              </Button>
            </CardContent>
          </Card>
        )}

        {!isLoading && courses.length > 0 && (
          <div className="grid lg:grid-cols-[1fr_320px] gap-6">
            <div className="space-y-4">
              {courses.map((course) => (
                <Card key={course.id}>
                  <CardContent className="p-5 flex flex-col sm:flex-row sm:items-center gap-4">
                    <img
                      src={course.image}
                      alt={course.title}
                      className="w-full sm:w-36 h-24 object-cover rounded-md"
                    />
                    <div className="flex-1 min-w-0">
                      <h2 className="font-display text-xl leading-tight">{course.title}</h2>
                      <p className="text-sm text-muted-foreground mt-1">{course.subtitle}</p>
                      <p className="text-sm text-muted-foreground mt-2">por {course.instructor}</p>
                    </div>
                    <div className="flex sm:flex-col gap-2 sm:items-end justify-between">
                      <p className="font-semibold text-lg">{formatMzn(course.price)}</p>
                      <div className="flex gap-2">
                        <Button
                          type="button"
                          variant="outline"
                          size="sm"
                          className="font-body"
                          onClick={() => handleRemove(course.id)}
                        >
                          <Trash2 className="h-4 w-4 mr-1" />
                          Remover
                        </Button>
                        <Button asChild size="sm" className="font-body bg-primary text-primary-foreground hover:bg-primary/90">
                          <Link to={`/checkout/${course.id}`}>Checkout</Link>
                        </Button>
                      </div>
                    </div>
                  </CardContent>
                </Card>
              ))}
            </div>

            <Card className="h-fit lg:sticky lg:top-24">
              <CardHeader>
                <CardTitle className="font-display text-xl">Resumo</CardTitle>
              </CardHeader>
              <CardContent className="space-y-4">
                <div className="flex justify-between text-sm">
                  <span className="text-muted-foreground">Itens</span>
                  <span>{courses.length}</span>
                </div>
                <div className="flex justify-between text-lg font-bold">
                  <span>Total</span>
                  <span>{formatMzn(total)}</span>
                </div>

                <Button
                  type="button"
                  className="w-full rounded-full bg-primary text-primary-foreground hover:bg-primary/90"
                  onClick={handleCheckoutFirst}
                >
                  Finalizar compra
                </Button>
                <p className="text-xs text-muted-foreground font-body">
                  Só é possível comprar um curso por vez.
                </p>
              </CardContent>
            </Card>
          </div>
        )}
      </div>
    </div>
  );
};

export default CartPage;
