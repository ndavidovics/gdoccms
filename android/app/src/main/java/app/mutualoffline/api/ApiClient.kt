package app.mutualoffline.api

import app.mutualoffline.BuildConfig
import app.mutualoffline.auth.SecurePrefs
import com.jakewharton.retrofit2.converter.kotlinx.serialization.asConverterFactory
import kotlinx.serialization.json.Json
import okhttp3.MediaType.Companion.toMediaType
import okhttp3.Interceptor
import okhttp3.OkHttpClient
import okhttp3.logging.HttpLoggingInterceptor
import retrofit2.Retrofit
import java.util.concurrent.TimeUnit

object ApiClient {

    private val json = Json {
        ignoreUnknownKeys = true
        encodeDefaults = true
        explicitNulls = false
        coerceInputValues = true
    }

    @Volatile
    private var instance: ApiService? = null

    fun get(prefs: SecurePrefs): ApiService {
        return instance ?: synchronized(this) {
            instance ?: build(prefs).also { instance = it }
        }
    }

    private fun build(prefs: SecurePrefs): ApiService {
        val authInterceptor = Interceptor { chain ->
            val original = chain.request()
            val token = prefs.token
            val builder = original.newBuilder()
                .header("Accept", "application/json")
            if (!token.isNullOrBlank()) {
                builder.header("Authorization", "Bearer $token")
            }
            chain.proceed(builder.build())
        }

        val logging = HttpLoggingInterceptor().apply {
            level = if (BuildConfig.DEBUG) {
                HttpLoggingInterceptor.Level.BODY
            } else {
                HttpLoggingInterceptor.Level.NONE
            }
        }

        val client = OkHttpClient.Builder()
            .connectTimeout(15, TimeUnit.SECONDS)
            .readTimeout(30, TimeUnit.SECONDS)
            .writeTimeout(30, TimeUnit.SECONDS)
            .addInterceptor(authInterceptor)
            .addInterceptor(logging)
            .build()

        val baseUrl = BuildConfig.API_BASE_URL.trimEnd('/') + "/"
        val contentType = "application/json".toMediaType()

        return Retrofit.Builder()
            .baseUrl(baseUrl)
            .client(client)
            .addConverterFactory(json.asConverterFactory(contentType))
            .build()
            .create(ApiService::class.java)
    }
}
