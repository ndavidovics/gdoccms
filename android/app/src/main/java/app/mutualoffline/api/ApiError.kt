package app.mutualoffline.api

sealed class ApiError(
    open val code: Int,
    message: String,
    cause: Throwable? = null,
) : RuntimeException(message, cause) {

    data class Unauthorized(override val code: Int = 401, val msg: String = "Unauthorized") :
        ApiError(code, msg)

    data class Forbidden(override val code: Int = 403, val msg: String = "Forbidden") :
        ApiError(code, msg)

    data class NotFound(override val code: Int = 404, val msg: String = "Not found") :
        ApiError(code, msg)

    data class Validation(
        override val code: Int = 422,
        val msg: String = "Validation failed",
        val errors: Map<String, List<String>> = emptyMap(),
    ) : ApiError(code, msg)

    data class Conflict(override val code: Int = 409, val msg: String = "Conflict") :
        ApiError(code, msg)

    data class Server(override val code: Int, val msg: String) : ApiError(code, msg)

    data class Network(val msg: String, val throwable: Throwable? = null) :
        ApiError(0, msg, throwable)

    data class Unknown(override val code: Int, val msg: String) : ApiError(code, msg)
}
