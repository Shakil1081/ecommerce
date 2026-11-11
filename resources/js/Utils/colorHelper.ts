/**
 * Enterprise Color Utility & Swatch Helper
 * Supports e-commerce, fashion, apparel, leather goods, tech gadgets,
 * and bilingual (English/Bangla) color detection and hex mapping.
 */

export interface ColorSwatchItem {
  name: string;
  hex: string;
  category?: string;
}

// Comprehensive dictionary of e-commerce fashion, apparel & tech colors
export const COLOR_DICTIONARY: Record<string, string> = {
  // Common typo / phonetic aliases
  'rad': '#ef4444', // Typo alias for red

  // Basic Colors
  'black': '#18181b',
  'white': '#ffffff',
  'off white': '#fafaf9',
  'off-white': '#fafaf9',
  'red': '#ef4444',
  'blue': '#3b82f6',
  'green': '#16a34a',
  'yellow': '#eab308',
  'orange': '#ea580c',
  'purple': '#9333ea',
  'pink': '#ec4899',
  'brown': '#78350f',
  'grey': '#64748b',
  'gray': '#64748b',

  // Bags, Leather & Earth Tones (Zara, Aldo, Michael Kors, etc.)
  'onyx black': '#111111',
  'midnight black': '#0f172a',
  'jet black': '#0a0a0a',
  'caramel tan': '#c68a4c',
  'caramel': '#b4703a',
  'tan': '#d2b48c',
  'camel': '#c19a6b',
  'nude': '#e3bc9a',
  'taupe': '#483c32',
  'chocolate': '#451a03',
  'coffee': '#4a2c11',
  'mocha': '#5d4037',
  'cognac': '#9a3412',
  'sand': '#e2d4b7',
  'khaki': '#a38a5e',
  'beige': '#f5f5dc',
  'cream': '#fef3c7',
  'ivory': '#fffff0',
  'almond': '#eed9c4',

  // Pastels & Soft Tones
  'soft lilac': '#d8b4e2',
  'lilac': '#c4b5fd',
  'lavender': '#c084fc',
  'pastel pink': '#fbcfe8',
  'baby pink': '#fce7f3',
  'dusty pink': '#db8d9f',
  'rose': '#f43f5e',
  'blush': '#fecdd3',
  'peach': '#fed7aa',
  'soft peach': '#ffedd5',
  'coral': '#f97316',
  'mint': '#a7f3d0',
  'mint green': '#6ee7b7',
  'sage': '#84a98c',
  'sage green': '#9ca3af',
  'baby blue': '#bae6fd',
  'powder blue': '#b0e0e6',
  'sky blue': '#38bdf8',

  // Reds, Maroons & Purples
  'maroon': '#831843',
  'burgundy': '#800020',
  'wine': '#722f37',
  'crimson': '#be123c',
  'ruby': '#e11d48',
  'scarlet': '#ff2400',
  'hot pink': '#db2777',
  'magenta': '#c026d3',
  'fuchsia': '#d946ef',
  'violet': '#7c3aed',
  'plum': '#581c87',
  'indigo': '#4f46e5',
  'berry': '#86198f',

  // Blues & Teals
  'navy': '#1e3a8a',
  'navy blue': '#1e3a8a',
  'midnight blue': '#0f172a',
  'dark blue': '#1e40af',
  'royal blue': '#2563eb',
  'electric blue': '#0284c7',
  'ocean blue': '#0369a1',
  'teal': '#0d9488',
  'dark teal': '#115e59',
  'turquoise': '#14b8a6',
  'cyan': '#06b6d4',
  'aqua': '#22d3ee',

  // Greens
  'emerald': '#059669',
  'emerald green': '#059669',
  'forest green': '#14532d',
  'dark green': '#166534',
  'olive': '#65a30d',
  'olive green': '#556b2f',
  'army green': '#4b5320',
  'lime': '#84cc16',
  'lime green': '#65a30d',
  'sea green': '#2e8b57',
  'pistachio': '#93c572',

  // Yellows & Oranges
  'mustard': '#d97706',
  'mustard yellow': '#d97706',
  'canary yellow': '#fef08a',
  'lemon': '#fef08a',
  'amber': '#f59e0b',
  'rust': '#b45309',
  'terracotta': '#c2410c',

  // Metallics & Neutrals
  'gold': '#d4af37',
  'golden': '#d4af37',
  'rose gold': '#b76e79',
  'silver': '#cbd5e1',
  'bronze': '#cd7f32',
  'copper': '#b87333',
  'champagne': '#f7e7ce',
  'platinum': '#e5e4e2',
  'charcoal': '#334155',
  'anthracite': '#293241',
  'slate': '#475569',
  'space grey': '#4b5563',
  'space gray': '#4b5563',
  'starlight': '#f0e9df',
  'midnight': '#0f172a',

  // Bangla Color Names (বাংলা রঙের নাম)
  'কালো': '#18181b',
  'সাদা': '#ffffff',
  'লাল': '#ef4444',
  'নীল': '#2563eb',
  'নেভি ব্লু': '#1e3a8a',
  'সবুজ': '#16a34a',
  'হলুদ': '#eab308',
  'গোলাপি': '#ec4899',
  'বেবি পিংক': '#fbcfe8',
  'কমলা': '#ea580c',
  'বাদামী': '#78350f',
  'বেগুনি': '#9333ea',
  'ধূসর': '#64748b',
  'সোনালী': '#d4af37',
  'রূপালী': '#cbd5e1',
  'আকাশি': '#38bdf8',
  'মেরুন': '#831843',
  'অলিভ': '#65a30d',
  'ক্যারামেল': '#c68a4c',
};

