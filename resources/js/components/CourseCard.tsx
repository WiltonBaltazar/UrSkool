import { Star, Users, Clock } from "lucide-react";
import { Link } from "react-router-dom";
import { Badge } from "@/components/ui/badge";
import { Skeleton } from "@/components/ui/skeleton";
import type { Course } from "@/lib/types";
import { formatMzn, toCategoryPt, toLevelPt } from "@/lib/labels";

interface CourseCardProps {
  course: Course;
}

const FALLBACK_IMAGE = "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='400' height='176' viewBox='0 0 400 176'%3E%3Crect width='400' height='176' fill='%23e5e5e5'/%3E%3Ctext x='50%25' y='50%25' dominant-baseline='middle' text-anchor='middle' font-size='14' fill='%23999'%3ESem imagem%3C/text%3E%3C/svg%3E";

const CourseCard = ({ course }: CourseCardProps) => {
  return (
    <article className="card-hover rounded-xl overflow-hidden bg-card border border-border">
      <Link
        to={`/course/${course.id}`}
        className="group block"
        aria-label={course.title}
      >
        <div className="relative overflow-hidden">
          <img
            src={course.image}
            alt=""
            loading="lazy"
            onError={(e) => {
              (e.currentTarget as HTMLImageElement).src = FALLBACK_IMAGE;
            }}
            className="w-full h-44 object-cover transition-transform duration-500 group-hover:scale-105"
          />
          <Badge className="absolute top-3 left-3 bg-accent text-accent-foreground border-0 font-body text-xs font-semibold">
            {toCategoryPt(course.category)}
          </Badge>
        </div>

        <div className="p-5">
          <h3 className="font-display text-lg leading-snug text-card-foreground mb-1 line-clamp-2">
            {course.title}
          </h3>
          <p className="text-sm text-muted-foreground mb-3">{course.instructor}</p>

          <div className="flex items-center gap-2 mb-3">
            <div className="flex items-center gap-1">
              <Star className="h-4 w-4 fill-accent text-accent" aria-hidden="true" />
              <span className="text-sm font-semibold text-card-foreground">{course.rating}</span>
            </div>
            <span className="text-xs text-muted-foreground">({course.reviewCount.toLocaleString()})</span>
          </div>

          <div className="flex items-center gap-3 text-xs text-muted-foreground mb-4">
            <span className="flex items-center gap-1">
              <Clock className="h-3.5 w-3.5" aria-hidden="true" />
              {course.totalHours}h
            </span>
            <span className="flex items-center gap-1">
              <Users className="h-3.5 w-3.5" aria-hidden="true" />
              {course.studentCount.toLocaleString()}
            </span>
            <Badge variant="outline" className="text-xs py-0">
              {toLevelPt(course.level)}
            </Badge>
          </div>

          <div className="flex items-center gap-2">
            <span className="text-xl font-bold text-card-foreground">{formatMzn(course.price)}</span>
            <span className="text-sm text-muted-foreground line-through">{formatMzn(course.originalPrice)}</span>
          </div>
        </div>
      </Link>
    </article>
  );
};

export const CourseCardSkeleton = () => (
  <div className="rounded-xl overflow-hidden bg-card border border-border">
    <Skeleton className="w-full h-44 rounded-none" />
    <div className="p-5 space-y-3">
      <Skeleton className="h-5 w-3/4" />
      <Skeleton className="h-4 w-1/2" />
      <div className="flex items-center gap-2">
        <Skeleton className="h-4 w-8" />
        <Skeleton className="h-4 w-16" />
      </div>
      <div className="flex items-center gap-3">
        <Skeleton className="h-3.5 w-10" />
        <Skeleton className="h-3.5 w-12" />
        <Skeleton className="h-5 w-16 rounded-full" />
      </div>
      <Skeleton className="h-6 w-20" />
    </div>
  </div>
);

export default CourseCard;
