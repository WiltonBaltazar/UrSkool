import type {
  AdminDashboardData,
  AdminEnrollmentSummary,
  AdminSettings,
  AdminUserSummary,
  AuthUser,
  CheckoutResult,
  Course,
  CourseAccess,
  CourseProgress,
  CoursePayload,
  EnrollmentPayload,
  SaveLessonProgressPayload,
} from "@/lib/types";

interface ApiResponse<T> {
  data: T;
}

interface SessionState {
  accessTokenExpiresAt: string;
  refreshTokenExpiresAt?: string;
  renewBeforeSeconds: number;
}

const baseJsonHeaders: Record<string, string> = {
  "Content-Type": "application/json",
  Accept: "application/json",
};
const SESSION_STORAGE_KEY = "urskool.auth.session.v1";
const apiBaseUrl = (import.meta.env.VITE_API_URL ?? "").replace(/\/+$/, "");
const csrfCookieEndpoint = "/sanctum/csrf-cookie";
const refreshSessionEndpoint = "/api/auth/refresh";
let csrfCookieRequest: Promise<void> | null = null;
let refreshSessionRequest: Promise<boolean> | null = null;
let renewTimerId: number | null = null;
let renewalInitialized = false;

const normalizeSessionState = (raw: unknown): SessionState | null => {
  if (!raw || typeof raw !== "object") {
    return null;
  }

  const candidate = raw as Record<string, unknown>;
  const accessTokenExpiresAt = candidate.access_token_expires_at;
  if (typeof accessTokenExpiresAt !== "string" || Number.isNaN(Date.parse(accessTokenExpiresAt))) {
    return null;
  }

  const refreshTokenExpiresAt = candidate.refresh_token_expires_at;
  const renewBeforeSecondsCandidate = Number(candidate.renew_before_seconds ?? 600);

  return {
    accessTokenExpiresAt,
    refreshTokenExpiresAt:
      typeof refreshTokenExpiresAt === "string" && !Number.isNaN(Date.parse(refreshTokenExpiresAt))
        ? refreshTokenExpiresAt
        : undefined,
    renewBeforeSeconds: Number.isFinite(renewBeforeSecondsCandidate)
      ? Math.max(60, Math.floor(renewBeforeSecondsCandidate))
      : 600,
  };
};

const readStoredSessionState = (): SessionState | null => {
  if (typeof window === "undefined") {
    return null;
  }

  try {
    const raw = window.localStorage.getItem(SESSION_STORAGE_KEY);
    if (!raw) {
      return null;
    }

    return normalizeSessionState(JSON.parse(raw));
  } catch {
    return null;
  }
};

let sessionState: SessionState | null = readStoredSessionState();

const apiPath = (path: string): string => {
  if (!apiBaseUrl || /^https?:\/\//i.test(path)) {
    return path;
  }

  return `${apiBaseUrl}${path.startsWith("/") ? path : `/${path}`}`;
};

const clearRenewTimer = (): void => {
  if (typeof window === "undefined") {
    return;
  }

  if (renewTimerId !== null) {
    window.clearTimeout(renewTimerId);
    renewTimerId = null;
  }
};

const persistSessionState = (next: SessionState | null): void => {
  sessionState = next;

  if (typeof window === "undefined") {
    return;
  }

  try {
    if (next) {
      window.localStorage.setItem(SESSION_STORAGE_KEY, JSON.stringify({
        access_token_expires_at: next.accessTokenExpiresAt,
        refresh_token_expires_at: next.refreshTokenExpiresAt,
        renew_before_seconds: next.renewBeforeSeconds,
      }));
    } else {
      window.localStorage.removeItem(SESSION_STORAGE_KEY);
    }
  } catch {
    // Ignore storage failures.
  }
};

const getCookieValue = (name: string): string | null => {
  const escapedName = name.replace(/[.*+?^${}()|[\]\\]/g, "\\$&");
  const match = document.cookie.match(new RegExp(`(?:^|; )${escapedName}=([^;]*)`));
  return match ? decodeURIComponent(match[1]) : null;
};

const getCsrfToken = (): string | null => {
  return getCookieValue("XSRF-TOKEN");
};

const getMetaCsrfToken = (): string | null => {
  return document
    .querySelector('meta[name="csrf-token"]')
    ?.getAttribute("content") ?? null;
};

