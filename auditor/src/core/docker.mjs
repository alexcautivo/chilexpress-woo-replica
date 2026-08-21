/**
 * Docker local (§10–§14). Entornos aislados, healthchecks encadenados y sin
 * `sleep` como mecanismo principal de espera.
 */
import { spawn } from 'node:child_process';

export function run(command, args, options = {}) {
  return new Promise((resolve) => {
    const child = spawn(command, args, {
      cwd: options.cwd,
      env: { ...process.env, ...(options.env || {}) },
      shell: false,
      windowsHide: true,
    });
    let stdout = '';
    let stderr = '';
    const timer = options.timeoutMs
      ? setTimeout(() => {
          child.kill('SIGKILL');
          stderr += `\n[auditor] timeout tras ${options.timeoutMs} ms`;
        }, options.timeoutMs)
      : null;
    child.stdout.on('data', (chunk) => {
      stdout += chunk.toString();
      if (options.onData) options.onData(chunk.toString());
    });
    child.stderr.on('data', (chunk) => {
      stderr += chunk.toString();
      if (options.onData) options.onData(chunk.toString());
    });
    child.on('error', (error) => {
      if (timer) clearTimeout(timer);
      resolve({ code: -1, stdout, stderr: `${stderr}\n${error.message}`, failed: true });
    });
    child.on('close', (code) => {
      if (timer) clearTimeout(timer);
      resolve({ code, stdout, stderr, failed: code !== 0 });
    });
  });
}

export async function dockerAvailable() {
  const version = await run('docker', ['version', '--format', '{{.Server.Version}}'], { timeoutMs: 20_000 });
  return { ok: !version.failed, detail: (version.stdout || version.stderr).trim() };
}

export function composeCmd(project, file, args) {
  return ['compose', '-p', project, '-f', file, ...args];
}

export async function compose(project, file, args, options = {}) {
  return run('docker', composeCmd(project, file, args), options);
}

export async function imageDigest(image) {
  const result = await run('docker', ['image', 'inspect', image, '--format', '{{index .RepoDigests 0}}'], {
    timeoutMs: 30_000,
  });
  return result.failed ? '' : result.stdout.trim();
}

/**
 * Espera activa por una condición. Devuelve cuánto tardó, para el reporte.
 */
export async function waitFor(label, check, { timeoutMs = 180_000, intervalMs = 3000, onTick } = {}) {
  const started = Date.now();
  let lastDetail = '';
  while (Date.now() - started < timeoutMs) {
    const outcome = await check();
    const ok = outcome === true || outcome?.ok === true;
    lastDetail = typeof outcome === 'object' && outcome ? outcome.detail || '' : '';
    if (ok) {
      return { label, ok: true, ms: Date.now() - started, detail: lastDetail };
    }
    if (onTick) onTick(label, Date.now() - started);
    await new Promise((resolve) => setTimeout(resolve, intervalMs));
  }
  return { label, ok: false, ms: Date.now() - started, detail: lastDetail || 'timeout' };
}

export async function containerHealthy(project, service) {
  const ps = await run('docker', ['compose', '-p', project, 'ps', '--format', 'json'], { timeoutMs: 30_000 });
  if (ps.failed) return { ok: false, detail: ps.stderr.trim().slice(0, 200) };
  const lines = ps.stdout.split(/\r?\n/).filter(Boolean);
  for (const line of lines) {
    try {
      const row = JSON.parse(line);
      if (row.Service === service) {
        const health = String(row.Health || '').toLowerCase();
        const state = String(row.State || '').toLowerCase();
        return { ok: health === 'healthy' || (health === '' && state === 'running'), detail: `${state}/${health || 'sin healthcheck'}` };
      }
    } catch {
      /* docker puede emitir una línea no JSON; se ignora */
    }
  }
  return { ok: false, detail: 'servicio no encontrado' };
}

export async function httpOk(url, { expectStatusBelow = 500, timeoutMs = 8000 } = {}) {
  try {
    const controller = new AbortController();
    const timer = setTimeout(() => controller.abort(), timeoutMs);
    const response = await fetch(url, { signal: controller.signal, redirect: 'manual' });
    clearTimeout(timer);
    return { ok: response.status < expectStatusBelow, detail: `HTTP ${response.status}` };
  } catch (error) {
    return { ok: false, detail: String(error.message || error) };
  }
}

export async function freePort(preferred) {
  const net = await import('node:net');
  const tryPort = (port) =>
    new Promise((resolve) => {
      const server = net.createServer();
      server.once('error', () => resolve(false));
      server.once('listening', () => server.close(() => resolve(true)));
      server.listen(port, '127.0.0.1');
    });
  for (let port = preferred; port < preferred + 50; port++) {
    if (await tryPort(port)) return port;
  }
  throw new Error(`No hay puertos libres desde ${preferred}`);
}
