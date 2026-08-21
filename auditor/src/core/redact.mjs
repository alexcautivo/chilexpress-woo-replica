/**
 * Privacidad y secretos (§35). Todo texto que salga hacia un proveedor de IA
 * pasa por aquí primero.
 */

const PATTERNS = [
  [/\b(sk-ant-[A-Za-z0-9_-]{10,})/g, 'ANTHROPIC_KEY'],
  [/\b(sk-[A-Za-z0-9]{20,})/g, 'OPENAI_KEY'],
  [/\bghp_[A-Za-z0-9]{20,}/g, 'GITHUB_TOKEN'],
  [/\beyJ[A-Za-z0-9_-]{10,}\.[A-Za-z0-9_-]{10,}\.[A-Za-z0-9_-]{10,}/g, 'JWT'],
  [/\bOcp-Apim-Subscription-Key\s*[:=]\s*\S+/gi, 'APIM_KEY'],
  [/\b(?:api[_-]?key|apikey|secret|token|password|passwd|pwd)\s*[:=]\s*["']?[^\s"',;]{6,}/gi, 'SECRET'],
  [/\bAuthorization\s*:\s*(?:Bearer|Basic)\s+\S+/gi, 'AUTH_HEADER'],
  [/\bSet-Cookie\s*:\s*[^\n]+/gi, 'COOKIE'],
  [/\b[\w.+-]+@[\w-]+\.[\w.]{2,}\b/g, 'EMAIL'],
  [/\b\d{1,2}\.\d{3}\.\d{3}-[\dkK]\b/g, 'RUT'],
  [/\b(?:\d[ -]?){13,19}\b/g, 'CARD'],
];

export function redact(input) {
  let text = typeof input === 'string' ? input : JSON.stringify(input ?? '', null, 2);
  const applied = [];
  for (const [pattern, label] of PATTERNS) {
    if (pattern.test(text)) {
      applied.push(label);
      text = text.replace(pattern, `[REDACTED:${label}]`);
    }
    pattern.lastIndex = 0;
  }
  return { text, redactions: applied };
}

export function redactDeep(value) {
  const { text, redactions } = redact(JSON.stringify(value ?? null));
  try {
    return { value: JSON.parse(text), redactions };
  } catch {
    return { value: text, redactions };
  }
}
