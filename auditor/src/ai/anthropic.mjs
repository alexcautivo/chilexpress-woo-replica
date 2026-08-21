/**
 * Proveedor Anthropic Claude (§28). Solo recibe contexto ya redactado (§35).
 */
import { AiProvider } from './provider.mjs';

const ENDPOINT = 'https://api.anthropic.com/v1/messages';
const API_VERSION = '2023-06-01';

export class AnthropicProvider extends AiProvider {
  get name() {
    return 'anthropic';
  }

  async analyze(prompt, { system = '', maxTokens, timeoutMs } = {}) {
    const controller = new AbortController();
    const timer = setTimeout(() => controller.abort(), timeoutMs || this.config.timeoutMs);
    try {
      const response = await fetch(ENDPOINT, {
        method: 'POST',
        signal: controller.signal,
        headers: {
          'content-type': 'application/json',
          'x-api-key': this.config.apiKey,
          'anthropic-version': API_VERSION,
        },
        body: JSON.stringify({
          model: this.config.model,
          max_tokens: maxTokens || this.config.maxTokens,
          system: system || undefined,
          messages: [{ role: 'user', content: prompt }],
        }),
      });

      if (!response.ok) {
        const detail = await response.text();
        return {
          ok: false,
          reason: `HTTP ${response.status} del proveedor`,
          // El cuerpo puede repetir cabeceras: se recorta y nunca incluye la key.
          detail: detail.slice(0, 400),
        };
      }

      const data = await response.json();
      const text = (data.content || [])
        .filter((block) => block.type === 'text')
        .map((block) => block.text)
        .join('\n')
        .trim();

      return {
        ok: true,
        text,
        model: data.model || this.config.model,
        usage: data.usage || null,
      };
    } catch (error) {
      return { ok: false, reason: String(error.name === 'AbortError' ? 'timeout' : error.message || error) };
    } finally {
      clearTimeout(timer);
    }
  }
}