const buildJsonHeaders = (): Record<string, string> => {
  const headers: Record<string, string> = { ...baseJsonHeaders };
  const cookieToken = getCsrfToken();

  if (cookieToken) {
    headers["X-XSRF-TOKEN"] = cookieToken;
    return headers;
  }

  const metaToken = getMetaCsrfToken();
  if (metaToken) {
    headers["X-CSRF-TOKEN"] = metaToken;
  }

  return headers;
};

const withCredentials = (options: RequestInit): RequestInit => {
  return {
    ...options,
    credentials: "include",
  };
};

const normalizeHeaders = (headers?: HeadersInit): Record<string, string> => {
  if (!headers) {
    return {};
  }

  if (headers instanceof Headers) {
    return Object.fromEntries(headers.entries());
  }

  if (Array.isArray(headers)) {
    return Object.fromEntries(headers);
  }

  return { ...headers };
};

const shouldSkipSessionRefresh = (target: string): boolean => {
  return target.includes("/api/auth/login")
    || target.includes("/api/auth/logout")
    || target.includes("/api/auth/refresh");
};

async function ensureCsrfCookie(force = false): Promise<void> {
  if (!force && getCookieValue("XSRF-TOKEN")) {
    return;
  }

  if (!csrfCookieRequest) {
    csrfCookieRequest = fetch(
      apiPath(csrfCookieEndpoint),
      withCredentials({
        headers: {
          Accept: "application/json",
        },
      }),
    )
      .then((response) => {
        if (!response.ok) {
          throw new Error(`Falha ao obter cookie CSRF (${response.status}).`);
        }
      })
      .finally(() => {
        csrfCookieRequest = null;
      });
  }

  await csrfCookieRequest;
}

const clearSessionMetadata = (): void => {
  clearRenewTimer();
  persistSessionState(null);
};

const scheduleSessionRenewal = (): void => {
  if (typeof window === "undefined" || !renewalInitialized) {
    return;
  }

  clearRenewTimer();

  if (!sessionState) {
    return;
  }

  const expiresAtMs = Date.parse(sessionState.accessTokenExpiresAt);
  if (Number.isNaN(expiresAtMs)) {
    clearSessionMetadata();
    return;
  }

  const thresholdMs = Math.max(60000, sessionState.renewBeforeSeconds * 1000);
  const refreshAtMs = expiresAtMs - thresholdMs;
  const delayMs = Math.max(0, refreshAtMs - Date.now());

  renewTimerId = window.setTimeout(() => {
    void refreshSession().then((ok) => {
      if (!ok) {
        clearSessionMetadata();
      } else {
        scheduleSessionRenewal();
      }
    });
  }, delayMs);
};

const applySessionMetadata = (rawSession: unknown): void => {
  const normalized = normalizeSessionState(rawSession);
  persistSessionState(normalized);
  scheduleSessionRenewal();
};

async function refreshSession(): Promise<boolean> {
  if (!refreshSessionRequest) {
    refreshSessionRequest = (async () => {
      try {
        await ensureCsrfCookie();

        const response = await fetch(
          apiPath(refreshSessionEndpoint),
          withCredentials({
            method: "POST",
            headers: buildJsonHeaders(),
          }),
        );

        const payload = await response.json().catch(() => ({}));
        if (response.ok) {
          const rawSession = payload?.session ?? payload?.data?.session;
          applySessionMetadata(rawSession);
          return true;
        }

        if (response.status === 401 || response.status === 419) {
          clearSessionMetadata();
        }

        return false;
      } catch {
        return false;
      }
    })().finally(() => {
      refreshSessionRequest = null;
    });
  }

  return refreshSessionRequest;
}

export function initializeSessionRenewal(): void {
  if (typeof window === "undefined" || renewalInitialized) {
    return;
  }

  renewalInitialized = true;
  scheduleSessionRenewal();
}

async function fetchWithCsrfRetry(input: RequestInfo | URL, init: RequestInit = {}): Promise<Response> {
  const method = (init.method || "GET").toUpperCase();
  const shouldFetchCsrfCookie = !["GET", "HEAD", "OPTIONS"].includes(method);
  const target = typeof input === "string" ? apiPath(input) : input;
  const targetPath = typeof target === "string" ? target : target.toString();
  const skipSessionRefresh = shouldSkipSessionRefresh(targetPath);

  if (shouldFetchCsrfCookie) {
    await ensureCsrfCookie();
  }

  const execute = () =>
    fetch(
      target,
      withCredentials({
        ...init,
        headers: {
          ...buildJsonHeaders(),
          ...normalizeHeaders(init.headers),
        },
      }),
    );

  let response = await execute();

  if (response.status !== 419) {
    if (response.status !== 401 || skipSessionRefresh) {
      return response;
    }

    const refreshed = await refreshSession();
    if (!refreshed) {
      return response;
    }

    return execute();
  }

  await ensureCsrfCookie(true);

  response = await execute();

  return response;
}

