package app.mutualoffline.util

/**
 * V1 phone normalizer. Strips non-digit characters and prefixes a `+`.
 *
 * NOTE: This is intentionally simplistic. For real-world matching you need
 * a proper E.164 implementation (e.g. libphonenumber) that knows the user's
 * default region and can reject malformed input. Tracked as future work.
 */
object PhoneNormalizer {

    fun normalizePhone(raw: String): String {
        val digits = raw.filter { it.isDigit() }
        if (digits.isEmpty()) return ""
        // If the raw string already had a leading '+', preserve E.164.
        return if (raw.trim().startsWith("+")) "+$digits" else "+$digits"
    }

    fun normalizeEmail(raw: String): String = raw.trim().lowercase()
}
