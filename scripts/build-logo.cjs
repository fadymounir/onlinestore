/**
 * Cut the shop's badge out of resources/images/logo-source.jpg, repair the
 * spelling in its outer ring, and write every size the storefront asks for.
 *
 *     npm run logo
 *
 * The source render is the only place the logo exists. Everything this writes
 * is derived from it and should never be edited by hand — replace the source
 * and run this again.
 *
 * ── the crop ──────────────────────────────────────────────────────────────
 * The render is a 1365x768 frame with the badge floating in a grey field, and
 * the badge is not quite round: measured along its centre lines the ring's
 * outer edge runs x 435..953 (518px) and y 107..655 (548px), so it is an
 * ellipse about 5% taller than it is wide. Extracting that box and resizing it
 * to a square pulls it back into a true circle — which matters twice over,
 * because the mark is masked to a circle at the end, and because the repair
 * below rotates pixels about the centre and only lands on itself if the thing
 * being rotated is circular.
 *
 * CROP is that ellipse plus a few pixels of bleed; MASK_RADIUS lands back on
 * the ring's outer edge so none of the grey field survives as a fringe.
 */
const sharp = require('sharp');
const fs = require('fs');
const path = require('path');

const root = path.join(__dirname, '..');
const source = path.join(root, 'resources/images/logo-source.jpg');

const CROP = { left: 430, top: 102, width: 528, height: 558 };
const WORK = 1536;   // Working square. Three times the master, so the sub-degree
const MASTER = 512;  // rotations below resample cleanly before the way down.
const MASK_RADIUS = 253;

// Paper, the page ground (--color-paper). The transparent corners have to go
// somewhere for the two files that are composited by someone else: iOS paints
// an apple-touch-icon on black, and the chat apps that read og:image are no
// more careful.
const PAPER = '#faf8f5';

// 64 colours is more than this artwork uses — two browns, the amber, the
// peach, and the ramps between them — and it is a third of the bytes of the
// truecolour PNG.
const png = { palette: true, colors: 64, quality: 80, effort: 10, compressionLevel: 9 };

/* ── the repair ────────────────────────────────────────────────────────────
 *
 * The render spells the ring HANDMADDE ACCESSORY & COSMETICS, with one D too
 * many. Rather than erase the ring and set it again in a substitute face, this
 * deletes the extra letter the way a typesetter would: drop the glyph, then
 * close the gap by sliding what is on either side of it along the arc. Every
 * pixel of the original lettering survives, because nothing is redrawn — two
 * wedges of the image are simply rotated about the badge's centre.
 *
 * All of the numbers below were measured off the render at WORK scale, by
 * sampling ink in the ring and reading off where each glyph starts and stops.
 * Degrees run clockwise from 3 o'clock, which is how atan2 sees a y-down
 * image; the ring's text reads from H at 167° anticlockwise through the bottom
 * to the last S at 12°.
 *
 *   BAND        the annulus the lettering lives in. Glyphs span r 600..680;
 *               this clears them with room to spare while staying inside the
 *               outer ring (r 728) and above the tip of the figure's line
 *               (r 594), neither of which may move.
 *   SHIFT       half of one D's advance (5.7°). The text before the deleted
 *               letter slides forward by this, the text after it slides back
 *               by the same, which closes the gap and leaves the whole string
 *               centred on the bottom of the circle exactly where it was.
 *   BEFORE/AFTER  the two wedges, cut in the empty gaps either side of the
 *               doomed D. Their far edges sit in the clear space between the
 *               lettering and the two bracket arcs (which end at 8.1° and
 *               begin at 172.0°, and must not move) — and they are cut wide
 *               enough that once rotated they tile CLEAR exactly, so the flat
 *               fill underneath is never actually seen.
 *   BAND_BG     the ring's background, which measures a flat 232 all the way
 *               round, varying by less than the JPEG's own noise.
 */
const BAND = { r1: 597, r2: 706 };
const SHIFT = 2.85;
const BEFORE = [125.7, 171.9];  // H A N D M A D
const AFTER = [8.8, 120.0];     // E   A C C E S S O R Y   &   C O S M E T I C S
const CLEAR = [11.65, 169.05];
const BAND_BG = 'rgb(232,232,232)';

const C = WORK / 2;
const pt = (r, deg) => {
    const a = deg * Math.PI / 180;
    return [C + r * Math.cos(a), C + r * Math.sin(a)];
};

