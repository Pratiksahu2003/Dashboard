/** Default Open Graph / Twitter image when profile media is missing. */
export const DEFAULT_PUBLIC_OG_IMAGE = 'https://app.suganta.com/logo/Su250.png';

/**
 * @param {string | null | undefined} url
 * @param {string} [origin] window.location.origin
 */
export function absoluteOgImage(url, origin) {
  if (url == null || typeof url !== 'string') return DEFAULT_PUBLIC_OG_IMAGE;
  const s = url.trim();
  if (!s) return DEFAULT_PUBLIC_OG_IMAGE;
  if (/^https?:\/\//i.test(s)) return s;
  if (s.startsWith('//')) return `https:${s}`;
  if (origin && s.startsWith('/')) return `${String(origin).replace(/\/$/, '')}${s}`;
  return s;
}

/**
 * @param {string | null | undefined} twitterUrl
 * @returns {string} e.g. @handle for meta twitter:site
 */
export function twitterSiteHandle(twitterUrl) {
  if (twitterUrl == null || typeof twitterUrl !== 'string') return '';
  const u = twitterUrl.trim();
  const m = u.match(/(?:twitter\.com|x\.com)\/([^/?#]+)/i);
  if (!m?.[1]) return '';
  const seg = m[1].toLowerCase();
  if (['intent', 'share', 'home', 'search'].includes(seg)) return '';
  const h = m[1].replace(/^@/, '');
  return h ? `@${h}` : '';
}

function prune(value) {
  if (value === undefined || value === null || value === '') return undefined;
  if (Array.isArray(value)) {
    const arr = value.map(prune).filter((x) => x !== undefined);
    return arr.length ? arr : undefined;
  }
  if (typeof value === 'object') {
    /** @type {Record<string, unknown>} */
    const out = {};
    for (const [k, v] of Object.entries(value)) {
      const p = prune(v);
      if (p !== undefined) out[k] = p;
    }
    return Object.keys(out).length ? out : undefined;
  }
  return value;
}

/**
 * @param {object} opts
 * @returns {Record<string, unknown>}
 */
export function buildTeacherJsonLd(opts) {
  const {
    pageUrl,
    siteUrl,
    siteName,
    personName,
    pageTitle,
    pageDescription,
    imageUrl,
    jobTitle,
    description,
    streetAddress,
    addressLocality,
    addressRegion,
    postalCode,
    addressCountry,
    telephone,
    email,
    sameAs = [],
    knowsAbout = [],
    ratingValue,
    reviewCount,
  } = opts;

  const ratingNum = Number(ratingValue);
  const countNum = Number(reviewCount);
  const hasRating = Number.isFinite(ratingNum) && ratingNum > 0 && Number.isFinite(countNum) && countNum > 0;

  const person = prune({
    '@type': 'Person',
    '@id': `${pageUrl}#person`,
    name: personName,
    url: pageUrl,
    image: imageUrl ? { '@type': 'ImageObject', url: imageUrl } : undefined,
    jobTitle: jobTitle || undefined,
    description: description || pageDescription,
    address: prune({
      '@type': 'PostalAddress',
      streetAddress: streetAddress || undefined,
      addressLocality: addressLocality || undefined,
      addressRegion: addressRegion || undefined,
      postalCode: postalCode || undefined,
      addressCountry: addressCountry || undefined,
    }),
    telephone: telephone || undefined,
    email: email || undefined,
    sameAs: sameAs.length ? [...new Set(sameAs.map(String))] : undefined,
    knowsAbout: knowsAbout.length ? [...new Set(knowsAbout.map(String))] : undefined,
    aggregateRating: hasRating
      ? {
          '@type': 'AggregateRating',
          ratingValue: Number(ratingNum.toFixed(1)),
          bestRating: 5,
          worstRating: 1,
          ratingCount: countNum,
        }
      : undefined,
  });

  const profilePage = prune({
    '@type': 'ProfilePage',
    '@id': `${pageUrl}#webpage`,
    url: pageUrl,
    name: pageTitle,
    description: pageDescription,
    isPartOf: prune({
      '@type': 'WebSite',
      name: siteName,
      url: siteUrl,
    }),
    mainEntity: { '@id': `${pageUrl}#person` },
  });

  return prune({
    '@context': 'https://schema.org',
    '@graph': [profilePage, person].filter(Boolean),
  });
}

/**
 * @param {object} opts
 * @returns {Record<string, unknown>}
 */
export function buildInstituteJsonLd(opts) {
  const {
    pageUrl,
    siteUrl,
    siteName,
    orgName,
    pageTitle,
    pageDescription,
    imageUrl,
    logoUrl,
    description,
    streetAddress,
    addressLocality,
    addressRegion,
    postalCode,
    addressCountry,
    telephone,
    email,
    sameAs = [],
    foundingDate,
    ratingValue,
    reviewCount,
    orgUrl,
    latitude,
    longitude,
    keywords = [],
  } = opts;

  const ratingNum = Number(ratingValue);
  const countNum = Number(reviewCount);
  const hasRating = Number.isFinite(ratingNum) && ratingNum > 0 && Number.isFinite(countNum) && countNum > 0;

  const la = Number(latitude);
  const lo = Number(longitude);
  const hasGeo = Number.isFinite(la) && Number.isFinite(lo) && la >= -90 && la <= 90 && lo >= -180 && lo <= 180;

  const organization = prune({
    '@type': ['EducationalOrganization', 'Organization'],
    '@id': `${pageUrl}#organization`,
    name: orgName,
    url: orgUrl || pageUrl,
    description: description || pageDescription,
    image: imageUrl ? { '@type': 'ImageObject', url: imageUrl } : undefined,
    logo: logoUrl ? { '@type': 'ImageObject', url: logoUrl } : undefined,
    address: prune({
      '@type': 'PostalAddress',
      streetAddress: streetAddress || undefined,
      addressLocality: addressLocality || undefined,
      addressRegion: addressRegion || undefined,
      postalCode: postalCode || undefined,
      addressCountry: addressCountry || undefined,
    }),
    geo: hasGeo
      ? {
          '@type': 'GeoCoordinates',
          latitude: la,
          longitude: lo,
        }
      : undefined,
    telephone: telephone || undefined,
    email: email || undefined,
    sameAs: sameAs.length ? [...new Set(sameAs.map(String))] : undefined,
    foundingDate: foundingDate || undefined,
    keywords: keywords.length ? keywords.join(', ') : undefined,
    aggregateRating: hasRating
      ? {
          '@type': 'AggregateRating',
          ratingValue: Number(ratingNum.toFixed(1)),
          bestRating: 5,
          worstRating: 1,
          ratingCount: countNum,
        }
      : undefined,
  });

  const profilePage = prune({
    '@type': 'ProfilePage',
    '@id': `${pageUrl}#webpage`,
    url: pageUrl,
    name: pageTitle,
    description: pageDescription,
    isPartOf: prune({
      '@type': 'WebSite',
      name: siteName,
      url: siteUrl,
    }),
    mainEntity: { '@id': `${pageUrl}#organization` },
  });

  return prune({
    '@context': 'https://schema.org',
    '@graph': [profilePage, organization].filter(Boolean),
  });
}
