import { useState } from "react";
import { Link, useLocation, useNavigate } from "react-router-dom";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { ArrowLeft, BookOpen, Eye, EyeOff } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { fetchSignupAvailability, login } from "@/lib/api";
import { useToast } from "@/hooks/use-toast";

const LoginPage = () => {
  const { toast } = useToast();
  const navigate = useNavigate();
  const location = useLocation();
  const queryClient = useQueryClient();
  const locationState = (location.state as { from?: string; email?: string } | null);

  const [email, setEmail] = useState(locationState?.email || "");
  const [password, setPassword] = useState("");
  const [showPassword, setShowPassword] = useState(false);

  const { data: signupAvailability } = useQuery({
    queryKey: ["signup-availability"],
    queryFn: fetchSignupAvailability,
  });
  const allowSelfSignup = signupAvailability?.allowSelfSignup ?? true;

  const loginMutation = useMutation({
    mutationFn: login,
    onSuccess: async (user) => {
      await queryClient.invalidateQueries({ queryKey: ["auth-user"] });
      const redirectTo = locationState?.from || (user.isAdmin ? "/admin" : "/courses");
      navigate(redirectTo, { replace: true });
    },
    onError: (error: Error) => {
      toast({
        variant: "destructive",
        title: "Falha na autenticação",
        description: error.message,
      });
    },
  });

  return (
    <div className="min-h-screen flex">
      {/* Left — Brand panel */}
      <div
        className="hidden lg:flex lg:w-[45%] xl:w-[40%] flex-col justify-between p-12 relative overflow-hidden"
        style={{ background: "var(--gradient-hero)" }}
      >
        {/* Decorative circles */}
        <div
          className="absolute -top-24 -right-24 w-96 h-96 rounded-full opacity-[0.06]"
          style={{ background: "hsl(0 0% 100%)" }}
        />
        <div
          className="absolute bottom-0 -left-16 w-72 h-72 rounded-full opacity-[0.04]"
          style={{ background: "hsl(0 0% 100%)" }}
        />
        <div
          className="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[500px] h-[500px] rounded-full opacity-[0.03]"
          style={{ border: "1px solid hsl(0 0% 100%)" }}
        />

        {/* Logo */}
        <div className="relative z-10 flex items-center gap-3">
          <div className="w-9 h-9 rounded-xl bg-white/10 flex items-center justify-center">
            <BookOpen className="w-5 h-5 text-white" />
          </div>
          <span className="font-display text-xl text-white tracking-tight">UrSkool</span>
        </div>

        {/* Hero copy */}
        <div className="relative z-10 space-y-5">
          <h1 className="font-display text-4xl xl:text-5xl text-white leading-tight">
            Bem-vindo de volta.
          </h1>
          <p className="font-body text-white/60 text-base leading-relaxed max-w-xs">
            Continua onde paraste e acelera o teu percurso de aprendizagem.
          </p>

          {/* Decorative stat pills */}
          <div className="flex flex-col gap-3 pt-4">
            {[
              { label: "Cursos disponíveis", value: "50+" },
              { label: "Alunos ativos", value: "2 000+" },
              { label: "Taxa de conclusão", value: "94%" },
            ].map(({ label, value }) => (
              <div
                key={label}
                className="flex items-center justify-between rounded-xl px-4 py-3"
                style={{ background: "hsl(0 0% 100% / 0.06)", border: "1px solid hsl(0 0% 100% / 0.08)" }}
              >
                <span className="font-body text-sm text-white/50">{label}</span>
                <span className="font-display text-base text-white">{value}</span>
              </div>
            ))}
          </div>
        </div>

        {/* Footer note */}
        <p className="relative z-10 font-body text-xs text-white/30">
          © {new Date().getFullYear()} UrSkool. Todos os direitos reservados.
        </p>
      </div>

      {/* Right — Form panel */}
      <div className="flex-1 flex flex-col bg-background">
        {/* Top bar */}
        <div className="flex items-center justify-between px-6 py-5 md:px-10">
          {/* Mobile logo */}
          <div className="flex items-center gap-2 lg:hidden">
            <BookOpen className="w-5 h-5 text-foreground" />
            <span className="font-display text-lg">UrSkool</span>
          </div>

          <Link
            to="/"
            className="ml-auto flex items-center gap-1.5 text-sm font-body text-muted-foreground hover:text-foreground transition-colors"
          >
            <ArrowLeft className="w-4 h-4" />
            Início
          </Link>
        </div>

        {/* Form */}
        <div className="flex-1 flex items-center justify-center px-6 py-10 md:px-16">
          <div className="w-full max-w-sm space-y-8">
            {/* Heading */}
            <div className="space-y-1.5">
              <h2 className="font-display text-3xl text-foreground">Iniciar sessão</h2>
              <p className="font-body text-sm text-muted-foreground">
                Entra para aceder aos teus cursos e progresso.
              </p>
            </div>

            {/* Fields */}
            <div className="space-y-5">
              <div className="space-y-1.5">
                <Label className="font-body text-sm font-medium text-foreground">E-mail</Label>
                <Input
                  type="email"
                  placeholder="nome@exemplo.com"
                  value={email}
                  onChange={(e) => setEmail(e.target.value)}
                  className="font-body h-11 bg-card border-border placeholder:text-muted-foreground/50 focus-visible:ring-1 focus-visible:ring-ring"
                />
              </div>

              <div className="space-y-1.5">
                <Label className="font-body text-sm font-medium text-foreground">Palavra-passe</Label>
                <div className="relative">
                  <Input
                    type={showPassword ? "text" : "password"}
                    placeholder="••••••••"
                    value={password}
                    onChange={(e) => setPassword(e.target.value)}
                    className="font-body h-11 bg-card border-border pr-10 placeholder:text-muted-foreground/50 focus-visible:ring-1 focus-visible:ring-ring"
                  />
                  <button
                    type="button"
                    onClick={() => setShowPassword((v) => !v)}
                    className="absolute right-3 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground transition-colors"
                    tabIndex={-1}
                  >
                    {showPassword
                      ? <EyeOff className="w-4 h-4" />
                      : <Eye className="w-4 h-4" />}
                  </button>
                </div>
              </div>

              <Button
                className="w-full h-11 bg-accent hover:bg-accent-hover text-accent-foreground font-body font-semibold text-sm transition-colors"
                disabled={loginMutation.isPending || !email || !password}
                onClick={() => loginMutation.mutate({ email, password })}
              >
                {loginMutation.isPending ? "A iniciar sessão…" : "Entrar"}
              </Button>
            </div>

            {/* Divider + signup link */}
            {allowSelfSignup && (
              <div className="space-y-4">
                <div className="relative">
                  <div className="absolute inset-0 flex items-center">
                    <div className="w-full border-t border-border" />
                  </div>
                  <div className="relative flex justify-center">
                    <span className="px-3 bg-background font-body text-xs text-muted-foreground">
                      Ainda não tens conta?
                    </span>
                  </div>
                </div>
                <Link
                  to="/signup"
                  className="flex items-center justify-center w-full h-11 rounded-[0.75rem] border border-border font-body text-sm font-medium text-foreground hover:bg-muted transition-colors"
                >
                  Criar conta gratuita
                </Link>
              </div>
            )}
          </div>
        </div>
      </div>
    </div>
  );
};

export default LoginPage;
