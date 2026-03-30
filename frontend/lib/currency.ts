/**
 * Format an integer fils amount as a BHD currency string.
 * Uses the user's locale; falls back to ar-BH for Arabic display.
 */
export function formatBHD(fils: number, locale = "ar-BH"): string {
  const bhd = fils / 1000
  return new Intl.NumberFormat(locale, {
    style: "currency",
    currency: "BHD",
    minimumFractionDigits: 3,
  }).format(bhd)
}