async function parseResponse<T>(response: Response): Promise<T> {
  const raw = await response.text();
  const payload = raw
    ? (() => {
      try {
        return JSON.parse(raw) as Record<string, unknown>;
      } catch {
        return null;
      }
    })()
    : null;

  if (!response.ok) {
    const validationErrors = payload && typeof payload === "object" && payload.errors && typeof payload.errors === "object"
      ? payload.errors as Record<string, unknown>
      : null;

    const firstValidationEntry = validationErrors
      ? Object.entries(validationErrors).find(([, value]) => Array.isArray(value) && value.length > 0)
      : null;

    const firstValidationField = firstValidationEntry?.[0];
    const firstValidationMessageRaw = firstValidationEntry && Array.isArray(firstValidationEntry[1])
      ? firstValidationEntry[1][0]
      : null;
    const firstValidationMessage = typeof firstValidationMessageRaw === "string"
      ? firstValidationMessageRaw
      : null;
    const normalizedValidationMessage = firstValidationMessage && firstValidationField
      ? (firstValidationMessage.startsWith("validation.")
        ? `${firstValidationField}: valor inválido.`
        : `${firstValidationField}: ${firstValidationMessage}`)
      : null;

    const serverMessage = typeof payload?.message === "string" ? payload.message : null;
    const readableServerMessage = serverMessage && serverMessage.startsWith("validation.")
      ? null
      : serverMessage;

    const message = response.status === 419
      ? "Sessão expirada (CSRF). Recarrega a página e tenta novamente."
      : normalizedValidationMessage
        || readableServerMessage
        || (raw && !raw.trim().startsWith("<") ? raw.slice(0, 200) : null)
        || `Pedido falhou com estado ${response.status}`;
    throw new Error(message);
  }

  if (payload === null) {
    throw new Error("Resposta inválida do servidor. Tenta novamente.");
  }

  return payload as T;
}

export async function fetchCategories(): Promise<string[]> {
  const response = await fetchWithCsrfRetry("/api/categories");

  const payload = await parseResponse<ApiResponse<string[]>>(response);
  return payload.data;
}

export async function fetchCourses(filters?: {
  search?: string;
  category?: string;
}): Promise<Course[]> {
  const params = new URLSearchParams();

  if (filters?.search) {
    params.set("search", filters.search);
  }

  if (filters?.category && filters.category !== "Todas" && filters.category !== "All") {
    params.set("category", filters.category);
  }

  const query = params.toString();
  const response = await fetchWithCsrfRetry(`/api/courses${query ? `?${query}` : ""}`);

  const payload = await parseResponse<ApiResponse<Course[]>>(response);
  return payload.data;
}

export async function fetchCourse(courseId: string): Promise<Course> {
  const response = await fetchWithCsrfRetry(`/api/courses/${courseId}`);

  const payload = await parseResponse<ApiResponse<Course>>(response);
  return payload.data;
}

export async function checkoutCourse(payload: EnrollmentPayload): Promise<CheckoutResult> {
  const response = await fetchWithCsrfRetry("/api/checkout", {
    method: "POST",
    body: JSON.stringify(payload),
  });

  const parsed = await parseResponse<ApiResponse<CheckoutResult>>(response);
  return parsed.data;
}

export async function fetchCourseAccess(courseId: string): Promise<CourseAccess> {
  const response = await fetchWithCsrfRetry(`/api/courses/${courseId}/access`);

  const payload = await parseResponse<ApiResponse<CourseAccess>>(response);
  return payload.data;
}

export async function fetchStudentCourse(courseId: string): Promise<Course> {
  const response = await fetchWithCsrfRetry(`/api/student/courses/${courseId}`);

  const payload = await parseResponse<ApiResponse<Course>>(response);
  return payload.data;
}

export async function fetchStudentCourses(): Promise<Course[]> {
  const response = await fetchWithCsrfRetry("/api/student/courses");

  const payload = await parseResponse<ApiResponse<Course[]>>(response);
  return payload.data;
}

export async function saveLessonProgress(
  courseId: string,
  lessonId: string,
  payload: SaveLessonProgressPayload,
): Promise<CourseProgress> {
  const response = await fetchWithCsrfRetry(`/api/student/courses/${courseId}/lessons/${lessonId}/progress`, {
    method: "PUT",
    body: JSON.stringify(payload),
  });

  const parsed = await parseResponse<ApiResponse<CourseProgress>>(response);
  return parsed.data;
}

