import { useState } from "react";
import { Star, ThumbsUp, User } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Textarea } from "@/components/ui/textarea";
import { Separator } from "@/components/ui/separator";
import { Progress } from "@/components/ui/progress";

interface Review {
  id: string;
  name: string;
  rating: number;
  date: string;
  comment: string;
  helpful: number;
}

const initialReviews: Review[] = [
  { id: "r1", name: "Jordan Lee", rating: 5, date: "há 2 semanas", comment: "Curso absolutamente fantástico! O instrutor explica temas complexos de forma muito acessível. Recomendo para quem quer evoluir.", helpful: 24 },
  { id: "r2", name: "Priya Sharma", rating: 4, date: "há 1 mês", comment: "Conteúdo muito bom no geral. Algumas secções pareceram rápidas, mas os projetos ajudaram muito a consolidar o meu entendimento.", helpful: 11 },
  { id: "r3", name: "Marcus Chen", rating: 5, date: "há 1 mês", comment: "Foi um dos melhores investimentos na minha jornada de aprendizagem. O currículo está bem estruturado e o ritmo é ótimo.", helpful: 18 },
  { id: "r4", name: "Emily Rodriguez", rating: 3, date: "há 2 meses", comment: "Curso bom para base. Gostaria de ver mais exemplos avançados e casos reais.", helpful: 5 },
];

const ratingDistribution = [
  { stars: 5, percent: 68 },
  { stars: 4, percent: 20 },
  { stars: 3, percent: 8 },
  { stars: 2, percent: 3 },
  { stars: 1, percent: 1 },
];

interface CourseReviewsProps {
  courseRating: number;
  reviewCount: number;
}

const StarRating = ({
  rating,
  interactive = false,
  onRate,
  size = "sm",
}: {
  rating: number;
  interactive?: boolean;
  onRate?: (r: number) => void;
  size?: "sm" | "lg";
}) => {
  const dim = size === "lg" ? "h-6 w-6" : "h-4 w-4";
  return (
    <div className="flex gap-0.5">
      {[1, 2, 3, 4, 5].map((s) => (
        <Star
          key={s}
          className={`${dim} ${
            s <= rating ? "fill-accent text-accent" : "text-border"
          } ${interactive ? "cursor-pointer hover:scale-110 transition-transform" : ""}`}
          onClick={() => interactive && onRate?.(s)}
        />
      ))}
    </div>
  );
};

const CourseReviews = ({ courseRating, reviewCount }: CourseReviewsProps) => {
  const [reviews, setReviews] = useState<Review[]>(initialReviews);
  const [newRating, setNewRating] = useState(0);
  const [newComment, setNewComment] = useState("");
  const [helpfulClicked, setHelpfulClicked] = useState<string[]>([]);

  const submitReview = () => {
    if (newRating === 0 || !newComment.trim()) return;
    const review: Review = {
      id: Date.now().toString(),
      name: "Tu",
      rating: newRating,
      date: "agora mesmo",
      comment: newComment.trim(),
      helpful: 0,
    };
    setReviews([review, ...reviews]);
    setNewRating(0);
    setNewComment("");
  };

  const toggleHelpful = (id: string) => {
    if (helpfulClicked.includes(id)) return;
    setHelpfulClicked([...helpfulClicked, id]);
    setReviews(
      reviews.map((r) => (r.id === id ? { ...r, helpful: r.helpful + 1 } : r))
    );
  };

  return (
    <div>
      <h2 className="font-display text-2xl text-foreground mb-6">Avaliações</h2>

      {/* Summary */}
      <div className="flex flex-col sm:flex-row gap-8 mb-8">
        <div className="text-center sm:text-left shrink-0">
          <div className="text-5xl font-bold text-foreground font-body">{courseRating}</div>
          <StarRating rating={Math.round(courseRating)} size="lg" />
          <p className="text-sm text-muted-foreground font-body mt-1">
            {reviewCount.toLocaleString()} avaliações
          </p>
        </div>
        <div className="flex-1 space-y-2">
          {ratingDistribution.map((d) => (
            <div key={d.stars} className="flex items-center gap-3">
              <span className="text-sm font-body text-muted-foreground w-12">{d.stars} estrela</span>
              <Progress value={d.percent} className="h-2 flex-1" />
              <span className="text-xs text-muted-foreground font-body w-10 text-right">
                {d.percent}%
              </span>
            </div>
          ))}
        </div>
      </div>

      <Separator className="mb-8" />

      {/* Escrever Avaliação */}
      <div className="bg-surface-elevated border border-border rounded-xl p-5 mb-8">
        <h3 className="font-body font-semibold text-foreground mb-3">Escrever Avaliação</h3>
        <div className="flex items-center gap-3 mb-3">
          <span className="text-sm text-muted-foreground font-body">A tua classificação:</span>
          <StarRating rating={newRating} interactive onRate={setNewRating} size="lg" />
        </div>
        <Textarea
          value={newComment}
          onChange={(e) => setNewComment(e.target.value)}
          placeholder="Partilha a tua experiência com este curso..."
          className="font-body min-h-[100px] mb-3"
        />
        <Button
          onClick={submitReview}
          disabled={newRating === 0 || !newComment.trim()}
          className="bg-accent hover:bg-accent-hover text-accent-foreground font-body font-semibold rounded-lg"
        >
          Enviar Avaliação
        </Button>
      </div>

      {/* Lista de Avaliações */}
      <div className="space-y-6">
        {reviews.map((review) => (
          <div key={review.id} className="flex gap-4">
            <div className="h-10 w-10 rounded-full bg-secondary flex items-center justify-center shrink-0">
              <User className="h-5 w-5 text-muted-foreground" />
            </div>
            <div className="flex-1">
              <div className="flex items-center gap-3 mb-1">
                <span className="font-body font-semibold text-sm text-foreground">
                  {review.name}
                </span>
                <StarRating rating={review.rating} />
                <span className="text-xs text-muted-foreground font-body">{review.date}</span>
              </div>
              <p className="text-sm text-muted-foreground font-body leading-relaxed mb-2">
                {review.comment}
              </p>
              <button
                onClick={() => toggleHelpful(review.id)}
                className={`flex items-center gap-1.5 text-xs font-body transition-colors ${
                  helpfulClicked.includes(review.id)
                    ? "text-accent"
                    : "text-muted-foreground hover:text-foreground"
                }`}
              >
                <ThumbsUp className="h-3.5 w-3.5" />
                Útil ({review.helpful})
              </button>
            </div>
          </div>
        ))}
      </div>
    </div>
  );
};

export default CourseReviews;
