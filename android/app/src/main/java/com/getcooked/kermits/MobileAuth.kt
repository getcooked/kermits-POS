package com.getcooked.kermits

import com.squareup.moshi.Moshi
import retrofit2.Response

internal class AuthRequestException(message: String, val restartVerification: Boolean = false) : Exception(message)

/** Keeps authentication response handling independent of screen state. */
internal class MobileAuth(private val api: KermitsApi) {
    suspend fun sendCode(email: String): SendCodeData {
        val response = api.sendRegistrationCode(SendCodeRequest(email.trim().lowercase()))
        checkResponse(response, "The verification code could not be sent. Please try again.")
        return response.body()?.data?.takeIf { it.challenge.isNotBlank() }
            ?: throw AuthRequestException("The server returned an incomplete verification response. Please request a new code.")
    }

    suspend fun verifyCode(challenge: String, email: String, code: String): VerifyCodeData {
        val response = api.verifyRegistrationCode(VerifyCodeRequest(challenge, email.trim().lowercase(), code.trim()))
        checkResponse(response, "The code could not be verified. Please try again.")
        return response.body()?.data?.takeIf { it.registration_token.isNotBlank() }
            ?: throw AuthRequestException("The server returned an incomplete verification response. Please request a new code.", true)
    }

    suspend fun register(request: RegisterRequest) {
        val response = api.register(request.copy(email = request.email.trim().lowercase()))
        checkResponse(response, "Could not create the account. Please try again.")
        if (response.body()?.get("data") == null) {
            throw AuthRequestException("Could not confirm account creation. Try logging in before registering again.")
        }
    }

    suspend fun requestPasswordReset(email: String): String {
        val response = api.forgotPassword(ForgotPasswordRequest(email.trim().lowercase()))
        checkResponse(response, "The reset request could not be sent. Please try again.")
        return response.body()?.message?.takeIf { it.isNotBlank() }
            ?: throw AuthRequestException("Could not confirm the reset request. Please try again.")
    }

    private fun checkResponse(response: Response<*>, fallback: String) {
        if (response.isSuccessful) return
        val body = response.errorBody()?.string()
        val error = body?.let { runCatching { errorAdapter.fromJson(it) }.getOrNull() }
        val retryAfter = error?.retry_after ?: response.headers()["Retry-After"]?.toIntOrNull()
        val message = error?.errors?.values?.flatten()?.firstOrNull()
            ?: error?.message?.takeIf { it.isNotBlank() && it != "Server Error" && it != "Too Many Attempts." }
            ?: when {
                response.code() == 429 -> "Too many requests. Please wait ${retryAfter?.coerceAtLeast(1) ?: 60} seconds and try again."
                response.code() >= 500 -> "Kermit's email service is unavailable. Please try again later."
                else -> fallback
            }
        val restart = error?.code in setOf("verification_expired", "verification_attempts_exceeded")
            || (response.code() == 422 && (message.contains("expired", ignoreCase = true)
                || message.contains("request a new code", ignoreCase = true)))
        throw AuthRequestException(message, restart)
    }

    private companion object {
        val errorAdapter = Moshi.Builder().build().adapter(ApiError::class.java)
    }
}
