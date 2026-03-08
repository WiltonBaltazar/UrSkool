import { useEffect, useState } from "react";
import { Link, useLocation } from "react-router-dom";
import { Search, ShoppingCart, User, BookOpen, LayoutDashboard } from "lucide-react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { fetchAuthUser, logout } from "@/lib/api";
import { useToast } from "@/hooks/use-toast";
import { clearCart, getCartItems, subscribeToCart } from "@/lib/cart";

const Navbar = () => {
  const location = useLocation();
  const queryClient = useQueryClient();
  const { toast } = useToast();
  const isAdmin = location.pathname.startsWith("/admin");
  const [cartItems, setCartItems] = useState(getCartItems());
  const cartCount = cartItems.length;

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
  const logoutMutation = useMutation({
    mutationFn: logout,
    onSuccess: async () => {
      queryClient.setQueryData(["auth-user"], null);
      await queryClient.invalidateQueries({ queryKey: ["auth-user"] });

      if (location.pathname.startsWith("/admin") || location.pathname.startsWith("/student")) {
        window.location.href = "/login";
      }
    },
    onError: async (error: Error) => {
      // Fallback: clear local auth cache so UI does not remain stuck in logged-in state.
      queryClient.setQueryData(["auth-user"], null);
      await queryClient.invalidateQueries({ queryKey: ["auth-user"] });

      toast({
        variant: "destructive",
        title: "Falha ao terminar sessão",
        description: error.message,
      });
    },
  });

  const handleClearCart = () => {
    clearCart();
    toast({
      title: "Carrinho limpo",
      description: "Todos os itens foram removidos do carrinho.",
    });
  };

  return (
    <header className="sticky top-0 z-50 border-b border-border bg-card/80 backdrop-blur-md">
      <div className="container mx-auto flex h-16 items-center justify-between gap-4 px-4">
        <Link to="/" className="flex items-center gap-2 shrink-0">
          <BookOpen className="h-7 w-7 text-accent" />
          <span className="font-display text-xl text-foreground">Learnova</span>
        </Link>

        <div className="hidden md:flex relative max-w-md flex-1">
          <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
          <Input
            placeholder="Pesquisar cursos..."
            className="pl-9 bg-surface-sunken border-border rounded-full h-10 font-body"
          />
        </div>

        <nav className="flex items-center gap-2">
          <Link to="/courses">
            <Button variant="ghost" size="sm" className="font-body text-sm">
              Cursos
            </Button>
          </Link>
          <Link to="/admin">
            <Button variant="ghost" size="sm" className="font-body text-sm">
              <LayoutDashboard className="h-4 w-4 mr-1" />
              {user?.isAdmin ? "Admin" : !isAdmin && "Ensinar"}
            </Button>
          </Link>
          {!user && (
            <Link to="/login">
              <Button variant="ghost" size="sm" className="font-body text-sm">
                Entrar
              </Button>
            </Link>
          )}
          {user && (
            <Button
              variant="ghost"
              size="sm"
              className="font-body text-sm"
              onClick={() => logoutMutation.mutate()}
              disabled={logoutMutation.isPending}
            >
              {logoutMutation.isPending ? "A terminar sessão..." : "Terminar sessão"}
            </Button>
          )}
          {cartCount > 0 && (
            <Button variant="ghost" size="sm" className="font-body text-sm" onClick={handleClearCart}>
              Limpar carrinho
            </Button>
          )}
          <Link to="/courses">
            <Button variant="ghost" size="sm" className="font-body text-sm">
              <User className="h-4 w-4 mr-1" />
              Minha Aprendizagem
            </Button>
          </Link>
          <Link to="/cart">
            <Button variant="ghost" size="icon" className="relative">
              <ShoppingCart className="h-5 w-5" />
              {cartCount > 0 && (
                <span className="absolute -top-0.5 -right-0.5 h-4 w-4 rounded-full bg-accent text-accent-foreground text-[10px] flex items-center justify-center font-bold">
                  {cartCount > 99 ? "99+" : cartCount}
                </span>
              )}
            </Button>
          </Link>
        </nav>
      </div>
    </header>
  );
};

export default Navbar;