/**
 * Determine whether an attribute name represents a Color option
 */
export function isColorAttribute(name?: string): boolean {
  if (!name) return false;
  const n = name.toLowerCase().trim();
  return (
    n.includes('color') ||
    n.includes('colour') ||
    n.includes('shade') ||
    n.includes('hue') ||
    n.includes('swatch') ||
    n.includes('রং') ||
    n.includes('রঙ') ||
    n.includes('কালার')
  );
}

/**
 * Extract or resolve a HEX color code from any color name, title, or value string
 */
export function getColorHex(colorValue?: string): string {
  if (!colorValue) return '#94a3b8';

  const raw = colorValue.trim();

  // If it's already a direct hex code (e.g. #ff0000 or #f00)
  if (/^#([0-9a-fA-F]{3,8})$/.test(raw)) {
    return raw;
  }

  // If string contains a hex code in parentheses or after slash (e.g. "Ruby Red (#e11d48)")
  const hexMatch = raw.match(/#([0-9a-fA-F]{6}|[0-9a-fA-F]{3})/);
  if (hexMatch) {
    return hexMatch[0];
  }

  // Normalize name for dictionary lookup
  const clean = raw
    .toLowerCase()
    .replace(/[()[\]{}]/g, '')
    .replace(/\s+/g, ' ')
    .trim();

  // 1. Direct dictionary match
  if (COLOR_DICTIONARY[clean]) {
    return COLOR_DICTIONARY[clean];
  }

  // 2. Multi-word phrase search: check if dictionary keys exist inside clean string
  for (const [key, hex] of Object.entries(COLOR_DICTIONARY)) {
    // Only check compound keys of 4+ chars to avoid premature single-letter matches
    if (key.length >= 4 && (clean.includes(key) || key.includes(clean))) {
      return hex;
    }
  }

  // 3. Tokenize by words and look for root colors (e.g. "Light Red" -> "red")
  const words = clean.split(/[\s\-_/]+/);
  for (const w of words) {
    if (COLOR_DICTIONARY[w]) {
      return COLOR_DICTIONARY[w];
    }
  }

  // 4. Fallback to a deterministic soft hash color if unknown
  return generateDeterministicColor(clean);
}

/**
 * Check whether a hex color is light-colored (useful for applying subtle border/shadow)
 */
export function isLightColor(hexStr: string): boolean {
  let hex = hexStr.replace('#', '');
  if (hex.length === 3) {
    hex = hex.split('').map((c) => c + c).join('');
  }
  if (hex.length < 6) return false;

  const r = parseInt(hex.substring(0, 2), 16);
  const g = parseInt(hex.substring(2, 4), 16);
  const b = parseInt(hex.substring(4, 6), 16);

  // Perceived brightness formula (YIQ)
  const brightness = (r * 299 + g * 587 + b * 114) / 1000;
  return brightness > 195;
}

/**
 * Generate a consistent pleasant pastel color for unknown color names
 */
function generateDeterministicColor(str: string): string {
  let hash = 0;
  for (let i = 0; i < str.length; i++) {
    hash = str.charCodeAt(i) + ((hash << 5) - hash);
  }
  const h = Math.abs(hash % 360);
  // Return a soft, pleasant HSL color converted to hex
  return hslToHex(h, 60, 65);
}

function hslToHex(h: number, s: number, l: number): string {
  l /= 100;
  const a = (s * Math.min(l, 1 - l)) / 100;
  const f = (n: number) => {
    const k = (n + h / 30) % 12;
    const color = l - a * Math.max(Math.min(k - 3, 9 - k, 1), -1);
    return Math.round(255 * color).toString(16).padStart(2, '0');
  };
  return `#${f(0)}${f(8)}${f(4)}`;
}

/**
 * Curated Color Presets for Admin Variant Matrix Generator
 */
export const ADMIN_COLOR_PRESETS: { category: string; colors: { name: string; hex: string }[] }[] = [
  {
    category: '👜 Bags, Leather & Neutrals',
    colors: [
      { name: 'Onyx Black', hex: '#111111' },
      { name: 'Caramel Tan', hex: '#c68a4c' },
      { name: 'Tan', hex: '#d2b48c' },
      { name: 'Camel', hex: '#c19a6b' },
      { name: 'Brown', hex: '#78350f' },
      { name: 'Chocolate', hex: '#451a03' },
      { name: 'Coffee', hex: '#4a2c11' },
      { name: 'Beige', hex: '#f5f5dc' },
      { name: 'Cream', hex: '#fef3c7' },
      { name: 'White', hex: '#ffffff' },
      { name: 'Charcoal', hex: '#334155' },
      { name: 'Silver', hex: '#cbd5e1' },
    ],
  },
  {
    category: '🌸 Pastels & Soft Tones',
    colors: [
      { name: 'Soft Lilac', hex: '#d8b4e2' },
      { name: 'Lavender', hex: '#c084fc' },
      { name: 'Baby Pink', hex: '#fbcfe8' },
      { name: 'Blush', hex: '#fecdd3' },
      { name: 'Dusty Pink', hex: '#db8d9f' },
      { name: 'Peach', hex: '#fed7aa' },
      { name: 'Mint Green', hex: '#a7f3d0' },
      { name: 'Sage Green', hex: '#84a98c' },
      { name: 'Sky Blue', hex: '#38bdf8' },
      { name: 'Baby Blue', hex: '#bae6fd' },
    ],
  },
  {
    category: '❤️ Reds, Pinks & Warm Tones',
    colors: [
      { name: 'Red', hex: '#ef4444' },
      { name: 'Maroon', hex: '#831843' },
      { name: 'Burgundy', hex: '#800020' },
      { name: 'Crimson', hex: '#be123c' },
      { name: 'Rose', hex: '#f43f5e' },
      { name: 'Hot Pink', hex: '#ec4899' },
      { name: 'Coral', hex: '#f97316' },
      { name: 'Orange', hex: '#ea580c' },
      { name: 'Mustard', hex: '#d97706' },
      { name: 'Yellow', hex: '#eab308' },
    ],
  },
  {
    category: '💎 Blues, Greens & Metallics',
    colors: [
      { name: 'Navy Blue', hex: '#1e3a8a' },
      { name: 'Royal Blue', hex: '#2563eb' },
      { name: 'Teal', hex: '#0d9488' },
      { name: 'Emerald', hex: '#059669' },
      { name: 'Forest Green', hex: '#14532d' },
      { name: 'Olive Green', hex: '#556b2f' },
      { name: 'Gold', hex: '#d4af37' },
      { name: 'Rose Gold', hex: '#b76e79' },
      { name: 'Bronze', hex: '#cd7f32' },
      { name: 'Purple', hex: '#9333ea' },
    ],
  },
];
