/**
 * El ZIP es el artefacto real (§4) y su hash es obligatorio (§5).
 * Nada de este módulo escribe sobre el ZIP original.
 */
import crypto from 'node:crypto';
import fs from 'node:fs';
import path from 'node:path';

export function describeArtifact(zipPath) {
  const absolute = path.resolve(zipPath);
  if (!fs.existsSync(absolute)) {
    throw new Error(`No existe el artefacto: ${absolute}`);
  }
  const stat = fs.statSync(absolute);
  if (!stat.isFile()) {
    throw new Error(`El artefacto debe ser un archivo ZIP: ${absolute}`);
  }
  const sha256 = crypto.createHash('sha256').update(fs.readFileSync(absolute)).digest('hex');
  return {
    path: absolute,
    directory: path.dirname(absolute),
    name: path.basename(absolute),
    sizeBytes: stat.size,
    modifiedAt: stat.mtime.toISOString(),
    sha256,
    origin: detectOrigin(absolute),
  };
}

/**
 * Distingue un release oficial de un ZIP empaquetado localmente. Nunca se
 * presenta un paquete local como si fuera el artefacto oficial (§4).
 */
function detectOrigin(absolute) {
  const marker = `${absolute}.origin`;
  if (fs.existsSync(marker)) {
    const value = fs.readFileSync(marker, 'utf8').trim();
    if (value) return value;
  }
  return 'official-zip';
}

export function writeOrigin(zipPath, origin) {
  fs.writeFileSync(`${path.resolve(zipPath)}.origin`, `${origin}\n`);
}

/** Copia inmutable para los modos que necesitan tocar archivos (FIX, §18). */
export function copyForWork(artifact, destinationDir) {
  fs.mkdirSync(destinationDir, { recursive: true });
  const copy = path.join(destinationDir, artifact.name);
  fs.copyFileSync(artifact.path, copy);
  fs.chmodSync(copy, 0o444);
  return copy;
}

export function assertUnchanged(artifact) {
  const current = crypto.createHash('sha256').update(fs.readFileSync(artifact.path)).digest('hex');
  if (current !== artifact.sha256) {
    throw new Error('El artefacto cambio durante la auditoria. Se aborta para no reportar evidencia falsa.');
  }
  return true;
}
