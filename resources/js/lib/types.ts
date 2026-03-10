export interface QuizQuestion {
  id?: string;
  question: string;
  options: string[];
  correctOptionIndex: number;
}

export interface LessonWorkspaceFile {
  id: string;
  name: string;
  language: "html" | "css" | "js";
  content: string;
}

export interface Lesson {
  id: string;
  title: string;
  duration: string;
  videoUrl?: string | null;
  type?: "video" | "text" | "code" | "quiz" | "project";
  language?: string | null;
  content?: string | null;
  starterCode?: string | null;
  htmlCode?: string | null;
  cssCode?: string | null;
  jsCode?: string | null;
  workspaceFiles?: LessonWorkspaceFile[] | null;
  entryHtmlFileId?: string | null;
  quizQuestions?: QuizQuestion[] | null;
  quizPassPercentage?: number | null;
  quizRandomizeQuestions?: boolean | null;
  isFree: boolean;
}

export interface Section {
  id: string;
  title: string;
  lessons: Lesson[];
}

export interface Course {
  id: string;
  title: string;
  subtitle: string;
  instructor: string;
  rating: number;
  reviewCount: number;
  studentCount: number;
  price: number;
  originalPrice: number;
  image: string;
  category: string;
  level: string;
  totalHours: number;
  totalLessons: number;
  sections: Section[];
  description: string;
  hasAccess?: boolean;
  progress?: CourseProgress;
  resumeLessonId?: string | null;
  enrolledAt?: string | null;
}

export interface EnrollmentPayload {
  courseId: number;
  fullName: string;
  email: string;
  mpesaContact: string;
  password?: string;
}

export interface CheckoutResult {
  courseId: string;
  status: "completed" | "pending" | "failed";
  paymentReference: string;
  accountCreated?: boolean;
}

export interface CourseAccess {
  hasAccess: boolean;
}

export type LessonProgressStatus = "not_started" | "in_progress" | "completed";

export interface LessonProgressEntry {
  lessonId: string;
  status: LessonProgressStatus;
  codeIsCorrect: boolean;
  quizScore?: number | null;
  quizPassed: boolean;
  completedAt?: string | null;
  updatedAt?: string | null;
}

export interface CourseProgress {
  completedLessonIds: string[];
  completedLessons: number;
  totalLessons: number;
  completionPercent: number;
  lessons: LessonProgressEntry[];
}

export interface SaveLessonProgressPayload {
  status: "in_progress" | "completed";
  codeIsCorrect?: boolean;
  quizScore?: number | null;
  quizPassed?: boolean;
}

export interface CoursePayload {
  title: string;
  subtitle: string;
  instructor: string;
  rating: number;
  reviewCount: number;
  studentCount: number;
  price: number;
  originalPrice: number;
  image: string;
  category: string;
  level: string;
  totalHours: number;
  description: string;
  sections: Array<{
    title: string;
    lessons: Array<{
      title: string;
      duration: string;
      videoUrl?: string;
      language?: string;
      content?: string;
      starterCode?: string;
      htmlCode?: string;
      cssCode?: string;
      jsCode?: string;
      workspaceFiles?: LessonWorkspaceFile[];
      entryHtmlFileId?: string;
      quizQuestions?: QuizQuestion[];
      quizPassPercentage?: number;
      quizRandomizeQuestions?: boolean;
      isFree: boolean;
      type: "video" | "text" | "code" | "quiz" | "project";
    }>;
  }>;
}

export interface AuthUser {
  id: string;
  name: string;
  email: string;
  isAdmin?: boolean;
}

export interface AdminStats {
  totalCourses: number;
  totalUsers: number;
  totalEnrollments: number;
  totalRevenue: number;
  totalLessons: number;
  instructorsCount: number;
}

export interface AdminCourseSummary {
  id: string;
  title: string;
  subtitle?: string;
  instructor: string;
  category: string;
  level: string;
  price: number;
  studentCount: number;
  updatedAt?: string;
}

export interface AdminUserSummary {
  id: string;
  name: string;
  email: string;
  isAdmin: boolean;
  createdAt?: string;
}

export interface AdminEnrollmentSummary {
  id: string;
  courseId: string;
  courseTitle: string;
  fullName: string;
  email: string;
  amount: number;
  status: string;
  createdAt?: string;
}

export interface AdminSettings {
  platformName: string;
  supportEmail: string;
  currency: string;
  maintenanceMode: boolean;
  allowSelfSignup: boolean;
  defaultCourseVisibility: "public" | "private";
}

export interface AdminCategoryBreakdown {
  name: string;
  count: number;
}

export interface AdminDashboardData {
  stats: AdminStats;
  courses: AdminCourseSummary[];
  users: AdminUserSummary[];
  enrollments: AdminEnrollmentSummary[];
  categories: AdminCategoryBreakdown[];
  coursePerformance: AdminCoursePerformance[];
  studentPerformance: AdminStudentPerformance[];
  settings: AdminSettings;
}

export interface AdminCoursePerformance {
  courseId: string;
  courseTitle: string;
  enrollments: number;
  activeStudents: number;
  completionRate: number;
  averageQuizScore: number;
  quizPassRate: number;
}

export interface AdminStudentPerformance {
  userId: string;
  name: string;
  email: string;
  enrolledCourses: number;
  completionRate: number;
  averageQuizScore: number;
  lastActivityAt?: string | null;
}