/** The slice of BAND between two angles, as a fill-able SVG path. */
const sector = ([d1, d2], fill) => {
    const [ox1, oy1] = pt(BAND.r2, d1);
    const [ox2, oy2] = pt(BAND.r2, d2);
    const [ix2, iy2] = pt(BAND.r1, d2);
    const [ix1, iy1] = pt(BAND.r1, d1);
    const large = d2 - d1 > 180 ? 1 : 0;
    return Buffer.from(
        `<svg xmlns="http://www.w3.org/2000/svg" width="${WORK}" height="${WORK}"><path d="` +
        `M ${ox1} ${oy1} A ${BAND.r2} ${BAND.r2} 0 ${large} 1 ${ox2} ${oy2} ` +
        `L ${ix2} ${iy2} A ${BAND.r1} ${BAND.r1} 0 ${large} 0 ${ix1} ${iy1} Z" fill="${fill}"/></svg>`
    );
};

/** Rotate about the square's centre and crop back to it, so the badge stays put. */
const spin = async (buffer, deg) => {
    const turned = await sharp(buffer)
        .rotate(deg, { background: { r: 0, g: 0, b: 0, alpha: 0 } })
        .png()
        .toBuffer();
    const { width } = await sharp(turned).metadata();
    const off = Math.round((width - WORK) / 2);
    return sharp(turned).extract({ left: off, top: off, width: WORK, height: WORK }).png().toBuffer();
};

const write = async (file, buffer) => {
    await sharp(buffer).png(png).toFile(path.join(root, file));
    console.log(file, fs.statSync(path.join(root, file)).size + 'B');
};

(async () => {
    const square = await sharp(source)
        .extract(CROP)
        .resize(WORK, WORK, { fit: 'fill' })
        .png()
        .toBuffer();

    const wedge = async (span) => sharp(square)
        .ensureAlpha()
        .composite([{ input: sector(span, '#fff'), blend: 'dest-in' }])
        .png()
        .toBuffer();

    const repaired = await sharp(square)
        .composite([
            { input: sector(CLEAR, BAND_BG) },
            { input: await spin(await wedge(BEFORE), -SHIFT) },
            { input: await spin(await wedge(AFTER), +SHIFT) },
        ])
        .png()
        .toBuffer();

    // median(3) is for the JPEG: the render's flat areas are full of
    // compression noise, which a palette encoder spends real bytes on and
    // which a 3px median clears without touching lines this heavy.
    const badge = await sharp(repaired)
        .resize(MASTER, MASTER)
        .median(3)
        .composite([{
            input: Buffer.from(
                `<svg xmlns="http://www.w3.org/2000/svg" width="${MASTER}" height="${MASTER}">` +
                `<circle cx="${MASTER / 2}" cy="${MASTER / 2}" r="${MASK_RADIUS}" fill="#fff"/></svg>`
            ),
            blend: 'dest-in',
        }])
        .png()
        .toBuffer();

    // Sizes come from where each file is actually used: 192 for the mark in
    // the header, footer and preloader and for the PNG icon link, 512 for
    // og:image, 180 for apple-touch-icon, and 16/32/48 inside the .ico.
    const at = (size) => sharp(badge).resize(size, size).png().toBuffer();
    const onPaper = (size) => sharp(badge).resize(size, size).flatten({ background: PAPER }).png().toBuffer();

    await write('public/images/logo-192.png', await at(192));
    await write('public/images/logo.png', await onPaper(512));
    await write('public/images/apple-touch-icon.png', await onPaper(180));

    // The .ico, assembled by hand: sharp writes no ICO, but every browser
    // since IE11 reads PNG-encoded entries, so the file is a 6-byte header,
    // one 16-byte directory entry per size, and the PNGs themselves.
    const icoSizes = [16, 32, 48];
    const pngs = [];
    for (const s of icoSizes) {
        pngs.push(await sharp(await at(s)).png(png).toBuffer());
    }

    const header = Buffer.alloc(6);
    header.writeUInt16LE(0, 0);              // reserved
    header.writeUInt16LE(1, 2);              // type: icon
    header.writeUInt16LE(pngs.length, 4);

    let offset = 6 + 16 * pngs.length;
    const dir = pngs.map((entry, i) => {
        const e = Buffer.alloc(16);
        e.writeUInt8(icoSizes[i] === 256 ? 0 : icoSizes[i], 0); // width  (0 == 256)
        e.writeUInt8(icoSizes[i] === 256 ? 0 : icoSizes[i], 1); // height
        e.writeUInt8(0, 2);                  // palette size
        e.writeUInt8(0, 3);                  // reserved
        e.writeUInt16LE(1, 4);               // colour planes
        e.writeUInt16LE(32, 6);              // bits per pixel
        e.writeUInt32LE(entry.length, 8);
        e.writeUInt32LE(offset, 12);
        offset += entry.length;
        return e;
    });

    fs.writeFileSync(path.join(root, 'public/favicon.ico'), Buffer.concat([header, ...dir, ...pngs]));
    console.log('public/favicon.ico', icoSizes.join('/'), fs.statSync(path.join(root, 'public/favicon.ico')).size + 'B');
})();
