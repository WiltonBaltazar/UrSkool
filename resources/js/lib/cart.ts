const CART_STORAGE_KEY = "urskool-cart-v1";
const CART_UPDATED_EVENT = "urskool-cart-updated";

export interface CartItem {
  courseId: string;
  addedAt: string;
}

export interface AddToCartResult {
  added: boolean;
  replacedCourseId: string | null;
}

const canUseStorage = (): boolean => typeof window !== "undefined" && Boolean(window.localStorage);

const readCart = (): CartItem[] => {
  if (!canUseStorage()) {
    return [];
  }

  try {
    const raw = window.localStorage.getItem(CART_STORAGE_KEY);
    if (!raw) {
      return [];
    }

    const parsed = JSON.parse(raw);
    if (!Array.isArray(parsed)) {
      return [];
    }

    const normalized = parsed
      .filter((item) => item && typeof item.courseId === "string")
      .map((item) => ({
        courseId: String(item.courseId),
        addedAt: typeof item.addedAt === "string" ? item.addedAt : new Date().toISOString(),
      }));

    if (normalized.length <= 1) {
      return normalized;
    }

    const mostRecent = [...normalized].sort((a, b) => {
      const left = Number.isNaN(Date.parse(a.addedAt)) ? 0 : Date.parse(a.addedAt);
      const right = Number.isNaN(Date.parse(b.addedAt)) ? 0 : Date.parse(b.addedAt);
      return right - left;
    })[0];

    return mostRecent ? [mostRecent] : [normalized[normalized.length - 1]];
  } catch {
    return [];
  }
};

const emitCartUpdated = () => {
  if (!canUseStorage()) {
    return;
  }

  window.dispatchEvent(new CustomEvent(CART_UPDATED_EVENT));
};

const writeCart = (items: CartItem[]) => {
  if (!canUseStorage()) {
    return;
  }

  window.localStorage.setItem(CART_STORAGE_KEY, JSON.stringify(items));
  emitCartUpdated();
};

export const getCartItems = (): CartItem[] => readCart();

export const getCartCount = (): number => readCart().length;

export const addToCart = (courseId: string): AddToCartResult => {
  const id = String(courseId).trim();
  if (!id) {
    return { added: false, replacedCourseId: null };
  }

  const items = readCart();
  const existing = items[0];
  if (existing?.courseId === id) {
    return { added: false, replacedCourseId: null };
  }

  writeCart([
    {
      courseId: id,
      addedAt: new Date().toISOString(),
    },
  ]);

  return {
    added: true,
    replacedCourseId: existing?.courseId ?? null,
  };
};

export const removeFromCart = (courseId: string) => {
  const id = String(courseId).trim();
  if (!id) {
    return;
  }

  const nextItems = readCart().filter((item) => item.courseId !== id);
  writeCart(nextItems);
};

export const clearCart = () => {
  writeCart([]);
};

export const subscribeToCart = (onChange: (items: CartItem[]) => void): (() => void) => {
  if (!canUseStorage()) {
    return () => undefined;
  }

  const handleStorage = (event: StorageEvent) => {
    if (event.key === CART_STORAGE_KEY) {
      onChange(readCart());
    }
  };

  const handleLocalUpdate = () => {
    onChange(readCart());
  };

  window.addEventListener("storage", handleStorage);
  window.addEventListener(CART_UPDATED_EVENT, handleLocalUpdate);

  return () => {
    window.removeEventListener("storage", handleStorage);
    window.removeEventListener(CART_UPDATED_EVENT, handleLocalUpdate);
  };
};
