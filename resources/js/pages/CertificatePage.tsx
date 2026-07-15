import { useMemo } from "react";
import { Link, useParams } from "react-router-dom";
import { useQuery } from "@tanstack/react-query";
import { Copy, Download, Share2 } from "lucide-react";
import { fetchPublicCertificate } from "@/lib/api";
import { Button } from "@/components/ui/button";
import { Card, CardContent } from "@/components/ui/card";
import { useToast } from "@/hooks/use-toast";

const CertificatePage = () => {
  const { toast } = useToast();
  const { code } = useParams();
  const { data: certificate, isLoading, isError } = useQuery({
    queryKey: ["public-certificate", code],
    queryFn: () => fetchPublicCertificate(code || ""),
    enabled: Boolean(code),
  });

  const issuedDate = useMemo(() => {
    if (!certificate?.issuedAt) return "-";
    return new Date(certificate.issuedAt).toLocaleDateString("pt-MZ", {
      day: "2-digit",
      month: "long",
      year: "numeric",
    });
  }, [certificate?.issuedAt]);

  const copyShareLink = async () => {
    if (!certificate?.shareUrl) return;

    try {
      await navigator.clipboard.writeText(certificate.shareUrl);
      toast({
        title: "Link copiado",
        description: "O link do certificado foi copiado para a área de transferência.",
      });
    } catch {
      toast({
        variant: "destructive",
        title: "Falha ao copiar",
        description: "Copia manualmente o link na barra de endereço.",
      });
    }
  };

  const nativeShare = async () => {
    if (!certificate?.shareUrl) return;

    if (navigator.share) {
      try {
        await navigator.share({
          title: `Certificado de ${certificate.recipientName} — ${certificate.courseTitle}`,
          url: certificate.shareUrl,
        });
      } catch {
        // user cancelled or share failed — silently ignore
      }
    } else {
      await copyShareLink();
    }
  };

  if (isLoading) {
    return (
      <div className="min-h-screen bg-background flex items-center justify-center">
        <p className="text-muted-foreground">A carregar certificado...</p>
      </div>
    );
  }

  if (isError || !certificate) {
    return (
      <div className="min-h-screen bg-background flex items-center justify-center px-4">
        <div className="max-w-md text-center space-y-3">
          <p className="text-muted-foreground">Não foi possível abrir este certificado.</p>
          <Link to="/my-learning" className="text-accent hover:underline">
            Voltar para Minha Aprendizagem
          </Link>
        </div>
      </div>
    );
  }

  return (
    <div className="min-h-screen bg-background px-4 py-8">
      <style>
        {`@media print {
          .certificate-actions { display: none !important; }
          body { background: #fff !important; }
        }`}
      </style>

      <div className="mx-auto max-w-5xl space-y-5">
        <div className="certificate-actions flex flex-wrap items-center justify-between gap-2">
          <Link to="/my-learning" className="text-sm text-muted-foreground hover:text-foreground">
            ← Minha Aprendizagem
          </Link>
          <div className="flex items-center gap-2">
            <Button type="button" variant="outline" onClick={nativeShare}>
              <Share2 className="mr-2 h-4 w-4" />
              Partilhar
            </Button>
            <Button type="button" variant="outline" onClick={copyShareLink}>
              <Copy className="mr-2 h-4 w-4" />
              Copiar link
            </Button>
            <Button type="button" className="bg-accent text-accent-foreground hover:bg-accent-hover" onClick={() => window.print()}>
              <Download className="mr-2 h-4 w-4" />
              Baixar
            </Button>
          </div>
        </div>

        <Card className="border-border">
          <CardContent className="p-8 md:p-14">
            <div className="space-y-6 text-center">
              <p className="text-xs uppercase tracking-[0.2em] text-muted-foreground">Certificado de Conclusão</p>
              <h1 className="font-display text-4xl md:text-5xl">{certificate.schoolName}</h1>
              <p className="text-lg text-muted-foreground">Certifica que</p>
              <p className="font-display text-4xl md:text-5xl">{certificate.recipientName}</p>
              <p className="text-lg text-muted-foreground max-w-3xl mx-auto">
                concluiu com sucesso o curso
              </p>
              <p className="font-display text-3xl md:text-4xl">{certificate.courseTitle}</p>
            </div>

            <div className="mt-12 grid gap-10 md:grid-cols-2">
              <div>
                <p className="text-xs uppercase tracking-[0.14em] text-muted-foreground">Data de Emissão</p>
                <p className="mt-2 text-lg font-medium">{issuedDate}</p>
              </div>
              <div className="space-y-2 md:text-right">
                <p className="text-xs uppercase tracking-[0.14em] text-muted-foreground">{certificate.issuerTitle}</p>
                <p className="text-lg font-semibold">{certificate.issuerName}</p>
                {certificate.signatureImageUrl ? (
                  <img
                    src={certificate.signatureImageUrl}
                    alt={`Assinatura de ${certificate.issuerName}`}
                    className="h-16 w-auto max-w-[200px] object-contain md:ml-auto"
                  />
                ) : (
                  <div className="h-16 border-b border-dashed border-border md:ml-auto md:w-48" />
                )}
              </div>
            </div>
          </CardContent>
        </Card>
      </div>
    </div>
  );
};

export default CertificatePage;
