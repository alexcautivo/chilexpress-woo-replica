/**
 * Abstracción AI_PROVIDER (§28). El Core nunca habla con Anthropic directamente.
 */

export class AiProvider {
  constructor(config) {
    this.config = config;
  }

  get name() {
    return 'abstract';
  }

  // eslint-disable-next-line no-unused-vars
  async analyze(_prompt, _options) {
    throw new Error('El proveedor de IA debe implementar analyze().');
  }
}

export class NullProvider extends AiProvider {
  get name() {
    return 'none';
  }

  async analyze() {
    return { ok: false, reason: 'No hay proveedor de IA configurado.' };
  }
}
