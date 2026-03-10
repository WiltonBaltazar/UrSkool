import { useState } from "react";
import { Link, useLocation, useNavigate } from "react-router-dom";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { ArrowLeft, Lock } from "lucide-react";
import Navbar from "@/components/Navbar";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
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
    <div className="min-h-screen bg-background">
      <Navbar />

      <section className="container mx-auto px-4 py-12">
        <Link
          to="/"
          className="inline-flex items-center gap-1 text-muted-foreground hover:text-foreground font-body text-sm mb-8 transition-colors"
        >
          <ArrowLeft className="h-4 w-4" />
          Voltar ao início
        </Link>

        <div className="max-w-md mx-auto">
          <Card className="border-border shadow-lg">
            <CardHeader>
              <CardTitle className="font-display text-2xl flex items-center gap-2">
                <Lock className="h-5 w-5 text-accent" />
                Iniciar sessão
              </CardTitle>
              <CardDescription className="font-body">
                Entra para aceder aos teus cursos e progresso.
              </CardDescription>
            </CardHeader>

            <CardContent className="space-y-4">
              <div className="space-y-1">
                <Label className="font-body text-sm">E-mail</Label>
                <Input
                  type="email"
                  value={email}
                  onChange={(event) => setEmail(event.target.value)}
                  className="font-body"
                />
              </div>
              <div className="space-y-1">
                <Label className="font-body text-sm">Palavra-passe</Label>
                <Input
                  type="password"
                  value={password}
                  onChange={(event) => setPassword(event.target.value)}
                  className="font-body"
                />
              </div>

              <Button
                className="w-full bg-accent hover:bg-accent-hover text-accent-foreground font-body font-semibold"
                disabled={loginMutation.isPending || !email || !password}
                onClick={() => loginMutation.mutate({ email, password })}
              >
                {loginMutation.isPending ? "A iniciar sessão..." : "Entrar"}
              </Button>

              {allowSelfSignup && (
                <p className="text-sm text-muted-foreground font-body text-center">
                  Ainda nao tens conta?{" "}
                  <Link to="/signup" className="text-foreground hover:underline">
                    Criar conta
                  </Link>
                </p>
              )}
            </CardContent>
          </Card>
        </div>
      </section>
    </div>
  );
};

export default LoginPage;
