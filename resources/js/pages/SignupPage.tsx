import { useState } from "react";
import { Link, useNavigate } from "react-router-dom";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { ArrowLeft, BookOpen, Eye, EyeOff } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { fetchSignupAvailability, register } from "@/lib/api";
import { useToast } from "@/hooks/use-toast";

const SignupPage = () => {
  const { toast } = useToast();
  const navigate = useNavigate();
  const queryClient = useQueryClient();

  const [name, setName] = useState("");
  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [passwordConfirmation, setPasswordConfirmation] = useState("");
  const [nameError, setNameError] = useState("");
  const [showPassword, setShowPassword] = useState(false);
  const [showConfirm, setShowConfirm] = useState(false);

  const { data: signupAvailability, isLoading: isSignupAvailabilityLoading } = useQuery({
    queryKey: ["signup-availability"],
    queryFn: fetchSignupAvailability,
  });
  const allowSelfSignup = signupAvailability?.allowSelfSignup ?? true;

  const registerMutation = useMutation({
    mutationFn: register,
    onSuccess: async (user) => {
      await queryClient.invalidateQueries({ queryKey: ["auth-user"] });
      navigate(user.isAdmin ? "/admin" : "/my-learning", { replace: true });
    },
    onError: (error: Error) => {
      toast({
        variant: "destructive",
        title: "Falha no registo",
        description: error.message,
      });
    },
  });

  const canSubmit =
    allowSelfSignup &&
    name.trim() &&
    email.trim() &&
    password &&
    passwordConfirmation &&
    !nameError;

  const handleNameChange = (value: string) => {
    setName(value);
    const emojiRegex = /\p{Emoji}/u;
    if (emojiRegex.test(value)) {
      setNameError("O nome não pode conter emojis.");
    } else {
      setNameError("");
    }
  };

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
            Começa a aprender hoje.
          </h1>
          <p className="font-body text-white/60 text-base leading-relaxed max-w-xs">
            Cria a tua conta gratuitamente e acede a dezenas de cursos no teu ritmo.
          </p>

          {/* Feature list */}
          <div className="flex flex-col gap-3 pt-4">
            {[
              "Progresso guardado automaticamente",
              "Certificados de conclusão",
              "Acesso vitalício ao conteúdo",
            ].map((item) => (
              <div
                key={item}
                className="flex items-center gap-3 rounded-xl px-4 py-3"
                style={{ background: "hsl(0 0% 100% / 0.06)", border: "1px solid hsl(0 0% 100% / 0.08)" }}
              >
                <div
                  className="w-1.5 h-1.5 rounded-full flex-shrink-0"
                  style={{ background: "hsl(152 60% 42%)" }}
                />
                <span className="font-body text-sm text-white/60">{item}</span>
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
              <h2 className="font-display text-3xl text-foreground">Criar conta</h2>
              <p className="font-body text-sm text-muted-foreground">
                {allowSelfSignup
                  ? "Regista-te para acompanhar o teu progresso e continuar de onde paraste."
                  : "O registo de novas contas está temporariamente desativado."}
              </p>
            </div>

            {/* Fields */}
            <fieldset disabled={!allowSelfSignup} className="space-y-5 disabled:opacity-50">
              <div className="space-y-1.5">
                <Label className="font-body text-sm font-medium text-foreground">Nome completo</Label>
                <Input
                  placeholder="O teu nome"
                  value={name}
                  onChange={(e) => handleNameChange(e.target.value)}
                  className="font-body h-11 bg-card border-border placeholder:text-muted-foreground/50 focus-visible:ring-1 focus-visible:ring-ring"
                />
                {nameError && (
                  <p className="text-xs text-destructive font-body">{nameError}</p>
                )}
              </div>

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

              <div className="grid grid-cols-2 gap-3">
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
                      {showPassword ? <EyeOff className="w-4 h-4" /> : <Eye className="w-4 h-4" />}
                    </button>
                  </div>
                </div>

                <div className="space-y-1.5">
                  <Label className="font-body text-sm font-medium text-foreground">Confirmar</Label>
                  <div className="relative">
                    <Input
                      type={showConfirm ? "text" : "password"}
                      placeholder="••••••••"
                      value={passwordConfirmation}
                      onChange={(e) => setPasswordConfirmation(e.target.value)}
                      className="font-body h-11 bg-card border-border pr-10 placeholder:text-muted-foreground/50 focus-visible:ring-1 focus-visible:ring-ring"
                    />
                    <button
                      type="button"
                      onClick={() => setShowConfirm((v) => !v)}
                      className="absolute right-3 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground transition-colors"
                      tabIndex={-1}
                    >
                      {showConfirm ? <EyeOff className="w-4 h-4" /> : <Eye className="w-4 h-4" />}
                    </button>
                  </div>
                </div>
              </div>

              <Button
                className="w-full h-11 bg-accent hover:bg-accent-hover text-accent-foreground font-body font-semibold text-sm transition-colors"
                disabled={isSignupAvailabilityLoading || registerMutation.isPending || !canSubmit}
                onClick={() =>
                  registerMutation.mutate({ name, email, password, passwordConfirmation })
                }
              >
                {isSignupAvailabilityLoading
                  ? "A validar…"
                  : registerMutation.isPending
                    ? "A criar conta…"
                    : "Criar conta"}
              </Button>
            </fieldset>

            {/* Login link */}
            <div className="space-y-4">
              <div className="relative">
                <div className="absolute inset-0 flex items-center">
                  <div className="w-full border-t border-border" />
                </div>
                <div className="relative flex justify-center">
                  <span className="px-3 bg-background font-body text-xs text-muted-foreground">
                    Já tens conta?
                  </span>
                </div>
              </div>
              <Link
                to="/login"
                className="flex items-center justify-center w-full h-11 rounded-[0.75rem] border border-border font-body text-sm font-medium text-foreground hover:bg-muted transition-colors"
              >
                Iniciar sessão
              </Link>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
};

export default SignupPage;
