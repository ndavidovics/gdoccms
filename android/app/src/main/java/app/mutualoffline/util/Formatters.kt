package app.mutualoffline.util

import java.time.Instant
import java.time.format.DateTimeFormatter
import java.util.Locale

object Formatters {

    /** Formats a duration as HH:MM:SS. */
    fun durationHms(totalSeconds: Long): String {
        val safe = totalSeconds.coerceAtLeast(0L)
        val hours = safe / 3600
        val minutes = (safe % 3600) / 60
        val seconds = safe % 60
        return String.format(Locale.ROOT, "%02d:%02d:%02d", hours, minutes, seconds)
    }

    /** Returns the current ISO-8601 UTC timestamp (e.g. 2026-04-29T12:00:00Z). */
    fun isoNow(): String =
        DateTimeFormatter.ISO_INSTANT.format(Instant.now())

    /** Parses an ISO-8601 timestamp to epoch seconds, or null if invalid. */
    fun parseIsoToEpochSeconds(value: String?): Long? {
        if (value.isNullOrBlank()) return null
        return runCatching { Instant.parse(value).epochSecond }.getOrNull()
    }
}
