/**
 * Rasterise public/images/mark.svg into every size the storefront asks for.
 *
 *     npm run logo
 *
 * The SVG is the only place the logo is drawn. Everything this writes is
 * derived from it and should never be edited by hand — redraw the SVG and run
 * this again. Sizes come from where each file is actually used: 192 for the
 * PNG icon link browsers fall back to, 512 for og:image, 180 for
 * apple-touch-icon, and 16/32/48 inside the .ico. Everywhere the page can
 * simply use the SVG — the header, the footer, the preloader, the maintenance
 * screen, the admin panel — it does.
 */
const sharp = require('sharp');
const fs = require('fs');
const path = require('path');

const root = '/var/www';
const svg = fs.readFileSync(path.join(root, 'public/images/mark.svg'));

const sizes = {
    'public/images/logo-192.png': 192,
    'public/images/logo.png': 512,
    'public/images/apple-touch-icon.png': 180,
};

(async () => {
    for (const [file, size] of Object.entries(sizes)) {
        await sharp(svg, { density: 384 }).resize(size, size).png({ compressionLevel: 9 }).toFile(path.join(root, file));
        console.log(file, size, fs.statSync(path.join(root, file)).size + 'B');
    }

    // The .ico, assembled by hand: sharp writes no ICO, but every browser
    // since IE11 reads PNG-encoded entries, so the file is a 6-byte header,
    // one 16-byte directory entry per size, and the PNGs themselves.
    const icoSizes = [16, 32, 48];
    const pngs = [];
    for (const s of icoSizes) {
        pngs.push(await sharp(svg, { density: 384 }).resize(s, s).png({ compressionLevel: 9 }).toBuffer());
    }

    const header = Buffer.alloc(6);
    header.writeUInt16LE(0, 0);              // reserved
    header.writeUInt16LE(1, 2);              // type: icon
    header.writeUInt16LE(pngs.length, 4);

    let offset = 6 + 16 * pngs.length;
    const dir = pngs.map((png, i) => {
        const e = Buffer.alloc(16);
        e.writeUInt8(icoSizes[i] === 256 ? 0 : icoSizes[i], 0); // width  (0 == 256)
        e.writeUInt8(icoSizes[i] === 256 ? 0 : icoSizes[i], 1); // height
        e.writeUInt8(0, 2);                  // palette size
        e.writeUInt8(0, 3);                  // reserved
        e.writeUInt16LE(1, 4);               // colour planes
        e.writeUInt16LE(32, 6);              // bits per pixel
        e.writeUInt32LE(png.length, 8);
        e.writeUInt32LE(offset, 12);
        offset += png.length;
        return e;
    });

    fs.writeFileSync(path.join(root, 'public/favicon.ico'), Buffer.concat([header, ...dir, ...pngs]));
    console.log('public/favicon.ico', icoSizes.join('/'), fs.statSync(path.join(root, 'public/favicon.ico')).size + 'B');
})();
