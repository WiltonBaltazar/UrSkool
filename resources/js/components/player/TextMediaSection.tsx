interface TextMediaSectionProps {
  lessonId: string;
  lessonTitle: string;
  courseTitle: string;
  textMediaType: "none" | "image" | "youtube";
  textMediaImageUrl: string | null;
  textMediaYoutubeUrl: string | null;
  embeddedTextMediaYoutubeUrl: string | null;
  visualLeadText: string;
  visualSupportText: string;
  textVisualBadges: string[];
}

export function TextMediaSection({
  lessonId,
  lessonTitle,
  courseTitle,
  textMediaType,
  textMediaImageUrl,
  textMediaYoutubeUrl,
  embeddedTextMediaYoutubeUrl,
  visualLeadText,
  visualSupportText,
  textVisualBadges,
}: TextMediaSectionProps) {
  return (
    <section className="relative min-h-0 flex flex-col overflow-hidden rounded-lg border border-black/15 bg-[#0a0a16] text-white">
      <div className="h-12 px-4 border-b border-white/10 flex items-center">
        <p className="text-sm font-medium text-white/90">Visual da lição</p>
      </div>

      <div className="flex-1 overflow-y-auto p-5 md:p-7">
        {textMediaType === "image" && textMediaImageUrl ? (
          <div className="mx-auto max-w-5xl space-y-4">
            <div className="space-y-2">
              <p className="text-xs uppercase tracking-[0.18em] text-white/60">{courseTitle}</p>
              <h3 className="text-3xl md:text-4xl font-display text-white leading-tight">{lessonTitle}</h3>
            </div>
            <div className="overflow-hidden rounded-xl border border-white/15 bg-[#111127] p-2 md:p-3">
              <img
                src={textMediaImageUrl}
                alt={`Apoio visual: ${lessonTitle}`}
                className="h-auto w-full rounded-lg object-contain"
                loading="lazy"
              />
            </div>
          </div>
        ) : textMediaType === "youtube" ? (
          <div className="mx-auto max-w-5xl space-y-4">
            <div className="space-y-2">
              <p className="text-xs uppercase tracking-[0.18em] text-white/60">{courseTitle}</p>
              <h3 className="text-3xl md:text-4xl font-display text-white leading-tight">{lessonTitle}</h3>
            </div>
            {embeddedTextMediaYoutubeUrl ? (
              <div className="overflow-hidden rounded-xl border border-white/15 bg-black">
                <iframe
                  title={`youtube-text-${lessonId}`}
                  className="aspect-video w-full"
                  src={embeddedTextMediaYoutubeUrl}
                  allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                  allowFullScreen
                />
              </div>
            ) : (
              <div className="rounded-xl border border-white/15 bg-[#101022] p-5">
                <p className="text-white/85">URL de YouTube inválida para incorporação.</p>
                {textMediaYoutubeUrl && (
                  <a
                    href={textMediaYoutubeUrl}
                    target="_blank"
                    rel="noreferrer"
                    className="mt-2 inline-flex text-sm text-accent hover:underline"
                  >
                    Abrir vídeo em nova aba
                  </a>
                )}
              </div>
            )}
          </div>
        ) : (
          <div className="mx-auto max-w-4xl space-y-6">
            <div className="space-y-2">
              <p className="text-xs uppercase tracking-[0.18em] text-white/60">{courseTitle}</p>
              <h3 className="text-3xl md:text-4xl font-display text-white leading-tight">{lessonTitle}</h3>
              <p className="max-w-3xl text-base leading-7 text-white/78">{visualLeadText}</p>
            </div>

            <div className="rounded-xl border border-white/15 bg-[#101022] p-4 md:p-6 space-y-4">
              <p className="text-sm text-white/75 leading-7">{visualSupportText}</p>
              <div className="flex flex-wrap gap-2">
                {textVisualBadges.map((point, index) => (
                  <span
                    key={`${lessonId}-text-visual-point-${index}`}
                    className="inline-flex items-center rounded-full border border-white/20 bg-white/10 px-3 py-1 text-xs text-white/90"
                  >
                    {point}
                  </span>
                ))}
              </div>
            </div>

            <div className="rounded-xl border border-white/15 bg-[#15162a] p-4 md:p-6">
              <p className="text-xs uppercase tracking-[0.16em] text-white/60">Onde isto aparece na prática</p>
              <div className="mt-4 rounded-lg border border-white/15 bg-white/95 p-3 md:p-4">
                <div className="flex items-center gap-2 border-b border-black/10 pb-3">
                  <span className="h-3 w-3 rounded-full bg-red-400" />
                  <span className="h-3 w-3 rounded-full bg-amber-400" />
                  <span className="h-3 w-3 rounded-full bg-emerald-400" />
                  <p className="ml-2 text-xs font-medium text-black/60">Exemplo de interface</p>
                </div>
                <div className="grid gap-3 pt-4 md:grid-cols-2">
                  <div className="rounded-md border border-black/10 bg-white p-3">
                    <p className="text-xs uppercase tracking-wide text-black/45">Título</p>
                    <p className="mt-1 text-sm font-semibold text-black">Painel de aprendizagem</p>
                  </div>
                  <div className="rounded-md border border-black/10 bg-white p-3">
                    <p className="text-xs uppercase tracking-wide text-black/45">Leitura</p>
                    <p className="mt-1 text-sm font-semibold text-black">Conteúdo claro e progressivo</p>
                  </div>
                  <div className="rounded-md border border-black/10 bg-white p-3 md:col-span-2">
                    <p className="text-xs uppercase tracking-wide text-black/45">Aplicação CSS</p>
                    <p className="mt-1 text-sm text-black/80">
                      Usa este conceito para melhorar clareza, contraste e organização visual dos blocos.
                    </p>
                  </div>
                </div>
              </div>
            </div>
          </div>
        )}
      </div>
    </section>
  );
}
