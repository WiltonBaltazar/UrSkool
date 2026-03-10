import { useState } from "react";
import { Link, useNavigate } from "react-router-dom";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { ArrowLeft, UserPlus } from "lucide-react";
import Navbar from "@/components/Navbar";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
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

  const canSubmit = allowSelfSignup && name.trim() && email.trim() && password && passwordConfirmation && !nameError;

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
    <div className="min-h-screen bg-background">
      <Navbar />

      <section className="container mx-auto px-4 py-12">
        <Link
          to="/"
          className="inline-flex items-center gap-1 text-muted-foreground hover:text-foreground font-body text-sm mb-8 transition-colors"
        >
          <ArrowLeft className="h-4 w-4" />
          Voltar ao inicio
        </Link>

        <div className="max-w-md mx-auto">
          <Card className="border-border shadow-lg">
            <CardHeader>
              <CardTitle className="font-display text-2xl flex items-center gap-2">
                <UserPlus className="h-5 w-5 text-accent" />
                Criar conta
              </CardTitle>
              <CardDescription className="font-body">
                {allowSelfSignup
                  ? "Regista-te para acompanhar o teu progresso e continuar de onde paraste."
                  : "O registo de novas contas esta temporariamente desativado."}
              </CardDescription>
            </CardHeader>

            <CardContent className="space-y-4">
              <div className="space-y-1">
                <Label className="font-body text-sm">Nome completo</Label>
                <Input
                  value={name}
                  onChange={(event) => handleNameChange(event.target.value)}
                  className="font-body"
                />
                {nameError && <p className="text-sm text-destructive font-body">{nameError}</p>}
              </div>

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

              <div className="space-y-1">
                <Label className="font-body text-sm">Confirmar palavra-passe</Label>
                <Input
                  type="password"
                  value={passwordConfirmation}
                  onChange={(event) => setPasswordConfirmation(event.target.value)}
                  className="font-body"
                />
              </div>

              <Button
                className="w-full bg-accent hover:bg-accent-hover text-accent-foreground font-body font-semibold"
                disabled={isSignupAvailabilityLoading || registerMutation.isPending || !canSubmit}
                onClick={() => registerMutation.mutate({ name, email, password, passwordConfirmation })}
              >
                {isSignupAvailabilityLoading
                  ? "A validar disponibilidade..."
                  : registerMutation.isPending
                    ? "A criar conta..."
                    : "Criar conta"}
              </Button>

              <p className="text-sm text-muted-foreground font-body text-center">
                Ja tens conta?{" "}
                <Link to="/login" className="text-foreground hover:underline">
                  Entrar
                </Link>
              </p>
            </CardContent>
          </Card>
        </div>
      </section>
    </div>
  );
};

export default SignupPage;