export async function createCourse(payload: CoursePayload): Promise<Course> {
  const response = await fetchWithCsrfRetry("/api/admin/courses", {
    method: "POST",
    body: JSON.stringify(payload),
  });

  const parsed = await parseResponse<ApiResponse<Course>>(response);
  return parsed.data;
}

export async function updateAdminCourse(courseId: string, payload: CoursePayload): Promise<Course> {
  const response = await fetchWithCsrfRetry(`/api/admin/courses/${courseId}`, {
    method: "PUT",
    body: JSON.stringify(payload),
  });

  const parsed = await parseResponse<ApiResponse<Course>>(response);
  return parsed.data;
}

export async function fetchAuthUser(): Promise<AuthUser | null> {
  const response = await fetchWithCsrfRetry("/api/auth/user");
  if (response.status === 401) {
    clearSessionMetadata();
    return null;
  }

  const payload = await parseResponse<ApiResponse<AuthUser | null>>(response);
  return payload.data;
}

export async function fetchSignupAvailability(): Promise<{ allowSelfSignup: boolean }> {
  const response = await fetchWithCsrfRetry("/api/auth/signup-availability");

  const payload = await parseResponse<ApiResponse<{ allowSelfSignup: boolean }>>(response);
  return payload.data;
}

export async function login(payload: {
  email: string;
  password: string;
}): Promise<AuthUser> {
  const response = await fetchWithCsrfRetry("/api/auth/login", {
    method: "POST",
    body: JSON.stringify(payload),
  });

  const parsed = await parseResponse<ApiResponse<AuthUser> & { session?: unknown }>(response);
  applySessionMetadata(parsed.session);
  return parsed.data;
}

export async function register(payload: {
  name: string;
  email: string;
  password: string;
  passwordConfirmation: string;
}): Promise<AuthUser> {
  const response = await fetchWithCsrfRetry("/api/auth/register", {
    method: "POST",
    body: JSON.stringify({
      name: payload.name,
      email: payload.email,
      password: payload.password,
      password_confirmation: payload.passwordConfirmation,
    }),
  });

  const parsed = await parseResponse<ApiResponse<AuthUser> & { session?: unknown }>(response);
  applySessionMetadata(parsed.session);
  return parsed.data;
}

export async function logout(): Promise<void> {
  try {
    const response = await fetchWithCsrfRetry("/api/auth/logout", {
      method: "POST",
    });

    // Treat already-expired sessions as effectively logged out on the client.
    if (response.status === 401 || response.status === 419) {
      return;
    }

    if (!response.ok) {
      const payload = await response.json().catch(() => ({}));
      const message = payload?.message || `Pedido falhou com estado ${response.status}`;
      throw new Error(message);
    }

    await response.json().catch(() => undefined);
  } finally {
    clearSessionMetadata();
  }
}

export async function fetchAdminDashboard(): Promise<AdminDashboardData> {
  const response = await fetchWithCsrfRetry("/api/admin/dashboard");

  const payload = await parseResponse<ApiResponse<AdminDashboardData>>(response);
  return payload.data;
}

export async function fetchAdminUsers(): Promise<AdminUserSummary[]> {
  const response = await fetchWithCsrfRetry("/api/admin/users");

  const payload = await parseResponse<ApiResponse<AdminUserSummary[]>>(response);
  return payload.data;
}

export async function fetchAdminEnrollments(): Promise<AdminEnrollmentSummary[]> {
  const response = await fetchWithCsrfRetry("/api/admin/enrollments");

  const payload = await parseResponse<ApiResponse<AdminEnrollmentSummary[]>>(response);
  return payload.data;
}

export async function fetchAdminSettings(): Promise<AdminSettings> {
  const response = await fetchWithCsrfRetry("/api/admin/settings");

  const payload = await parseResponse<ApiResponse<AdminSettings>>(response);
  return payload.data;
}

export async function updateAdminSettings(payload: AdminSettings): Promise<AdminSettings> {
  const response = await fetchWithCsrfRetry("/api/admin/settings", {
    method: "PUT",
    body: JSON.stringify(payload),
  });

  const parsed = await parseResponse<ApiResponse<AdminSettings>>(response);
  return parsed.data;
}

export async function deleteAdminCourse(courseId: string): Promise<void> {
  const response = await fetchWithCsrfRetry(`/api/admin/courses/${courseId}`, {
    method: "DELETE",
  });

  await parseResponse<{ message: string }>(response);
}
