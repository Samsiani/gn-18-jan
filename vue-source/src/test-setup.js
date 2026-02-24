// Test setup for Vitest + jsdom
// jsdom provides localStorage natively — no polyfill needed.

// Silence classList warnings that arise when theme-toggle code runs during
// tests (document.documentElement is a minimal jsdom stub).
document.documentElement.classList.add = vi.fn()
document.documentElement.classList.remove = vi.fn()
