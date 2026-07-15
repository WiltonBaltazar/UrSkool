import { createRoot } from "react-dom/client";
import App from "./App.tsx";
import "./index.css";

window.onerror = (msg, src, line, col, err) => {
  const root = document.getElementById("root");
  if (root) {
    root.innerHTML = `<pre style="color:red;padding:2rem;font-size:13px;white-space:pre-wrap">[JS Error]\n${msg}\n${src}:${line}:${col}\n${err?.stack ?? ""}</pre>`;
  }
};

window.addEventListener("unhandledrejection", (e) => {
  const root = document.getElementById("root");
  if (root && !root.hasChildNodes()) {
    root.innerHTML = `<pre style="color:red;padding:2rem;font-size:13px;white-space:pre-wrap">[Unhandled Promise]\n${e.reason}</pre>`;
  }
});

try {
  createRoot(document.getElementById("root")!).render(<App />);
} catch (e) {
  const root = document.getElementById("root");
  if (root) {
    root.innerHTML = `<pre style="color:red;padding:2rem;font-size:13px;white-space:pre-wrap">[Render Error]\n${e}</pre>`;
  }
}
