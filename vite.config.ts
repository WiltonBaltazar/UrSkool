import { defineConfig } from "vite";
import react from "@vitejs/plugin-react-swc";
import path from "path";
import laravel from "laravel-vite-plugin";
import { componentTagger } from "lovable-tagger";

const hmrHost = process.env.VITE_HMR_HOST;
const hmrPort = Number(process.env.VITE_HMR_PORT || 5173);
const usePolling = process.env.CHOKIDAR_USEPOLLING === "true";

export default defineConfig(({ mode }) => ({
  server: {
    host: "0.0.0.0",
    port: 5173,
    hmr: {
      ...(hmrHost ? { host: hmrHost } : {}),
      port: hmrPort,
      clientPort: hmrPort,
      overlay: false,
    },
    watch: {
      usePolling,
      ignored: ["**/storage/framework/views/**"],
    },
  },
  plugins: [
    laravel({
      input: ["resources/js/main.tsx"],
      refresh: true,
    }),
    react(),
    mode === "development" && componentTagger(),
  ].filter(Boolean),
  resolve: {
    alias: {
      "@": path.resolve(__dirname, "./resources/js"),
    },
  },
}));
