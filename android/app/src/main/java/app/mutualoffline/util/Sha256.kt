package app.mutualoffline.util

import java.security.MessageDigest

/** Returns the lowercase hex SHA-256 digest of this string's UTF-8 bytes. */
fun String.sha256Hex(): String {
    val digest = MessageDigest.getInstance("SHA-256")
        .digest(toByteArray(Charsets.UTF_8))
    val sb = StringBuilder(digest.size * 2)
    for (b in digest) {
        sb.append(((b.toInt() ushr 4) and 0x0F).toString(16))
        sb.append((b.toInt() and 0x0F).toString(16))
    }
    return sb.toString()
}
