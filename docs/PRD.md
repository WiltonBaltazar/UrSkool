# UrSkool Product Requirements Document (PRD)

## 1. Product Overview

UrSkool is an e-learning platform focused on helping beginners learn programming through text-based, interactive lessons and in-app coding practice (similar to Codecademy-style workflows).

The product combines:
- Structured learning paths (courses -> sections -> lessons)
- Short, readable text explanations
- Immediate coding practice and validation
- Progress tracking and course completion

## 2. Problem Statement

Many aspiring developers stop learning because tutorials are passive, too long, or disconnected from practice. Learners need a guided experience where each concept is explained briefly and applied immediately in the same flow.

## 3. Vision

Build the most accessible coding learning experience for Portuguese-speaking learners in emerging markets: lightweight, affordable, mobile-friendly, and deeply practice-oriented.

## 4. Goals and Non-Goals

### Goals (MVP to V1)
- Deliver a text-first learning experience for core coding topics.
- Enable in-browser code practice with auto-checks.
- Track lesson/course progress and resume learning seamlessly.
- Support paid course enrollments and free course access.
- Provide an admin area for creating and maintaining courses.

### Non-Goals (initially)
- Live mentorship or real-time classroom sessions.
- Video-first content production pipeline.
- Peer-to-peer social network features.
- Native iOS/Android apps.

## 5. Target Users

### Primary Persona: Beginner Learner
- Age: 16-35
- Goal: Get practical coding skills for freelance/work opportunities.
- Needs: Clear steps, short lessons, immediate feedback, low friction.

### Secondary Persona: Upskilling Professional
- Has basic tech familiarity and wants structured learning.
- Values progress visibility and practical exercises.

### Internal Persona: Content/Admin Team
- Needs intuitive tools to publish and update curriculum quickly.

## 6. User Jobs To Be Done

- When I start learning to code, I want bite-sized lessons and practical exercises so I can stay motivated and make visible progress.
- When I get stuck, I want precise instructions and test feedback so I can correct my solution quickly.
- When I return later, I want to resume exactly where I left off.

## 7. Scope

### In Scope (MVP)
1. Authentication and session management.
2. Course catalog with categories/search.
3. Course detail pages with text-based curriculum.
4. Lesson types:
   - `text` (concept explanation)
   - `code` (hands-on coding tasks)
   - `quiz` (knowledge checks)
5. In-app coding workspace for exercises (HTML/CSS/JS support for MVP).
6. Lesson completion rules:
   - text/video-like lessons: mark complete on learner action
   - code lessons: complete only when validator passes
   - quiz lessons: complete when score >= pass threshold
7. Progress tracking by user/course/lesson.
8. My Learning dashboard with resume action.
9. Checkout/enrollment flow (including M-Pesa integration).
10. Admin dashboard + course management (CRUD for courses/sections/lessons).

### Out of Scope (MVP)
- Community forums and comments.
- Certificates with external verification.
- AI tutor/chat assistant.
- Multi-language UI beyond current default locale.

## 8. Functional Requirements

### 8.1 Learner Experience
- Users can browse all courses and filter by category/search.
- Users can view detailed course structure before purchase.
- Users can enroll in paid/free courses.
- Enrolled users can access lesson player and navigate section by section.
- Progress is saved automatically and shown as completion percentage.
- User can continue from the next incomplete lesson.

### 8.2 Interactive Practice
- Code lessons must include:
  - challenge instructions
  - starter code
  - expected completion criteria
- User code is validated in-app via deterministic checks.
- Show clear pass/fail feedback and allow retry without losing context.

### 8.3 Quiz Engine
- Quiz lessons support multiple questions and options.
- Pass threshold is configurable per lesson.
- Optional randomization of question order.
- Quiz completion status influences lesson/course progress.

### 8.4 Progress and Analytics
- Track lesson status: `not_started`, `in_progress`, `completed`.
- Store quiz score and pass result.
- Compute and expose course-level completion metrics.
- Provide admin visibility into course/student performance.

### 8.5 Admin and Content Management
- Admin can create/update/delete courses.
- Admin can structure sections and lessons with ordering.
- Admin can configure lesson types and exercise/quiz content.
- Admin can view key platform metrics (users, enrollments, revenue, completion stats).

## 9. Non-Functional Requirements

- **Performance**: API responses for course lists and student dashboards should be optimized (avoid N+1 query patterns).
- **Availability**: Core learning and checkout flows should remain operational with graceful error handling.
- **Security**: Auth-protected routes for learner/admin features; CSRF/session protections in web flows.
- **Scalability**: Data model supports growth in users, courses, and lesson progress records.
- **Mobile usability**: Responsive experience for common smartphone sizes.

## 10. Success Metrics

### Activation
- % of new users who complete first lesson within 24h.
- % of enrolled users who start first course lesson.

### Engagement
- Weekly active learners.
- Average lessons completed per active learner per week.
- Resume rate (users returning to continue learning).

### Learning Outcomes
- Course completion rate.
- Quiz pass rate and first-attempt pass rate.

### Business
- Checkout conversion rate.
- Revenue per active learner.
- Refund/failure rate in payment flow.

## 11. Release Plan

### Phase 1 (Current Foundation)
- Auth/session, catalog, checkout, course player, progress tracking, admin basics.

### Phase 2 (Learning Quality)
- Improve code validation UX, richer hints, stronger quiz analytics.

### Phase 3 (Growth)
- Learning paths, recommendations, cohort features, localization expansion.

## 12. Risks and Mitigations

- **Risk:** Learners drop due to difficult exercises.
  - **Mitigation:** Add progressive hints and better error messaging.
- **Risk:** Content quality inconsistency.
  - **Mitigation:** Create internal authoring standards and QA checklist.
- **Risk:** Payment failures reduce trust.
  - **Mitigation:** clear pending/failed states and retry-safe checkout flows.
- **Risk:** Performance degradation as data grows.
  - **Mitigation:** proactive query optimization, indexes, and lazy-loading prevention in development/testing.

## 13. Open Questions

1. Should the first public learning path focus on Web Development only or include Python fundamentals at launch?
2. What level of exercise auto-grading is required in MVP (exact output vs. test-suite based)?
3. Do we want monthly subscription, one-time course purchase, or hybrid pricing in V1?
4. Should users receive shareable completion certificates in V1 or V2?

## 14. Definition of Done (V1)

- Learner can sign up/login, enroll, complete interactive lessons, and track progress end-to-end.
- Admin can create and publish a full course with text, code exercises, and quizzes.
- Payment and enrollment workflows are reliable and auditable.
- Performance baseline is validated for primary learner endpoints.
- Core product metrics are instrumented and reviewable.
