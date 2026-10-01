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
                    Response.success(ForgotPasswordResponse("Sent.", SendCodeData("reset-challenge", "customer@gmail.com", 600)))
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

    @Test fun invalidFieldDisplaysTheValidationErrorAndKeepsVerifiedEmail() = runBlocking {
        val auth = MobileAuth(api { _, _ -> failure(422, """{"message":"The given data was invalid.","errors":{"phone":["The phone number must contain exactly 11 digits and start with 09."]}}""") })
        val error = failureOf { auth.register(registration()) }
        assertEquals("The phone number must contain exactly 11 digits and start with 09.", error.message)
        assertFalse(error.restartVerification)
    }

    @Test fun registrationAcceptsTheCustomerResponseAndNormalizesEmail() = runBlocking {
        val auth = MobileAuth(api { _, request ->
            assertEquals("customer@gmail.com", (request as RegisterRequest).email)
            Response.success(mapOf("data" to User(1, "Customer", "customer@gmail.com", "09123456789", "customer")))
        })
        auth.register(registration().copy(email = " Customer@Gmail.com "))
    }

    @Test fun passwordResetRequestReturnsTheChallengeForTheInAppCodeStep() = runBlocking {
        val auth = MobileAuth(api { _, request ->
            assertEquals(ForgotPasswordRequest("customer@gmail.com"), request)
            Response.success(ForgotPasswordResponse("A reset code was sent.", SendCodeData("reset-challenge", "customer@gmail.com", 600)))
        })
        assertEquals("reset-challenge", auth.requestPasswordReset(" Customer@Gmail.com ").challenge)
    }

    @Test fun passwordResetSendsCodeAndNewPasswordAndReturnsServerMessage() = runBlocking {
        val auth = MobileAuth(api { method, request ->
            assertEquals("resetPassword", method)
            assertEquals(ResetPasswordRequest("reset-challenge", "customer@gmail.com", "012345", "NewPass123!", "NewPass123!"), request)
            Response.success(ApiError(message = "Your password has been reset."))
        })
        assertEquals("Your password has been reset.", auth.resetPassword("reset-challenge", " Customer@Gmail.com ", "012345", "NewPass123!", "NewPass123!"))
    }

    @Test fun wrongResetCodeKeepsTheCodeStepButExpiredCodeRestartsIt() = runBlocking {
        val wrong = MobileAuth(api { _, _ -> failure(422, """{"message":"The reset code is incorrect."}""") })
        val wrongError = failureOf { wrong.resetPassword("reset-challenge", "customer@gmail.com", "111111", "NewPass123!", "NewPass123!") }
        assertEquals("The reset code is incorrect.", wrongError.message)
        assertFalse(wrongError.restartVerification)

        val expired = MobileAuth(api { _, _ -> failure(422, """{"code":"verification_expired","message":"The reset code has expired. Please request a new code."}""") })
        assertTrue(failureOf { expired.resetPassword("reset-challenge", "customer@gmail.com", "111111", "NewPass123!", "NewPass123!") }.restartVerification)
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
        val auth = MobileAuth(api { _, _ -> Response.success(ForgotPasswordResponse(message = "Sent.")) })
        assertEquals("Could not confirm the reset request. Please try again.", failureOf { auth.requestPasswordReset("customer@gmail.com") }.message)
    }

    private fun registration() = RegisterRequest("token", "Customer", "customer@gmail.com", "09123456789", "2000-09-15", "female", "Bantayan, Cebu", "SecurePass123!", "SecurePass123!")

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
