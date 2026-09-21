package com.getcooked.kermits

import java.lang.reflect.Proxy
import kotlinx.coroutines.runBlocking
import okhttp3.MediaType.Companion.toMediaType
import okhttp3.ResponseBody.Companion.toResponseBody
import org.junit.Assert.*
import org.junit.Test
import retrofit2.Response

class MobileAuthTest {
    @Test fun sendCodeNormalizesEmailAndReturnsTheChallengeNeededForVerification() = runBlocking {
        val auth = MobileAuth(api { method, request ->
            assertEquals("sendRegistrationCode", method)
            assertEquals("customer@gmail.com", (request as SendCodeRequest).email)
            Response.success(SendCodeResponse(SendCodeData("challenge", "customer@gmail.com", 600)))
        })
        assertEquals("challenge", auth.sendCode(" Customer@Gmail.com ").challenge)
    }

    @Test fun recaptchaTokensAreForwardedOnProtectedEmailRequests() = runBlocking {
        val auth = MobileAuth(api { method, request ->
            when (method) {
                "sendRegistrationCode" -> {
                    assertEquals("signup-token", (request as SendCodeRequest).recaptcha_token)
                    Response.success(SendCodeResponse(SendCodeData("challenge", "customer@gmail.com", 600)))
                }
                "forgotPassword" -> {
                    assertEquals("reset-token", (request as ForgotPasswordRequest).recaptcha_token)
                    Response.success(ApiError(message = "Sent."))
                }
                else -> throw AssertionError("Unexpected API call: $method")
            }
        })

        auth.sendCode("customer@gmail.com", "signup-token")
        auth.requestPasswordReset("customer@gmail.com", "reset-token")
        Unit
    }

    @Test fun verificationReturnsRegistrationTokenAndPreservesLeadingZeroInCode() = runBlocking {
        val auth = MobileAuth(api { _, request ->
            assertEquals(VerifyCodeRequest("challenge", "customer@gmail.com", "012345"), request)
            Response.success(VerifyCodeResponse(VerifyCodeData("registration-token", "customer@gmail.com", 900)))
        })
        assertEquals("registration-token", auth.verifyCode("challenge", " Customer@Gmail.com ", "012345").registration_token)
    }

    @Test fun wrongCodeShowsServerMessageWithoutDiscardingVerification() = runBlocking {
        val auth = MobileAuth(api { _, _ -> failure(422, """{"message":"The verification code is incorrect."}""") })
        val error = failureOf { auth.verifyCode("challenge", "customer@gmail.com", "111111") }
        assertEquals("The verification code is incorrect.", error.message)
        assertFalse(error.restartVerification)
    }

    @Test fun expiredVerificationCanRestartInsteadOfLeavingEmailLocked() = runBlocking {
        val auth = MobileAuth(api { _, _ -> failure(422, """{"code":"verification_expired","message":"Please verify your email again."}""") })
        assertTrue(failureOf { auth.verifyCode("challenge", "customer@gmail.com", "111111") }.restartVerification)
    }

    @Test fun oldServerExpiryMessageAlsoRestartsVerification() = runBlocking {
        val auth = MobileAuth(api { _, _ -> failure(422, """{"message":"Email verification has expired. Please verify your Gmail address again."}""") })
        assertTrue(failureOf { auth.register(registration()) }.restartVerification)
    }

    @Test fun duplicateUsernameDisplaysTheValidationErrorAndKeepsVerifiedEmail() = runBlocking {
        val auth = MobileAuth(api { _, _ -> failure(422, """{"message":"The given data was invalid.","errors":{"username":["The username has already been taken."]}}""") })
        val error = failureOf { auth.register(registration()) }
        assertEquals("The username has already been taken.", error.message)
        assertFalse(error.restartVerification)
    }

    @Test fun registrationAcceptsTheCustomerResponseAndNormalizesEmail() = runBlocking {
        val auth = MobileAuth(api { _, request ->
            assertEquals("customer@gmail.com", (request as RegisterRequest).email)
            Response.success(mapOf("data" to User(1, "Customer", "customer", "customer@gmail.com", "09123456789", "customer")))
        })
        auth.register(registration().copy(email = " Customer@Gmail.com "))
    }

    @Test fun passwordResetDisplaysConfirmedServerResponse() = runBlocking {
        val auth = MobileAuth(api { _, request ->
            assertEquals(ForgotPasswordRequest("customer@gmail.com"), request)
            Response.success(ApiError(message = "A password reset link was sent to your registered email address."))
        })
        assertEquals("A password reset link was sent to your registered email address.", auth.requestPasswordReset(" Customer@Gmail.com "))
    }

    @Test fun unregisteredCustomerCannotRequestAPasswordReset() = runBlocking {
        val auth = MobileAuth(api { _, _ -> failure(422, """{"code":"account_not_found","message":"No registered customer account was found with that email address.","errors":{"email":["No registered customer account was found with that email address."]}}""") })
        assertEquals(
            "No registered customer account was found with that email address.",
            failureOf { auth.requestPasswordReset("unknown@example.com") }.message,
        )
    }

    @Test fun throttledResetShowsWaitTimeInsteadOfClaimingEmailWasSent() = runBlocking {
        val auth = MobileAuth(api { _, _ -> failure(429, """{"message":"Too Many Attempts.","retry_after":42}""") })
        assertEquals("Too many requests. Please wait 42 seconds and try again.", failureOf { auth.requestPasswordReset("customer@gmail.com") }.message)
    }

    @Test fun mailServerFailureDoesNotReportAResetEmailWasSent() = runBlocking {
        val auth = MobileAuth(api { _, _ -> failure(503, "<html>Service unavailable</html>") })
        assertEquals("Kermit's email service is unavailable. Please try again later.", failureOf { auth.requestPasswordReset("customer@gmail.com") }.message)
    }

    @Test fun emptySuccessfulResetResponseDoesNotClaimEmailWasSent() = runBlocking {
        val auth = MobileAuth(api { _, _ -> Response.success<ApiError>(null) })
        assertEquals("Could not confirm the reset request. Please try again.", failureOf { auth.requestPasswordReset("customer@gmail.com") }.message)
    }

    private fun registration() = RegisterRequest("token", "Customer", "customer", "customer@gmail.com", "09123456789", "2000-09-15", "female", "Bantayan, Cebu", "SecurePass123!", "SecurePass123!")

    private fun failure(status: Int, json: String): Response<Any> = Response.error(status, json.toResponseBody("application/json".toMediaType()))

    private suspend fun failureOf(action: suspend () -> Unit): AuthRequestException {
        try { action() } catch (error: AuthRequestException) { return error }
        throw AssertionError("Expected authentication request to fail")
    }

    private fun api(answer: (String, Any?) -> Response<*>): KermitsApi = Proxy.newProxyInstance(
        KermitsApi::class.java.classLoader,
        arrayOf(KermitsApi::class.java),
    ) { _, method, arguments -> answer(method.name, arguments?.firstOrNull()) } as KermitsApi
}
