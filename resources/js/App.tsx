import { Component, useEffect, type ErrorInfo, type ReactNode } from "react";
import { Toaster } from "@/components/ui/toaster";
import { Toaster as Sonner } from "@/components/ui/sonner";
import { TooltipProvider } from "@/components/ui/tooltip";
import { QueryClient, QueryClientProvider, useQuery } from "@tanstack/react-query";
import { BrowserRouter, Routes, Route, Navigate, useLocation, Link } from "react-router-dom";
import { fetchAuthUser, initializeSessionRenewal } from "@/lib/api";
import Index from "./pages/Index";
import CoursesPage from "./pages/CoursesPage";
import CoursePage from "./pages/CoursePage";
import CheckoutPage from "./pages/CheckoutPage";
import CartPage from "./pages/CartPage";
import AdminPage from "./pages/AdminPage";
import StudentPlayerPage from "./pages/StudentPlayerPage";
import LoginPage from "./pages/LoginPage";
import NotFound from "./pages/NotFound";

const queryClient = new QueryClient();

class RouteErrorBoundary extends Component<{ children: ReactNode }, { hasError: boolean }> {
  constructor(props: { children: ReactNode }) {
    super(props);
    this.state = { hasError: false };
  }

  static getDerivedStateFromError(): { hasError: boolean } {
    return { hasError: true };
  }

  componentDidCatch(_error: Error, _errorInfo: ErrorInfo): void {
    // Keep silent in UI; console already receives the stack in development.
  }

  render() {
    if (!this.state.hasError) {
      return this.props.children;
    }

    return (
      <div className="min-h-screen bg-background flex items-center justify-center px-4">
        <div className="max-w-md text-center space-y-3">
          <p className="font-body text-muted-foreground">
            Não foi possível abrir o leitor desta lição agora.
          </p>
          <Link to="/courses" className="text-accent hover:underline font-body">
            Voltar aos cursos
          </Link>
        </div>
      </div>
    );
  }
}

const SessionRenewalBootstrap = () => {
  useEffect(() => {
    initializeSessionRenewal();
  }, []);

  return null;
};

const RequireUser = ({ children }: { children: JSX.Element }) => {
  const location = useLocation();
  const { data: user, isLoading } = useQuery({
    queryKey: ["auth-user"],
    queryFn: fetchAuthUser,
  });

  if (isLoading) {
    return (
      <div className="min-h-screen bg-background flex items-center justify-center">
        <p className="text-muted-foreground font-body">A verificar acesso...</p>
      </div>
    );
  }

  if (!user) {
    return <Navigate to="/login" replace state={{ from: location.pathname }} />;
  }

  return children;
};

const RequireAdmin = ({ children }: { children: JSX.Element }) => {
  const { data: user, isLoading } = useQuery({
    queryKey: ["auth-user"],
    queryFn: fetchAuthUser,
  });

  if (isLoading) {
    return (
      <div className="min-h-screen bg-background flex items-center justify-center">
        <p className="text-muted-foreground font-body">A verificar acesso...</p>
      </div>
    );
  }

  if (!user) {
    return <Navigate to="/login" replace />;
  }

  if (!user.isAdmin) {
    return <Navigate to="/" replace />;
  }

  return children;
};

const GuestOnly = ({ children }: { children: JSX.Element }) => {
  const { data: user, isLoading } = useQuery({
    queryKey: ["auth-user"],
    queryFn: fetchAuthUser,
  });

  if (isLoading) {
    return (
      <div className="min-h-screen bg-background flex items-center justify-center">
        <p className="text-muted-foreground font-body">A carregar...</p>
      </div>
    );
  }

  if (user) {
    return <Navigate to={user.isAdmin ? "/admin" : "/courses"} replace />;
  }

  return children;
};

const App = () => (
  <QueryClientProvider client={queryClient}>
    <SessionRenewalBootstrap />
    <TooltipProvider>
      <Toaster />
      <Sonner />
      <BrowserRouter>
        <Routes>
          <Route path="/" element={<Index />} />
          <Route path="/courses" element={<CoursesPage />} />
          <Route path="/course/:id" element={<CoursePage />} />
          <Route path="/cart" element={<CartPage />} />
          <Route path="/checkout/:id" element={<CheckoutPage />} />
          <Route
            path="/admin"
            element={
              <RequireAdmin>
                <AdminPage />
              </RequireAdmin>
            }
          />
          <Route
            path="/login"
            element={
              <GuestOnly>
                <LoginPage />
              </GuestOnly>
            }
          />
          <Route
            path="/student/:courseId/:lessonId"
            element={
              <RequireUser>
                <RouteErrorBoundary>
                  <StudentPlayerPage />
                </RouteErrorBoundary>
              </RequireUser>
            }
          />
          <Route path="*" element={<NotFound />} />
        </Routes>
      </BrowserRouter>
    </TooltipProvider>
  </QueryClientProvider>
);

export default App;
