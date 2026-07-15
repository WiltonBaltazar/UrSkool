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

export interface CodeValidationRule {
  kind: "html_includes" | "css_includes" | "js_includes" | "selector_exists" | "text_includes";
  value: string;
}

export type LessonTextMediaType = "none" | "image" | "youtube";

export interface SubmittedLessonCode {
  html: string;
  css: string;
  js: string;
}

export interface Lesson {
  id: string;
  title: string;
  duration: string;
  videoUrl?: string | null;
  type?: "video" | "text" | "code" | "quiz" | "project";
  textMediaType?: LessonTextMediaType | null;
  textMediaImageUrl?: string | null;
  textMediaYoutubeUrl?: string | null;
  language?: string | null;
  content?: string | null;
  starterCode?: string | null;
  htmlCode?: string | null;
  cssCode?: string | null;
  jsCode?: string | null;
  workspaceFiles?: LessonWorkspaceFile[] | null;
  entryHtmlFileId?: string | null;
  validationRules?: CodeValidationRule[] | null;
  quizQuestions?: QuizQuestion[] | null;
  quizPassPercentage?: number | null;
  quizRandomizeQuestions?: boolean | null;
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
  quizAnswers?: Record<string, number>;
  submittedCode?: SubmittedLessonCode;
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
      textMediaType?: LessonTextMediaType;
      textMediaImageUrl?: string;
      textMediaImageFile?: File;
      removeTextMediaImage?: boolean;
      textMediaYoutubeUrl?: string;
      language?: string;
      content?: string;
      starterCode?: string;
      htmlCode?: string;
      cssCode?: string;
      jsCode?: string;
      workspaceFiles?: LessonWorkspaceFile[];
      entryHtmlFileId?: string;
      validationRules?: CodeValidationRule[];
      quizQuestions?: QuizQuestion[];
      quizPassPercentage?: number;
      quizRandomizeQuestions?: boolean;
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

export interface CourseCertificate {
  id: string;
  shareCode: string;
  shareUrl: string;
  recipientName: string;
  courseId: string;
  courseTitle: string;
  schoolName: string;
  issuerTitle: string;
  issuerName: string;
  signatureImageUrl?: string | null;
  issuedAt: string;
}

export interface AdminSettings {
  platformName: string;
  supportEmail: string;
  currency: string;
  maintenanceMode: boolean;
  allowSelfSignup: boolean;
  defaultCourseVisibility: "public" | "private";
  certificateSchoolName: string;
  certificateIssuerTitle: string;
  certificateIssuerName: string;
  certificateSignatureUrl: string;
}

export interface AdminCategoryBreakdown {
  name: string;
  count: number;
}

export interface AdminDailyActivity {
  date: string;
  activeLearners: number;
  lessonsCompleted: number;
}

export interface AdminEngagementMetrics {
  weeklyActiveLearners: number;
  weeklyLessonsCompleted: number;
  avgLessonsPerActiveLearner: number;
  activationRate: number;
  checkoutConversionRate: number;
  revenuePerLearner: number;
  activitySeries: AdminDailyActivity[];
}

export interface AdminDashboardData {
  stats: AdminStats;
  courses: AdminCourseSummary[];
  users: AdminUserSummary[];
  enrollments: AdminEnrollmentSummary[];
  categories: AdminCategoryBreakdown[];
  coursePerformance: AdminCoursePerformance[];
  studentPerformance: AdminStudentPerformance[];
  engagement: AdminEngagementMetrics;
  settings: AdminSettings;
}

export interface PaginationMeta {
  currentPage: number;
  lastPage: number;
  perPage: number;
  total: number;
}

export interface PaginatedResponse<T> {
  data: T[];
  meta: PaginationMeta;
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
