/**
 * Lector ZIP mínimo y sin dependencias.
 *
 * El ZIP se considera no confiable (§45): aquí solo se parsea el índice y se
 * descomprimen entradas en memoria. Nunca se escribe ni se ejecuta en el host.
 */
import fs from 'node:fs';
import zlib from 'node:zlib';

const EOCD_SIG = 0x06054b50;
const EOCD64_LOCATOR_SIG = 0x07064b50;
const EOCD64_SIG = 0x06064b50;
const CENTRAL_SIG = 0x02014b50;
const LOCAL_SIG = 0x04034b50;
const MAX_ENTRY_BYTES = 8 * 1024 * 1024;

function findEocd(buf) {
  const min = Math.max(0, buf.length - 66_000);
  for (let i = buf.length - 22; i >= min; i--) {
    if (buf.readUInt32LE(i) === EOCD_SIG) return i;
  }
  return -1;
}

function readDirectoryLocation(buf, eocd) {
  let count = buf.readUInt16LE(eocd + 10);
  let offset = buf.readUInt32LE(eocd + 16);
  const locator = eocd - 20;
  if (locator >= 0 && buf.readUInt32LE(locator) === EOCD64_LOCATOR_SIG) {
    const eocd64 = Number(buf.readBigUInt64LE(locator + 8));
    if (eocd64 >= 0 && eocd64 + 56 <= buf.length && buf.readUInt32LE(eocd64) === EOCD64_SIG) {
      count = Number(buf.readBigUInt64LE(eocd64 + 32));
      offset = Number(buf.readBigUInt64LE(eocd64 + 48));
    }
  }
  return { count, offset };
}

export function readZip(zipPath) {
  const buf = fs.readFileSync(zipPath);
  const eocd = findEocd(buf);
  if (eocd < 0) {
    throw new Error('El archivo no es un ZIP valido (no se encontro el fin del directorio central).');
  }
  const { count, offset } = readDirectoryLocation(buf, eocd);
  const entries = [];
  let pointer = offset;
  for (let i = 0; i < count; i++) {
    if (pointer + 46 > buf.length || buf.readUInt32LE(pointer) !== CENTRAL_SIG) break;
    const method = buf.readUInt16LE(pointer + 10);
    const crc32 = buf.readUInt32LE(pointer + 16);
    const compressedSize = buf.readUInt32LE(pointer + 20);
    const uncompressedSize = buf.readUInt32LE(pointer + 24);
    const nameLength = buf.readUInt16LE(pointer + 28);
    const extraLength = buf.readUInt16LE(pointer + 30);
    const commentLength = buf.readUInt16LE(pointer + 32);
    const localOffset = buf.readUInt32LE(pointer + 42);
    // Compress-Archive de Windows escribe "\" como separador: se normaliza para
    // que los predicados de ruta funcionen igual en cualquier origen.
    const name = buf.toString('utf8', pointer + 46, pointer + 46 + nameLength).replace(/\\/g, '/');
    entries.push({
      name,
      method,
      crc32,
      compressedSize,
      uncompressedSize,
      localOffset,
      isDirectory: name.endsWith('/'),
    });
    pointer += 46 + nameLength + extraLength + commentLength;
  }
  return { buffer: buf, entries };
}

export function extractEntry(zip, entry) {
  if (entry.isDirectory) return Buffer.alloc(0);
  if (entry.uncompressedSize > MAX_ENTRY_BYTES) {
    throw new Error(`Entrada demasiado grande para inspeccionar: ${entry.name}`);
  }
  const buf = zip.buffer;
  const local = entry.localOffset;
  if (buf.readUInt32LE(local) !== LOCAL_SIG) {
    throw new Error(`Cabecera local invalida en ${entry.name}`);
  }
  const nameLength = buf.readUInt16LE(local + 26);
  const extraLength = buf.readUInt16LE(local + 28);
  const start = local + 30 + nameLength + extraLength;
  const raw = buf.subarray(start, start + entry.compressedSize);
  if (entry.method === 0) return Buffer.from(raw);
  if (entry.method === 8) return zlib.inflateRawSync(raw);
  throw new Error(`Metodo de compresion no soportado (${entry.method}) en ${entry.name}`);
}

export function readText(zip, entry, limit = 512 * 1024) {
  try {
    return extractEntry(zip, entry).subarray(0, limit).toString('utf8');
  } catch {
    return '';
  }
}

export function findEntry(zip, predicate) {
  return zip.entries.find((entry) => !entry.isDirectory && predicate(entry.name.toLowerCase(), entry));
}

export function findEntries(zip, predicate) {
  return zip.entries.filter((entry) => !entry.isDirectory && predicate(entry.name.toLowerCase(), entry));
}

/** Carpeta raíz común, si el ZIP la tiene (caso normal en plugins). */
export function rootFolder(zip) {
  const tops = new Set(
    zip.entries
      .map((entry) => entry.name.split('/')[0])
      .filter((name) => name && name !== '.' && name !== '__MACOSX'),
  );
  return tops.size === 1 ? [...tops][0] : '';
}

/** Rutas peligrosas: zip-slip y enlaces fuera del árbol (§45). */
export function unsafePaths(zip) {
  return zip.entries
    .map((entry) => entry.name)
    .filter((name) => name.startsWith('/') || name.includes('..') || /^[a-z]:/i.test(name));
}
