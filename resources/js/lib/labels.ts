const categoryMap: Record<string, string> = {
  "Web Development": "Desenvolvimento Web",
  "Web Design": "Design Web",
  "UI Design": "Design de UI",
};

const levelMap: Record<string, string> = {
  Beginner: "Iniciante",
  Intermediate: "Intermediário",
  Advanced: "Avançado",
};

const enrollmentStatusMap: Record<string, string> = {
  completed: "concluída",
  pending: "pendente",
  failed: "falhada",
};

const mznFormatter = new Intl.NumberFormat("pt-MZ", {
  style: "currency",
  currency: "MZN",
  minimumFractionDigits: 2,
  maximumFractionDigits: 2,
});

export const toCategoryPt = (value: string) => categoryMap[value] ?? value;

export const toLevelPt = (value: string) => levelMap[value] ?? value;

export const toEnrollmentStatusPt = (value: string) => enrollmentStatusMap[value.toLowerCase()] ?? value;

export const formatMzn = (value: number) => mznFormatter.format(Number.isFinite(value) ? value : 0);
