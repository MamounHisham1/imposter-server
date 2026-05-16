export const AVATAR_BASE = '/avatars/'

export const AVATAR_HEADS = [
  'head1.png', 'head2.png', 'head3.png', 'head4.png', 'head7.png', 'head8.png'
]

export const AVATAR_ITEMS = {
  eyes: ['eye1.png', 'eye002.png', 'eye004.png', 'eye005.png', 'eye006.png', 'eye6.png', 'eye007.png', 'eye9.png'],
  hair: ['hair3.png', 'hair5.png', 'hair7.png', 'hair9.png', 'hair10.png', 'hair11.png', 'hair12.png', 'haur001.png'],
  beard: ['beard00.png', 'beard001.png', 'beard002.png', 'beard2.png', 'beard3.png', 'beard4.png', 'beard5.png', 'beard6.png', 'beard7.png', 'beard9.png', 'beard10.png', 'beard12.png', 'beard13.png', 'beard14.png']
}

export const AVATAR_ALIGNMENT = {
  eyes: {
    'eye1.png': { scale: 70, x: 0, y: -16 },
    'eye002.png': { scale: 58, x: 0, y: -23 },
    'eye004.png': { scale: 72, x: -16, y: -17 },
    'eye005.png': { scale: 56, x: 0, y: -17 },
    'eye006.png': { scale: 78, x: 0, y: -10 },
    'eye6.png': { scale: 61, x: -33, y: 0 },
    'eye007.png': { scale: 55, x: 0, y: -19 },
    'eye9.png': { scale: 70, x: 0, y: -15 }
  },
  hair: {
    'hair3.png': { scale: 96, x: 0, y: -40 },
    'hair5.png': { scale: 100, x: 0, y: -50 },
    'hair7.png': { scale: 126, x: 0, y: -12 },
    'hair9.png': { scale: 114, x: 0, y: -10 },
    'hair10.png': { scale: 129, x: 0, y: -25 },
    'hair11.png': { scale: 115, x: 0, y: -44 },
    'hair12.png': { scale: 97, x: 0, y: -98 },
    'haur001.png': { scale: 104, x: 0, y: 0 }
  },
  beard: {
    'beard00.png': { scale: 71, x: 22, y: 52 },
    'beard001.png': { scale: 38, x: 0, y: 55 },
    'beard002.png': { scale: 45, x: 3, y: 60 },
    'beard2.png': { scale: 88, x: 16, y: 46 },
    'beard3.png': { scale: 83, x: 0, y: 36 },
    'beard4.png': { scale: 105, x: 0, y: 9 },
    'beard5.png': { scale: 157, x: 0, y: 183 },
    'beard6.png': { scale: 117, x: 0, y: 61 },
    'beard7.png': { scale: 69, x: 0, y: 37 },
    'beard9.png': { scale: 57, x: 0, y: 37 },
    'beard10.png': { scale: 49, x: 0, y: 45 },
    'beard12.png': { scale: 63, x: 0, y: 56 },
    'beard13.png': { scale: 93, x: 0, y: 38 },
    'beard14.png': { scale: 45, x: -23, y: 64 }
  }
}

const ALIGNMENT_TOOL_SIZE = 256

export function getLayerStyle(layer, filename, containerSize) {
  if (!filename) return { display: 'none' }
  const al = AVATAR_ALIGNMENT[layer]?.[filename]
  if (!al) return { display: 'none' }
  const ratio = containerSize / ALIGNMENT_TOOL_SIZE
  return {
    backgroundImage: `url(${AVATAR_BASE}${filename})`,
    backgroundSize: 'contain',
    backgroundPosition: 'center',
    backgroundRepeat: 'no-repeat',
    transform: `translate(${al.x * ratio}px, ${al.y * ratio}px) scale(${al.scale / 100})`
  }
}
