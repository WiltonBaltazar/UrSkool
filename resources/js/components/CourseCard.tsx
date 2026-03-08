import { Star, Users, Clock } from "lucide-react";
import { Link } from "react-router-dom";
import { Badge } from "@/components/ui/badge";
import type { Course } from "@/lib/types";
import { formatMzn, toCategoryPt, toLevelPt } from "@/lib/labels";

interface CourseCardProps {
  course: Course;
}

const CourseCard = ({ course }: CourseCardProps) => {
  return (
    <Link to={`/course/${course.id}`} className="group block">
      <div className="card-hover rounded-xl overflow-hidden bg-card border border-border">
        <div className="relative overflow-hidden">
          <img
            src={course.image}
            alt={course.title}
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
              <Star className="h-4 w-4 fill-accent text-accent" />
              <span className="text-sm font-semibold text-card-foreground">{course.rating}</span>
            </div>
            <span className="text-xs text-muted-foreground">({course.reviewCount.toLocaleString()})</span>
          </div>

          <div className="flex items-center gap-3 text-xs text-muted-foreground mb-4">
            <span className="flex items-center gap-1">
              <Clock className="h-3.5 w-3.5" />
              {course.totalHours}h
            </span>
            <span className="flex items-center gap-1">
              <Users className="h-3.5 w-3.5" />
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
      </div>
    </Link>
  );
};

export default CourseCard;
