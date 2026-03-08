import { useEffect, useState, type FormEvent } from "react";
import { useParams, Link, useNavigate } from "react-router-dom";
import { ArrowLeft, Shield, Smartphone, Lock } from "lucide-react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Separator } from "@/components/ui/separator";
import Navbar from "@/components/Navbar";
import { checkoutCourse, fetchAuthUser, fetchCourse, fetchCourseAccess } from "@/lib/api";
import { useToast } from "@/hooks/use-toast";
import { formatMzn } from "@/lib/labels";
import { addToCart, clearCart, getCartCount, removeFromCart } from "@/lib/cart";

const CheckoutPage = () => {
  const { id } = useParams();
  const { toast } = useToast();
  const navigate = useNavigate();
  const queryClient = useQueryClient();
  const [fullName, setFullName] = useState("");
  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [confirmPassword, setConfirmPassword] = useState("");
  const [mpesaContact, setMpesaContact] = useState("");
  const [formErrors, setFormErrors] = useState<Record<string, string>>({});
  const { data: user } = useQuery({
    queryKey: ["auth-user"],
    queryFn: fetchAuthUser,
  });

  const { data: course, isLoading, isError } = useQuery({
    queryKey: ["course", id],
    queryFn: () => fetchCourse(id || "1"),
    enabled: Boolean(id),
  });
  const { data: accessData } = useQuery({
    queryKey: ["course-access", id, user?.id],
    queryFn: () => fetchCourseAccess(id || "1"),
    enabled: Boolean(id && user && course && course.price > 0 && !course.hasAccess),
  });

  const checkout = useMutation({
    mutationFn: checkoutCourse,
    onSuccess: async (data) => {
      await queryClient.invalidateQueries({ queryKey: ["auth-user"] });
      await queryClient.invalidateQueries({ queryKey: ["course", id] });
      await queryClient.invalidateQueries({ queryKey: ["course-access", id] });

      if (data.status === "pending") {
        toast({
          title: "Pagamento pendente",
          description: "Pedido enviado. Confirma o PIN no teu telemóvel M-Pesa.",
        });
        return;
      }

      if (course?.id) {
        removeFromCart(String(course.id));
      }

      const coursePath = `/course/${course?.id || id}`;
      const authUser = await queryClient.fetchQuery({
        queryKey: ["auth-user"],
        queryFn: fetchAuthUser,
      });

      toast({
        title: "Compra concluída",
        description: `Referência de pagamento: ${data.paymentReference}`,
      });

      if (!authUser) {
        navigate("/login", {
          replace: true,
          state: {
            from: coursePath,
            email,
          },
        });
        return;
      }

      navigate(coursePath, { replace: true });
    },
    onError: (error: Error) => {
      toast({
        variant: "destructive",
        title: "Falha no pagamento",
        description: error.message,
      });
    },
  });

  useEffect(() => {
    if (user) {
      setFullName(user.name);
      setEmail(user.email);
    }
  }, [user]);

  const normalizeMpesaContact = (value: string): string =>
    value.replace(/\D+/g, "");

  const updateFieldError = (field: string, message?: string) => {
    setFormErrors((prev) => {
      const next = { ...prev };
      if (message) {
        next[field] = message;
      } else {
        delete next[field];
      }
      return next;
    });
  };

  const validateCheckoutForm = (): Record<string, string> => {
    const errors: Record<string, string> = {};
    const trimmedName = fullName.trim();
    const trimmedEmail = email.trim();
    const normalizedContact = normalizeMpesaContact(mpesaContact);
    const requiresPayment = course.price > 0;
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    const mpesaRegex = /^(?:258)?(?:82|83|84|85|86|87)[0-9]{7}$/;

    if (trimmedName.length < 3) {
      errors.fullName = "Informe o nome completo (mínimo 3 caracteres).";
    }

    if (!emailRegex.test(trimmedEmail)) {
      errors.email = "Informe um e-mail válido.";
    }

    if (requiresPayment && !mpesaRegex.test(normalizedContact)) {
      errors.mpesaContact = "Número M-Pesa inválido. Exemplo: 84xxxxxxx ou 25884xxxxxxx.";
    }

    if (!user) {
      if (password.length < 8) {
        errors.password = "A palavra-passe deve ter no mínimo 8 caracteres.";
      } else if (!/(?=.*[A-Za-z])(?=.*\d)/.test(password)) {
        errors.password = "A palavra-passe deve conter letras e números.";
      }

      if (confirmPassword !== password) {
        errors.confirmPassword = "A confirmação da palavra-passe não corresponde.";
      }
    }

    return errors;
  };

  const validateSingleField = (field: string) => {
    const allErrors = validateCheckoutForm();
    updateFieldError(field, allErrors[field]);
  };

  useEffect(() => {
    if (id) {
      addToCart(String(id));
    }
  }, [id]);

  const hasAccess = Boolean(user) && Boolean(course) && (
    course.price === 0
    || Boolean(course.hasAccess)
    || Boolean(accessData?.hasAccess)
  );

  useEffect(() => {
    if (!hasAccess || !course?.id) {
      return;
    }

    removeFromCart(String(course.id));
  }, [course?.id, hasAccess]);

  if (isLoading) {
    return (
      <div className="min-h-screen bg-background flex items-center justify-center">
        <p className="text-muted-foreground font-body">A carregar pagamento...</p>
      </div>
    );
  }

  if (isError || !course) {
    return (
      <div className="min-h-screen bg-background flex items-center justify-center">
        <p className="text-muted-foreground font-body">Curso não encontrado.</p>
      </div>
    );
  }

  if (hasAccess) {
    const firstLessonId = course.sections?.flatMap((section) => section.lessons)?.[0]?.id;
    const startPath = firstLessonId
      ? `/student/${course.id}/${firstLessonId}`
      : `/course/${course.id}`;

    return (
      <div className="min-h-screen bg-background">
        <Navbar />
        <div className="container mx-auto px-4 py-10">
          <Link to={`/course/${course.id}`} className="inline-flex items-center gap-1 text-muted-foreground hover:text-foreground font-body text-sm mb-8 transition-colors">
            <ArrowLeft className="h-4 w-4" />
            Voltar ao curso
          </Link>
          <div className="max-w-xl border border-border rounded-xl bg-card p-6 space-y-4">
            <h1 className="font-display text-3xl text-foreground">Curso já adquirido</h1>
            <p className="text-muted-foreground font-body">
              Este curso já está associado à tua conta. Não precisas pagar novamente.
            </p>
            <div className="flex flex-wrap gap-3">
              <Button asChild className="bg-primary text-primary-foreground hover:bg-primary/90">
                <Link to={startPath}>Ir para o curso</Link>
              </Button>
              <Button asChild variant="outline">
                <Link to={`/course/${course.id}`}>Ver página do curso</Link>
              </Button>
            </div>
          </div>
        </div>
      </div>
    );
  }

  const discount = course.originalPrice - course.price;
  const cartCount = getCartCount();

  const handleClearCart = () => {
    clearCart();
    toast({
      title: "Carrinho limpo",
      description: "Os itens do carrinho foram removidos.",
    });
    navigate("/courses", { replace: true });
  };

  const handleCheckoutSubmit = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();

    const errors = validateCheckoutForm();
    setFormErrors(errors);

    if (Object.keys(errors).length > 0) {
      toast({
        variant: "destructive",
        title: "Corrige os campos obrigatórios",
        description: course.price > 0
          ? "Revê os dados de nome, contacto, e-mail e palavra-passe."
          : "Revê os dados de nome, e-mail e palavra-passe.",
      });
      return;
    }

    checkout.mutate({
      courseId: Number(course.id),
      fullName: fullName.trim(),
      email: email.trim(),
      mpesaContact: normalizeMpesaContact(mpesaContact),
      password: user ? undefined : password,
    });
  };

  return (
    <div className="min-h-screen bg-background">
      <Navbar />

      <div className="container mx-auto px-4 py-10">
        <Link to={`/course/${course.id}`} className="inline-flex items-center gap-1 text-muted-foreground hover:text-foreground font-body text-sm mb-8 transition-colors">
          <ArrowLeft className="h-4 w-4" />
          Voltar ao curso
        </Link>

        <h1 className="font-display text-3xl text-foreground mb-8">Pagamento</h1>

        <div className="grid md:grid-cols-5 gap-10">
          {/* Form */}
          <div className="md:col-span-3 space-y-6">
            <form className="space-y-6" onSubmit={handleCheckoutSubmit} noValidate>
              <div className="bg-card border border-border rounded-xl p-6">
                <h2 className="font-display text-xl text-card-foreground mb-4">Detalhes de Pagamento</h2>

                <div className="space-y-4">
                  <div>
                    <Label htmlFor="checkout-full-name" className="font-body text-sm">Nome Completo</Label>
                    <Input
                      id="checkout-full-name"
                      name="fullName"
                      type="text"
                      placeholder="João Silva"
                      className="mt-1 font-body"
                      value={fullName}
                      onChange={(e) => {
                        setFullName(e.target.value);
                        updateFieldError("fullName");
                      }}
                      onBlur={() => validateSingleField("fullName")}
                      readOnly={Boolean(user)}
                      autoComplete="name"
                      required
                      minLength={3}
                      maxLength={255}
                      aria-invalid={Boolean(formErrors.fullName)}
                    />
                    {formErrors.fullName && (
                      <p className="text-xs text-destructive mt-1">{formErrors.fullName}</p>
                    )}
                  </div>
                  <div>
                    <Label htmlFor="checkout-email" className="font-body text-sm">E-mail</Label>
                    <Input
                      id="checkout-email"
                      name="email"
                      type="email"
                      placeholder="joao@exemplo.com"
                      className="mt-1 font-body"
                      value={email}
                      onChange={(e) => {
                        setEmail(e.target.value);
                        updateFieldError("email");
                      }}
                      onBlur={() => validateSingleField("email")}
                      readOnly={Boolean(user)}
                      autoComplete="email"
                      required
                      maxLength={255}
                      aria-invalid={Boolean(formErrors.email)}
                    />
                    {formErrors.email && (
                      <p className="text-xs text-destructive mt-1">{formErrors.email}</p>
                    )}
                  </div>

                  <Separator />

                  {course.price > 0 && (
                    <div>
                      <Label htmlFor="checkout-contact" className="font-body text-sm flex items-center gap-2">
                        <Smartphone className="h-4 w-4 text-muted-foreground" />
                        Contacto M-Pesa
                      </Label>
                      <Input
                        id="checkout-contact"
                        name="mpesaContact"
                        type="tel"
                        inputMode="numeric"
                        pattern="(?:258)?(?:82|83|84|85|86|87)[0-9]{7}"
                        placeholder="84xxxxxxx ou 25884xxxxxxx"
                        className="mt-1 font-body"
                        value={mpesaContact}
                        onChange={(e) => {
                          setMpesaContact(normalizeMpesaContact(e.target.value).slice(0, 12));
                          updateFieldError("mpesaContact");
                        }}
                        onBlur={() => validateSingleField("mpesaContact")}
                        autoComplete="tel-national"
                        required={course.price > 0}
                        maxLength={12}
                        aria-invalid={Boolean(formErrors.mpesaContact)}
                      />
                      {formErrors.mpesaContact && (
                        <p className="text-xs text-destructive mt-1">{formErrors.mpesaContact}</p>
                      )}
                    </div>
                  )}

                  {!user && (
                    <div>
                      <Label htmlFor="checkout-password" className="font-body text-sm flex items-center gap-2">
                        <Lock className="h-4 w-4 text-muted-foreground" />
                        Criar palavra-passe
                      </Label>
                      <Input
                        id="checkout-password"
                        name="password"
                        type="password"
                        placeholder="Mínimo 8 caracteres"
                        className="mt-1 font-body"
                        value={password}
                        onChange={(e) => {
                          setPassword(e.target.value);
                          updateFieldError("password");
                        }}
                        onBlur={() => validateSingleField("password")}
                        autoComplete="new-password"
                        required={!user}
                        minLength={8}
                        aria-invalid={Boolean(formErrors.password)}
                      />
                      {formErrors.password && (
                        <p className="text-xs text-destructive mt-1">{formErrors.password}</p>
                      )}
                      <Input
                        id="checkout-confirm-password"
                        name="confirmPassword"
                        type="password"
                        placeholder="Confirmar palavra-passe"
                        className="mt-2 font-body"
                        value={confirmPassword}
                        onChange={(e) => {
                          setConfirmPassword(e.target.value);
                          updateFieldError("confirmPassword");
                        }}
                        onBlur={() => validateSingleField("confirmPassword")}
                        autoComplete="new-password"
                        required={!user}
                        minLength={8}
                        aria-invalid={Boolean(formErrors.confirmPassword)}
                      />
                      {formErrors.confirmPassword && (
                        <p className="text-xs text-destructive mt-1">{formErrors.confirmPassword}</p>
                      )}
                      <p className="text-xs text-muted-foreground mt-1">
                        Vamos criar a tua conta automaticamente após o pagamento.
                      </p>
                    </div>
                  )}
                </div>
              </div>

              <div className="flex items-center gap-2 text-sm text-muted-foreground font-body">
                <Shield className="h-4 w-4" />
                <span>Pagamento via M-Pesa com confirmação por PIN no telemóvel.</span>
              </div>

              <Button
                type="submit"
                disabled={checkout.isPending}
                className="w-full bg-accent hover:bg-accent-hover text-accent-foreground font-body font-semibold h-12 rounded-lg text-base"
              >
                {checkout.isPending ? "A processar..." : "Pagar com M-Pesa"} — {formatMzn(course.price)}
              </Button>
            </form>
          </div>

          {/* Summary */}
          <div className="md:col-span-2">
            <div className="bg-card border border-border rounded-xl p-6 sticky top-24">
              <h2 className="font-display text-lg text-card-foreground mb-4">Resumo da Encomenda</h2>
              <p className="text-xs text-muted-foreground font-body mb-3">
                Itens no carrinho: {cartCount}
              </p>

              <div className="flex gap-4 mb-4">
                <img
                  src={course.image}
                  alt={course.title}
                  className="w-20 h-14 object-cover rounded-md"
                />
                <div>
                  <h3 className="font-body font-semibold text-sm text-card-foreground leading-tight">
                    {course.title}
                  </h3>
                  <p className="text-xs text-muted-foreground mt-1">por {course.instructor}</p>
                </div>
              </div>

              <Separator className="my-4" />

              <div className="space-y-2 text-sm font-body">
                <div className="flex justify-between">
                  <span className="text-muted-foreground">Preço Original</span>
                  <span className="text-card-foreground">{formatMzn(course.originalPrice)}</span>
                </div>
                <div className="flex justify-between text-success">
                  <span>Desconto</span>
                  <span>-{formatMzn(discount)}</span>
                </div>
                <Separator className="my-2" />
                <div className="flex justify-between text-lg font-bold">
                  <span className="text-card-foreground">Total</span>
                  <span className="text-card-foreground">{formatMzn(course.price)}</span>
                </div>
              </div>

              <Button
                type="button"
                variant="outline"
                onClick={handleClearCart}
                className="w-full mt-5 font-body"
                disabled={checkout.isPending}
              >
                Limpar carrinho
              </Button>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
};

export default CheckoutPage;
