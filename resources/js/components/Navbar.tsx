import { useEffect, useState } from "react";
import { Link, NavLink, useLocation, useNavigate } from "react-router-dom";
import { Search, ShoppingCart, User, BookOpen, LayoutDashboard, ChevronDown, LogOut, Moon, Sun } from "lucide-react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
  AlertDialogTrigger,
} from "@/components/ui/alert-dialog";
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import { fetchAuthUser, fetchSignupAvailability, logout } from "@/lib/api";
import { useToast } from "@/hooks/use-toast";
import { clearCart, getCartItems, subscribeToCart } from "@/lib/cart";

const Navbar = () => {
  const location = useLocation();
  const navigate = useNavigate();
  const queryClient = useQueryClient();
  const { toast } = useToast();
  const [navSearch, setNavSearch] = useState("");
  const [cartItems, setCartItems] = useState(getCartItems());
  const cartCount = cartItems.length;
  const [isDark, setIsDark] = useState(() => {
    if (typeof window === "undefined") return false;
    return (
      localStorage.getItem("theme") === "dark" ||
      document.documentElement.classList.contains("dark")
    );
  });

  useEffect(() => {
    document.documentElement.classList.toggle("dark", isDark);
    localStorage.setItem("theme", isDark ? "dark" : "light");
  }, [isDark]);

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
  const { data: signupAvailability } = useQuery({
    queryKey: ["signup-availability"],
    queryFn: fetchSignupAvailability,
  });
  const allowSelfSignup = signupAvailability?.allowSelfSignup ?? true;
  const displayName = user?.name?.trim() || "Utilizador";
  const userInitials = displayName
    .split(/\s+/)
    .filter(Boolean)
    .slice(0, 2)
    .map((part) => part.charAt(0))
    .join("")
    .toUpperCase();

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
          <span className="font-display text-xl text-foreground">UrSkool</span>
        </Link>

        <div className="hidden md:flex relative max-w-md flex-1">
          <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" aria-hidden="true" />
          <Input
            aria-label="Pesquisar cursos"
            placeholder="Pesquisar cursos..."
            className="pl-9 pr-20 bg-surface-sunken border-border rounded-full h-10 font-body"
            value={navSearch}
            onChange={(e) => setNavSearch(e.target.value)}
            onKeyDown={(e) => {
              if (e.key === "Enter" && navSearch.trim()) {
                navigate(`/courses?search=${encodeURIComponent(navSearch.trim())}`);
                setNavSearch("");
              }
            }}
          />
          <Button
            type="button"
            size="sm"
            className="absolute right-1 top-1/2 -translate-y-1/2 h-8 px-3 rounded-full bg-accent text-accent-foreground hover:bg-accent-hover font-body text-xs"
            disabled={!navSearch.trim()}
            onClick={() => {
              if (navSearch.trim()) {
                navigate(`/courses?search=${encodeURIComponent(navSearch.trim())}`);
                setNavSearch("");
              }
            }}
          >
            Pesquisar
          </Button>
        </div>

        <nav className="flex items-center gap-2" aria-label="Navegação principal">
          <NavLink to="/courses">
            {({ isActive }) => (
              <Button
                variant="ghost"
                size="sm"
                className="font-body text-sm"
                aria-current={isActive ? "page" : undefined}
              >
                Cursos
              </Button>
            )}
          </NavLink>

          {user?.isAdmin && (
            <NavLink to="/admin">
              {({ isActive }) => (
                <Button
                  variant="ghost"
                  size="sm"
                  className="font-body text-sm"
                  aria-current={isActive ? "page" : undefined}
                >
                  <LayoutDashboard className="mr-1 h-4 w-4" aria-hidden="true" />
                  Admin
                </Button>
              )}
            </NavLink>
          )}

          {!user && (
            <>
              {allowSelfSignup && (
                <NavLink to="/signup">
                  {({ isActive }) => (
                    <Button
                      variant="ghost"
                      size="sm"
                      className="font-body text-sm"
                      aria-current={isActive ? "page" : undefined}
                    >
                      Criar conta
                    </Button>
                  )}
                </NavLink>
              )}
              <NavLink to="/login">
                {({ isActive }) => (
                  <Button
                    size="sm"
                    className="font-body text-sm bg-foreground text-background hover:bg-foreground/90"
                    aria-current={isActive ? "page" : undefined}
                  >
                    Entrar
                  </Button>
                )}
              </NavLink>
            </>
          )}

          {cartCount > 0 && (
            <AlertDialog>
              <AlertDialogTrigger asChild>
                <Button variant="ghost" size="sm" className="font-body text-sm">
                  Limpar carrinho
                </Button>
              </AlertDialogTrigger>
              <AlertDialogContent>
                <AlertDialogHeader>
                  <AlertDialogTitle>Limpar carrinho?</AlertDialogTitle>
                  <AlertDialogDescription>
                    Todos os itens serão removidos do carrinho. Esta ação não pode ser desfeita.
                  </AlertDialogDescription>
                </AlertDialogHeader>
                <AlertDialogFooter>
                  <AlertDialogCancel>Cancelar</AlertDialogCancel>
                  <AlertDialogAction onClick={handleClearCart}>Limpar</AlertDialogAction>
                </AlertDialogFooter>
              </AlertDialogContent>
            </AlertDialog>
          )}

          {user && (
            <NavLink to="/my-learning">
              {({ isActive }) => (
                <Button
                  variant="ghost"
                  size="sm"
                  className="font-body text-sm"
                  aria-current={isActive ? "page" : undefined}
                >
                  <User className="mr-1 h-4 w-4" aria-hidden="true" />
                  Minha Aprendizagem
                </Button>
              )}
            </NavLink>
          )}

          <Button
            variant="ghost"
            size="icon"
            onClick={() => setIsDark((prev) => !prev)}
            aria-label={isDark ? "Mudar para modo claro" : "Mudar para modo escuro"}
          >
            {isDark ? <Sun className="h-4 w-4" /> : <Moon className="h-4 w-4" />}
          </Button>

          {user && (
            <DropdownMenu>
              <DropdownMenuTrigger asChild>
                <Button
                  variant="ghost"
                  size="sm"
                  className="h-10 gap-2 rounded-full border border-border bg-background px-2 font-body text-sm text-foreground hover:bg-muted/60 hover:text-foreground data-[state=open]:bg-muted/60 data-[state=open]:text-foreground"
                  aria-label={`Menu do utilizador: ${displayName}`}
                >
                  <span
                    className="inline-flex h-7 w-7 items-center justify-center rounded-full bg-muted text-xs font-semibold text-foreground"
                    aria-hidden="true"
                  >
                    {userInitials || "U"}
                  </span>
                  <span className="hidden max-w-[140px] truncate md:inline">{displayName}</span>
                  <ChevronDown className="h-4 w-4 text-muted-foreground" aria-hidden="true" />
                </Button>
              </DropdownMenuTrigger>
              <DropdownMenuContent align="end" className="w-64 rounded-2xl border-border p-2">
                <div className="space-y-0.5 px-2 py-1.5">
                  <p className="truncate text-sm font-semibold text-foreground">{displayName}</p>
                  <p className="truncate text-xs text-muted-foreground">{user.email}</p>
                </div>
                <DropdownMenuSeparator />
                <DropdownMenuItem
                  className="h-10 rounded-lg px-2.5"
                  onSelect={() => logoutMutation.mutate()}
                  disabled={logoutMutation.isPending}
                >
                  <LogOut className="mr-2 h-4 w-4" aria-hidden="true" />
                  {logoutMutation.isPending ? "A terminar sessão..." : "Terminar sessão"}
                </DropdownMenuItem>
              </DropdownMenuContent>
            </DropdownMenu>
          )}

          <Link
            to="/cart"
            className="relative"
            aria-label={cartCount > 0 ? `Carrinho — ${cartCount} item${cartCount > 1 ? "s" : ""}` : "Carrinho"}
          >
            <Button variant="ghost" size="icon" tabIndex={-1} aria-hidden="true">
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
