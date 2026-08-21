/**
 * Empaquetador ZIP para artefactos de prueba locales.
 *
 * No se usa Compress-Archive de Windows: escribe "\" como separador dentro del
 * ZIP y los consumidores POSIX (WordPress, Composer) lo interpretan como parte
 * del nombre del archivo, no como una carpeta. Aquí siempre se emite "/".
 */
import fs from 'node:fs';
import path from 'node:path';
import zlib from 'node:zlib';

function crc32(buf) {
  if (typeof zlib.crc32 === 'function') return zlib.crc32(buf) >>> 0;
  let c = ~0;
  for (const byte of buf) {
    c ^= byte;
    for (let i = 0; i < 8; i++) c = (c >>> 1) ^ (0xedb88320 & -(c & 1));
  }
  return ~c >>> 0;
}

function dosTime(date) {
  const time = ((date.getHours() & 0x1f) << 11) | ((date.getMinutes() & 0x3f) << 5) | ((date.getSeconds() / 2) & 0x1f);
  const day = (((date.getFullYear() - 1980) & 0x7f) << 9) | (((date.getMonth() + 1) & 0x0f) << 5) | (date.getDate() & 0x1f);
  return { time, day };
}

function walk(root, prefix, files) {
  for (const item of fs.readdirSync(root, { withFileTypes: true })) {
    const absolute = path.join(root, item.name);
    const relative = prefix ? `${prefix}/${item.name}` : item.name;
    if (item.isDirectory()) {
      walk(absolute, relative, files);
    } else if (item.isFile()) {
      files.push({ absolute, name: relative });
    }
  }
  return files;
}

export function packFolder(sourceDir, targetZip) {
  const source = path.resolve(sourceDir);
  if (!fs.existsSync(source) || !fs.statSync(source).isDirectory()) {
    throw new Error(`No es una carpeta: ${source}`);
  }
  const rootName = path.basename(source);
  const files = walk(source, rootName, []);
  if (!files.length) throw new Error(`La carpeta esta vacia: ${source}`);

  const chunks = [];
  const central = [];
  let offset = 0;

  for (const file of files) {
    const data = fs.readFileSync(file.absolute);
    const deflated = zlib.deflateRawSync(data, { level: 9 });
    const useDeflate = deflated.length < data.length;
    const payload = useDeflate ? deflated : data;
    const method = useDeflate ? 8 : 0;
    const nameBuf = Buffer.from(file.name, 'utf8');
    const crc = crc32(data);
    const { time, day } = dosTime(fs.statSync(file.absolute).mtime);

    const local = Buffer.alloc(30);
    local.writeUInt32LE(0x04034b50, 0);
    local.writeUInt16LE(20, 4);
    local.writeUInt16LE(0x0800, 6); // nombres en UTF-8
    local.writeUInt16LE(method, 8);
    local.writeUInt16LE(time, 10);
    local.writeUInt16LE(day, 12);
    local.writeUInt32LE(crc, 14);
    local.writeUInt32LE(payload.length, 18);
    local.writeUInt32LE(data.length, 22);
    local.writeUInt16LE(nameBuf.length, 26);
    chunks.push(local, nameBuf, payload);

    const entry = Buffer.alloc(46);
    entry.writeUInt32LE(0x02014b50, 0);
    entry.writeUInt16LE(0x031e, 4); // creado en Unix
    entry.writeUInt16LE(20, 6);
    entry.writeUInt16LE(0x0800, 8);
    entry.writeUInt16LE(method, 10);
    entry.writeUInt16LE(time, 12);
    entry.writeUInt16LE(day, 14);
    entry.writeUInt32LE(crc, 16);
    entry.writeUInt32LE(payload.length, 20);
    entry.writeUInt32LE(data.length, 24);
    entry.writeUInt16LE(nameBuf.length, 28);
    // El desplazamiento produce un entero con signo en JS: hay que normalizarlo.
    entry.writeUInt32LE(((0o100644 << 16) >>> 0), 38); // permisos POSIX de archivo
    entry.writeUInt32LE(offset, 42);
    central.push(entry, nameBuf);

    offset += local.length + nameBuf.length + payload.length;
  }

  const centralBuf = Buffer.concat(central);
  const eocd = Buffer.alloc(22);
  eocd.writeUInt32LE(0x06054b50, 0);
  eocd.writeUInt16LE(files.length, 8);
  eocd.writeUInt16LE(files.length, 10);
  eocd.writeUInt32LE(centralBuf.length, 12);
  eocd.writeUInt32LE(offset, 16);

  fs.mkdirSync(path.dirname(targetZip), { recursive: true });
  fs.writeFileSync(targetZip, Buffer.concat([...chunks, centralBuf, eocd]));
  return { target: targetZip, fileCount: files.length, rootName };
}
